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
		$writable = is_writable( dirname( $upload['basedir'] ) );
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

		// Updater.
		$gh = get_transient( 'icod_update_gh' );
		if ( is_array( $gh ) && ! empty( $gh['unreachable'] ) ) {
			$this->add( 'Updater (GitHub)', __( 'injoignable', 'infinitycod' ), self::WARN, isset( $gh['repo'] ) ? $gh['repo'] : '' );
		} elseif ( is_array( $gh ) && ! empty( $gh['version'] ) ) {
			$this->add( 'Updater (GitHub)', 'v' . $gh['version'], self::PASS, 'Dépôt public des releases' );
		} else {
			$this->add( 'Updater (GitHub)', __( 'jamais vérifié', 'infinitycod' ), self::WARN, 'InfinityCod → Mises à jour → Vérifier' );
		}

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
}
