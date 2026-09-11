<?php
/**
 * Page admin : Statistiques P&L — KPI, graphique 30 j, répartitions.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Admin\Pages;

defined( 'ABSPATH' ) || exit;

class StatsPage {

	/**
	 * Période en jours.
	 *
	 * @var int
	 */
	private $days = 30;

	/**
	 * Constructeur.
	 */
	public function __construct() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filtre de lecture.
		$days        = isset( $_GET['days'] ) ? absint( $_GET['days'] ) : 30;
		$this->days  = in_array( $days, array( 7, 30, 90 ), true ) ? $days : 30;
		// phpcs:enable
	}

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public function render() {
		$stats = infinitycod()->module( 'stats' );
		if ( ! $stats ) {
			printf( '<div class="wrap"><p>%s</p></div>', esc_html__( 'Module statistiques indisponible.', 'infinitycod' ) );
			return;
		}

		$from   = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $this->days * DAY_IN_SECONDS );
		$to     = current_time( 'mysql' );
		$kpis   = $stats->kpis( $from, $to );
		$series = $stats->daily_series( $from, $to );

		wp_enqueue_script( 'icod-chart', INFINITYCOD_URL . 'assets/admin/js/chart.umd.min.js', array(), '4.4.1', true );
		wp_script_add_data( 'icod-chart', 'strategy', 'defer' );

		$chart_data = array(
			'labels'  => array_map( function ( $point ) { return mysql2date( 'd/m', $point['date'] ); }, $series ),
			'orders'  => array_map( function ( $point ) { return (int) $point['orders']; }, $series ),
			'revenue' => array_map( function ( $point ) { return (float) $point['revenue']; }, $series ),
		);
		?>
		<div class="wrap icod-wrap">
			<h1 class="icod-title"><?php esc_html_e( 'Statistiques P&L', 'infinitycod' ); ?></h1>

			<nav class="nav-tab-wrapper icod-tabs">
				<?php foreach ( array( 7 => __( '7 jours', 'infinitycod' ), 30 => __( '30 jours', 'infinitycod' ), 90 => __( '90 jours', 'infinitycod' ) ) as $days_value => $label ) : ?>
					<a href="?page=infinitycod-stats&days=<?php echo (int) $days_value; ?>" class="nav-tab <?php echo $this->days === $days_value ? 'nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<div class="icod-kpi-grid">
				<div class="icod-card icod-kpi-card">
					<h2><?php esc_html_e( 'CA encaissé (livrées)', 'infinitycod' ); ?></h2>
					<p class="icod-kpi-value"><?php echo esc_html( number_format_i18n( $kpis['revenue_delivered'], 0 ) ); ?></p>
					<p class="icod-kpi-label">DA</p>
				</div>
				<div class="icod-card icod-kpi-card">
					<h2><?php esc_html_e( 'CA confirmé', 'infinitycod' ); ?></h2>
					<p class="icod-kpi-value"><?php echo esc_html( number_format_i18n( $kpis['revenue_confirmed'], 0 ) ); ?></p>
					<p class="icod-kpi-label">DA</p>
				</div>
				<div class="icod-card icod-kpi-card">
					<h2><?php esc_html_e( 'Taux de confirmation', 'infinitycod' ); ?></h2>
					<p class="icod-kpi-value"><?php echo esc_html( number_format_i18n( $kpis['confirmation_rate'], 1 ) ); ?>%</p>
					<p class="icod-kpi-label"><?php echo (int) $kpis['confirmed']; ?>/<?php echo (int) $kpis['total']; ?> <?php esc_html_e( 'commandes', 'infinitycod' ); ?></p>
				</div>
				<div class="icod-card icod-kpi-card">
					<h2><?php esc_html_e( 'Taux de retour', 'infinitycod' ); ?></h2>
					<p class="icod-kpi-value"><?php echo esc_html( number_format_i18n( $kpis['return_rate'], 1 ) ); ?>%</p>
					<p class="icod-kpi-label"><?php echo (int) $kpis['returned']; ?> <?php esc_html_e( 'retours', 'infinitycod' ); ?></p>
				</div>
			</div>

			<div class="icod-card">
				<h2><?php esc_html_e( 'Commandes et CA confirmé', 'infinitycod' ); ?></h2>
				<div class="icod-chart-wrap"><canvas id="icod-chart" height="120"></canvas></div>
			</div>

			<div class="icod-dashboard-cols">
				<div class="icod-card">
					<h2><?php esc_html_e( 'Par wilaya', 'infinitycod' ); ?></h2>
					<?php $this->table_wilaya( $stats, $from, $to ); ?>
				</div>
				<div>
					<div class="icod-card">
						<h2><?php esc_html_e( 'Par transporteur', 'infinitycod' ); ?></h2>
						<?php $this->table_carrier( $stats, $from, $to ); ?>
					</div>
					<div class="icod-card">
						<h2><?php esc_html_e( 'Par produit', 'infinitycod' ); ?></h2>
						<?php $this->table_product( $stats, $from, $to ); ?>
					</div>
					<div class="icod-card">
						<h2><?php esc_html_e( 'Détails', 'infinitycod' ); ?></h2>
						<ul class="icod-kpi-details">
							<li><span><?php esc_html_e( 'Articles vendus', 'infinitycod' ); ?></span><strong><?php echo (int) $kpis['items']; ?></strong></li>
							<li><span><?php esc_html_e( 'Panier moyen confirmé', 'infinitycod' ); ?></span><strong><?php echo esc_html( number_format_i18n( $kpis['avg_order_value'], 2 ) ); ?> <?php echo esc_html( \InfinityCod\Core\Settings::currency_label() ); ?></strong></li>
							<li><span><?php esc_html_e( 'Frais livraison encaissés', 'infinitycod' ); ?></span><strong><?php echo esc_html( number_format_i18n( $kpis['shipping_collected'], 0 ) ); ?> <?php echo esc_html( \InfinityCod\Core\Settings::currency_label() ); ?></strong></li>
							<li><span><?php esc_html_e( 'Remises accordées', 'infinitycod' ); ?></span><strong>−<?php echo esc_html( number_format_i18n( $kpis['discounts'], 0 ) ); ?> <?php echo esc_html( \InfinityCod\Core\Settings::currency_label() ); ?></strong></li>
							<li><span><?php esc_html_e( 'Taux de livraison', 'infinitycod' ); ?></span><strong><?php echo esc_html( number_format_i18n( $kpis['delivery_rate'], 1 ) ); ?>%</strong></li>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<script>
		window.addEventListener('load', function () {
			if (typeof Chart === 'undefined') { return; }
			var data = <?php echo wp_json_encode( $chart_data ); ?>;
			new Chart(document.getElementById('icod-chart'), {
				type: 'bar',
				data: {
					labels: data.labels,
					datasets: [
						{
							type: 'line',
							label: '<?php echo esc_js( __( 'CA confirmé (DA)', 'infinitycod' ) ); ?>',
							data: data.revenue,
							borderColor: '#0e7a4f',
							backgroundColor: 'rgba(14,122,79,.15)',
							tension: .3,
							fill: true,
							yAxisID: 'y'
						},
						{
							type: 'bar',
							label: '<?php echo esc_js( __( 'Commandes', 'infinitycod' ) ); ?>',
							data: data.orders,
							backgroundColor: 'rgba(29,95,168,.55)',
							yAxisID: 'y1'
						}
					]
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					interaction: { mode: 'index', intersect: false },
					scales: {
						y:  { type: 'linear', position: 'left', title: { display: true, text: 'DA' } },
						y1: { type: 'linear', position: 'right', grid: { drawOnChartArea: false }, ticks: { precision: 0 } }
					}
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Tableau par wilaya.
	 */
	private function table_wilaya( $stats, $from, $to ) {
		$rows = $stats->by_wilaya( $from, $to );
		echo '<div class="icod-table-scroll"><table class="widefat striped icod-table"><thead><tr>'
			. '<th>' . esc_html__( 'Wilaya', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__( 'Commandes', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__( 'Livrées', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__( 'Retours', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__( 'Taux livraison', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__( 'CA livré', 'infinitycod' ) . '</th>'
			. '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			printf(
				'<tr><td><strong>%1$s</strong></td><td>%2$d</td><td>%3$d</td><td>%4$d</td><td>%5$s%%</td><td>%6$s ' . \InfinityCod\Core\Settings::currency_label() . '</td></tr>',
				esc_html( $row['name'] ),
				(int) $row['orders'],
				(int) $row['delivered'],
				(int) $row['returned'],
				esc_html( number_format_i18n( $row['delivery_rate'], 1 ) ),
				esc_html( number_format_i18n( $row['revenue'], 0 ) )
			);
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="6">' . esc_html__( 'Aucune donnée sur la période.', 'infinitycod' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Tableau par transporteur.
	 */
	private function table_carrier( $stats, $from, $to ) {
		$rows = $stats->by_carrier( $from, $to );
		echo '<ul class="icod-top-list">';
		foreach ( $rows as $row ) {
			printf(
				'<li><span>%1$s</span><strong>%2$d exp. · %3$s%% livrées</strong></li>',
				esc_html( $row['carrier'] ),
				(int) $row['shipped'],
				esc_html( number_format_i18n( $row['delivery_rate'], 1 ) )
			);
		}
		if ( ! $rows ) {
			echo '<li>' . esc_html__( 'Aucun colis expédié sur la période.', 'infinitycod' ) . '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Tableau par produit.
	 */
	private function table_product( $stats, $from, $to ) {
		$rows = $stats->by_product( $from, $to );
		echo '<ul class="icod-top-list">';
		foreach ( $rows as $row ) {
			printf(
				'<li><span>%1$s</span><strong>%2$d cmd · %3$s ' . \InfinityCod\Core\Settings::currency_label() . '</strong></li>',
				esc_html( $row['name'] ),
				(int) $row['orders'],
				esc_html( number_format_i18n( $row['revenue'], 0 ) )
			);
		}
		if ( ! $rows ) {
			echo '<li>' . esc_html__( 'Aucune donnée sur la période.', 'infinitycod' ) . '</li>';
		}
		echo '</ul>';
	}
}
