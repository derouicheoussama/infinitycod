<?php
/**
 * Licence : activation et vérification auprès du serveur Infinity Coder
 * (protocole du serveur de licences Infinity Coder).
 *
 * Produit gratuit de base ; la licence débloque WhatsApp automatique,
 * transporteurs, statistiques P&L et offres avancées.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\License;

use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class LicenseManager {

	const OPTION = 'infinitycod_license';

	/**
	 * Url du serveur de licences (modifiable, ex. copie dédiée).
	 *
	 * @return string
	 */
	public static function server_url() {
		$url = (string) Settings::get( 'license_server', 'https://infinitycoder.app/api.php' );

		// Migration défensive : les anciennes installations pointant vers
		// factexpert.online sont redirigées vers le domaine Infinity Coder.
		if ( '' === trim( $url ) || false !== strpos( $url, 'factexpert.online' ) ) {
			$url = 'https://infinitycoder.app/api.php';
		}

		/**
		 * Url de l'API du serveur de licences Infinity Coder.
		 *
		 * @param string $url Url par défaut.
		 */
		return apply_filters( 'infinitycod_license_server_url', $url );
	}

	/**
	 * Hooks : vérification hebdomadaire.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'infinitycod_license_heartbeat', array( $this, 'heartbeat' ) );

		if ( ! wp_next_scheduled( 'infinitycod_license_heartbeat' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'infinitycod_license_heartbeat' );
		}

		// Mises à jour à distance via l'API standard WordPress.
		( new Updater() )->register();
	}

	/**
	 * Données de licence stockées.
	 *
	 * @return array
	 */
	public static function stored() {
		$data = get_option( self::OPTION, array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Identifiant machine : empreinte du site (un site = une machine).
	 *
	 * @return string
	 */
	public static function machine_id() {
		return hash( 'sha256', home_url( '/' ) . '|' . get_option( 'infinitycod_install_id', '' ) );
	}

	/**
	 * Identifiant d'installation stable.
	 *
	 * @return string
	 */
	public static function install_id() {
		$install_id = get_option( 'infinitycod_install_id', '' );
		if ( ! $install_id ) {
			$install_id = wp_generate_password( 24, false, false );
			update_option( 'infinitycod_install_id', $install_id, true );
		}
		return $install_id;
	}

	/**
	 * Active une clé de licence.
	 *
	 * @param string $key Clé fournie par le marchand.
	 * @return array{ok: bool, message: string}
	 */
	public function activate( $key ) {
		$key = trim( (string) $key );

		if ( '' === $key ) {
			return array( 'ok' => false, 'message' => __( 'Veuillez saisir votre clé de licence.', 'infinitycod' ) );
		}

		// Clé de développement : uniquement si INFINITYCOD_DEV_MODE est défini
		// dans wp-config.php (jamais actif dans la build commerciale).
		if ( hash_equals( 'INFINITY-DEV', $key ) ) {
			if ( ! defined( 'INFINITYCOD_DEV_MODE' ) || ! INFINITYCOD_DEV_MODE ) {
				return array( 'ok' => false, 'message' => __( 'Clé de développement non autorisée sur ce site.', 'infinitycod' ) );
			}
			update_option( self::OPTION, array(
				'key_hash'   => hash( 'sha256', $key ),
				'status'     => 'ACTIVE',
				'client'     => 'Development',
				'expires_at' => '',
				'checked_at' => current_time( 'mysql' ),
			), true );
			return array( 'ok' => true, 'message' => __( 'Licence de développement activée.', 'infinitycod' ) );
		}

		self::install_id();

		$response = wp_remote_post(
			add_query_arg( 'action', 'activate', self::server_url() ),
			array(
				'timeout' => 20,
				'body'    => array(
					'machine'    => self::machine_id(),
					'key_hash'   => hash( 'sha256', $key ),
					'install_id' => self::install_id(),
					'product'    => 'infinitycod',
					'version'    => INFINITYCOD_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'message' => $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : array();
		$status = isset( $body['status'] ) ? (string) $body['status'] : 'INVALID';

		if ( 'ACTIVE' === $status ) {
			update_option( self::OPTION, array(
				'key_hash'   => hash( 'sha256', $key ),
				'status'     => 'ACTIVE',
				'client'     => isset( $body['client'] ) ? (string) $body['client'] : '',
				'email'      => isset( $body['email'] ) ? (string) $body['email'] : '',
				'expires_at' => isset( $body['expires_at'] ) ? (string) $body['expires_at'] : '',
				'checked_at' => current_time( 'mysql' ),
			), true );

			return array(
				'ok' => true,
				/* translators: %s : nom du client titulaire. */
				'message' => sprintf( __( 'Licence activée. Merci %s !', 'infinitycod' ), isset( $body['client'] ) ? $body['client'] : '' ),
			);
		}

		$message = isset( $body['message'] ) ? (string) $body['message'] : '';
		return array(
			'ok' => false,
			/* translators: %s : statut renvoyé par le serveur. */
			'message' => sprintf( __( 'Activation refusée (%1$s). %2$s', 'infinitycod' ), $status, $message ),
		);
	}

	/**
	 * Requête vers le serveur de licences.
	 *
	 * @param string $action   Action API (activate, heartbeat, deactivate).
	 * @param string $key_hash Empreinte SHA-256 de la clé.
	 * @return array|WP_Error Réponse HTTP ou erreur.
	 */
	private function remote_status( $action, $key_hash ) {
		return wp_remote_post(
			add_query_arg( 'action', $action, self::server_url() ),
			array(
				'timeout' => 20,
				'body'    => array(
					'machine'    => self::machine_id(),
					'key_hash'   => $key_hash,
					'install_id' => self::install_id(),
					'product'    => 'infinitycod',
					'version'    => INFINITYCOD_VERSION,
				),
			)
		);
	}

	/**
	 * Vérification périodique (tâche hebdomadaire).
	 *
	 * @return void
	 */
	public function heartbeat() {
		$stored = self::stored();
		if ( empty( $stored['key_hash'] ) ) {
			return;
		}

		$response = $this->remote_status( 'heartbeat', $stored['key_hash'] );

		if ( is_wp_error( $response ) ) {
			return; // Serveur injoignable : statut conservé.
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$body   = is_array( $body ) ? $body : array();
		$status = isset( $body['status'] ) ? (string) $body['status'] : '';

		if ( '' !== $status ) {
			$stored['status']     = $status;
			$stored['checked_at'] = current_time( 'mysql' );
			update_option( self::OPTION, $stored, true );
		}
	}

	/**
	 * Vérification manuelle déclenchée depuis l'écran Licence.
	 *
	 * @return array{ok: bool, message: string}
	 */
	public function manual_check() {
		$stored = self::stored();

		if ( empty( $stored['key_hash'] ) ) {
			return array( 'ok' => false, 'message' => __( 'Aucune clé enregistrée : activez d’abord votre licence.', 'infinitycod' ) );
		}

		$response = $this->remote_status( 'heartbeat', $stored['key_hash'] );

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'message' => __( 'Serveur de licences injoignable. Votre statut local est conservé (période de grâce).', 'infinitycod' ) );
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$body   = is_array( $body ) ? $body : array();
		$status = isset( $body['status'] ) ? (string) $body['status'] : '';

		if ( '' === $status ) {
			return array( 'ok' => false, 'message' => __( 'Réponse inattendue du serveur de licences.', 'infinitycod' ) );
		}

		$stored['status']     = $status;
		$stored['checked_at'] = current_time( 'mysql' );
		if ( ! empty( $body['expires_at'] ) ) {
			$stored['expires_at'] = (string) $body['expires_at'];
		}
		update_option( self::OPTION, $stored, true );

		if ( 'ACTIVE' === $status ) {
			return array( 'ok' => true, 'message' => __( 'Licence vérifiée auprès du serveur : active.', 'infinitycod' ) );
		}

		if ( 'UNKNOWN' === $status ) {
			return array( 'ok' => true, 'message' => __( 'Serveur injoignable : statut conservé (période de grâce).', 'infinitycod' ) );
		}

		/* translators: %s : statut renvoyé par le serveur. */
		return array( 'ok' => false, 'message' => sprintf( __( 'Le serveur indique le statut : %s.', 'infinitycod' ), $status ) );
	}

	/**
	 * Désactive la licence localement (et libère le siège côté serveur).
	 *
	 * @return array{ok: bool, message: string}
	 */
	public function deactivate() {
		$stored = self::stored();

		if ( empty( $stored['key_hash'] ) ) {
			return array( 'ok' => false, 'message' => __( 'Aucune licence active à désactiver.', 'infinitycod' ) );
		}

		// Best effort : prévenir le serveur pour libérer la machine.
		wp_remote_post(
			add_query_arg( 'action', 'deactivate', self::server_url() ),
			array(
				'timeout' => 10,
				'body'    => array(
					'machine'    => self::machine_id(),
					'key_hash'   => $stored['key_hash'],
					'install_id' => self::install_id(),
					'product'    => 'infinitycod',
				),
			)
		);

		delete_option( self::OPTION );

		return array(
			'ok'      => true,
			'message' => __( 'Licence désactivée. Les fonctionnalités Premium sont verrouillées ; vos données (commandes, tarifs, réglages) sont conservées.', 'infinitycod' ),
		);
	}

	/**
	 * Les fonctionnalités premium sont-elles débloquées ?
	 *
	 * Tolérance hors-ligne : une licence UNKNOWN (serveur injoignable lors
	 * de la dernière vérification réussie) reste active, comme le protocole
	 * du serveur le prévoit.
	 *
	 * @return bool
	 */
	public static function is_premium() {
		/**
		 * Court-circuite le contrôle de licence (tests, distributions spéciales).
		 *
		 * @param bool|null $override Null = contrôle normal.
		 */
		$override = apply_filters( 'infinitycod_premium_override', null );
		if ( null !== $override ) {
			return (bool) $override;
		}

		$stored = self::stored();
		return ! empty( $stored['key_hash'] ) && in_array( isset( $stored['status'] ) ? $stored['status'] : '', array( 'ACTIVE', 'UNKNOWN' ), true );
	}

	/**
	 * Libellé du statut courant.
	 *
	 * @return string
	 */
	public static function status_label() {
		if ( self::is_premium() ) {
			return __( 'Premium actif', 'infinitycod' );
		}
		return __( 'Version gratuite', 'infinitycod' );
	}
}
