<?php
/**
 * Page admin : Wilayas, communes et bureaux — tarifs de livraison.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Admin\Pages;

use InfinityCod\Core\Schema;
use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class GeoPage {

	/**
	 * Onglet actif.
	 *
	 * @var string
	 */
	private $tab = 'wilayas';

	/**
	 * Constructeur : lit l'onglet demandé.
	 */
	public function __construct() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation par onglet uniquement.
		$tab       = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'wilayas';
		$this->tab = in_array( $tab, array( 'wilayas', 'communes', 'stopdesks' ), true ) ? $tab : 'wilayas';
		// phpcs:enable
	}

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public function render() {
		$message = isset( $_GET['icod_msg'] ) ? sanitize_key( wp_unslash( $_GET['icod_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap icod-wrap">
			<h1 class="icod-title"><?php esc_html_e( 'Wilayas & Tarifs de livraison', 'infinitycod' ); ?></h1>

			<?php if ( 'saved' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Tarifs enregistrés avec succès.', 'infinitycod' ); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper icod-tabs">
				<a href="?page=infinitycod-geo" class="nav-tab <?php echo 'wilayas' === $this->tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Tarifs par wilaya', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-geo&tab=communes" class="nav-tab <?php echo 'communes' === $this->tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Communes', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-geo&tab=stopdesks" class="nav-tab <?php echo 'stopdesks' === $this->tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Bureaux Stopdesk', 'infinitycod' ); ?></a>
			</nav>

			<?php
			switch ( $this->tab ) {
				case 'communes':
					$this->render_communes();
					break;
				case 'stopdesks':
					$this->render_stopdesks();
					break;
				default:
					$this->render_wilayas();
			}
			?>
		</div>
		<?php
	}

	/**
	 * Onglet tarifs par wilaya : 58 lignes éditables + défauts globaux.
	 *
	 * @return void
	 */
	private function render_wilayas() {
		$geo     = infinitycod()->module( 'geo' );
		$wilayas = $geo ? $geo->wilayas( false ) : array();

		$default_home = Settings::get( 'default_price_home' );
		$default_desk = Settings::get( 'default_price_desk' );
		$free_qty     = (int) Settings::get( 'free_shipping_qty' );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="icod_save_wilayas" />
			<input type="hidden" name="MAX_FILE_SIZE" value="2097152" />
			<?php wp_nonce_field( 'icod_save_wilayas' ); ?>

			<div class="icod-card icod-defaults">
				<h2><?php esc_html_e( 'Tarifs par défaut', 'infinitycod' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Appliqués aux wilayas sans tarif personnalisé (laisser vide un champ de wilaya = hériter de ces valeurs).', 'infinitycod' ); ?></p>
				<div class="icod-defaults-grid">
					<label>
						<span><?php esc_html_e( 'Domicile (DA)', 'infinitycod' ); ?></span>
						<input type="number" step="0.01" min="0" name="icod_default_home" value="<?php echo esc_attr( $default_home ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Stopdesk (DA)', 'infinitycod' ); ?></span>
						<input type="number" step="0.01" min="0" name="icod_default_desk" value="<?php echo esc_attr( $default_desk ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Livraison gratuite à partir de (qté)', 'infinitycod' ); ?></span>
						<input type="number" min="0" name="icod_free_qty" value="<?php echo esc_attr( $free_qty ); ?>" />
					</label>
				</div>
			</div>


			<div class="icod-card icod-free-weight">
				<h2><?php esc_html_e( 'Livraison gratuite intelligente & poids', 'infinitycod' ); ?></h2>
				<div class="icod-toggles">
					<label class="icod-toggle">
						<input type="checkbox" name="icod_free_amount_enabled" value="1" <?php checked( (int) Settings::get( 'free_amount_enabled' ), 1 ); ?> />
						<span><?php esc_html_e( 'Livraison gratuite à partir d\'un montant de panier', 'infinitycod' ); ?></span>
					</label>
					<label class="icod-toggle">
						<input type="checkbox" name="icod_weight_fee_enabled" value="1" <?php checked( (int) Settings::get( 'weight_fee_enabled' ), 1 ); ?> />
						<span><?php esc_html_e( 'Supplément poids (produits lourds)', 'infinitycod' ); ?></span>
					</label>
				</div>
				<div class="icod-grid">
					<label>
						<span><?php esc_html_e( 'Seuil de gratuité (DA)', 'infinitycod' ); ?></span>
						<input type="number" min="0" step="50" name="icod_free_amount_threshold" value="<?php echo esc_attr( Settings::get( 'free_amount_threshold' ) ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Message dynamique (variable {reste})', 'infinitycod' ); ?></span>
						<input type="text" name="icod_free_amount_message" value="<?php echo esc_attr( Settings::get( 'free_amount_message' ) ); ?>" class="regular-text" />
					</label>
					<label>
						<span><?php esc_html_e( 'Prix par kg supplémentaire (DA)', 'infinitycod' ); ?></span>
						<input type="number" min="0" step="10" name="icod_weight_fee_per_kg" value="<?php echo esc_attr( Settings::get( 'weight_fee_per_kg' ) ); ?>" />
					</label>
					<label>
						<span><?php esc_html_e( 'Kg inclus sans frais', 'infinitycod' ); ?></span>
						<input type="number" min="0" step="1" name="icod_weight_fee_free_kg" value="<?php echo esc_attr( Settings::get( 'weight_fee_free_kg' ) ); ?>" />
					</label>
					<label class="icod-toggle">
						<input type="checkbox" name="icod_carrier_autosync" value="1" <?php checked( '1', (string) Settings::get( 'carrier_autosync', '1' ) ); ?> />
						<span><?php esc_html_e( 'Synchronisation automatique du suivi transporteur (chaque heure)', 'infinitycod' ); ?></span>
					</label>
				</div>
			</div>

			<div class="icod-card">
				<div class="icod-table-toolbar">
					<input type="search" id="icod-wilaya-search" class="icod-search" placeholder="<?php esc_attr_e( 'Rechercher une wilaya…', 'infinitycod' ); ?>" />
					<span class="icod-hint"><?php esc_html_e( 'Vide = hérite du défaut', 'infinitycod' ); ?></span>
					<span class="icod-hint">·</span>
					<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=icod_rates_export' ), 'icod_rates_export' ) ); ?>">📤 <?php esc_html_e( 'Exporter', 'infinitycod' ); ?></a>
				</div>
				<div class="icod-table-scroll">
					<table class="widefat striped icod-table icod-wilayas-table">
						<thead>
							<tr>
								<th class="icod-col-code"><?php esc_html_e( 'Code', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Wilaya', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Domicile (DA)', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Stopdesk (DA)', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Délai (jours)', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Min (DA)', 'infinitycod' ); ?></th>
								<th class="icod-col-check"><?php esc_html_e( 'Active', 'infinitycod' ); ?></th>
								<th class="icod-col-check"><?php esc_html_e( 'Gratuite', 'infinitycod' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $wilayas as $w ) : ?>
								<tr data-search="<?php echo esc_attr( $w['code'] . ' ' . $w['name_fr'] . ' ' . $w['name_ar'] ); ?>">
									<td><?php echo esc_html( $w['code'] ); ?></td>
									<td>
										<strong><?php echo esc_html( $w['name_fr'] ); ?></strong>
										<?php if ( ! empty( $w['name_ar'] ) ) : ?>
											<span dir="rtl" class="icod-ar"><?php echo esc_html( $w['name_ar'] ); ?></span>
										<?php endif; ?>
									</td>
									<td><input type="number" step="0.01" min="0" name="icod_wilaya[<?php echo esc_attr( $w['code'] ); ?>][home]" value="<?php echo esc_attr( $w['price_home'] >= 0 ? $w['price_home'] : '' ); ?>" placeholder="<?php echo esc_attr( $default_home ); ?>" /></td>
									<td><input type="number" step="0.01" min="0" name="icod_wilaya[<?php echo esc_attr( $w['code'] ); ?>][desk]" value="<?php echo esc_attr( $w['price_desk'] >= 0 ? $w['price_desk'] : '' ); ?>" placeholder="<?php echo esc_attr( $default_desk ); ?>" /></td>
									<td><input type="text" name="icod_wilaya[<?php echo esc_attr( $w['code'] ); ?>][days]" value="<?php echo esc_attr( $w['delivery_days'] ); ?>" placeholder="2-4" /></td>
									<td><input type="number" step="0.01" min="0" name="icod_wilaya[<?php echo esc_attr( $w['code'] ); ?>][min]" value="<?php echo esc_attr( $w['min_order'] > 0 ? $w['min_order'] : '' ); ?>" /></td>
									<td class="icod-col-check"><input type="checkbox" name="icod_wilaya[<?php echo esc_attr( $w['code'] ); ?>][active]" <?php checked( ! empty( $w['active'] ) ); ?> /></td>
									<td class="icod-col-check"><input type="checkbox" name="icod_wilaya[<?php echo esc_attr( $w['code'] ); ?>][free]" <?php checked( ! empty( $w['free_shipping'] ) ); ?> /></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<p class="icod-submit">
				<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Enregistrer les tarifs', 'infinitycod' ); ?></button>
			</p>
		</form>

		<form method="post" enctype="multipart/form-data" class="icod-card" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
			<strong style="font-size:13px">📥 <?php esc_html_e( 'Import CSV des tarifs', 'infinitycod' ); ?></strong>
			<span class="icod-hint"><?php esc_html_e( 'Format : code;domicile;stopdesk;active;gratuite', 'infinitycod' ); ?></span>
			<input type="hidden" name="action" value="icod_rates_import" />
			<?php wp_nonce_field( 'icod_save_wilayas' ); ?>
			<input type="file" name="icod_rates_csv_file" accept=".csv" required />
			<button type="submit" class="button"><?php esc_html_e( 'Importer', 'infinitycod' ); ?></button>
		</form>
		<?php
	}

	/**
	 * Onglet communes : sélection wilaya puis liste avec overrides.
	 *
	 * @return void
	 */
	private function render_communes() {
		$geo          = infinitycod()->module( 'geo' );
		$wilayas      = $geo ? $geo->wilayas( false ) : array();
		$current_code = isset( $_GET['wilaya'] ) ? sanitize_text_field( wp_unslash( $_GET['wilaya'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$communes     = ( $geo && $current_code ) ? $geo->communes( $current_code, false ) : array();
		?>
		<div class="icod-card">
			<form method="get" class="icod-wilaya-picker">
				<input type="hidden" name="page" value="infinitycod-geo" />
				<input type="hidden" name="tab" value="communes" />
				<label for="icod-commune-wilaya" class="screen-reader-text"><?php esc_html_e( 'Wilaya', 'infinitycod' ); ?></label>
				<select id="icod-commune-wilaya" name="wilaya">
					<option value=""><?php esc_html_e( '— Choisir une wilaya —', 'infinitycod' ); ?></option>
					<?php foreach ( $wilayas as $w ) : ?>
						<option value="<?php echo esc_attr( $w['code'] ); ?>" <?php selected( $current_code, $w['code'] ); ?>>
							<?php echo esc_html( $w['code'] . ' — ' . $w['name_fr'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Afficher', 'infinitycod' ); ?></button>
				<?php if ( $current_code ) : ?>
					<span class="icod-hint" id="icod-commune-count"><?php /* translators: %d : nombre de communes. */ printf( esc_html__( '%d communes', 'infinitycod' ), (int) count( $communes ) ); ?></span>
				<?php endif; ?>
			</form>

			<?php if ( $current_code && $communes ) : ?>
				<div class="icod-table-toolbar">
					<input type="search" id="icod-commune-search" class="icod-search" placeholder="<?php esc_attr_e( 'Rechercher une commune…', 'infinitycod' ); ?>" />
					<span class="icod-hint"><?php esc_html_e( 'Vide = hérite du tarif wilaya', 'infinitycod' ); ?></span>
				</div>
				<div class="icod-table-scroll" id="icod-communes-wrap" data-wilaya="<?php echo esc_attr( $current_code ); ?>">
					<table class="widefat striped icod-table icod-communes-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Commune', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Domicile (DA)', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Stopdesk (DA)', 'infinitycod' ); ?></th>
								<th class="icod-col-check"><?php esc_html_e( 'Bureau', 'infinitycod' ); ?></th>
								<th class="icod-col-check"><?php esc_html_e( 'Active', 'infinitycod' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $communes as $c ) : ?>
								<tr data-search="<?php echo esc_attr( $c['name_fr'] . ' ' . $c['name_ar'] ); ?>" data-id="<?php echo esc_attr( $c['id'] ); ?>">
									<td>
										<strong><?php echo esc_html( $c['name_fr'] ); ?></strong>
										<?php if ( ! empty( $c['name_ar'] ) ) : ?>
											<span dir="rtl" class="icod-ar"><?php echo esc_html( $c['name_ar'] ); ?></span>
										<?php endif; ?>
									</td>
									<td><input type="number" step="0.01" min="0" class="icod-commune-input" data-field="price_home" value="<?php echo esc_attr( $c['price_home'] >= 0 ? $c['price_home'] : '' ); ?>" /></td>
									<td><input type="number" step="0.01" min="0" class="icod-commune-input" data-field="price_desk" value="<?php echo esc_attr( $c['price_desk'] >= 0 ? $c['price_desk'] : '' ); ?>" /></td>
									<td class="icod-col-check"><span class="icod-desk-badge <?php echo ! empty( $c['has_desk'] ) ? 'has' : ''; ?>"><?php echo ! empty( $c['has_desk'] ) ? '●' : '—'; ?></span></td>
									<td class="icod-col-check"><input type="checkbox" class="icod-commune-input" data-field="active" <?php checked( ! empty( $c['active'] ) ); ?> /></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php elseif ( $current_code ) : ?>
				<p><?php esc_html_e( 'Aucune commune trouvée pour cette wilaya.', 'infinitycod' ); ?></p>
			<?php else : ?>
				<p class="icod-hint"><?php esc_html_e( 'Choisissez une wilaya pour gérer ses communes : prix spécifiques, activation, présence de bureau.', 'infinitycod' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Onglet bureaux stopdesk.
	 *
	 * @return void
	 */
	private function render_stopdesks() {
		global $wpdb;

		$table  = Schema::table( 'stopdesks' );
		$total  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
		$by_carrier = $wpdb->get_results( "SELECT carrier, COUNT(*) AS n FROM {$table} GROUP BY carrier ORDER BY n DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		?>
		<div class="icod-card">
			<h2><?php esc_html_e( 'Bureaux Stopdesk', 'infinitycod' ); ?></h2>
			<?php if ( $total ) : ?>
				<p>
					<?php
					printf(
						/* translators: %d : nombre total de bureaux. */
						esc_html__( '%d bureaux enregistrés.', 'infinitycod' ),
						(int) $total
					);
					?>
				</p>
				<ul class="icod-desk-stats">
					<?php foreach ( $by_carrier as $row ) : ?>
						<li><strong><?php echo esc_html( $row['carrier'] ); ?></strong> — <?php echo esc_html( $row['n'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="icod-hint"><?php esc_html_e( 'Aucun bureau pour le moment. Les bureaux seront importés automatiquement depuis les API Yalidine et ZR Express dans l‘onglet Transporteurs (clés API requises).', 'infinitycod' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
