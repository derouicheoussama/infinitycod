<?php
/**
 * Mises à jour à distance via GitHub Releases : l'API standard de
 * WordPress (écran Extensions, notification, mise à jour en 1 clic ou
 * automatique) consulte le dépôt public des releases.
 *
 * GitHub : le plugin interroge l'API `/releases/latest` du dépôt public
 * des releases (Réglages → Avancé). Le dépôt privé des sources + token
 * reste utilisable en repli.
 *
 * @package InfinityCod
 */

namespace InfinityCod\License;

use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Updater {

	/**
	 * Dépôt PUBLIC des releases (« proprietaire/depot »), consultable sans
	 * token par toutes les boutiques clientes. Prioritaire sur github_repo.
	 *
	 * @return string
	 */
	public static function releases_repo() {
		$repo = trim( (string) Settings::get( 'releases_repo', '' ) );
		return preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo ) ? $repo : '';
	}

	/**
	 * Dépôt GitHub configuré (« proprietaire/depot ») ou vide.
	 *
	 * @return string
	 */
	public static function github_repo() {
		$repo = trim( (string) Settings::get( 'github_repo', '' ) );
		return preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo ) ? $repo : '';
	}

	/**
	 * Token GitHub (lecture) éventuel — requis pour un dépôt privé.
	 *
	 * @return string
	 */
	public static function github_token() {
		return trim( (string) Settings::get( 'github_token', '' ) );
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'auth_private_download' ), 10, 4 );

		// Vérification horaire indépendante du cycle natif de WordPress (12 h) :
		// les clients voient une nouvelle release en quelques heures max.
		add_action( 'infinitycod_update_check', array( $this, 'force_check' ) );
		if ( ! wp_next_scheduled( 'infinitycod_update_check' ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', 'infinitycod_update_check' );
		}

		// Mise à jour automatique (option marchand).
		add_filter( 'auto_update_plugin', array( $this, 'auto_update' ), 20, 2 );

		// Diagnostic : prévenir si les mises à jour sont indisponibles.
		add_action( 'admin_notices', array( $this, 'update_notice' ) );
	}

	/**
	 * Notice admin quand la vérification GitHub échoue (dépôt privé sans
	 * token ni dépôt public de releases).
	 *
	 * @return void
	 */
	public function update_notice() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// Dépôt public configuré : rien à signaler.
		if ( '' !== self::releases_repo() ) {
			return;
		}

		$cached = get_transient( 'icod_update_gh' );
		if ( ! is_array( $cached ) || empty( $cached['unreachable'] ) ) {
			return;
		}

		if ( 'network' === ( isset( $cached['reason'] ) ? $cached['reason'] : '' ) ) {
			return; // Simple incident réseau : pas d'alerte.
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'InfinityCod — mises à jour indisponibles :', 'infinitycod' ),
			esc_html__( 'le dépôt GitHub est privé et aucun token n‘est configuré. Créez un dépôt public « releases » (recommandé, sans token côté clients) ou collez un token GitHub dans les réglages.', 'infinitycod' ),
			esc_url( admin_url( 'admin.php?page=infinitycod-settings&tab=advanced' ) ),
			esc_html__( 'Configurer', 'infinitycod' )
		);
	}

	/**
	 * Rafraîchit les informations de version et redéclenche la vérification WP.
	 *
	 * @return void
	 */
	public function force_check() {
		self::clear_cache();

		if ( function_exists( 'wp_update_plugins' ) ) {
			delete_site_transient( 'update_plugins' );
			wp_update_plugins();
		}
	}

	/**
	 * Mise à jour automatique du plugin si le marchand l'a activée.
	 *
	 * @param bool   $update Décision courante.
	 * @param object $item   Plugin concerné.
	 * @return bool
	 */
	public function auto_update( $update, $item ) {
		if ( isset( $item->plugin ) && INFINITYCOD_BASENAME === $item->plugin && Settings::get( 'auto_update' ) ) {
			return true;
		}
		return $update;
	}

	/**
	 * Infos de la dernière version disponible (public, pour la page À propos).
	 *
	 * @return array|null
	 */
	public function latest() {
		return $this->remote();
	}

	/**
	 * Récupère les infos de la dernière version (GitHub Releases uniquement).
	 *
	 * @return array|null version, download_url, homepage, changelog.
	 */
	private function remote() {
		return $this->remote_github();
	}

	/**
	 * Dernière release GitHub (avec cache 2 h).
	 *
	 * Source prioritaire : dépôt PUBLIC des releases (aucun token requis
	 * chez les clients). Repli : dépôt privé + token.
	 *
	 * @return array|null
	 */
	private function remote_github() {
		$repo  = self::releases_repo();
		$token = '';

		if ( '' === $repo ) {
			$repo  = self::github_repo();
			$token = self::github_token();
		}

		if ( '' === $repo ) {
			return null;
		}

		$cached = get_transient( 'icod_update_gh' );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$args = array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'InfinityCod-Updater/' . INFINITYCOD_VERSION,
			),
		);

		if ( '' !== $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get( 'https://api.github.com/repos/' . rawurlencode( str_replace( '.git', '', $repo ) ) . '/releases/latest', $args );

		$status = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$body   = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
		$release = $status >= 200 && $status < 300 ? json_decode( $body, true ) : null;

		if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
			// 404 = dépôt privé sans token, ou aucune release publiée.
			set_transient( 'icod_update_gh', array(
				'unreachable' => 1,
				'reason'      => 404 === $status ? 'private_or_empty' : 'network',
				'repo'        => $repo,
			), 30 * MINUTE_IN_SECONDS );
			return null;
		}

		// Le zip attaché à la release (généré par le workflow GitHub Actions).
		$download = '';
		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( isset( $asset['browser_download_url'] ) && substr( strtolower( (string) $asset['name'] ), -4 ) === '.zip' ) {
					$download = (string) $asset['browser_download_url'];
					break;
				}
			}
		}

		$data = array(
			'version'      => ltrim( (string) $release['tag_name'], 'vV' ),
			'download_url' => $download,
			'homepage'     => isset( $release['html_url'] ) ? (string) $release['html_url'] : '',
			'changelog'    => isset( $release['body'] ) ? (string) $release['body'] : '',
		);

		set_transient( 'icod_update_gh', $data, 2 * HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * Vide les caches de mise à jour (bouton « Vérifier les mises à jour »).
	 *
	 * @return void
	 */
	public static function clear_cache() {
		delete_transient( 'icod_update_gh' );
	}

	/**
	 * Injecte la mise à jour dans la transient WordPress si plus récente.
	 *
	 * @param object $transient Transient update_plugins.
	 * @return object
	 */
	public function inject_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = $this->remote();

		if ( ! $remote || empty( $remote['version'] ) || empty( $remote['download_url'] ) ) {
			return $transient;
		}

		if ( version_compare( INFINITYCOD_VERSION, (string) $remote['version'], '>=' ) ) {
			return $transient;
		}

		$package = (string) $remote['download_url'];

		$transient->response[ INFINITYCOD_BASENAME ] = (object) array(
			'slug'        => 'infinitycod',
			'plugin'      => INFINITYCOD_BASENAME,
			'new_version' => (string) $remote['version'],
			'url'         => ! empty( $remote['homepage'] ) ? (string) $remote['homepage'] : 'https://infinitycoder.app',
			'package'     => $package,
			'requires'    => '6.0',
			'requires_php' => '7.4',
			'tested'      => get_bloginfo( 'version' ),
		);

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

		$changelog = '';
		if ( ! empty( $remote['changelog'] ) ) {
			$changelog = '<pre style="white-space:pre-wrap;font-family:inherit">' . esc_html( (string) $remote['changelog'] ) . '</pre>';
		}

		$info                = new \stdClass();
		$info->name          = 'InfinityCod — Paiement à la livraison (COD Algérie)';
		$info->slug          = 'infinitycod';
		$info->version       = (string) $remote['version'];
		$info->requires      = '6.0';
		$info->requires_php  = '7.4';
		$info->tested        = get_bloginfo( 'version' );
		$info->author        = '<a href="https://infinitycoder.app">Infinity Coder</a>';
		$info->homepage      = ! empty( $remote['homepage'] ) ? (string) $remote['homepage'] : 'https://infinitycoder.app';
		$info->download_link = (string) $remote['download_url'];
		$info->sections      = array(
			'description' => '<p>' . __( 'Solution COD tout-en-un pour WooCommerce Algérie : formulaire de commande rapide, 58 wilayas & 1541 communes, transporteurs intégrés, WhatsApp automatique et statistiques P&L.', 'infinitycod' ) . '</p>',
			'changelog'   => $changelog,
		);

		return $info;
	}

	/**
	 * Téléchargement d'un asset GitHub de dépôt PRIVÉ.
	 *
	 * WordPress télécharge le zip sans en-tête d'autorisation : GitHub
	 * répond 404/403 sur un dépôt privé. On effectue le téléchargement
	 * nous-mêmes en deux temps (auth sur github.com, puis l'URL signée S3
	 * de redirection SANS en-tête d'autorisation) et on renvoie les octets
	 * à l'upgrader.
	 *
	 * @param false|mixed $reply    Valeur par défaut (false = comportement standard).
	 * @param string      $package  Url du zip en cours de téléchargement.
	 * @return false|string Octets du zip, ou false pour le comportement standard.
	 */
	public function auth_private_download( $reply, $package ) {
		if ( $reply || ! is_string( $package ) ) {
			return $reply;
		}

		$repos = array_filter( array( self::releases_repo(), self::github_repo() ) );
		$matched = '';

		foreach ( $repos as $repo ) {
			if ( false !== strpos( $package, 'github.com/' . $repo . '/' ) ) {
				$matched = $repo;
				break;
			}
		}

		if ( '' === $matched ) {
			return $reply;
		}

		$token = self::github_token();
		if ( '' === $token ) {
			return $reply; // Dépôt public : téléchargement standard.
		}

		// 1re étape : URL signée (redirection suivie manuellement).
		$step1 = wp_remote_get(
			$package,
			array(
				'timeout'    => 60,
				'redirection' => 0,
				'headers'    => array(
					'Authorization' => 'Bearer ' . $token,
					'User-Agent'    => 'InfinityCod-Updater/' . INFINITYCOD_VERSION,
					'Accept'        => 'application/octet-stream',
				),
			)
		);

		if ( is_wp_error( $step1 ) ) {
			return $reply;
		}

		$code = (int) wp_remote_retrieve_response_code( $step1 );

		// Dépôt public ou asset déjà servi directement : contenu renvoyé tel quel.
		if ( $code >= 200 && $code < 300 ) {
			return wp_remote_retrieve_body( $step1 );
		}

		$location = wp_remote_retrieve_header( $step1, 'location' );

		if ( ! $location || ( $code < 300 || $code >= 400 ) ) {
			return $reply;
		}

		// 2e étape : S3 signé, SANS en-tête d'autorisation.
		$step2 = wp_remote_get(
			$location,
			array(
				'timeout'    => 120,
				'redirection' => 2,
			)
		);

		if ( is_wp_error( $step2 ) ) {
			return $reply;
		}

		$zip = wp_remote_retrieve_body( $step2 );

		if ( '' === $zip || 'PK' !== substr( $zip, 0, 2 ) ) {
			return $reply;
		}

		return $zip;
	}
}
