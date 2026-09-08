<?php
/**
 * Page admin : tableur des commandes COD.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Admin\Pages;

use InfinityCod\Core\Schema;
use InfinityCod\Orders\OrderStore;

defined( 'ABSPATH' ) || exit;

class OrdersPage {

	/**
	 * Commandes par page.
	 */
	const PER_PAGE = 30;

	/**
	 * Filtres courants.
	 *
	 * @var array
	 */
	private $filters = array();

	/**
	 * Constructeur : lit les filtres GET.
	 */
	public function __construct() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filtres de lecture.
		$this->filters = array(
			'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'wilaya' => isset( $_GET['wilaya'] ) ? sanitize_text_field( wp_unslash( $_GET['wilaya'] ) ) : '',
			'q'      => isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '',
			'from'   => isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
			'to'     => isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '',
			'paged'  => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
		);
		// phpcs:enable
	}

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public function render() {
		$msg = isset( $_GET['icod_msg'] ) ? sanitize_key( wp_unslash( $_GET['icod_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		list( $rows, $total, $counts_by_status ) = $this->query();

		$geo     = infinitycod()->module( 'geo' );
		$wilayas = $geo ? $geo->wilayas( false ) : array();
		?>
		<div class="wrap icod-wrap">
			<h1 class="icod-title"><?php esc_html_e( 'Commandes COD', 'infinitycod' ); ?></h1>

			<?php if ( 'done' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Action appliquée.', 'infinitycod' ); ?></p></div>
			<?php endif; ?>

			<div class="icod-mini-grid">
				<div class="icod-mini"><strong class="icod-mini-pending"><?php echo (int) ( $counts_by_status['pending'] ?? 0 ); ?></strong><span><?php esc_html_e( 'En attente', 'infinitycod' ); ?></span></div>
				<div class="icod-mini"><strong><?php echo (int) ( $counts_by_status['confirmed'] ?? 0 ); ?></strong><span><?php esc_html_e( 'Confirmées', 'infinitycod' ); ?></span></div>
				<div class="icod-mini"><strong><?php echo (int) ( $counts_by_status['shipped'] ?? 0 ); ?></strong><span><?php esc_html_e( 'Expédiées', 'infinitycod' ); ?></span></div>
				<div class="icod-mini"><strong><?php echo (int) ( $counts_by_status['delivered'] ?? 0 ); ?></strong><span><?php esc_html_e( 'Livrées', 'infinitycod' ); ?></span></div>
			</div>

			<form method="get" class="icod-orders-filters icod-card">
				<input type="hidden" name="page" value="infinitycod-orders" />
				<div class="icod-filters-grid">
					<label>
						<span><?php esc_html_e( 'Statut', 'infinitycod' ); ?></span>
						<select name="status">
							<option value=""><?php esc_html_e( 'Tous', 'infinitycod' ); ?></option>
							<?php foreach ( OrderStore::STATUSES as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $this->filters['status'], $key ); ?>>
									<?php echo esc_html( $label . ' (' . ( isset( $counts_by_status[ $key ] ) ? $counts_by_status[ $key ] : 0 ) . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Wilaya', 'infinitycod' ); ?></span>
						<select name="wilaya">
							<option value=""><?php esc_html_e( 'Toutes', 'infinitycod' ); ?></option>
							<?php foreach ( $wilayas as $w ) : ?>
								<option value="<?php echo esc_attr( $w['code'] ); ?>" <?php selected( $this->filters['wilaya'], $w['code'] ); ?>>
									<?php echo esc_html( $w['code'] . ' — ' . $w['name_fr'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Recherche (nom, téléphone)', 'infinitycod' ); ?></span>
						<input type="search" name="q" value="<?php echo esc_attr( $this->filters['q'] ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Du', 'infinitycod' ); ?></span>
						<input type="date" name="from" value="<?php echo esc_attr( $this->filters['from'] ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Au', 'infinitycod' ); ?></span>
						<input type="date" name="to" value="<?php echo esc_attr( $this->filters['to'] ); ?>" />
					</label>
					<div class="icod-filters-actions">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Filtrer', 'infinitycod' ); ?></button>
						<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-orders' ) ); ?>"><?php esc_html_e( 'Réinitialiser', 'infinitycod' ); ?></a>
					</div>
				</div>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="icod-orders-form">
				<input type="hidden" name="action" value="icod_orders_bulk" />
				<?php wp_nonce_field( 'icod_orders_bulk' ); ?>

				<div class="icod-table-toolbar">
					<label class="screen-reader-text" for="icod-bulk-action"><?php esc_html_e( 'Action groupée', 'infinitycod' ); ?></label>
					<select id="icod-bulk-action" name="bulk">
						<option value=""><?php esc_html_e( 'Actions groupées', 'infinitycod' ); ?></option>
						<option value="confirmed"><?php esc_html_e( '→ Confirmer', 'infinitycod' ); ?></option>
						<option value="no_answer"><?php esc_html_e( '→ Sans réponse', 'infinitycod' ); ?></option>
						<option value="cancelled"><?php esc_html_e( '→ Annuler', 'infinitycod' ); ?></option>
						<option value="delivered"><?php esc_html_e( '→ Marquer livrée', 'infinitycod' ); ?></option>
						<option value="returned"><?php esc_html_e( '→ Marquer retournée', 'infinitycod' ); ?></option>
						<option value="blacklist"><?php esc_html_e( '→ Blacklister les téléphones', 'infinitycod' ); ?></option>
					</select>
					<button type="submit" class="button"><?php esc_html_e( 'Appliquer', 'infinitycod' ); ?></button>
					<a class="button" href="<?php echo esc_url( $this->export_url() ); ?>">📥 <?php esc_html_e( 'Export CSV', 'infinitycod' ); ?></a>
					<span class="icod-hint">
						<?php
						printf(
							/* translators: %d : nombre total de commandes filtrées. */
							esc_html__( '%d commande(s)', 'infinitycod' ),
							(int) $total
						);
						?>
					</span>
				</div>

				<div class="icod-table-scroll">
					<table class="widefat striped icod-table icod-orders-table">
						<thead>
							<tr>
								<td class="icod-col-check"><input type="checkbox" id="icod-check-all" /></td>
								<th><?php esc_html_e( 'Client', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Produit', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Destination', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Total', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Statut', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Risque', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Date', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Transporteur', 'infinitycod' ); ?></th>
								<th class="icod-col-actions"><?php esc_html_e( 'Actions', 'infinitycod' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( $rows ) : ?>
								<?php foreach ( $rows as $row ) : ?>
									<?php $this->render_row( $row ); ?>
								<?php endforeach; ?>
							<?php else : ?>
								<tr><td colspan="10"><?php esc_html_e( 'Aucune commande pour ces filtres.', 'infinitycod' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</form>

			<?php $this->pagination( $total ); ?>
		</div>
		<?php
	}

	/**
	 * Une ligne du tableau + sa ligne détail dépliable.
	 *
	 * @param array $row Ligne icod_orders enrichie.
	 * @return void
	 */
	private function render_row( array $row ) {
		$status   = $row['status'];
		$statuses = OrderStore::STATUSES;
		$label    = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
		$risk     = (int) $row['fraud_score'];
		$product  = $row['product_id'] ? get_the_title( (int) $row['product_id'] ) : '';
		?>
		<tr class="icod-order-row <?php echo esc_attr( 'icod-st-' . $status ); ?>" data-id="<?php echo esc_attr( $row['id'] ); ?>">
			<td class="icod-col-check"><input type="checkbox" name="ids[]" value="<?php echo esc_attr( $row['id'] ); ?>" /></td>
			<td data-label="<?php esc_attr_e( 'Client', 'infinitycod' ); ?>">
				<strong><?php echo esc_html( $row['customer_name'] ); ?></strong>
				<span class="icod-sub"><?php echo esc_html( $row['phone'] ); ?></span>
			</td>
			<td data-label="<?php esc_attr_e( 'Produit', 'infinitycod' ); ?>">
				<?php echo esc_html( $product ); ?>
				<span class="icod-sub">× <?php echo (int) $row['quantity']; ?></span>
			</td>
			<td data-label="<?php esc_attr_e( 'Destination', 'infinitycod' ); ?>">
				<?php echo esc_html( $row['wilaya_name'] ); ?>
				<span class="icod-sub"><?php echo esc_html( $row['commune'] . ' · ' . ( 'desk' === $row['delivery_mode'] ? __( 'Bureau', 'infinitycod' ) : __( 'Domicile', 'infinitycod' ) ) ); ?></span>
			</td>
			<td data-label="<?php esc_attr_e( 'Total', 'infinitycod' ); ?>"><strong><?php echo esc_html( number_format_i18n( (float) $row['total'], 2 ) ); ?> DA</strong><?php if ( ! empty( $row['paid'] ) ) : ?> <span title="<?php esc_attr_e( 'Payé en ligne', 'infinitycod' ); ?>">💳</span><?php endif; ?></td>
			<td data-label="<?php esc_attr_e( 'Statut', 'infinitycod' ); ?>">
				<span class="icod-status icod-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $label ); ?></span>
			</td>
			<td data-label="<?php esc_attr_e( 'Risque', 'infinitycod' ); ?>">
				<?php if ( $risk >= 60 ) : ?>
					<span class="icod-risk icod-risk-high" title="<?php echo esc_attr( $row['fraud_flags'] ); ?>"><?php esc_html_e( 'Élevé', 'infinitycod' ); ?> (<?php echo (int) $risk; ?>)</span>
				<?php elseif ( $risk >= 25 ) : ?>
					<span class="icod-risk icod-risk-mid" title="<?php echo esc_attr( $row['fraud_flags'] ); ?>"><?php esc_html_e( 'Moyen', 'infinitycod' ); ?> (<?php echo (int) $risk; ?>)</span>
				<?php else : ?>
					<span class="icod-risk icod-risk-low"><?php esc_html_e( 'Faible', 'infinitycod' ); ?></span>
				<?php endif; ?>
			</td>
			<td data-label="<?php esc_attr_e( 'Date', 'infinitycod' ); ?>">
				<?php echo esc_html( mysql2date( 'd/m/Y H:i', $row['created_at'] ) ); ?>
			</td>
			<td data-label="<?php esc_attr_e( 'Transporteur', 'infinitycod' ); ?>">
				<?php if ( $row['carrier'] ) : ?>
					<?php echo esc_html( $row['carrier'] ); ?>
					<?php if ( $row['tracking'] ) : ?>
						<span class="icod-sub"><?php echo esc_html( $row['tracking'] ); ?></span>
					<?php endif; ?>
				<?php else : ?>
					<span class="icod-sub">—</span>
				<?php endif; ?>
			</td>
			<td class="icod-col-actions icod-row-actions">
				<a href="#" class="icod-detail-toggle button button-small" aria-expanded="false">🔍</a>
				<?php if ( in_array( $status, array( 'pending', 'no_answer' ), true ) ) : ?>
					<button type="button" class="button button-small icod-quick" data-id="<?php echo esc_attr( $row['id'] ); ?>" data-status="confirmed" title="<?php esc_attr_e( 'Confirmer', 'infinitycod' ); ?>">✓</button>
				<?php endif; ?>
				<?php if ( 'confirmed' === $status ) : ?>
					<button type="button" class="button button-small icod-quick" data-id="<?php echo esc_attr( $row['id'] ); ?>" data-status="shipped" title="<?php esc_attr_e( 'Expédiée', 'infinitycod' ); ?>">📦</button>
				<?php endif; ?>
				<?php if ( ! in_array( $status, array( 'delivered', 'cancelled' ), true ) ) : ?>
					<button type="button" class="button button-small icod-quick icod-danger" data-id="<?php echo esc_attr( $row['id'] ); ?>" data-status="cancelled" title="<?php esc_attr_e( 'Annuler', 'infinitycod' ); ?>">✕</button>
				<?php endif; ?>
			</td>
		</tr>
		<tr class="icod-detail-row icod-hidden">
			<td colspan="10">
				<div class="icod-detail-grid">
					<div>
						<h4><?php esc_html_e( 'Coordonnées', 'infinitycod' ); ?></h4>
						<p>
											<?php echo esc_html( $row['customer_name'] ); ?><br />
											<a href="tel:<?php echo esc_attr( $row['phone'] ); ?>"><?php echo esc_html( $row['phone'] ); ?></a><br />
											<a href="https://wa.me/213<?php echo esc_attr( ltrim( $row['phone'], '0' ) ); ?>" target="_blank" rel="noopener">💬 WhatsApp</a>
										</p>
						<?php if ( $row['stopdesk'] ) : ?>
							<p><strong><?php esc_html_e( 'Bureau :', 'infinitycod' ); ?></strong> <?php echo esc_html( $row['stopdesk'] ); ?></p>
						<?php endif; ?>
					</div>
					<div>
						<?php if ( ! empty( $row['paid'] ) ) : ?>
							<p><span class="icod-status icod-status-confirmed">💳 <?php esc_html_e( 'Payé en ligne (CIB/Edahabia)', 'infinitycod' ); ?></span><?php if ( $row['paid_at'] ) : ?> <span class="icod-sub"><?php echo esc_html( mysql2date( 'd/m/Y H:i', $row['paid_at'] ) ); ?></span><?php endif; ?></p>
						<?php elseif ( 'online' === $row['payment'] ) : ?>
							<p><span class="icod-status icod-status-no_answer">⏳ <?php esc_html_e( 'Paiement en ligne non finalisé', 'infinitycod' ); ?></span></p>
						<?php endif; ?>
						<h4><?php esc_html_e( 'Montants', 'infinitycod' ); ?></h4>
						<p>
							<?php
							printf(
								/* translators: 1 : sous-total, 2 : remise, 3 : livraison, 4 : total. */
								esc_html__( 'Sous-total : %1$s DA / Remise : %2$s DA / Livraison : %3$s DA / Total : %4$s DA', 'infinitycod' ),
								esc_html( number_format_i18n( (float) $row['subtotal'], 2 ) ),
								esc_html( number_format_i18n( (float) $row['discount'], 2 ) ),
								esc_html( number_format_i18n( (float) $row['shipping'], 2 ) ),
								esc_html( number_format_i18n( (float) $row['total'], 2 ) )
							);
							?>
						</p>
					</div>
					<div>
						<h4><?php esc_html_e( 'Suivi', 'infinitycod' ); ?></h4>
						<p>
							WC #<?php echo esc_html( $row['wc_order_id'] ); ?> ·
							<?php echo esc_html( $row['carrier_status'] ? $row['carrier_status'] : '—' ); ?>
						</p>
						<?php if ( $row['fraud_flags'] ) : ?>
							<p class="icod-risk-flags">⚠️ <?php echo esc_html( $row['fraud_flags'] ); ?></p>
						<?php endif; ?>
						<?php if ( $row['ip'] ) : ?>
							<p class="icod-sub">IP : <?php echo esc_html( $row['ip'] ); ?></p>
						<?php endif; ?>
					</div>
					<div>
						<?php if ( $row['wc_order_id'] ) : ?>
							<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( (int) $row['wc_order_id'] ) ); ?>"><?php esc_html_e( 'Voir dans WooCommerce', 'infinitycod' ); ?></a>
						<?php endif; ?>
						<button type="button" class="button button-small icod-bl" data-phone="<?php echo esc_attr( $row['phone'] ); ?>">🚫 <?php esc_html_e( 'Blacklister', 'infinitycod' ); ?></button>
					</div>
				</div>
			</td>
		</tr>
		<?php
	}

	/**
	 * Requête principale : lignes filtrées + total + compteurs par statut.
	 *
	 * @return array [rows, total, counts_by_status]
	 */
	private function query() {
		global $wpdb;

		$orders  = Schema::table( 'orders' );
		$wilayas = Schema::table( 'wilayas' );

		$where  = array( '1=1' );
		$params = array();

		if ( $this->filters['status'] && array_key_exists( $this->filters['status'], OrderStore::STATUSES ) ) {
			$where[]  = 'o.status = %s';
			$params[] = $this->filters['status'];
		}
		if ( $this->filters['wilaya'] && preg_match( '/^\d{1,2}$/', $this->filters['wilaya'] ) ) {
			$where[]  = 'o.wilaya_code = %s';
			$params[] = str_pad( $this->filters['wilaya'], 2, '0', STR_PAD_LEFT );
		}
		if ( $this->filters['q'] ) {
			$like     = '%' . $wpdb->esc_like( $this->filters['q'] ) . '%';
			$where[]  = '(o.customer_name LIKE %s OR o.phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		if ( $this->filters['from'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $this->filters['from'] ) ) {
			$where[]  = 'o.created_at >= %s';
			$params[] = $this->filters['from'] . ' 00:00:00';
		}
		if ( $this->filters['to'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $this->filters['to'] ) ) {
			$where[]  = 'o.created_at <= %s';
			$params[] = $this->filters['to'] . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders} o WHERE {$where_sql}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		$offset = ( $this->filters['paged'] - 1 ) * self::PER_PAGE;
		$query  = "SELECT o.*, w.name_fr AS wilaya_name FROM {$orders} o
			LEFT JOIN {$wilayas} w ON w.code = o.wilaya_code
			WHERE {$where_sql}
			ORDER BY o.created_at DESC, o.id DESC
			LIMIT %d OFFSET %d";

		$rows = $wpdb->get_results(
			$wpdb->prepare( $query, array_merge( $params, array( self::PER_PAGE, $offset ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL
			ARRAY_A
		);

		$counts = array();
		foreach ( $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$orders} GROUP BY status", ARRAY_A ) as $line ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$counts[ $line['status'] ] = (int) $line['n'];
		}

		return array( $rows ? $rows : array(), $total, $counts );
	}

	/**
	 * Url d'export CSV avec les filtres courants.
	 *
	 * @return string
	 */
	private function export_url() {
		$args = array(
			'action'  => 'icod_orders_export',
			'_wpnonce' => wp_create_nonce( 'icod_orders_export' ),
		);
		foreach ( array( 'status', 'wilaya', 'q', 'from', 'to' ) as $key ) {
			if ( $this->filters[ $key ] ) {
				$args[ $key ] = $this->filters[ $key ];
			}
		}
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	/**
	 * Pagination.
	 *
	 * @param int $total Nombre total de lignes.
	 * @return void
	 */
	private function pagination( $total ) {
		$pages = (int) ceil( $total / self::PER_PAGE );

		if ( $pages < 2 ) {
			return;
		}

		$base = add_query_arg( array_merge( array_filter( $this->filters ) ), admin_url( 'admin.php?page=infinitycod-orders' ) );

		echo '<nav class="tablenav"><div class="tablenav-pages">';
		echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput
			'base'      => add_query_arg( 'paged', '%#%' ),
			'format'    => '',
			'current'   => $this->filters['paged'],
			'total'     => $pages,
			'prev_text' => '‹',
			'next_text' => '›',
		) );
		echo '</div></nav>';
	}
}
