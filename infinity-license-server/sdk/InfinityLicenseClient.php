<?php
/**
 * InfinityLicenseClient — SDK PHP réutilisable pour les plugins Infinity.
 *
 * Intégration en 3 lignes :
 *   $client = new InfinityLicenseClient('https://votre-serveur', 'infinitycod', 'INFC-XXXX-…');
 *   $state  = $client->state();          // active | expired | suspended | … (avec grâce hors-ligne)
 *   $client->heartbeat('4.2.0');
 *
 * Principes :
 *  - Aucune coupure brutale : cache local + période de grâce configurable.
 *  - La clé n'est jamais stockée en clair (hash SHA-256 transmis et conservé).
 *  - Les réponses signées Ed25519 peuvent être vérifiées via verifySignature().
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'INFINITY_SDK' ) ) { exit; }

class InfinityLicenseClient {

	/** @var string URL du serveur de licences (sans / final). */
	private $server;
	/** @var string Slug produit côté serveur. */
	private $product;
	/** @var string Clé en clair (détenue par le plugin appelant). */
	private $license_key;
	/** @var string Identifiant d'installation stable. */
	private $installation_id;
	/** @var int Heures de grâce si serveur injoignable. */
	private $grace_hours;
	/** @var callable|null stockage : get(string), set(string,mixed). */
	private $store;

	public function __construct( $server, $product, $license_key, $installation_id = '', $grace_hours = 72, $store = null ) {
		$this->server          = rtrim( (string) $server, '/' );
		$this->product         = (string) $product;
		$this->license_key     = (string) $license_key;
		$this->installation_id = (string) $installation_id;
		$this->grace_hours     = (int) $grace_hours;
		$this->store           = $store;
	}

	/* ---------- API ---------- */

	public function activate( $domain, $site_url = '', $plugin_version = '', $wp_version = '', $php_version = '' ) {
		return $this->post( '/license/activate', array_filter( array(
			'license_key'     => $this->license_key,
			'product'         => $this->product,
			'domain'          => $domain,
			'site_url'        => $site_url,
			'plugin_version'  => $plugin_version,
			'wordpress_version' => $wp_version,
			'php_version'     => $php_version,
			'installation_id' => $this->installation_id,
		) ) );
	}

	public function validate() {
		return $this->post( '/license/validate', array(
			'license_key'     => $this->license_key,
			'product'         => $this->product,
			'installation_id' => $this->installation_id,
		) );
	}

	public function deactivate( $domain ) {
		return $this->post( '/license/deactivate', array(
			'license_key'     => $this->license_key,
			'installation_id' => $this->installation_id,
			'domain'          => $domain,
		) );
	}

	public function heartbeat( $plugin_version = '' ) {
		return $this->post( '/license/heartbeat', array(
			'license_key'     => $this->license_key,
			'installation_id' => $this->installation_id,
			'plugin_version'  => $plugin_version,
		) );
	}

	public function checkUpdate( $current_version, $channel = 'stable' ) {
		return $this->post( '/license/check-update', array(
			'license_key' => $this->license_key,
			'product'     => $this->product,
			'version'     => $current_version,
			'channel'     => $channel,
		) );
	}

	/* ---------- État résilient (cache + grâce) ---------- */

	/**
	 * État effectif de la licence, tolérant aux pannes du serveur :
	 * serveur OK → statut réel (mis en cache) ;
	 * serveur KO → dernier statut en cache tant que la grâce n'est pas écoulée.
	 *
	 * @return array{state:string, cached:bool, checked_at:string}
	 */
	public function state() {
		$cached = $this->cacheGet( 'infinity_license_state_' . sha1( $this->license_key ) );
		$resp   = null;

		try {
			$resp = $this->validate();
		} catch ( \Exception $e ) {
			$resp = null; // Serveur injoignable.
		}

		if ( is_array( $resp ) && isset( $resp['status'] ) ) {
			$state = array( 'state' => (string) $resp['status'], 'cached' => false, 'checked_at' => gmdate( 'c' ), 'license' => isset( $resp['license'] ) ? $resp['license'] : array() );
			$this->cacheSet( 'infinity_license_state_' . sha1( $this->license_key ), $state );
			return $state;
		}

		if ( is_array( $cached ) && ! empty( $cached['checked_at'] ) ) {
			$age = time() - strtotime( $cached['checked_at'] . 'Z' );
			if ( $age < $this->grace_hours * 3600 ) {
				$cached['cached'] = true;
				return $cached; // Période de grâce : le plugin continue de fonctionner.
			}
			return array( 'state' => 'grace_expired', 'cached' => true, 'checked_at' => $cached['checked_at'] );
		}

		return array( 'state' => 'unknown', 'cached' => false, 'checked_at' => '' );
	}

	/** Vérifie la signature Ed25519 d'une réponse (clé publique embarquée par l'hôte). */
	public function verifySignature( $response, $public_key_pem ) {
		if ( empty( $response['signature'] ) ) { return false; }
		$data = $response;
		$sig  = $data['signature'];
		unset( $data['signature'], $data['algorithm'] );
		$ok = openssl_verify( json_encode( $data ), base64_decode( $sig ), $public_key_pem, OPENSSL_ALGO_ED25519 );
		return 1 === $ok;
	}

	/* ---------- Transport ---------- */

	private function post( $path, $body ) {
		$args = array(
			'timeout' => 20,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $body ),
		);
		$response = defined( 'ABSPATH' )
			? wp_remote_post( $this->server . '/api/v1' . $path, $args )
			: null;

		if ( $response === null ) { // Contexte CLI/test sans WordPress.
			$ch = curl_init( $this->server . '/api/v1' . $path );
			curl_setopt_array( $ch, array( CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $args['headers'], CURLOPT_POSTFIELDS => $args['body'], CURLOPT_TIMEOUT => 20 ) );
			$raw  = curl_exec( $ch );
			$code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
			curl_close( $ch );
			$decoded = json_decode( (string) $raw, true );
			if ( ! is_array( $decoded ) ) { throw new \Exception( 'HTTP ' . $code ); }
			return $decoded;
		}

		if ( is_wp_error( $response ) ) { throw new \Exception( $response->get_error_message() ); }
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) ) { throw new \Exception( 'Invalid server response.' ); }
		return $decoded;
	}

	/* ---------- Cache abstrait (wp_options par défaut) ---------- */

	private function cacheGet( $key ) {
		if ( $this->store ) { return call_user_func( $this->store, $key ); }
		if ( function_exists( 'get_option' ) ) { return get_option( $key, null ); }
		$file = sys_get_temp_dir() . '/' . $key . '.json';
		return file_exists( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
	}
	private function cacheSet( $key, $value ) {
		if ( $this->store ) { call_user_func( $this->store, $key, $value ); return; }
		if ( function_exists( 'update_option' ) ) { update_option( $key, $value, false ); return; }
		file_put_contents( sys_get_temp_dir() . '/' . $key . '.json', json_encode( $value ) );
	}
}
