<?php
/**
 * Page admin : paniers abandonnés — relance manuelle wa.me et historique.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Admin\Pages;

use InfinityCod\Core\Schema;
use InfinityCod\Core\Settings;
use InfinityCod\Whatsapp\WhatsappManager;

defined( 'ABSPATH' ) || exit;

class AbandonedPage {

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public function render() {
		global $wpdb;

		$table  = Schema::table( 'abandoned' );
		$rows   = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT 100", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$counts = array( 'open' => 0, 'recovered' => 0, 'archived' => 0 );
		foreach ( $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$table} GROUP BY status", ARRAY_A ) as $line ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$counts[ $line['status'] ] = (int) $line['n'];
		}

		$log = get_option( 'infinitycod_wa_log', array() );
		$log = is_array( $log ) ? array_slice( $log, 0, 10 ) : array();
		?>
		<div class="wrap icod-wrap">
			<h1 class="icod-title"><?php esc_html_e( 'Paniers abandonnés', 'infinitycod' ); ?></h1>

			<div class="icod-kpi-grid">
				<div class="icod-card icod-kpi-card">
					<h2><?php esc_html_e( 'Ouverts', 'infinitycod' ); ?></h2>
					<p class="icod-kpi-value"><?php echo (int) $counts['open']; ?></p>
					<p class="icod-kpi-label"><?php esc_html_e( 'sessions incomplètes', 'infinitycod' ); ?></p>
				</div>
				<div class="icod-card icod-kpi-card">
					<h2><?php esc_html_e( 'Récupérés', 'infinitycod' ); ?></h2>
					<p class="icod-kpi-value"><?php echo (int) $counts['recovered']; ?></p>
					<p class="icod-kpi-label"><?php esc_html_e( 'devenus commandes', 'infinitycod' ); ?></p>
				</div>
				<div class="icod-card icod-kpi-card">
					<h2><?php esc_html_e( 'Relance automatique', 'infinitycod' ); ?></h2>
					<p class="icod-kpi-value"><?php echo Settings::get( 'abandoned_enabled' ) ? 'ON' : 'OFF'; ?></p>
					<p class="icod-kpi-label">
						<?php
						printf(
							/* translators: 1 : délai minutes, 2 : nombre relances. */
							esc_html__( '%1$d min · %2$d relance(s) max', 'infinitycod' ),
							(int) Settings::get( 'abandoned_delay' ),
							(int) Settings::get( 'abandoned_max' )
						);
						?>
					</p>
				</div>
			</div>

			<div class="icod-card">
				<h2><?php esc_html_e( 'Sessions récentes', 'infinitycod' ); ?></h2>
				<div class="icod-table-scroll">
					<table class="widefat striped icod-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Client', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Produit', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Progression', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Panier', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Relances', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Statut', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Mise à jour', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Action', 'infinitycod' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( (array) $rows as $row ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $row['customer_name'] ? $row['customer_name'] : '—' ); ?></strong><span class="icod-sub"><?php echo esc_html( $row['phone'] ); ?></span></td>
									<td><?php echo esc_html( $row['product_id'] ? get_the_title( (int) $row['product_id'] ) : '—' ); ?></td>
									<td>
										<span class="icod-progress"><span style="width:<?php echo (int) $row['progress']; ?>%"></span></span>
										<?php echo (int) $row['progress']; ?>%
									</td>
									<td><?php echo esc_html( number_format_i18n( (float) $row['cart_total'], 0 ) ); ?> DA</td>
									<td><?php echo (int) $row['reminders_sent']; ?></td>
									<td><span class="icod-status icod-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( $row['status'] ); ?></span></td>
									<td><?php echo esc_html( mysql2date( 'd/m H:i', $row['updated_at'] ) ); ?></td>
									<td>
										<?php
										$intl = \InfinityCod\Form\Validator::phone_to_international( $row['phone'] );
										if ( $intl ) :
											$msg = WhatsappManager::render_template( Settings::get( 'msg_abandoned' ), array(
												'nom'     => $row['customer_name'],
												'produit' => $row['product_id'] ? get_the_title( (int) $row['product_id'] ) : __( 'votre article', 'infinitycod' ),
												'total'   => number_format_i18n( (float) $row['cart_total'], 2 ) . ' DA',
											) );
											?>
											<a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url( WhatsappManager::wame_url( $intl, $msg ) ); ?>">💬 <?php esc_html_e( 'Relancer', 'infinitycod' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if ( ! $rows ) : ?>
								<tr><td colspan="8"><?php esc_html_e( 'Aucun panier abandonné pour le moment.', 'infinitycod' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

			<?php if ( $log ) : ?>
				<div class="icod-card">
					<h2><?php esc_html_e( 'Derniers messages WhatsApp', 'infinitycod' ); ?></h2>
					<ul class="icod-fraud-list">
						<?php foreach ( $log as $entry ) : ?>
							<li>
								<strong><?php echo esc_html( $entry['to'] ); ?></strong> — <span class="icod-sub"><?php echo esc_html( mysql2date( 'd/m H:i', $entry['at'] ) ); ?> · <?php echo esc_html( $entry['gateway'] ); ?></span>
								<?php if ( ! empty( $entry['error'] ) ) : ?>
									<span class="icod-risk-high"> ⚠️ <?php echo esc_html( $entry['error'] ); ?></span>
								<?php endif; ?>
								<span class="icod-sub"><?php echo esc_html( mb_substr( $entry['message'], 0, 120 ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
