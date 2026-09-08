<?php
/**
 * Mises à jour à distance : branche le plugin sur le serveur Infinity Coder
 * avec l'API de mise à jour standard de WordPress (écran Extensions,
 * notifications automatiques, mise à jour en 1 clic).
 *
 * Le serveur expose un JSON simple (voir docs/SERVEUR-MISES-A-JOUR.md) :
 * { "version": "1.2.0", "download_url": "...", "requires": "6.0", ... }
 *
 * @package InfinityCod
 */

namespace InfinityCod\License;

defined( 'ABSPATH' ) || exit;

class Updater {

	/**
	 * Url du manifeste de mise à jour.
	 *
	 * @return string
	 */
	public static function info_url() {
		/**
		 * Url du manifeste de versions InfinityCod.
		 *
		 * @param string $url Url par défaut.
		 */
		return apply_filters( 'infinitycod_update_info_url', 'https://factexpert.online/updates/infinitycod.json' );
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
	}

	/**
	 * Télécharge (avec cache 6 h) le manifeste distant.
	 *
	 * @return array|null
	 */
	private function remote() {
		$cached = get_transient( 'icod_update_info' );

		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$response = wp_remote_get(
			self::info_url(),
			array( 'timeout' => 10 )
		);

		if ( is_wp_error( $response ) ) {
			set_transient( 'icod_update_info', array( 'unreachable' => 1 ), 30 * MINUTE_IN_SECONDS );
			return null;
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( $status < 200 || $status >= 300 || ! is_array( $body ) || empty( $body['version'] ) ) {
			set_transient( 'icod_update_info', array( 'unreachable' => 1 ), 30 * MINUTE_IN_SECONDS );
			return null;
		}

		set_transient( 'icod_update_info', $body, 6 * HOUR_IN_SECONDS );
		return $body;
	}

	/**
	 * Vide le cache de manifeste (bouton « Vérifier les mises à jour »).
	 *
	 * @return void
	 */
	public static function clear_cache() {
		delete_transient( 'icod_update_info' );
	}

	/**
	 * Injecte la mise à jour dans la transient WordPress si une version
	 * plus récente existe.
	 *
	 * @param object $transient Transient update_plugins.
	 * @return object
	 */
	public function inject_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = $this->remote();

		if ( ! $remote || empty( $remote['version'] ) ) {
			return $transient;
		}

		if ( version_compare( INFINITYCOD_VERSION, (string) $remote['version'], '>=' ) ) {
			return $transient;
		}

		$package = ! empty( $remote['download_url'] ) ? (string) $remote['download_url'] : '';

		// Serveur de téléchargement protégé par licence : clé transmise pour vérification.
		$stored  = LicenseManager::stored();
		if ( $package && ! empty( $stored['key_hash'] ) ) {
			$package = add_query_arg( 'key_hash', rawurlencode( $stored['key_hash'] ), $package );
		}

		$update = array(
			'slug'        => 'infinitycod',
			'plugin'      => INFINITYCOD_BASENAME,
			'new_version' => (string) $remote['version'],
			'url'         => ! empty( $remote['homepage'] ) ? (string) $remote['homepage'] : 'https://infinitycoder.app',
			'package'     => $package,
			'requires'    => isset( $remote['requires'] ) ? (string) $remote['requires'] : '6.0',
			'requires_php' => isset( $remote['requires_php'] ) ? (string) $remote['requires_php'] : '7.4',
			'tested'      => isset( $remote['tested'] ) ? (string) $remote['tested'] : get_bloginfo( 'version' ),
		);

		$transient->response[ INFINITYCOD_BASENAME ] = (object) $update;

		return $transient;
	}

	/**
	 * Fiche « Informations sur l'extension » (modal WordPress).
	 *
	 * @param false|object|array $result Résultat par défaut.
	 * @param string             $action Action demandée.
	 * @param object             $args   Arguments (slug…).
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'infinitycod' !== $args->slug ) {
			return $result;
		}

		$remote = $this->remote();

		if ( ! $remote ) {
			return $result;
		}

		$info                = new \stdClass();
		$info->name          = isset( $remote['name'] ) ? $remote['name'] : 'InfinityCod';
		$info->slug          = 'infinitycod';
		$info->version       = (string) $remote['version'];
		$info->requires      = isset( $remote['requires'] ) ? $remote['requires'] : '6.0';
		$info->requires_php  = isset( $remote['requires_php'] ) ? $remote['requires_php'] : '7.4';
		$info->tested        = isset( $remote['tested'] ) ? $remote['tested'] : get_bloginfo( 'version' );
		$info->author        = '<a href="https://infinitycoder.app">Infinity Coder</a>';
		$info->homepage      = isset( $remote['homepage'] ) ? $remote['homepage'] : 'https://infinitycoder.app';
		$info->download_link = ! empty( $remote['download_url'] ) ? $remote['download_url'] : '';
		$info->sections      = array(
			'description' => isset( $remote['sections']['description'] ) ? wp_kses_post( $remote['sections']['description'] ) : '<p>' . __( 'Solution COD tout-en-un pour WooCommerce Algérie.', 'infinitycod' ) . '</p>',
			'changelog'   => isset( $remote['sections']['changelog'] ) ? wp_kses_post( $remote['sections']['changelog'] ) : '',
		);

		return $info;
	}
}
