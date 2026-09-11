<?php
/**
 * Page admin : paniers abandonnés — relance manuelle wa.me et historique.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Admin\Pages;

use InfinityCod\Core\Schema;
use InfinityCod\Core\Settings;
use InfinityCod\Whatsapp\WhatsappManager;

defined( 'ABSPATH' ) || exit;

class AbandonedPage {

	/**
	 * Libellés français des statuts de panier.
	 *
	 * @return array<string,string>
	 */
	private function status_labels() {
		return array(
			'open'      => __( 'En attente', 'infinitycod' ),
			'recovered' => __( 'Récupéré', 'infinitycod' ),
			'archived'  => __( 'Archivé', 'infinitycod' ),
		);
	}

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public function render() {
		global $wpdb;

		$table  = Schema::table( 'abandoned' );
		$counts = array( 'open' => 0, 'recovered' => 0, 'archived' => 0 );
		foreach ( $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$table} GROUP BY status", ARRAY_A ) as $line ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			if ( isset( $counts[ $line['status'] ] ) ) {
				$counts[ $line['status'] ] = (int) $line['n'];
			}
		}
		$open_value = (float) $wpdb->get_var( "SELECT COALESCE(SUM(cart_total),0) FROM {$table} WHERE status = 'open'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

		// Filtre par statut (nav d'onglets locale).
		$filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! array_key_exists( $filter, $counts ) && '' !== $filter ) {
			$filter = '';
		}
		$base_url = admin_url( 'admin.php?page=infinitycod-abandoned' );

		if ( '' !== $filter ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s ORDER BY updated_at DESC LIMIT 100", $filter ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT 100", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		}

		$auto_on   = (bool) Settings::get( 'abandoned_enabled' );
		$log       = get_option( 'infinitycod_wa_log', array() );
		$log       = is_array( $log ) ? array_slice( $log, 0, 10 ) : array();
		$labels    = $this->status_labels();
		$total_all = $counts['open'] + $counts['recovered'] + $counts['archived'];
		$rate      = ( $counts['recovered'] + $counts['open'] ) > 0
			? (int) round( $counts['recovered'] / max( 1, $counts['recovered'] + $counts['open'] ) * 100 )
			: 0;
		?>
		<div class="wrap icod-wrap">
			<h1 class="icod-title"><?php esc_html_e( 'Paniers abandonnés', 'infinitycod' ); ?></h1>

			<?php if ( ! $auto_on ) : ?>
				<div class="notice notice-warning is-dismissible"><p>
					<strong><?php esc_html_e( 'La relance automatique est désactivée.', 'infinitycod' ); ?></strong>
					<?php esc_html_e( 'Chaque panier peut quand même être relancé en un clic ci-dessous (bouton WhatsApp).', 'infinitycod' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-settings&tab=whatsapp' ) ); ?>"><?php esc_html_e( 'Activer la relance automatique', 'infinitycod' ); ?></a>
				</p></div>
			<?php endif; ?>

			<div class="icod-kpi-grid">
				<div class="icod-card icod-kpi-card">
					<h2><?php esc_html_e( 'Ouverts', 'infinitycod' ); ?></h2>
					<p class="icod-kpi-value"><?php echo (int) $counts['open']; ?></p>
					<p class="icod-kpi-label"><?php esc_html_e( 'sessions incomplètes', 'infinitycod' ); ?></p>
				</div>
				<div class="icod-card icod-kpi-card">
					<h2><?php esc_html_e( 'Valeur en jeu', 'infinitycod' ); ?></h2>
					<p class="icod-kpi-value"><?php echo esc_html( number_format_i18n( $open_value, 0 ) ); ?> <?php echo esc_html( Settings::currency_label() ); ?></p>
					<p class="icod-kpi-label"><?php esc_html_e( 'paniers ouverts, à relancer', 'infinitycod' ); ?></p>
				</div>
				<div class="icod-card icod-kpi-card">
					<h2><?php esc_html_e( 'Récupérés', 'infinitycod' ); ?></h2>
					<p class="icod-kpi-value"><?php echo (int) $counts['recovered']; ?> <small class="icod-sub"><?php echo (int) $rate; ?>%</small></p>
					<p class="icod-kpi-label"><?php esc_html_e( 'devenus commandes', 'infinitycod' ); ?></p>
				</div>
				<div class="icod-card icod-kpi-card">
					<h2><?php esc_html_e( 'Relance automatique', 'infinitycod' ); ?></h2>
					<p class="icod-kpi-value"><?php echo $auto_on ? 'ON' : 'OFF'; ?></p>
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

				<ul class="subsubsub" style="margin:0 0 10px">
					<li>
						<a href="<?php echo esc_url( $base_url ); ?>" <?php checked( '' === $filter, true ); ?>><?php echo esc_html( __( 'Tous', 'infinitycod' ) ); ?> (<?php echo (int) $total_all; ?>)</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'status', 'open', $base_url ) ); ?>" <?php checked( 'open' === $filter, true ); ?>><?php echo esc_html( $labels['open'] ); ?> (<?php echo (int) $counts['open']; ?>)</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'status', 'recovered', $base_url ) ); ?>" <?php checked( 'recovered' === $filter, true ); ?>><?php echo esc_html( $labels['recovered'] ); ?> (<?php echo (int) $counts['recovered']; ?>)</a> |
					</li>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'status', 'archived', $base_url ) ); ?>" <?php checked( 'archived' === $filter, true ); ?>><?php echo esc_html( $labels['archived'] ); ?> (<?php echo (int) $counts['archived']; ?>)</a>
					</li>
				</ul>

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
								<?php
								$intl = \InfinityCod\Form\Validator::phone_to_international( $row['phone'] );
								$msg  = WhatsappManager::render_template( Settings::get( 'msg_abandoned' ), array(
									'nom'     => $row['customer_name'],
									'produit' => $row['product_id'] ? get_the_title( (int) $row['product_id'] ) : __( 'votre article', 'infinitycod' ),
									'total'   => number_format_i18n( (float) $row['cart_total'], 2 ) . ' DA',
								) );
								?>
								<tr>
									<td>
										<strong><?php echo esc_html( $row['customer_name'] ? $row['customer_name'] : '—' ); ?></strong>
										<span class="icod-sub">
											<?php if ( $intl ) : ?>
												<a href="<?php echo esc_url( WhatsappManager::wame_url( $intl, $msg ) ); ?>" target="_blank" rel="noopener" dir="ltr"><?php echo esc_html( $row['phone'] ); ?></a>
											<?php else : ?>
												<?php echo esc_html( $row['phone'] ); ?>
											<?php endif; ?>
										</span>
									</td>
									<td><?php echo esc_html( $row['product_id'] ? get_the_title( (int) $row['product_id'] ) : '—' ); ?></td>
									<td>
										<span class="icod-progress"><span style="width:<?php echo (int) $row['progress']; ?>%"></span></span>
										<?php echo (int) $row['progress']; ?>%
									</td>
									<td><?php echo esc_html( number_format_i18n( (float) $row['cart_total'], 0 ) ); ?> <?php echo esc_html( Settings::currency_label() ); ?></td>
									<td><?php echo (int) $row['reminders_sent']; ?></td>
									<td>
										<span class="icod-status icod-status-<?php echo esc_attr( $row['status'] ); ?>"><?php echo esc_html( isset( $labels[ $row['status'] ] ) ? $labels[ $row['status'] ] : $row['status'] ); ?></span>
									</td>
									<td>
										<?php echo esc_html( mysql2date( 'd/m H:i', $row['updated_at'] ) ); ?>
										<span class="icod-sub"><?php echo esc_html( sprintf( /* translators: %s : durée. */ __( 'il y a %s', 'infinitycod' ), human_time_diff( strtotime( $row['updated_at'] ) ) ) ); ?></span>
									</td>
									<td>
										<?php if ( $intl ) : ?>
											<a class="button button-small" target="_blank" rel="noopener" href="<?php echo esc_url( WhatsappManager::wame_url( $intl, $msg ) ); ?>">💬 <?php esc_html_e( 'Relancer', 'infinitycod' ); ?></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							<?php if ( ! $rows ) : ?>
								<tr>
									<td colspan="8" style="text-align:center;padding:28px 12px">
										<p style="font-size:15px;margin:0 0 6px">🛒 <?php esc_html_e( 'Aucun panier abandonné ici.', 'infinitycod' ); ?></p>
										<p class="description" style="margin:0"><?php esc_html_e( 'Dès qu’un client remplit le formulaire sans confirmer, il apparaît dans cette liste pour être relancé en un clic via WhatsApp.', 'infinitycod' ); ?></p>
									</td>
								</tr>
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
