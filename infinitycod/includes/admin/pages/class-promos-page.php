<?php
/**
 * Page admin : Codes promo personnalisés — CRUD, statistiques, historique.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Admin\Pages;

use InfinityCod\Core\Schema;
use InfinityCod\Orders\Promo;

defined( 'ABSPATH' ) || exit;

class PromosPage {

	/**
	 * Palette de pastilles partagée avec Réglages.
	 *
	 * @var array
	 */
	private $swatch_colors = array(
		'#0E7A4F', '#1877C2', '#D6336C', '#E8590C', '#6D28D9',
		'#1971C2', '#0CA678', '#F59F00', '#E03131', '#7048E8',
	);

	/**
	 * Rendu de la page.
	 *
	 * @return void
	 */
	public function render() {
		global $wpdb;

		$edit_id    = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture.
		$history_id = isset( $_GET['history'] ) ? absint( $_GET['history'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg        = isset( $_GET['icod_msg'] ) ? sanitize_key( wp_unslash( $_GET['icod_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$table = Schema::table( 'promos' );

		// Mode historique d'un code.
		if ( $history_id ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $history_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			if ( $row ) {
				$this->render_history( $row );
				return;
			}
		}

		// Mode édition.
		$edit_row = null;
		if ( $edit_id ) {
			$edit_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $edit_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		}

		$stats = Promo::stats();
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		?>
		<div class="wrap icod-wrap icod-admin-polish">
			<h1 class="icod-title">🎟️ <?php esc_html_e( 'Codes promo', 'infinitycod' ); ?></h1>

			<?php if ( 'saved' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Code promo enregistré.', 'infinitycod' ); ?></p></div>
			<?php elseif ( 'deleted' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Code promo supprimé.', 'infinitycod' ); ?></p></div>
			<?php elseif ( 'toggled' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Statut du code mis à jour.', 'infinitycod' ); ?></p></div>
			<?php elseif ( 'invalid' === $msg ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Données du code incomplètes (code et valeur requis).', 'infinitycod' ); ?></p></div>
			<?php endif; ?>

			<div class="icod-mini-grid">
				<div class="icod-mini"><strong><?php echo (int) $stats['codes']; ?></strong><span><?php esc_html_e( 'Codes créés', 'infinitycod' ); ?></span></div>
				<div class="icod-mini"><strong class="icod-mini-pending"><?php echo (int) $stats['active']; ?></strong><span><?php esc_html_e( 'Actifs', 'infinitycod' ); ?></span></div>
				<div class="icod-mini"><strong><?php echo (int) $stats['uses']; ?></strong><span><?php esc_html_e( 'Utilisations', 'infinitycod' ); ?></span></div>
				<div class="icod-mini"><strong>−<?php echo esc_html( number_format_i18n( $stats['discount'], 0 ) ); ?></strong><span><?php esc_html_e( 'DA remisés', 'infinitycod' ); ?></span></div>
			</div>

			<?php $this->render_form( $edit_row ); ?>

			<div class="icod-card" style="margin-top:18px">
				<h2>🎟️ <?php esc_html_e( 'Mes codes promo', 'infinitycod' ); ?></h2>
				<div class="icod-table-scroll">
					<table class="widefat striped icod-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Code', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Remise', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Période', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Produits', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Utilisations', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'CA généré', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Statut', 'infinitycod' ); ?></th>
								<th class="icod-col-actions"><?php esc_html_e( 'Actions', 'infinitycod' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php if ( $rows ) : ?>
							<?php foreach ( $rows as $row ) : ?>
								<?php $this->render_row( $row ); ?>
							<?php endforeach; ?>
						<?php else : ?>
							<tr><td colspan="8"><?php esc_html_e( 'Aucun code promo. Créez le premier avec le formulaire ci-dessus.', 'infinitycod' ); ?></td></tr>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Formulaire de création / édition d'un code.
	 *
	 * @param array|null $row Ligne existante (édition) ou null.
	 * @return void
	 */
	private function render_form( $row ) {
		$is_edit = ! empty( $row['id'] );
		$code    = $row['code'] ?? '';
		$type    = $row['discount_type'] ?? 'percent';
		$value   = $row['discount_value'] ?? '';
		$starts  = ! empty( $row['starts_at'] ) ? mysql2date( 'Y-m-d', $row['starts_at'] ) : '';
		$ends    = ! empty( $row['ends_at'] ) ? mysql2date( 'Y-m-d', $row['ends_at'] ) : '';
		$min     = $row['min_total'] ?? '';
		$limit   = ( $row['usage_limit'] ?? 0 ) > 0 ? (int) $row['usage_limit'] : '';
		$active  = ! isset( $row['active'] ) || (int) $row['active'] === 1;
		$pids    = array_filter( array_map( 'absint', explode( ',', (string) ( $row['product_ids'] ?? '' ) ) ) );
		?>
		<div class="icod-card">
			<h2><?php echo $is_edit ? '✎ ' . esc_html__( 'Modifier le code', 'infinitycod' ) : '➕ ' . esc_html__( 'Nouveau code promo', 'infinitycod' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="icod_promo_save" />
				<input type="hidden" name="promo_id" value="<?php echo (int) ( $row['id'] ?? 0 ); ?>" />
				<?php wp_nonce_field( 'icod_promo_save' ); ?>
				<div class="icod-grid">
					<label>
						<span><?php esc_html_e( 'Code *', 'infinitycod' ); ?></span>
						<input type="text" name="promo_code" dir="ltr" maxlength="40" value="<?php echo esc_attr( $code ); ?>" placeholder="SOLDE2026" required />
					</label>
					<label>
						<span><?php esc_html_e( 'Type de remise', 'infinitycod' ); ?></span>
						<select name="promo_type">
							<option value="percent" <?php selected( $type, 'percent' ); ?>><?php esc_html_e( 'Pourcentage (%)', 'infinitycod' ); ?></option>
							<option value="fixed" <?php selected( $type, 'fixed' ); ?>><?php esc_html_e( 'Montant fixe (DA)', 'infinitycod' ); ?></option>
						</select>
					</label>
					<label>
						<span><?php esc_html_e( 'Valeur *', 'infinitycod' ); ?></span>
						<input type="number" step="0.01" min="0.01" name="promo_value" value="<?php echo esc_attr( $value ); ?>" required />
					</label>
					<label>
						<span><?php esc_html_e( 'Début de validité', 'infinitycod' ); ?></span>
						<input type="date" name="promo_starts" value="<?php echo esc_attr( $starts ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Fin de validité', 'infinitycod' ); ?></span>
						<input type="date" name="promo_ends" value="<?php echo esc_attr( $ends ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Minimum de commande (DA)', 'infinitycod' ); ?></span>
						<input type="number" step="0.01" min="0" name="promo_min_total" value="<?php echo esc_attr( $min ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Limite d’utilisations (0 = illimité)', 'infinitycod' ); ?></span>
						<input type="number" min="0" name="promo_usage_limit" value="<?php echo esc_attr( $limit ); ?>" />
					</label>
					<label class="icod-toggle" style="align-self:end">
						<input type="checkbox" name="promo_active" value="1" <?php checked( $active ); ?> />
						<span><?php esc_html_e( 'Code actif', 'infinitycod' ); ?></span>
					</label>
				</div>
				<p class="description"><strong><?php esc_html_e( 'Produits concernés', 'infinitycod' ); ?></strong> — <?php esc_html_e( 'ne rien cocher = tous les produits.', 'infinitycod' ); ?></p>
				<?php
				$products = function_exists( 'wc_get_products' ) ? wc_get_products( array( 'limit' => 300, 'status' => 'publish', 'orderby' => 'title', 'order' => 'ASC', 'return' => 'objects' ) ) : array();
				$selected = $pids;
				if ( $products ) :
					?>
					<div class="icod-promo-products" style="max-height:180px;overflow-y:auto;border:1px solid #dcdcde;border-radius:8px;padding:10px;display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:6px">
						<?php foreach ( $products as $product ) : ?>
							<label style="display:flex;gap:6px;align-items:center">
								<input type="checkbox" name="promo_products[]" value="<?php echo (int) $product->get_id(); ?>" <?php checked( in_array( (int) $product->get_id(), $selected, true ) ); ?> />
								<span><?php echo esc_html( $product->get_name() ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<p class="icod-submit">
					<button type="submit" class="button button-primary button-hero"><?php $is_edit ? esc_html_e( 'Mettre à jour le code', 'infinitycod' ) : esc_html_e( 'Créer le code promo', 'infinitycod' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Ligne du tableau des codes.
	 *
	 * @param array $row Ligne icod_promos.
	 * @return void
	 */
	private function render_row( $row ) {
		$is_percent = 'percent' === $row['discount_type'];
		$discount   = $is_percent ? (float) $row['discount_value'] . ' %' : number_format_i18n( (float) $row['discount_value'], 0 ) . ' ' . \InfinityCod\Core\Settings::currency_label();
		$period     = array();
		if ( ! empty( $row['starts_at'] ) ) { $period[] = mysql2date( 'd/m/Y', $row['starts_at'] ); }
		if ( ! empty( $row['ends_at'] ) ) { $period[] = mysql2date( 'd/m/Y', $row['ends_at'] ); }
		$pids   = array_filter( array_map( 'absint', explode( ',', (string) ( $row['product_ids'] ?? '' ) ) ) );
		$limit  = (int) $row['usage_limit'];
		$active = (int) $row['active'] === 1;
		?>
		<tr>
			<td><code dir="ltr" style="font-weight:700"><?php echo esc_html( $row['code'] ); ?></code></td>
			<td><strong><?php echo esc_html( $discount ); ?></strong></td>
			<td><?php echo esc_html( $period ? implode( ' → ', $period ) : '—' ); ?></td>
			<td><?php echo empty( $pids ) ? esc_html__( 'Tous', 'infinitycod' ) : (int) count( $pids ); ?></td>
			<td><strong><?php echo (int) $row['used_count']; ?></strong><?php echo $limit > 0 ? ' / ' . $limit : ''; ?></td>
			<td><?php echo esc_html( number_format_i18n( (float) $row['revenue_total'], 0 ) ); ?> <?php echo esc_html( \InfinityCod\Core\Settings::currency_label() ); ?></td>
			<td>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<input type="hidden" name="action" value="icod_promo_toggle" />
					<input type="hidden" name="promo_id" value="<?php echo (int) $row['id']; ?>" />
					<?php wp_nonce_field( 'icod_promo_toggle' ); ?>
					<button type="submit" class="button button-small"><?php echo $active ? esc_html__( 'Désactiver', 'infinitycod' ) : esc_html__( 'Activer', 'infinitycod' ); ?></button>
				</form>
			</td>
			<td class="icod-col-actions">
				<a class="button button-small" href="?page=infinitycod-promos&edit=<?php echo (int) $row['id']; ?>">✎</a>
				<a class="button button-small" href="?page=infinitycod-promos&history=<?php echo (int) $row['id']; ?>" title="<?php esc_attr_e( 'Historique', 'infinitycod' ); ?>">📜</a>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( 'Supprimer ce code promo ?', 'infinitycod' ) ); ?>');">
					<input type="hidden" name="action" value="icod_promo_delete" />
					<input type="hidden" name="promo_id" value="<?php echo (int) $row['id']; ?>" />
					<?php wp_nonce_field( 'icod_promo_delete' ); ?>
					<button type="submit" class="button button-small icod-danger">🗑</button>
				</form>
			</td>
		</tr>
		<?php
	}

	/**
	 * Historique d'utilisation d'un code.
	 *
	 * @param array $row Ligne promo.
	 * @return void
	 */
	private function render_history( $row ) {
		$history = Promo::history( $row['code'] );
		?>
		<div class="wrap icod-wrap icod-admin-polish">
			<h1 class="icod-title">
				📜 <?php printf( esc_html__( 'Historique — %s', 'infinitycod' ), '<code>' . esc_html( $row['code'] ) . '</code>' ); ?>
			</h1>
			<p><a class="button" href="?page=infinitycod-promos">← <?php esc_html_e( 'Retour aux codes promo', 'infinitycod' ); ?></a></p>

			<div class="icod-mini-grid">
				<div class="icod-mini"><strong><?php echo (int) $row['used_count']; ?></strong><span><?php esc_html_e( 'Utilisations', 'infinitycod' ); ?></span></div>
				<div class="icod-mini"><strong>−<?php echo esc_html( number_format_i18n( (float) $row['discount_total'], 0 ) ); ?></strong><span><?php esc_html_e( 'DA remisés', 'infinitycod' ); ?></span></div>
				<div class="icod-mini"><strong><?php echo esc_html( number_format_i18n( (float) $row['revenue_total'], 0 ) ); ?></strong><span><?php esc_html_e( 'DA de CA généré', 'infinitycod' ); ?></span></div>
			</div>

			<div class="icod-card">
				<div class="icod-table-scroll">
					<table class="widefat striped icod-table">
						<thead><tr>
							<th><?php esc_html_e( 'Date', 'infinitycod' ); ?></th>
							<th><?php esc_html_e( 'Client', 'infinitycod' ); ?></th>
							<th><?php esc_html_e( 'Téléphone', 'infinitycod' ); ?></th>
							<th><?php esc_html_e( 'Total', 'infinitycod' ); ?></th>
							<th><?php esc_html_e( 'Statut', 'infinitycod' ); ?></th>
						</tr></thead>
						<tbody>
						<?php if ( $history ) : ?>
							<?php foreach ( $history as $h ) : ?>
								<tr>
									<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $h['created_at'] ) ); ?></td>
									<td><?php echo esc_html( $h['customer_name'] ); ?><br /><span class="icod-sub"><?php echo esc_html( $h['phone'] ); ?></span></td>
									<td><strong><?php echo esc_html( number_format_i18n( (float) $h['total'], 0 ) ); ?> <?php echo esc_html( \InfinityCod\Core\Settings::currency_label() ); ?></strong></td>
									<td><span class="icod-status icod-status-<?php echo esc_attr( $h['status'] ); ?>"><?php echo esc_html( $h['status'] ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr><td colspan="5"><?php esc_html_e( 'Aucune utilisation pour le moment.', 'infinitycod' ); ?></td></tr>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}
}
