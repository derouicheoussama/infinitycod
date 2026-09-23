<?php
/**
 * Web Push (VAPID) — notifications de commandes au marchand, même écran
 * verrouillé, SANS service tiers : clés VAPID générées localement,
 * abonnements stockés sur le site, chiffrement aes128gcm (RFC 8291) et
 * signature VAPID ES256 (RFC 8292) en PHP pur via OpenSSL.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Core;

defined( 'ABSPATH' ) || exit;

class Webpush {

	const OPTION_KEYS = 'icod_webpush_vapid';
	const OPTION_SUBS = 'icod_webpush_subs';

	/**
	 * Hooks AJAX.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_icod_push_vapid_generate', array( $this, 'handle_vapid_generate' ) );
		add_action( 'wp_ajax_icod_push_subscribe', array( $this, 'handle_subscribe' ) );
		add_action( 'wp_ajax_icod_push_test', array( $this, 'handle_test' ) );
		add_action( 'wp_ajax_icod_push_status', array( $this, 'handle_status' ) );
	}

	/**
	 * Clés VAPID (générées à la demande, stockées localement).
	 *
	 * @return array{public: string, public_pem: string, private_pem: string}
	 */
	public static function vapid_keys() {
		$keys = get_option( self::OPTION_KEYS, array() );
		if ( is_array( $keys ) && ! empty( $keys['public_b64u'] ) && ! empty( $keys['private_pem'] ) ) {
			return $keys;
		}
		return array();
	}

	/**
	 * Génère et stocke la paire de clés VAPID (P-256).
	 *
	 * @return bool
	 */
	public static function generate_vapid() {
		$res = openssl_pkey_new( array(
			'curve_name'       => 'prime256v1',
			'private_key_type' => OPENSSL_KEYTYPE_EC, // Sans cette option OpenSSL génère un RSA et ignore curve_name.
		) );
		if ( false === $res ) {
			return false;
		}
		$export = '';
		openssl_pkey_export( $res, $export );
		$details = openssl_pkey_get_details( $res );
		if ( empty( $details['ec']['x'] ) || empty( $details['ec']['y'] ) || empty( $details['ec']['d'] ) ) {
			return false;
		}

		$raw_public = "\x04" . $details['ec']['x'] . $details['ec']['y']; // Point non compressé (65 octets).
		// NB : stocker des octets bruts casse certains stockages SQLite —
		// tout est conservé en base64 (décodé au moment de l'envoi).
		update_option( self::OPTION_KEYS, array(
			'public_b64u' => self::b64url_encode( $raw_public ), // Envoyée au navigateur (applicationServerKey).
			'public_pem'  => $details['key'],
			'private_pem' => $export,
			'created_at'  => gmdate( 'c' ),
		), true );
		return true;
	}

	/**
	 * Abonnements push enregistrés.
	 *
	 * @return array[]
	 */
	public static function subscriptions() {
		$subs = get_option( self::OPTION_SUBS, array() );
		return is_array( $subs ) ? $subs : array();
	}

	/**
	 * Ajoute / met à jour un abonnement pour cet appareil.
	 *
	 * @param array $sub {endpoint, keys:{p256dh, auth}, device}.
	 * @return void
	 */
	public static function save_subscription( array $sub ) {
		$subs = self::subscriptions();
		$subs = array_values( array_filter( $subs, static function ( $s ) use ( $sub ) {
			return empty( $s['endpoint'] ) || $s['endpoint'] !== $sub['endpoint'];
		} ) );
		$sub['time'] = time();
		$subs[]      = $sub;
		update_option( self::OPTION_SUBS, array_slice( $subs, -20 ), true ); // 20 appareils max.
	}

	/**
	 * Notification « nouvelle commande » à tous les appareils.
	 *
	 * @param string $title    Titre.
	 * @param string $body     Corps du message.
	 * @param string $url      Url ouverte au clic.
	 * @return int Nombre d'envois réussis.
	 */
	public static function notify_all( $title, $body, $url = '' ) {
		if ( ! Settings::get( 'webpush_enabled', 0 ) ) {
			return 0;
		}
		$keys = self::vapid_keys();
		if ( empty( $keys ) ) {
			return 0;
		}
		$sent   = 0;
		$dead   = array();
		$payload = wp_json_encode( array(
			'title' => $title,
			'body'  => $body,
			'url'   => $url ? $url : admin_url( 'admin.php?page=infinitycod-orders' ),
			'icon'  => INFINITYCOD_URL . 'assets/icon-256x256.png',
			'tag'   => 'icod-order',
		) );
		foreach ( self::subscriptions() as $sub ) {
			$res = self::send( $sub, $payload, $keys );
			if ( true === $res ) {
				$sent++;
			} elseif ( is_array( $res ) && in_array( $res[1], array( 404, 410 ), true ) ) {
				$dead[] = $sub['endpoint'];
			}
		}
		if ( $dead ) {
			$subs = self::subscriptions();
			$subs = array_values( array_filter( $subs, static function ( $s ) use ( $dead ) {
				return ! in_array( $s['endpoint'], $dead, true );
			} ) );
			update_option( self::OPTION_SUBS, $subs, true );
		}
		return $sent;
	}

	/**
	 * Envoie un message push chiffré à un abonnement.
	 *
	 * @param array  $sub     Abonnement (endpoint, keys.p256dh, keys.auth).
	 * @param string $payload Payload JSON.
	 * @param array  $vapid   Clés VAPID.
	 * @return true|array{0: string, 1: int} true si envoyé, sinon [raison, code HTTP].
	 */
	public static function send( array $sub, $payload, array $vapid ) {
		if ( empty( $sub['endpoint'] ) || empty( $sub['keys']['p256dh'] ) || empty( $sub['keys']['auth'] ) ) {
			return array( 'abonnement incomplet', 0 );
		}

		$eph_res = openssl_pkey_new( array(
			'curve_name'       => 'prime256v1',
			'private_key_type' => OPENSSL_KEYTYPE_EC,
		) );
		$eph_pub_raw = '';
		if ( $eph_res ) {
			$d = openssl_pkey_get_details( $eph_res );
			if ( ! empty( $d['ec']['x'] ) ) {
				$eph_pub_raw = "\x04" . $d['ec']['x'] . $d['ec']['y'];
			}
		}
		if ( '' === $eph_pub_raw ) {
			return array( 'génération clé éphémère impossible', 0 );
		}

		$sub_pub_raw = self::b64url_decode( $sub['keys']['p256dh'] );
		$auth_secret = self::b64url_decode( $sub['keys']['auth'] );
		if ( false === $sub_pub_raw || false === $auth_secret || strlen( $sub_pub_raw ) !== 65 ) {
			return array( __( 'Clés d’abonnement invalides.', 'infinitycod' ), 0 );
		}

		// ECDH partagé (clé publique abonné, clé privée éphémère).
		$sub_pub_pem = self::ec_point_to_pem( $sub_pub_raw );
		if ( null === $sub_pub_pem ) {
			return array( 'clé publique abonné illisible', 0 );
		}
		$sub_pub_key = openssl_pkey_get_public( $sub_pub_pem );
		$shared      = openssl_pkey_derive( $sub_pub_key, $eph_res, 32 );
		if ( false === $shared ) {
			return array( 'ECDH impossible', 0 );
		}

		// RFC 8291 §5 : IKM = HKDF(auth_secret, ecdh, "WebPush: info"+0x00+len||pubs).
		$ikm = hash_hkdf(
			'sha256',
			$shared,
			32,
			"WebPush: info\x00" . pack( 'n', 65 ) . $sub_pub_raw . pack( 'C', 65 ) . $eph_pub_raw,
			$auth_secret
		);

		$salt     = random_bytes( 16 );
		$cek      = hash_hkdf( 'sha256', $ikm, 32, "Content-Encoding: aes128gcm\x00", $salt );
		$nonce    = hash_hkdf( 'sha256', $ikm, 12, "Content-Encoding: nonce\x00", $salt );

		// Dernier enregistrement : séparateur de padding 0x02.
		$plaintext = $payload . "\x02";

		$tag  = '';
		$ciph = openssl_encrypt( $plaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );
		if ( false === $ciph ) {
			return array( 'chiffrement impossible', 0 );
		}

		// En-tête aes128gcm : sel ‖ rs(4096) ‖ idlen=65 ‖ clé publique éphémère ‖ ciphertext ‖ tag.
		// La clé éphémère DOIT voyager dans l'en-tête (RFC 8291 §4) sinon le service push ne peut pas déchiffrer.
		$record = $salt . pack( 'N', 4096 ) . "\x41" . $eph_pub_raw . $ciph . $tag;

		$jwt  = self::vapid_jwt( $vapid, parse_url( $sub['endpoint'], PHP_URL_HOST ) );
		$headers = array(
			'Content-Encoding'  => 'aes128gcm',
			'TTL'               => (string) 3600,
			'Urgency'           => 'high',
			'Authorization'     => 'vapid t=' . $jwt . ', k=' . self::b64url_encode( (string) self::b64url_decode( $vapid['public_b64u'] ) ), // public_b64u est en base64url : base64_decode standard renverrait false.
			'Content-Type'      => 'application/octet-stream',
		);

		$res = wp_remote_post( $sub['endpoint'], array(
			'timeout' => 15,
			'headers' => $headers,
			'body'    => $record,
		) );
		if ( is_wp_error( $res ) ) {
			return array( 'HTTP : ' . $res->get_error_message(), 0 );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code >= 200 && $code < 300 ) {
			return true;
		}
		return array( 'HTTP ' . $code, $code );
	}

	/**
	 * JWT ES256 signé VAPID (RFC 8292).
	 *
	 * @param array  $vapid  Clés VAPID.
	 * @param string $audience Origine du endpoint push.
	 * @return string
	 */
	private static function vapid_jwt( array $vapid, $audience ) {
		$header = self::b64url_encode( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'ES256' ) ) );
		$claims = self::b64url_encode( wp_json_encode( array(
			'aud' => 'https://' . $audience,
			'exp' => time() + 12 * HOUR_IN_SECONDS,
			'sub' => 'mailto:' . get_option( 'admin_email' ),
		) ) );
		$data   = $header . '.' . $claims;

		$pkey = openssl_pkey_get_private( $vapid['private_pem'] );
		openssl_sign( $data, $sig_raw, $pkey, OPENSSL_ALGO_SHA256 );
		return $data . '.' . self::b64url_encode( $sig_raw );
	}

	/**
	 * Point EC non compressé (65 octets) → PEM public P-256.
	 *
	 * @param string $point Point brut.
	 * @return string|null
	 */
	private static function ec_point_to_pem( $point ) {
		$der = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00" . $point;
		return '-----BEGIN PUBLIC KEY-----' . "\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . '-----END PUBLIC KEY-----';
	}

	/**
	 * Base64url encode.
	 *
	 * @param string $b Données brutes.
	 * @return string
	 */
	private static function b64url_encode( $b ) {
		return rtrim( strtr( base64_encode( $b ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64url decode.
	 *
	 * @param string $s Chaîne base64url.
	 * @return string|false
	 */
	private static function b64url_decode( $s ) {
		return base64_decode( strtr( rtrim( (string) $s, '=' ), '-_', '+/' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- décodage Web Push standard.
	}

	/* ---------------- AJAX ---------------- */

	/**
	 * Génère les clés VAPID (une fois).
	 *
	 * @return void
	 */
	public function handle_vapid_generate() {
		check_ajax_referer( 'icod_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$ok   = self::generate_vapid();
		$keys = $ok ? self::vapid_keys() : array();
		wp_send_json_success( array(
			'ok'          => $ok,
			'message'     => $ok ? __( 'Clés VAPID générées.', 'infinitycod' ) : __( 'Génération impossible (OpenSSL requis).', 'infinitycod' ),
			'count'       => count( self::subscriptions() ),
			'public_b64u' => ! empty( $keys['public_b64u'] ) ? $keys['public_b64u'] : '',
		) );
	}

	/**
	 * Enregistre l'abonnement push de l'appareil courant.
	 *
	 * @return void
	 */
	public function handle_subscribe() {
		check_ajax_referer( 'icod_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$sub = isset( $_POST['subscription'] ) ? json_decode( wp_unslash( $_POST['subscription'] ), true ) : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- JSON validé ci-dessous.
		if ( ! is_array( $sub ) || empty( $sub['endpoint'] ) || empty( $sub['keys']['p256dh'] ) || empty( $sub['keys']['auth'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Abonnement invalide.', 'infinitycod' ) ) );
		}
		self::save_subscription( array(
			'endpoint' => esc_url_raw( $sub['endpoint'] ),
			'keys'     => array(
				'p256dh' => sanitize_text_field( $sub['keys']['p256dh'] ),
				'auth'   => sanitize_text_field( $sub['keys']['auth'] ),
			),
			'device'   => isset( $_POST['device'] ) ? sanitize_text_field( wp_unslash( $_POST['device'] ) ) : '',
		) );
		wp_send_json_success( array( 'ok' => true, 'message' => __( 'Appareil abonné.', 'infinitycod' ), 'count' => count( self::subscriptions() ) ) );
	}

	/**
	 * Envoie une notification de test à tous les appareils.
	 *
	 * @return void
	 */
	public function handle_test() {
		check_ajax_referer( 'icod_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		if ( empty( self::vapid_keys() ) ) {
			wp_send_json_error( array( 'data' => array( 'ok' => false, 'message' => __( 'Générez d’abord les clés VAPID.', 'infinitycod' ) ) ) );
		}
		$sent = self::notify_all(
			'🧪 ' . get_bloginfo( 'name' ),
			__( 'Test Web Push InfinityCod : si vous lisez ceci, les notifications fonctionnent.', 'infinitycod' ),
			admin_url( 'admin.php?page=infinitycod-orders' )
		);
		$total = count( self::subscriptions() );
		wp_send_json( array(
			'success' => $sent > 0,
			'data'    => array(
				'ok'      => $sent > 0,
				'message' => $sent > 0
					? sprintf( /* translators: %d : nombre d'appareils. */ __( 'Notification envoyée à %d appareil(s).', 'infinitycod' ), $sent )
					: __( 'Aucun appareil abonné ou abonnement expiré — cliquez d’abord sur « Activer sur cet appareil ».', 'infinitycod' ),
				'count'   => $total,
			),
		) );
	}

	/**
	 * État : clés générées ? nombre d'appareils.
	 *
	 * @return void
	 */
	public function handle_status() {
		check_ajax_referer( 'icod_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		$keys = self::vapid_keys();
		wp_send_json_success( array(
			'keys'        => ! empty( $keys ),
			'public_b64u' => ! empty( $keys['public_b64u'] ) ? $keys['public_b64u'] : '',
			'count'       => count( self::subscriptions() ),
		) );
	}
}
