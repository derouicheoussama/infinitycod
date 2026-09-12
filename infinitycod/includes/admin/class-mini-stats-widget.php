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
		<p style="margin:10px 0 0;text-align:center">
			<a class="button button-small" href="<?php echo esc_url( $orders_url ); ?>"><?php esc_html_e( 'Voir toutes les commandes', 'infinitycod' ); ?></a>
		</p>
		<?php
	}
}
