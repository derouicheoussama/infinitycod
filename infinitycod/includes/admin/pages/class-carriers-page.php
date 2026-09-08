<?php
/**
 * Page admin : Transporteurs — connexions, expédition, import bureaux.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Admin\Pages;

use InfinityCod\Carriers\CarrierManager;
use InfinityCod\Core\Schema;

defined( 'ABSPATH' ) || exit;

class CarriersPage {

	/**
	 * Onglet actif.
	 *
	 * @var string
	 */
	private $tab = 'connections';

	/**
	 * Constructeur.
	 */
	public function __construct() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation.
		$tab       = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'connections';
		$this->tab = in_array( $tab, array( 'connections', 'ship' ), true ) ? $tab : 'connections';
		// phpcs:enable
	}

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public function render() {
		$msg = isset( $_GET['icod_msg'] ) ? sanitize_key( wp_unslash( $_GET['icod_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap icod-wrap">
			<h1 class="icod-title"><?php esc_html_e( 'Transporteurs', 'infinitycod' ); ?></h1>

			<?php if ( 'saved' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Configuration enregistrée.', 'infinitycod' ); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper icod-tabs">
				<a href="?page=infinitycod-carriers" class="nav-tab <?php echo 'connections' === $this->tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Connexions API', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-carriers&tab=ship" class="nav-tab <?php echo 'ship' === $this->tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Créer des colis', 'infinitycod' ); ?></a>
			</nav>

			<?php if ( 'ship' === $this->tab ) : ?>
				<?php $this->render_ship(); ?>
			<?php else : ?>
				<?php $this->render_connections(); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Onglet connexions : un bloc par transporteur.
	 *
	 * @return void
	 */
	private function render_connections() {
		$manager = infinitycod()->module( 'carriers' );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="icod_carrier_save" />';
		wp_nonce_field( 'icod_carrier_save' );

		foreach ( CarrierManager::catalog() as $entry ) {
			$config = CarrierManager::config( $entry['code'] );
			$enabled = ! empty( $config['enabled'] );
			?>
			<div class="icod-card icod-carrier-card" data-code="<?php echo esc_attr( $entry['code'] ); ?>">
				<div class="icod-carrier-head">
					<h2><?php echo esc_html( $entry['name'] ); ?></h2>
					<label class="icod-toggle">
						<input type="checkbox" name="icod_carrier[<?php echo esc_attr( $entry['code'] ); ?>][enabled]" value="1" <?php checked( $enabled ); ?> />
						<span><?php esc_html_e( 'Activé', 'infinitycod' ); ?></span>
					</label>
				</div>

				<div class="icod-grid">
					<?php foreach ( $entry['fields'] as $field ) : ?>
						<?php
						$label = '';
						$type  = 'text';
						switch ( $field ) {
							case 'api_id':     $label = __( 'API ID', 'infinitycod' ); break;
							case 'api_token':  $label = __( 'Jeton API (token)', 'infinitycod' ); $type = 'password'; break;
							case 'api_key':    $label = __( 'Clé API', 'infinitycod' ); $type = 'password'; break;
							case 'user_guid':  $label = __( 'User GUID (optionnel)', 'infinitycod' ); break;
							case 'base_url':   $label = __( 'URL de l‘instance', 'infinitycod' ); break;
						}
						$value = isset( $config[ $field ] ) ? $config[ $field ] : '';
						?>
						<label>
							<span><?php echo esc_html( $label ); ?></span>
							<input type="<?php echo esc_attr( $type ); ?>" autocomplete="new-password" name="icod_carrier[<?php echo esc_attr( $entry['code'] ); ?>][<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $value ); ?>" />
						</label>
					<?php endforeach; ?>
				</div>

				<div class="icod-carrier-actions">
					<button type="button" class="button icod-test" data-code="<?php echo esc_attr( $entry['code'] ); ?>"><?php esc_html_e( 'Tester la connexion', 'infinitycod' ); ?></button>
					<span class="icod-test-result" data-result="<?php echo esc_attr( $entry['code'] ); ?>"></span>
					<?php if ( ! empty( $entry['offices'] ) ) : ?>
						<button type="button" class="button icod-import-offices" data-code="yalidine"><?php esc_html_e( '📥 Importer les bureaux Stopdesk', 'infinitycod' ); ?></button>
					<?php endif; ?>
				</div>
			</div>
			<?php
		}

		echo '<p class="icod-submit"><button type="submit" class="button button-primary button-hero">' . esc_html__( 'Enregistrer les connexions', 'infinitycod' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * Onglet expédition : commandes confirmées sans colis.
	 *
	 * @return void
	 */
	private function render_ship() {
		global $wpdb;

		$manager = infinitycod()->module( 'carriers' );
		$table   = Schema::table( 'orders' );
		$wilayas = Schema::table( 'wilayas' );

		$rows = $wpdb->get_results(
			"SELECT o.*, w.name_fr AS wilaya_name FROM {$table} o
			 LEFT JOIN {$wilayas} w ON w.code = o.wilaya_code
			 WHERE o.status IN ('confirmed') AND (o.tracking = '' OR o.tracking IS NULL)
			 ORDER BY o.confirmed_at DESC LIMIT 100", // phpcs:ignore WordPress.DB.PreparedSQL
			ARRAY_A
		);

		// Transporteurs utilisables.
		$usable = array();
		foreach ( CarrierManager::catalog() as $entry ) {
			if ( $manager && $manager->is_configured( $entry['code'] ) ) {
				$usable[] = $entry;
			}
		}
		?>
		<div class="icod-card">
			<h2><?php esc_html_e( 'Commandes confirmées à expédier', 'infinitycod' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Créez le colis directement chez le transporteur — le numéro de suivi est récupéré automatiquement et la commande passe en « Expédiée ».', 'infinitycod' ); ?>
				<button type="button" class="button icod-sync-now" style="margin-inline-start:8px">🔄 <?php esc_html_e( 'Synchroniser les suivis maintenant', 'infinitycod' ); ?></button>
			</p>

			<?php if ( ! $usable ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'Aucun transporteur configuré : renseignez vos clés API dans l‘onglet « Connexions API ».', 'infinitycod' ); ?></p></div>
			<?php endif; ?>

			<?php if ( $rows ) : ?>
				<div class="icod-table-scroll">
					<table class="widefat striped icod-table icod-ship-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Client', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Destination', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Produit', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Montant', 'infinitycod' ); ?></th>
								<th><?php esc_html_e( 'Expédier via', 'infinitycod' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $row['customer_name'] ); ?></strong><span class="icod-sub"><?php echo esc_html( $row['phone'] ); ?></span></td>
									<td>
										<?php echo esc_html( $row['wilaya_name'] ); ?>
										<span class="icod-sub"><?php echo esc_html( $row['commune'] . ' · ' . ( 'desk' === $row['delivery_mode'] ? __( 'Bureau', 'infinitycod' ) : __( 'Domicile', 'infinitycod' ) ) ); ?></span>
									</td>
									<td><?php echo esc_html( $row['product_id'] ? get_the_title( (int) $row['product_id'] ) : '—' ); ?> × <?php echo (int) $row['quantity']; ?></td>
									<td><?php echo esc_html( number_format_i18n( (float) $row['total'], 0 ) ); ?> DA</td>
									<td class="icod-ship-cell">
										<select class="icod-ship-carrier">
											<?php foreach ( $usable as $entry ) : ?>
												<option value="<?php echo esc_attr( $entry['code'] ); ?>"><?php echo esc_html( $entry['name'] ); ?></option>
											<?php endforeach; ?>
										</select>
										<button type="button" class="button button-primary icod-ship-btn" data-id="<?php echo esc_attr( $row['id'] ); ?>">📦 <?php esc_html_e( 'Créer le colis', 'infinitycod' ); ?></button>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<p><?php esc_html_e( 'Aucune commande confirmée en attente d‘expédition 🎉', 'infinitycod' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}
}
