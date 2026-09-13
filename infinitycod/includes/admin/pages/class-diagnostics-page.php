<?php
/**
 * Page admin : Diagnostics — santé de l'environnement et du plugin.
 *
 * Chaque test : PASS / WARNING / FAIL avec explication. Rapport
 * téléchargeable SANS secrets (aucune clé, aucun token).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Admin\Pages;

use InfinityCod\Core\Schema;

defined( 'ABSPATH' ) || exit;

class DiagnosticsPage {

	/**
	 * Tests calculés.
	 *
	 * @var array[]
	 */
	private $results = array();

	/**
	 * Constructeur : handler du rapport.
	 */
	public function __construct() {
		add_action( 'admin_post_icod_diagnostics_download', array( $this, 'handle_download' ) );
		add_action( 'admin_post_icod_test_updater', array( $this, 'handle_test_updater' ) );
	}

	/**
	 * Statuts.
	 */
	const PASS = 'PASS';
	const WARN = 'WARNING';
	const FAIL = 'FAIL';

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public function render() {
		$this->run_checks();
		?>
		<div class="wrap icod-wrap">
			<h1 class="icod-title"><?php esc_html_e( 'Diagnostics InfinityCod', 'infinitycod' ); ?></h1>

			<?php $tested = isset( $_GET['icod_msg'] ) ? sanitize_key( wp_unslash( $_GET['icod_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php if ( 'tested' === $tested ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'Test de connexion effectué — voir la ligne « Dernier test de connexion » ci-dessous.', 'infinitycod' ); ?></p></div>
			<?php endif; ?>

			<div class="icod-card">
				<table class="widefat striped icod-table icod-diag-table">
					<thead><tr>
						<th><?php esc_html_e( 'Test', 'infinitycod' ); ?></th>
						<th><?php esc_html_e( 'Valeur', 'infinitycod' ); ?></th>
						<th><?php esc_html_e( 'Statut', 'infinitycod' ); ?></th>
						<th><?php esc_html_e( 'Détail', 'infinitycod' ); ?></th>
					</tr></thead>
					<tbody>
						<?php foreach ( $this->results as $row ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $row['label'] ); ?></strong></td>
								<td><?php echo esc_html( $row['value'] ); ?></td>
								<td>
									<span class="icod-status <?php echo esc_attr( $row['class'] ); ?>"><?php echo esc_html( $row['status'] ); ?></span>
								</td>
								<td class="icod-sub"><?php echo esc_html( $row['detail'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:14px">
					<input type="hidden" name="action" value="icod_diagnostics_download" />
					<?php wp_nonce_field( 'icod_diagnostics_download' ); ?>
					<button type="submit" class="button">⬇ <?php esc_html_e( 'Télécharger le rapport de diagnostic', 'infinitycod' ); ?></button>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=icod_test_updater' ), 'icod_test_updater' ) ); ?>">📡 <?php esc_html_e( 'Tester la connexion', 'infinitycod' ); ?></a>
					<span class="icod-hint" style="margin-inline-start:8px"><?php esc_html_e( 'Le rapport ne contient aucune clé ni donnée client.', 'infinitycod' ); ?></span>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Ajoute un résultat.
	 *
	 * @param string $label  Libellé.
	 * @param string $value  Valeur observée.
	 * @param string $status PASS|WARNING|FAIL.
	 * @param string $detail Explication.
	 * @return void
	 */
	private function add( $label, $value, $status, $detail = '' ) {
		$this->results[] = array(
			'label'  => $label,
			'value'  => $value,
			'status' => $status,
			'class'  => self::PASS === $status ? 'icod-status-delivered' : ( self::WARN === $status ? 'icod-status-no_answer' : 'icod-status-returned' ),
			'detail' => $detail,
		);
	}

	/**
	 * Exécute tous les contrôles.
	 *
	 * @return void
	 */
	private function run_checks() {
		global $wpdb, $wp_version;

		// Environnement.
		$this->add( 'PHP', PHP_VERSION, version_compare( PHP_VERSION, '7.4', '>=' ) ? self::PASS : self::FAIL, 'Requis : 7.4+' );
		$this->add( 'WordPress', $wp_version ?? get_bloginfo( 'version' ), version_compare( get_bloginfo( 'version' ), '6.0', '>=' ) ? self::PASS : self::WARN, 'Requis : 6.0+' );

		$wc = class_exists( 'WooCommerce' );
		$this->add( 'WooCommerce', $wc ? ( defined( 'WC_VERSION' ) ? WC_VERSION : __( 'actif', 'infinitycod' ) ) : __( 'non actif', 'infinitycod' ), $wc ? self::PASS : self::FAIL, 'Requis : 6.0+' );

		$this->add( 'MySQL', (string) $wpdb->db_version(), $wpdb->db_version() ? self::PASS : self::WARN, '' );

		$mem = ini_get( 'memory_limit' );
		$this->add( 'Memory limit', (string) $mem, ( -1 === (int) $mem || $this->shorthand_to_bytes( $mem ) >= 128 * 1048576 ) ? self::PASS : self::WARN, 'Recommandé : 128M+' );

		$this->add( 'Upload max', (string) ini_get( 'upload_max_filesize' ), self::PASS, '' );

		// REST disponible ?
		$rest_ok = class_exists( 'WP_REST_Server' );
		$this->add( 'REST API', $rest_ok ? __( 'disponible', 'infinitycod' ) : __( 'indisponible', 'infinitycod' ), $rest_ok ? self::PASS : self::FAIL, rest_url( 'infinitycod/v1/' ) );

		// WP-Cron.
		$next_update_check = wp_next_scheduled( 'infinitycod_update_check' );
		$this->add( 'WP-Cron (vérification mises à jour)', $next_update_check ? mysql2date( 'd/m H:i', gmdate( 'Y-m-d H:i:s', $next_update_check ) ) : __( 'non planifié', 'infinitycod' ), $next_update_check ? self::PASS : self::WARN, __( 'WP-Cron fonctionne lors des visites du site', 'infinitycod' ) );

		// Filesystem.
		$upload = wp_get_upload_dir();
		$writable = wp_is_writable( dirname( $upload['basedir'] ) );
		$this->add( 'Filesystem (uploads)', $writable ? __( 'inscriptible', 'infinitycod' ) : __( 'non inscriptible', 'infinitycod' ), $writable ? self::PASS : self::WARN, $upload['basedir'] );

		// Tables du plugin.
		$orders_table = Schema::table( 'orders' );
		$table_ok = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $orders_table ) ) === $orders_table );
		$this->add( 'Tables InfinityCod', $table_ok ? __( 'présentes', 'infinitycod' ) : __( 'absentes', 'infinitycod' ), $table_ok ? self::PASS : self::FAIL, 'DB v' . get_option( 'infinitycod_db_version', '?' ) );

		// Colonnes récentes (migration 1.6.0).
		if ( $table_ok ) {
			$columns = (array) $wpdb->get_col( "DESCRIBE {$orders_table}", 0 ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
			$this->add( 'Colonne email (1.6.0)', in_array( 'email', $columns, true ) ? __( 'présente', 'infinitycod' ) : __( 'absente', 'infinitycod' ), in_array( 'email', $columns, true ) ? self::PASS : self::FAIL, 'Migration DB v1.2.0' );
		}

		// Licence.
		$this->add( 'Licence', \InfinityCod\License\LicenseManager::status_label(), \InfinityCod\License\LicenseManager::is_premium() ? self::PASS : self::PASS, '' );

		// Updater : GitHub (source primaire).
		$gh = get_transient( 'icod_update_gh' );
		$gh = is_array( $gh ) ? $gh : array();

		$reason_labels = array(
			'private_or_empty' => __( 'dépôt privé sans token, ou aucune release publiée', 'infinitycod' ),
			'network'          => __( 'serveur injoignable (réseau restreint ou limite de débit GitHub)', 'infinitycod' ),
			'rate_limited'     => __( 'quota API GitHub atteint (403) — les miroirs CDN prennent le relais', 'infinitycod' ),
			'no_package'       => __( 'release publiée sans package zip', 'infinitycod' ),
			'bad_signature'    => __( 'signature du manifest invalide — mise à jour refusée par sécurité', 'infinitycod' ),
		);

		if ( ! empty( $gh['unreachable'] ) ) {
			$reason = isset( $gh['reason'] ) ? $gh['reason'] : '';
			$detail = isset( $reason_labels[ $reason ] ) ? $reason_labels[ $reason ] : '';
			if ( ! empty( $gh['repo'] ) ) {
				$detail .= ' · ' . $gh['repo'];
			}
			$this->add( 'Updater — GitHub (primaire)', __( 'injoignable', 'infinitycod' ), self::WARN, $detail );
		} elseif ( ! empty( $gh['version'] ) ) {
			$this->add( 'Updater — GitHub (primaire)', 'v' . $gh['version'], self::PASS, __( 'dépôt public des releases', 'infinitycod' ) );
		}

		// Résultat du dernier test manuel.
		$test = get_option( 'icod_updater_test', array() );
		$test = is_array( $test ) ? $test : array();
		if ( ! empty( $test['time'] ) ) {
			$this->add(
				__( 'Dernier test de connexion', 'infinitycod' ),
				$this->esc( $test['ok'] ? 'OK' : 'échec' ) . ' · ' . $this->esc( $test['source'] ) . ( ! empty( $test['version'] ) ? ' · v' . $this->esc( $test['version'] ) : '' ),
				$test['ok'] ? self::PASS : self::WARN,
				$this->esc( $test['time'] )
			);
		}

		// Moteur de formulaire : configuration, plan de champs, captcha.
		$settings_count = count( \InfinityCod\Core\Settings::all() );
		$this->add( 'Configuration (moteur de réglages)', $settings_count . ' ' . __( 'clés chargées', 'infinitycod' ), $settings_count > 50 ? self::PASS : self::WARN, 'option : infinitycod_settings' );

		// Réglages RÉELLEMENT stockés (différences avec les défauts) : répond
		// à « mes changements sont-ils bien enregistrés ? ».
		$saved_raw  = get_option( 'infinitycod_settings', array() );
		$saved_raw  = is_array( $saved_raw ) ? $saved_raw : array();
		$saved_accent  = isset( $saved_raw['accent_color'] ) ? (string) $saved_raw['accent_color'] : __( '(défaut)', 'infinitycod' );
		$saved_captcha = isset( $saved_raw['captcha_enabled'] ) ? (int) $saved_raw['captcha_enabled'] : 0;
		$saved_timer   = isset( $saved_raw['timer_urgency_enabled'] ) ? (int) $saved_raw['timer_urgency_enabled'] : 0;
		$saved_theme   = isset( $saved_raw['form_theme'] ) ? (string) $saved_raw['form_theme'] : 'light';
		$this->add( __( 'Réglages modifiés stockés en base', 'infinitycod' ), count( $saved_raw ) . ' ' . __( 'clés', 'infinitycod' ), count( $saved_raw ) > 0 ? self::PASS : self::WARN, 'accent=' . $saved_accent . ' · captcha=' . $saved_captcha . ' · timer=' . $saved_timer . ' · thème=' . $saved_theme );

		$saved_at = get_option( 'icod_settings_saved_at', '' );
		$this->add( __( 'Dernière sauvegarde des réglages', 'infinitycod' ), $saved_at ? mysql2date( 'd/m/Y H:i', $saved_at ) : __( 'jamais (valeurs par défaut)', 'infinitycod' ), $saved_at ? self::PASS : self::WARN, '' );

		// Test d'écriture → lecture : détecte un cache d'objets défectueux
		// qui ferait « disparaître » les sauvegardes.
		$write_token = wp_generate_password( 12, false );
		update_option( 'icod_write_test', $write_token, false );
		wp_cache_delete( 'alloptions', 'options' );
		$read_back   = (string) get_option( 'icod_write_test', '' );
		delete_option( 'icod_write_test' );
		$persist_ok  = hash_equals( $write_token, $read_back );
		$this->add( __( 'Persistance (écriture → lecture immédiate)', 'infinitycod' ), $persist_ok ? __( 'OK — la sauvegarde fonctionne', 'infinitycod' ) : __( 'ÉCHEC — un cache d\'objets défectueux avale les sauvegardes', 'infinitycod' ), $persist_ok ? self::PASS : self::FAIL, '' );

		$ext_cache = function_exists( 'wp_using_ext_object_cache' ) ? wp_using_ext_object_cache() : false;
		$this->add( __( 'Cache d\'objets', 'infinitycod' ), $ext_cache ? __( 'externe actif (risque si mal configuré)', 'infinitycod' ) : __( 'interne (aucun risque)', 'infinitycod' ), $ext_cache ? self::WARN : self::PASS, $ext_cache ? __( 'Si les réglages ne s\'appliquent pas : vider le cache d\'objets du serveur.', 'infinitycod' ) : '' );

		$plan          = \InfinityCod\Form\FormManager::fields_plan();
		$active_fields = array_values( array_filter( $plan, static function ( $f ) { return ! empty( $f['on'] ); } ) );
		$active_keys   = implode( ', ', array_map( static function ( $f ) { return $f['key'] . ( ! empty( $f['req'] ) ? '*' : '' ); }, $active_fields ) );
		$this->add( __( 'Moteur de formulaire', 'infinitycod' ), class_exists( '\InfinityCod\Form\FormManager' ) ? __( 'opérationnel', 'infinitycod' ) : __( 'absent', 'infinitycod' ), class_exists( '\InfinityCod\Form\FormManager' ) ? self::PASS : self::FAIL, '[infinitycod_form] · ' . sprintf( /* translators: 1 : nombre de champs actifs, 2 : nombre total. */ __( '%1$d champ(s) actif(s) sur %2$d', 'infinitycod' ), count( $active_fields ), count( $plan ) ) . ' : ' . $active_keys );

		$provider_labels = array(
			'off'          => __( 'désactivé', 'infinitycod' ),
			'math'         => __( 'question mathématique', 'infinitycod' ),
			'recaptcha_v3' => __( 'Google reCAPTCHA v3', 'infinitycod' ),
		);
		$provider = \InfinityCod\Form\FormManager::captcha_provider();
		$this->add( __( 'Captcha', 'infinitycod' ), isset( $provider_labels[ $provider ] ) ? $provider_labels[ $provider ] : $provider, 'off' === $provider ? self::PASS : self::PASS, 'off' === $provider ? __( 'aucun script ni validation chargé', 'infinitycod' ) : '' );

		$this->add( __( 'Devise', 'infinitycod' ), \InfinityCod\Core\Settings::currency() . ' · ' . \InfinityCod\Core\Settings::currency_label() . ' · ' . ( 'left' === \InfinityCod\Core\Settings::get( 'currency_position', 'right' ) ? __( 'avant le montant', 'infinitycod' ) : __( 'après le montant', 'infinitycod' ) ), self::PASS, '' );

		// Canal.
		$this->add( 'Canal de mise à jour', \InfinityCod\License\Updater::channel(), self::PASS, '' );
	}

	/**
	 * Convertit une notation shorthand PHP (128M) en octets.
	 *
	 * @param string $shorthand Notation.
	 * @return int
	 */
	private function shorthand_to_bytes( $shorthand ) {
		$value = (int) $shorthand;
		switch ( strtoupper( substr( trim( (string) $shorthand ), -1 ) ) ) {
			case 'G': return $value * 1073741824;
			case 'M': return $value * 1048576;
			case 'K': return $value * 1024;
			default: return $value;
		}
	}

	/**
	 * Téléchargement du rapport (texte, sans secrets).
	 *
	 * @return void
	 */
	public function handle_download() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_diagnostics_download' );

		if ( empty( $this->results ) ) {
			$this->run_checks();
		}

		$lines   = array();
		$lines[] = 'InfinityCod — Rapport de diagnostic';
		$lines[] = 'Généré : ' . current_time( 'mysql' );
		$lines[]  = str_repeat( '-', 60 );

		foreach ( $this->results as $row ) {
			$lines[] = sprintf( '[%s] %s = %s %s', $row['status'], $row['label'], $row['value'], $row['detail'] ? '(' . $row['detail'] . ')' : '' );
		}

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=infinitycod-diagnostics-' . gmdate( 'Ymd-Hi' ) . '.txt' );
		echo esc_html( implode( "\n", $lines ) );
		exit;
	}

	/**
	 * Test manuel de connexion updater (GitHub puis repli).
	 *
	 * @return void
	 */
	public function handle_test_updater() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_test_updater' );

		\InfinityCod\License\Updater::clear_cache();
		delete_site_transient( 'update_plugins' );
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}

		$updater = new \InfinityCod\License\Updater();
		try {
			$latest = $updater->latest();
		} catch ( \Throwable $e ) {
			\InfinityCod\Logging\Logger::log( 'error', 'Test updater : ' . $e->getMessage() );
			$latest = null;
		}

		$test = array(
			'time'   => current_time( 'mysql' ),
			'ok'     => ! empty( $latest['version'] ),
			'source' => isset( $latest['source'] ) ? $this->source_label( (string) $latest['source'] ) : __( 'aucune source joignable', 'infinitycod' ),
			'version'=> isset( $latest['version'] ) ? (string) $latest['version'] : '',
		);
		update_option( 'icod_updater_test', $test, false );

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-diagnostics&icod_msg=tested' ) );
		exit;
	}

	/**
	 * Libellé lisible de la source qui a répondu.
	 *
	 * @param string $source Identifiant interne de source.
	 * @return string
	 */
	private function source_label( $source ) {
		$labels = array(
			'github'          => __( 'GitHub API', 'infinitycod' ),
			'atom'            => __( 'GitHub (flux atom)', 'infinitycod' ),
			'mirror-raw'      => __( 'Miroir GitHub brut', 'infinitycod' ),
			'mirror-jsdelivr' => __( 'Miroir CDN', 'infinitycod' ),
		);
		return isset( $labels[ $source ] ) ? $labels[ $source ] : $source;
	}

	/**
	 * Échappement minimal pour l'affichage interne.
	 *
	 * @param string $value Valeur.
	 * @return string
	 */
	private function esc( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES );
	}
}
