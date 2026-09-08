<?php
/**
 * Updater commercial InfinityCod : GitHub Releases (dépôt public) via
 * l'API de mise à jour native de WordPress.
 *
 * Chaîne de sécurité d'une mise à jour :
 *   manifest (update.json) → compatibilité PHP/WP/WC → téléchargement
 *   → vérification SHA-256 → sauvegarde de la version courante → installation.
 *
 * Canaux : stable (défaut, /releases/latest) et beta (prereleases récentes).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\License;

use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Updater {

	/**
	 * Clé PUBLIQUE Ed25519 de vérification des manifests (base64).
	 *
	 * La clé privée de signature n existe QUE côté CI/serveur
	 * (GitHub Secrets) — jamais dans le plugin (§118-119).
	 */
	const SIGNING_PUBLIC_KEY = 'beDoIoaR5hZvEA2U93fiu80Bzg2uz78MT0n0EydFEKk=';

	/**
	 * Vérifie la signature Ed25519 d'un manifest signé.
	 *
	 * @param string $raw_json Corps JSON brut du manifest.
	 * @param string $sig_b64  Signature détachée (base64).
	 * @return bool
	 */
	public static function verify_manifest_signature( $raw_json, $sig_b64 ) {
		$sig = base64_decode( (string) $sig_b64, true );
		$pk  = base64_decode( self::SIGNING_PUBLIC_KEY, true );

		if ( false === $sig || 64 !== strlen( $sig ) || false === $pk ) {
			return false;
		}

		if ( function_exists( 'sodium_crypto_sign_detached_verify' ) ) {
			try {
				return sodium_crypto_sign_detached_verify( (string) $raw_json, $sig, $pk );
			} catch ( \SodiumException $e ) {
				return false;
			}
		}

		if ( class_exists( 'Paragonie_Sodium_Compat' ) ) {
			try {
				return \Paragonie_Sodium_Compat::crypto_sign_detached_verify( (string) $raw_json, $sig, $pk );
			} catch ( \Exception $e ) {
				return false;
			}
		}

		return false;
	}


	/**
	 * Dépôt PUBLIC des releases (« proprietaire/depot »).
	 *
	 * @return string
	 */
	public static function releases_repo() {
		$repo = trim( (string) Settings::get( 'releases_repo', '' ) );
		return preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo ) ? $repo : '';
	}

	/**
	 * Dépôt des sources (« proprietaire/depot »), repli avec token.
	 *
	 * @return string
	 */
	public static function github_repo() {
		$repo = trim( (string) Settings::get( 'github_repo', '' ) );
		return preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo ) ? $repo : '';
	}

	/**
	 * Token GitHub (lecture) — seulement pour le repli dépôt privé.
	 *
	 * @return string
	 */
	public static function github_token() {
		return trim( (string) Settings::get( 'github_token', '' ) );
	}

	/**
	 * Canal de mise à jour : 'stable' ou 'beta'.
	 *
	 * @return string
	 */
	public static function channel() {
		$channel = Settings::get( 'update_channel', 'stable' );
		return in_array( $channel, array( 'stable', 'beta' ), true ) ? $channel : 'stable';
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'secure_download' ), 10, 4 );
		add_action( 'upgrader_process_complete', array( $this, 'on_upgrade_complete' ), 10, 2 );

		// Vérification horaire (indépendante du cycle natif de 12 h).
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

		if ( '' !== self::releases_repo() ) {
			return;
		}

		$cached = get_transient( 'icod_update_gh' );
		if ( ! is_array( $cached ) || empty( $cached['unreachable'] ) ) {
			return;
		}

		if ( 'network' === ( isset( $cached['reason'] ) ? $cached['reason'] : '' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
			esc_html__( 'InfinityCod — mises à jour indisponibles :', 'infinitycod' ),
			esc_html__( 'le dépôt GitHub est privé et aucun token n‘est configuré. Créez un dépôt public « releases » ou collez un token GitHub dans les réglages.', 'infinitycod' ),
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
	 * Mise à jour automatique si le marchand l'a activée.
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
	 * Infos de la dernière version (public — page À propos / Updates).
	 *
	 * @return array|null
	 */
	public function latest() {
		return $this->remote();
	}

	/**
	 * Construit une url d'API GitHub pour un dépôt validé.
	 *
	 * IMPORTANT : le slash du chemin « owner/repo » ne doit JAMAIS être
	 * encodé (%2F) — un rawurlencode global renvoie un 404 systématique.
	 * Le dépôt est déjà validé par la regex [A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+.
	 *
	 * @param string $repo Dépôt validé (owner/name).
	 * @param string $path Chemin API (ex. /releases/latest).
	 * @return string
	 */
	public static function api_url( $repo, $path ) {
		return 'https://api.github.com/repos/' . $repo . $path;
	}

	/**
	 * Liste des releases récentes (pour le rollback sur la page Updates).
	 *
	 * @param int $limit Nombre maximum.
	 * @return array[] version, url, date, prerelease.
	 */
	public function recent_releases( $limit = 10 ) {
		$repo = self::releases_repo();
		if ( '' === $repo ) {
			return array();
		}

		$args = array(
			'timeout' => 15,
			'headers' => array(
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'InfinityCod-Updater/' . INFINITYCOD_VERSION,
			),
		);

		$token = self::github_token();
		if ( '' !== $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get( self::api_url( $repo, '/releases?per_page=' . (int) $limit ), $args );
		$body     = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
		$releases = json_decode( $body, true );

		$out = array();
		if ( is_array( $releases ) ) {
			foreach ( $releases as $release ) {
				if ( empty( $release['tag_name'] ) || ! empty( $release['draft'] ) ) {
					continue;
				}
				$zip = '';
				foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
					if ( isset( $asset['browser_download_url'] ) && substr( strtolower( (string) $asset['name'] ), -4 ) === '.zip' ) {
						$zip = (string) $asset['browser_download_url'];
						break;
					}
				}
				if ( '' === $zip ) {
					continue;
				}
				$out[] = array(
					'version'    => ltrim( (string) $release['tag_name'], 'vV' ),
					'url'        => (string) ( $release['html_url'] ?? '' ),
					'date'       => isset( $release['published_at'] ) ? substr( (string) $release['published_at'], 0, 10 ) : '',
					'prerelease' => ! empty( $release['prerelease'] ),
					'package'    => $zip,
				);
			}
		}

		return $out;
	}

	/**
	 * Infos de la dernière version disponible — source unique : GitHub.
	 *
	 * @return array|null version, download_url, homepage, changelog, sha256.
	 */
	private function remote() {
		try {
			return $this->remote_github();
		} catch ( \Throwable $e ) {
			\InfinityCod\Logging\Logger::log( 'error', 'remote : ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Dernière release GitHub selon le canal (cache 2 h).
	 *
	 * @return array|null version, download_url, homepage, changelog, sha256.
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

		// Canal beta : liste des releases récentes (prereleases incluses).
		$endpoint = 'beta' === self::channel()
			? self::api_url( $repo, '/releases?per_page=5' )
			: self::api_url( $repo, '/releases/latest' );

		$response = wp_remote_get( $endpoint, $args );

		$status  = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$body    = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		$release = null;
		if ( 'beta' === self::channel() && is_array( $decoded ) ) {
			foreach ( $decoded as $candidate ) {
				if ( ! empty( $candidate['tag_name'] ) ) {
					$release = $candidate; // La plus récente, prerelease incluse.
					break;
				}
			}
		} elseif ( is_array( $decoded ) && ! empty( $decoded['tag_name'] ) ) {
			$release = $decoded;
		}

		if ( ! is_array( $release ) || empty( $release['tag_name'] ) ) {
			set_transient( 'icod_update_gh', array(
				'unreachable' => 1,
				'reason'      => 404 === $status ? 'private_or_empty' : 'network',
				'repo'        => $repo,
			), 30 * MINUTE_IN_SECONDS );
			return null;
		}

		// Manifest update.json prioritaire (contient le SHA-256), sinon zip direct.
		$manifest = null;
		$zip      = '';
		foreach ( (array) ( $release['assets'] ?? array() ) as $asset ) {
			$name = strtolower( (string) ( $asset['name'] ?? '' ) );
			$url  = (string) ( $asset['browser_download_url'] ?? '' );
			if ( 'update.json' === $name ) {
				$mres = wp_remote_get( $url, $args );
				$mraw = is_wp_error( $mres ) ? '' : (string) wp_remote_retrieve_body( $mres );
				$mdec = json_decode( $mraw, true );

				// Signature Ed25519 (update.json.sig) : si présente, elle doit être valide.
				$sres = wp_remote_get( $url . '.sig', $args );
				$sig  = is_wp_error( $sres ) ? '' : trim( (string) wp_remote_retrieve_body( $sres ) );

				if ( '' !== $sig && ! self::verify_manifest_signature( $mraw, $sig ) ) {
					\InfinityCod\Logging\Logger::log( 'security', 'Manifest signature INVALIDE — manifest ignoré (mise à jour non proposée).' );
					set_transient( 'icod_update_gh', array( 'unreachable' => 1, 'reason' => 'bad_signature' ), 30 * MINUTE_IN_SECONDS );
					return null;
				}

				if ( is_array( $mdec ) && ! empty( $mdec['version'] ) && ! empty( $mdec['sha256'] ) ) {
					$manifest = $mdec;
				}
			}
			if ( '' === $zip && substr( $name, -4 ) === '.zip' ) {
				$zip = $url;
			}
		}

		$data = array(
			'version'      => $manifest['version'] ?? ltrim( (string) $release['tag_name'], 'vV' ),
			'download_url' => '' !== ( $manifest['download_url'] ?? '' ) ? (string) $manifest['download_url'] : $zip,
			'homepage'     => isset( $release['html_url'] ) ? (string) $release['html_url'] : '',
			'changelog'    => isset( $release['body'] ) ? (string) $release['body'] : '',
			'sha256'       => $manifest['sha256'] ?? '',
			'requires_php' => $manifest['requires_php'] ?? '7.4',
			'requires'     => $manifest['requires'] ?? '6.0',
			'source'       => 'github',
		);

		if ( '' === $data['download_url'] ) {
			set_transient( 'icod_update_gh', array( 'unreachable' => 1, 'reason' => 'no_package' ), 30 * MINUTE_IN_SECONDS );
			return null;
		}

		set_transient( 'icod_update_gh', $data, 2 * HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * Vide les caches de mise à jour.
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

		$transient->response[ INFINITYCOD_BASENAME ] = (object) array(
			'slug'        => 'infinitycod',
			'plugin'      => INFINITYCOD_BASENAME,
			'new_version' => (string) $remote['version'],
			'url'         => ! empty( $remote['homepage'] ) ? (string) $remote['homepage'] : 'https://infinitycoder.app',
			'package'     => (string) $remote['download_url'],
			'requires'    => (string) ( $remote['requires'] ?? '6.0' ),
			'requires_php' => (string) ( $remote['requires_php'] ?? '7.4' ),
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

		$features = '<h4>' . esc_html__( 'Ce que fait InfinityCod', 'infinitycod' ) . '</h4><ul>'
			. '<li>' . esc_html__( 'Formulaire COD one-page mobile-first : 5 thèmes, mode sombre, RTL arabe.', 'infinitycod' ) . '</li>'
			. '<li>' . esc_html__( '58 wilayas + 1541 communes officielles (noms français et arabes).', 'infinitycod' ) . '</li>'
			. '<li>' . esc_html__( 'Bouclier anti-fraude : score de risque, blacklist, doublons, limitation par IP.', 'infinitycod' ) . '</li>'
			. '<li>' . esc_html__( 'Dashboard commandes avec filtres, actions groupées et export Excel.', 'infinitycod' ) . '</li>'
			. '<li>' . esc_html__( 'Transporteurs intégrés : Yalidine, ZR Express, Maystro, Noest, E-COM, DHD — colis en 1 clic et suivi automatique.', 'infinitycod' ) . '</li>'
			. '<li>' . esc_html__( 'WhatsApp automatique et relance des paniers abandonnés.', 'infinitycod' ) . '</li>'
			. '<li>' . esc_html__( 'Paiement en ligne CIB / Edahabia via Chargily Pay.', 'infinitycod' ) . '</li>'
			. '<li>' . esc_html__( 'Offres par quantité et statistiques P&L par wilaya, transporteur et produit.', 'infinitycod' ) . '</li>'
			. '</ul>';

		$installation = '<ol>'
			. '<li>' . esc_html__( 'Téléversez le zip via Extensions → Ajouter → Téléverser, puis activez.', 'infinitycod' ) . '</li>'
			. '<li>' . esc_html__( 'Les 58 wilayas et 1541 communes sont importées automatiquement.', 'infinitycod' ) . '</li>'
			. '<li>' . esc_html__( 'InfinityCod → Wilayas & Tarifs : définissez vos tarifs domicile / stopdesk.', 'infinitycod' ) . '</li>'
			. '<li>' . esc_html__( 'Réglages → Formulaire : personnalisez le thème, les libellés, l‘upsell et la redirection.', 'infinitycod' ) . '</li>'
			. '</ol>';

		$faq = '<h4>' . esc_html__( 'Le formulaire apparaît où ?', 'infinitycod' ) . '</h4><p>' . esc_html__( 'Automatiquement (position réglable). Shortcode : [infinitycod_form] ou [infinitycod_form id="123"].', 'infinitycod' ) . '</p>'
			. '<h4>' . esc_html__( 'Compatibilité des thèmes ?', 'infinitycod' ) . '</h4><p>' . esc_html__( 'Oui : styles isolés et renforcés (Astra, Flatsome, WoodMart, Divi…), mode sombre et RTL inclus.', 'infinitycod' ) . '</p>'
			. '<h4>' . esc_html__( 'Comment fonctionnent les mises à jour ?', 'infinitycod' ) . '</h4><p>' . esc_html__( 'Vérification horaire via GitHub ; mise à jour en 1 clic ou automatique ; intégrité SHA-256 vérifiée et sauvegarde créée avant installation.', 'infinitycod' ) . '</p>'
			. '<h4>' . esc_html__( 'Le paiement en ligne est-il sécurisé ?', 'infinitycod' ) . '</h4><p>' . esc_html__( 'Le client paie sur la page sécurisée Chargily ; la confirmation est vérifiée par API et par webhook signé.', 'infinitycod' ) . '</p>';

		$changelog = '';
		if ( $remote && ! empty( $remote['changelog'] ) ) {
			$changelog = '<pre style="white-space:pre-wrap;font-family:inherit">' . esc_html( (string) $remote['changelog'] ) . '</pre>';
		}

		$info                = new \stdClass();
		$info->name          = 'InfinityCod — Paiement à la livraison (COD Algérie)';
		$info->slug          = 'infinitycod';
		$info->version       = $remote && ! empty( $remote['version'] ) ? (string) $remote['version'] : INFINITYCOD_VERSION;
		$info->requires      = '6.0';
		$info->requires_php  = '7.4';
		$info->tested        = get_bloginfo( 'version' );
		$info->author        = '<a href="https://infinitycoder.app">Infinity Coder</a>';
		$info->homepage      = 'https://infinitycoder.app';
		$info->download_link = $remote && ! empty( $remote['download_url'] ) ? (string) $remote['download_url'] : '';
		$info->sections      = array(
			'description'  => '<p>' . esc_html__( 'La solution de paiement à la livraison tout-en-un pensée pour l‘Algérie.', 'infinitycod' ) . '</p>' . $features,
			'installation' => $installation,
			'faq'          => $faq,
			'changelog'    => $changelog,
		);

		return $info;
	}

	/**
	 * Intercepte le téléchargement du package pour :
	 * 1. vérifier la compatibilité PHP / WordPress ;
	 * 2. vérifier l'intégrité SHA-256 quand le manifest le fournit ;
	 * 3. sauvegarder la version courante avant remplacement ;
	 * 4. télécharger nous-mêmes les assets d'un dépôt privé (auth + S3).
	 *
	 * @param false|mixed $reply   Valeur par défaut.
	 * @param string      $package Url du package.
	 * @return false|string|\\WP_Error False = comportement standard ; string = octets du zip ; WP_Error = abandon avec message.
	 */
	public function secure_download( $reply, $package ) {
		if ( ! is_string( $package ) || false === strpos( $package, 'infinitycod' ) ) {
			return $reply;
		}

		// 1. Compatibilité : bloquer une version incompatible avant téléchargement.
		$remote = $this->remote();
		if ( $remote ) {
			$compat = $this->check_compatibility( $remote );
			if ( is_wp_error( $compat ) ) {
				return $compat; // Message explicite, version courante conservée.
			}
		}

		// 2. Sauvegarde de la version courante (fichiers + réglages).
		$backup = $this->backup_current();
		if ( is_wp_error( $backup ) ) {
			\InfinityCod\Logging\Logger::log( 'update', 'Backup failed: ' . $backup->get_error_message() );
			// Non bloquant : l'update peut continuer, mais on le journalise.
		} else {
			\InfinityCod\Logging\Logger::log( 'update', 'Backup created: ' . (string) $backup );
		}

		// 3. Intégrité : si un SHA-256 est connu, télécharger et vérifier nous-mêmes.
		$sha256 = is_array( $remote ) ? (string) ( $remote['sha256'] ?? '' ) : '';
		if ( '' !== $sha256 ) {
			$bytes = $this->fetch_package( $package );
			if ( is_wp_error( $bytes ) ) {
				return new \WP_Error( 'icod_download_failed', __( 'InfinityCod : le téléchargement du package a échoué. Votre version actuelle reste installée. ', 'infinitycod' ) . $bytes->get_error_message() );
			}
			if ( ! $this->verify_sha256( $bytes, $sha256 ) ) {
				\InfinityCod\Logging\Logger::log( 'security', 'SHA-256 mismatch — update aborted.' );
				return new \WP_Error( 'icod_checksum', __( 'InfinityCod : mise à jour annulée — la vérification d‘intégrité SHA-256 a échoué. Votre version actuelle reste installée.', 'infinitycod' ) );
			}
			\InfinityCod\Logging\Logger::log( 'update', 'Package SHA-256 verified.' );
			return $bytes;
		}

		return $reply; // Pas de manifest : comportement WordPress standard.
	}

	/**
	 * Compatibilité de la version cible avec l'environnement.
	 *
	 * @param array $remote Infos distantes (requires_php, requires).
	 * @return true|\\WP_Error
	 */
	public function check_compatibility( $remote ) {
		$requires_php = (string) ( $remote['requires_php'] ?? '7.4' );
		$requires_wp  = (string) ( $remote['requires'] ?? '6.0' );

		if ( version_compare( PHP_VERSION, $requires_php, '<' ) ) {
			return new \WP_Error(
				'icod_php_incompatible',
				sprintf(
					/* translators: 1 : version requise, 2 : version actuelle. */
					__( 'InfinityCod %1$s requiert PHP %2$s+. Votre serveur utilise PHP %3$s. Mise à jour bloquée, votre version actuelle reste installée.', 'infinitycod' ),
					(string) ( $remote['version'] ?? '' ),
					$requires_php,
					PHP_VERSION
				)
			);
		}

		global $wp_version;
		if ( isset( $wp_version ) && version_compare( $wp_version, $requires_wp, '<' ) ) {
			return new \WP_Error(
				'icod_wp_incompatible',
				sprintf(
					/* translators: 1 : version requise, 2 : version actuelle. */
					__( 'InfinityCod %1$s requiert WordPress %2$s+. Votre site utilise WordPress %3$s. Mise à jour bloquée.', 'infinitycod' ),
					(string) ( $remote['version'] ?? '' ),
					$requires_wp,
					$wp_version
				)
			);
		}

		return true;
	}

	/**
	 * Vérifie l'intégrité SHA-256 des octets du package.
	 *
	 * @param string $bytes  Contenu du zip.
	 * @param string $sha256 Empreinte attendue (hex).
	 * @return bool
	 */
	public static function verify_sha256( $bytes, $sha256 ) {
		$sha256 = strtolower( trim( (string) $sha256 ) );
		if ( '' === $sha256 || 64 !== strlen( $sha256 ) ) {
			return false;
		}
		return hash_equals( $sha256, hash( 'sha256', (string) $bytes ) );
	}

	/**
	 * Sauvegarde la version courante du plugin (fichiers + réglages clés)
	 * dans uploads/infinitycod-backups/infinitycod-VERSION/. Conserve les
	 * 3 sauvegardes les plus récentes.
	 *
	 * @return string|\\WP_Error Chemin de la sauvegarde.
	 */
	public function backup_current() {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return new \WP_Error( 'icod_fs', 'Filesystem unavailable' );
		}

		$backup_dir = $wp_filesystem->wp_content_dir() . 'uploads/infinitycod-backups';
		$target     = $backup_dir . '/infinitycod-' . INFINITYCOD_VERSION;

		if ( $wp_filesystem->is_dir( $target ) ) {
			return $target; // Déjà sauvegardé pour cette version.
		}
		if ( ! $wp_filesystem->is_dir( $backup_dir ) && ! $wp_filesystem->mkdir( $backup_dir, FS_CHMOD_DIR ) ) {
			return new \WP_Error( 'icod_fs', 'Cannot create backup directory' );
		}

		// Copie récursive des fichiers du plugin.
		$source = WP_PLUGIN_DIR . '/' . dirname( INFINITYCOD_BASENAME );
		$this->copy_recursive( $source, $target );

		// Réglages + état des migrations.
		$state = array(
			'version'    => INFINITYCOD_VERSION,
			'date'       => current_time( 'mysql' ),
			'settings'   => get_option( 'infinitycod_settings', array() ),
			'db_version' => get_option( 'infinitycod_db_version', '' ),
			'license'    => get_option( 'infinitycod_license', array() ),
		);
		$wp_filesystem->put_contents( $target . '/backup-state.json', wp_json_encode( $state ), FS_CHMOD_FILE );

		// Politique de rétention : 3 sauvegardes maximum (§84).
		$dirs = (array) $wp_filesystem->dirlist( $backup_dir );
		$kept = array();
		foreach ( $dirs as $name => $info ) {
			if ( 'd' === $info['type'] && 0 === strpos( $name, 'infinitycod-' ) ) {
				$kept[ $name ] = $info['time'];
			}
		}
		if ( count( $kept ) > 3 ) {
			asort( $kept );
			$oldest = array_slice( array_keys( $kept ), 0, count( $kept ) - 3 );
			foreach ( $oldest as $name ) {
				$wp_filesystem->delete( $backup_dir . '/' . $name, true );
			}
		}

		return $target;
	}

	/**
	 * Copie récursive via WP_Filesystem.
	 *
	 * @param string $src Source.
	 * @param string $dst Destination.
	 * @return void
	 */
	private function copy_recursive( $src, $dst ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem->is_dir( $dst ) && ! $wp_filesystem->mkdir( $dst, FS_CHMOD_DIR ) ) {
			return;
		}

		foreach ( (array) $wp_filesystem->dirlist( $src ) as $name => $info ) {
			$from = trailingslashit( $src ) . $name;
			$to   = trailingslashit( $dst ) . $name;
			if ( 'd' === $info['type'] ) {
				$this->copy_recursive( $from, $to );
			} else {
				$wp_filesystem->copy( $from, $to, true, FS_CHMOD_FILE );
			}
		}
	}

	/**
	 * Restaure une sauvegarde de fichiers du plugin (rollback réel).
	 *
	 * @param string $version Version sauvegardée (ex. 2.0.0).
	 * @return true|\\WP_Error
	 */
	public function restore_backup( $version ) {
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return new \WP_Error( 'icod_fs', 'Filesystem unavailable' );
		}

		$version  = preg_replace( '/[^0-9.]/', '', (string) $version );
		$backup   = $wp_filesystem->wp_content_dir() . 'uploads/infinitycod-backups/infinitycod-' . $version;
		$state    = $backup . '/backup-state.json';

		if ( ! $wp_filesystem->is_dir( $backup ) || ! $wp_filesystem->exists( $state ) ) {
			return new \WP_Error( 'icod_no_backup', __( 'Aucune sauvegarde trouvée pour cette version.', 'infinitycod' ) );
		}

		$plugin_dir = WP_PLUGIN_DIR . '/' . dirname( INFINITYCOD_BASENAME );

		// Vide le dossier du plugin puis restaure les fichiers sauvegardés.
		$wp_filesystem->delete( $plugin_dir, true );
		$this->copy_recursive( $backup, $plugin_dir );

		// Restaure réglages + licence (la version BDD reste compatible : migrations non destructives).
		$raw = $wp_filesystem->get_contents( $state );
		$st  = json_decode( (string) $raw, true );
		if ( is_array( $st ) ) {
			if ( isset( $st['settings'] ) ) {
				update_option( 'infinitycod_settings', $st['settings'], true );
			}
			if ( isset( $st['license'] ) ) {
				update_option( 'infinitycod_license', $st['license'], true );
			}
		}

		$this->record_history( INFINITYCOD_VERSION, $version, 'rollback', 'success', '' );
		self::clear_cache();

		\InfinityCod\Logging\Logger::log( 'update', 'Rollback ' . INFINITYCOD_VERSION . ' → ' . $version . ' (success).' );

		return true;
	}

	/**
	 * Enregistre une entrée d'historique de mise à jour.
	 *
	 * @param string $from        Version avant.
	 * @param string $to          Version après.
	 * @param string $action      update|rollback.
	 * @param string $result      success|failed.
	 * @param string $error       Message d'erreur éventuel.
	 * @return void
	 */
	public function record_history( $from, $to, $action, $result, $error = '' ) {
		$history = get_option( 'infinitycod_update_history', array() );
		$history = is_array( $history ) ? $history : array();

		array_unshift( $history, array(
			'from'   => (string) $from,
			'to'     => (string) $to,
			'action' => (string) $action,
			'result' => (string) $result,
			'error'  => mb_substr( (string) $error, 0, 200 ),
			'date'   => current_time( 'mysql' ),
		) );

		update_option( 'infinitycod_update_history', array_slice( $history, 0, 30 ), false );
	}

	/**
	 * Après une mise à jour réussie par WordPress : journalise.
	 *
	 * @param object $upgrader Upgrader.
	 * @param array  $hook_extra Contexte (type, action, plugins…).
	 * @return void
	 */
	public function on_upgrade_complete( $upgrader, $hook_extra ) {
		if ( empty( $hook_extra['type'] ) || 'plugin' !== $hook_extra['type'] || empty( $hook_extra['plugins'] ) ) {
			return;
		}
		if ( ! in_array( INFINITYCOD_BASENAME, (array) $hook_extra['plugins'], true ) ) {
			return;
		}

		$this->record_history( INFINITYCOD_VERSION, INFINITYCOD_VERSION, 'update', 'success', '' );
		\InfinityCod\Logging\Logger::log( 'update', 'Plugin updated via WordPress (installed now: ' . INFINITYCOD_VERSION . ').' );
	}

	/**
	 * Intercepte le téléchargement du package pour l'authentification
	 * d'un dépôt PRIVÉ (auth + redirection S3) et pour la vérification
	 * SHA-256 quand un manifest la fournit.
	 *
	 * @param string $package Url du zip.
	 * @return string|\\WP_Error Octets du zip ou erreur.
	 */
	private function fetch_package( $package ) {
		$token = self::github_token();
		$needs_auth = '' !== $token && (
			false !== strpos( $package, 'github.com/' . self::github_repo() . '/' )
			|| false !== strpos( $package, 'github.com/' . self::releases_repo() . '/' )
		);

		$headers = array( 'User-Agent' => 'InfinityCod-Updater/' . INFINITYCOD_VERSION );

		if ( $needs_auth ) {
			// Étape 1 : obtenir l'URL signée (redirection suivie manuellement).
			$signed = wp_remote_get(
				$package,
				array(
					'timeout'    => 60,
					'redirection' => 0,
					'headers'    => array_merge( $headers, array(
						'Authorization' => 'Bearer ' . $token,
						'Accept'        => 'application/octet-stream',
					) ),
				)
			);

			if ( is_wp_error( $signed ) ) {
				return $signed;
			}

			$code     = (int) wp_remote_retrieve_response_code( $signed );
			$location = wp_remote_retrieve_header( $signed, 'location' );

			if ( $code >= 300 && $code < 400 && $location ) {
				// Étape 2 : S3 signé, SANS Authorization.
				$final = wp_remote_get( $location, array( 'timeout' => 120, 'redirection' => 2 ) );
				if ( is_wp_error( $final ) ) {
					return $final;
				}
				return wp_remote_retrieve_body( $final );
			}

			if ( $code >= 200 && $code < 300 ) {
				return wp_remote_retrieve_body( $signed );
			}

			return new \WP_Error( 'icod_http_' . $code, 'HTTP ' . $code );
		}

		// Dépôt public : téléchargement direct.
		$response = wp_remote_get( $package, array( 'timeout' => 120, 'redirection' => 3 ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Téléchargement d'un asset GitHub de dépôt PRIVÉ (fallback upgrader
	 * standard utilisé quand aucun SHA-256 n'est disponible).
	 *
	 * @param false|mixed $reply   Valeur par défaut.
	 * @param string      $package Url du zip.
	 * @return false|string
	 */
	public function auth_private_download( $reply, $package ) {
		if ( $reply || ! is_string( $package ) ) {
			return $reply;
		}

		$repos  = array_filter( array( self::releases_repo(), self::github_repo() ) );
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

		$bytes = $this->fetch_package( $package );
		if ( is_wp_error( $bytes ) || '' === $bytes || 'PK' !== substr( $bytes, 0, 2 ) ) {
			return $reply;
		}
		return $bytes;
	}
}
