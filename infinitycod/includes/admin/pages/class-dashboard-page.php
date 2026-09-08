<?php
/**
 * Page admin : tableau de bord — indicateurs clés du jour, semaine, mois.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Admin\Pages;

use InfinityCod\Core\Schema;
use InfinityCod\Orders\OrderStore;

defined( 'ABSPATH' ) || exit;

class DashboardPage {

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public function render() {
		global $wpdb;

		$orders = Schema::table( 'orders' );

		$periods = array(
			'today' => __( "Aujourd'hui", 'infinitycod' ),
			'7d'    => __( '7 derniers jours', 'infinitycod' ),
			'30d'   => __( '30 derniers jours', 'infinitycod' ),
		);

		$cutoffs = array(
			// current_time('timestamp') = epoch ajusté au fuseau du site :
			// pattern canonique pour des dates SQL cohérentes avec created_at.
			'today' => gmdate( 'Y-m-d H:i:s', strtotime( 'today', current_time( 'timestamp' ) ) ),
			'7d'    => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 7 * DAY_IN_SECONDS ),
			'30d'   => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 30 * DAY_IN_SECONDS ),
		);

		$kpi = array();
		foreach ( $cutoffs as $key => $cutoff ) {
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT
					COUNT(*) AS total,
					SUM(CASE WHEN status IN ('confirmed','shipped','delivered') THEN 1 ELSE 0 END) AS good,
					SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
					SUM(CASE WHEN status IN ('confirmed','shipped','delivered') THEN total ELSE 0 END) AS revenue
				 FROM {$orders} WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL
				$cutoff
			), ARRAY_A );

			$kpi[ $key ] = array(
				'total'     => (int) $row['total'],
				'good'      => (int) $row['good'],
				'delivered' => (int) $row['delivered'],
				'revenue'   => (float) $row['revenue'],
			);
		}

		// Top wilayas (30 jours).
		$wilayas_table = Schema::table( 'wilayas' );
		$top_wilayas   = $wpdb->get_results( $wpdb->prepare(
			"SELECT o.wilaya_code, w.name_fr, COUNT(*) AS n
			 FROM {$orders} o LEFT JOIN {$wilayas_table} w ON w.code = o.wilaya_code
			 WHERE o.created_at >= %s
			 GROUP BY o.wilaya_code, w.name_fr
			 ORDER BY n DESC LIMIT 6", // phpcs:ignore WordPress.DB.PreparedSQL
			$cutoffs['30d']
		), ARRAY_A );

		// Dernières commandes.
		$latest = $wpdb->get_results( "SELECT o.*, w.name_fr AS wilaya_name FROM {$orders} o LEFT JOIN {$wilayas_table} w ON w.code = o.wilaya_code ORDER BY o.created_at DESC LIMIT 8", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

		// Activité anti-fraude.
		$logs    = Schema::table( 'fraud_logs' );
		$frauds  = $wpdb->get_results( "SELECT * FROM {$logs} ORDER BY created_at DESC LIMIT 6", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

		$statuses = OrderStore::STATUSES;
		?>
		<div class="wrap icod-wrap">
			<h1 class="icod-title"><?php esc_html_e( 'InfinityCod — Tableau de bord', 'infinitycod' ); ?></h1>

			<div class="icod-kpi-grid">
				<?php foreach ( $periods as $key => $label ) : ?>
					<div class="icod-card icod-kpi-card">
						<h2><?php echo esc_html( $label ); ?></h2>
						<p class="icod-kpi-value"><?php echo (int) $kpi[ $key ]['total']; ?></p>
						<p class="icod-kpi-label"><?php esc_html_e( 'commandes', 'infinitycod' ); ?></p>
						<ul class="icod-kpi-details">
							<li><?php esc_html_e( 'À confirmer + en cours :', 'infinitycod' ); ?> <strong><?php echo (int) $kpi[ $key ]['good']; ?></strong></li>
							<li><?php esc_html_e( 'Livrées :', 'infinitycod' ); ?> <strong><?php echo (int) $kpi[ $key ]['delivered']; ?></strong></li>
							<li><?php esc_html_e( 'CA confirmé :', 'infinitycod' ); ?> <strong><?php echo esc_html( number_format_i18n( $kpi[ $key ]['revenue'], 0 ) ); ?> DA</strong></li>
							<?php if ( $kpi[ $key ]['total'] > 0 ) : ?>
								<li><?php esc_html_e( 'Taux de confirmation :', 'infinitycod' ); ?>
									<strong><?php echo esc_html( number_format_i18n( $kpi[ $key ]['good'] / $kpi[ $key ]['total'] * 100, 1 ) ); ?>%</strong>
								</li>
							<?php endif; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="icod-dashboard-cols">
				<div class="icod-card">
					<h2><?php esc_html_e( 'Dernières commandes', 'infinitycod' ); ?></h2>
					<div class="icod-table-scroll">
						<table class="widefat striped icod-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Client', 'infinitycod' ); ?></th>
									<th><?php esc_html_e( 'Wilaya', 'infinitycod' ); ?></th>
									<th><?php esc_html_e( 'Total', 'infinitycod' ); ?></th>
									<th><?php esc_html_e( 'Statut', 'infinitycod' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( (array) $latest as $row ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $row['customer_name'] ); ?></strong><br /><span class="icod-sub"><?php echo esc_html( $row['phone'] ); ?></span></td>
										<td><?php echo esc_html( $row['wilaya_name'] ? $row['wilaya_name'] : $row['wilaya_code'] ); ?></td>
										<td><?php echo esc_html( number_format_i18n( (float) $row['total'], 0 ) ); ?> DA</td>
										<td><span class="icod-status icod-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( isset( $statuses[ $row['status'] ] ) ? $statuses[ $row['status'] ] : $row['status'] ); ?></span></td>
									</tr>
								<?php endforeach; ?>
								<?php if ( ! $latest ) : ?>
									<tr><td colspan="4"><?php esc_html_e( 'Pas encore de commande. Partagez une fiche produit contenant le formulaire !', 'infinitycod' ); ?></td></tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
					<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-orders' ) ); ?>"><?php esc_html_e( 'Toutes les commandes', 'infinitycod' ); ?></a></p>
				</div>

				<div>
					<div class="icod-card">
						<h2><?php esc_html_e( 'Top wilayas (30 jours)', 'infinitycod' ); ?></h2>
						<ul class="icod-top-list">
							<?php foreach ( (array) $top_wilayas as $row ) : ?>
								<li>
									<span><?php echo esc_html( $row['name_fr'] ? $row['name_fr'] : $row['wilaya_code'] ); ?></span>
									<strong><?php echo (int) $row['n']; ?></strong>
								</li>
							<?php endforeach; ?>
							<?php if ( ! $top_wilayas ) : ?>
								<li><?php esc_html_e( 'Aucune donnée', 'infinitycod' ); ?></li>
							<?php endif; ?>
						</ul>
					</div>

					<div class="icod-card">
						<h2><?php esc_html_e( 'Activité anti-fraude', 'infinitycod' ); ?></h2>
						<ul class="icod-fraud-list">
							<?php foreach ( (array) $frauds as $log ) : ?>
								<li>
									<span class="icod-fraud-flags"><?php echo esc_html( $log['flags'] ); ?></span>
									<span class="icod-sub"><?php echo esc_html( mysql2date( 'd/m H:i', $log['created_at'] ) ); ?> · <?php echo esc_html( $log['phone'] ); ?> · <?php echo esc_html( $log['action'] ); ?></span>
								</li>
							<?php endforeach; ?>
							<?php if ( ! $frauds ) : ?>
								<li><?php esc_html_e( 'Aucune activité suspecte 🎉', 'infinitycod' ); ?></li>
							<?php endif; ?>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
