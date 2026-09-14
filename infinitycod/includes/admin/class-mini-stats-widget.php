<?php
/**
 * Widget tableau de bord WordPress : mini-statistiques InfinityCod.
 *
 * Commandes du jour, chiffre d'affaires, en attente et paniers ouverts —
 * visibles dès la connexion, avec liens directs vers les pages du plugin.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Admin;

use InfinityCod\Core\Schema;
use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class MiniStatsWidget {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
	}

	/**
	 * Enregistre le widget (marchands uniquement).
	 *
	 * @return void
	 */
	public function register_widget() {
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'icod_mini_stats',
			__( 'InfinityCod — Aujourd’hui', 'infinitycod' ),
			array( $this, 'render' )
		);
	}

	/**
	 * Rendu du widget.
	 *
	 * @return void
	 */
	public function render() {
		global $wpdb;

		$orders    = Schema::table( 'orders' );
		$abandoned = Schema::table( 'abandoned' );
		$today     = gmdate( 'Y-m-d H:i:s', strtotime( 'today', current_time( 'timestamp' ) ) );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN status IN ('confirmed','shipped','delivered') THEN total ELSE 0 END),0) AS revenue
			 FROM {$orders} WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL
			$today
		), ARRAY_A );

		if ( ! is_array( $row ) ) {
			$row = array( 'n' => 0, 'revenue' => 0 );
		}

		$pending        = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$orders} WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$abandoned_open = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$abandoned} WHERE status = 'open'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

		$orders_url = admin_url( 'admin.php?page=infinitycod-orders' );
		$cards      = array(
			array( '#e5f2e9', '#0e7a4f', __( 'Commandes du jour', 'infinitycod' ), (string) ( $row['n'] ?? 0 ), '' ),
			array( '#e3edfa', '#1d5fa8', __( 'Chiffre d’affaires', 'infinitycod' ), number_format_i18n( (float) ( $row['revenue'] ?? 0 ), 0 ) . ' ' . Settings::currency_label(), '' ),
			array( '#fdf3e0', '#996800', __( 'En attente', 'infinitycod' ), (string) $pending, add_query_arg( 'status', 'pending', $orders_url ) ),
			array( '#f0e7fd', '#7b2d9e', __( 'Paniers ouverts', 'infinitycod' ), (string) $abandoned_open, admin_url( 'admin.php?page=infinitycod-abandoned' ) ),
		);
		?>
		<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
			<?php foreach ( $cards as $card ) : ?>
				<?php list( $bg, $fg, $label, $value, $link ) = $card; ?>
				<div style="background:<?php echo esc_attr( $bg ); ?>;border-radius:10px;padding:10px 12px">
					<div style="font-size:11px;color:<?php echo esc_attr( $fg ); ?>;font-weight:700;text-transform:uppercase;letter-spacing:.04em"><?php echo esc_html( $label ); ?></div>
					<div style="font-size:22px;font-weight:800;color:<?php echo esc_attr( $fg ); ?>"><?php echo esc_html( $value ); ?></div>
					<?php if ( $link ) : ?>
						<a href="<?php echo esc_url( $link ); ?>" style="font-size:11px"><?php esc_html_e( 'Ouvrir →', 'infinitycod' ); ?></a>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		$rev7  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN status IN ('confirmed','shipped','delivered') THEN total ELSE 0 END),0) FROM {$orders} WHERE created_at >= %s", gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 7 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$daily = $wpdb->get_results( $wpdb->prepare( "SELECT SUBSTR(created_at,1,10) AS d, COALESCE(SUM(CASE WHEN status IN ('confirmed','shipped','delivered') THEN total ELSE 0 END),0) AS v FROM {$orders} WHERE created_at >= %s GROUP BY SUBSTR(created_at,1,10) ORDER BY d", gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 7 * DAY_IN_SECONDS ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$pts   = array();
		$maxv  = 0;
		foreach ( (array) $daily as $d ) { $maxv = max( $maxv, (float) $d['v'] ); }
		$cnt   = count( (array) $daily );
		$ci    = 0;
		foreach ( (array) $daily as $d ) {
			$x = $cnt > 1 ? $ci * ( 120 / ( $cnt - 1 ) ) : 60;
			$y = $maxv > 0 ? 26 - ( (float) $d['v'] / $maxv ) * 22 : 26;
			$pts[] = round( $x, 1 ) . ',' . round( $y, 1 );
			$ci++;
		}
		?>
		<div style="margin:10px 0 0;padding:10px 12px;background:#f6f8fa;border-radius:10px">
			<div style="display:flex;justify-content:space-between;align-items:baseline">
				<span style="font-size:11px;font-weight:700;color:#1d5fa8;text-transform:uppercase"><?php echo esc_html( 'CA 7 jours', 'infinitycod' ); ?></span>
				<strong><?php echo esc_html( number_format_i18n( $rev7, 0 ) ); ?> <?php echo esc_html( $currency ); ?></strong>
			</div>
			<?php if ( $pts ) : ?>
			<svg width="100%" height="30" viewBox="0 0 120 30" preserveAspectRatio="none" style="margin-top:6px"><polyline points="<?php echo esc_attr( implode( ' ', $pts ) ); ?>" fill="none" stroke="#1d5fa8" stroke-width="2" /></svg>
			<?php endif; ?>
		</div>
		<p style="margin:10px 0 0;text-align:center">
			<a class="button button-small" href="<?php echo esc_url( $orders_url ); ?>"><?php esc_html_e( 'Voir toutes les commandes', 'infinitycod' ); ?></a>
		</p>
		<?php
	}
}
