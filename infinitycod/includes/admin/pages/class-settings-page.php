<?php
/**
 * Page admin : Réglages du plugin.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Admin\Pages;

use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

	/**
	 * Onglet actif.
	 *
	 * @var string
	 */
	private $tab = 'form';

	/**
	 * Constructeur.
	 */
	public function __construct() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation par onglet.
		$tab       = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'form';
		$this->tab = in_array( $tab, array( 'form', 'fraud', 'whatsapp', 'license', 'advanced' ), true ) ? $tab : 'form';
		// phpcs:enable

		add_action( 'admin_post_icod_save_settings', array( $this, 'handle_save' ) );
		add_action( 'admin_post_icod_activate_license', array( $this, 'handle_license' ) );
	}

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public function render() {
		$saved = isset( $_GET['icod_msg'] ) ? sanitize_key( wp_unslash( $_GET['icod_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap icod-wrap">
			<h1 class="icod-title"><?php esc_html_e( 'Réglages InfinityCod', 'infinitycod' ); ?></h1>

			<?php if ( 'saved' === $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Réglages enregistrés.', 'infinitycod' ); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper icod-tabs">
				<a href="?page=infinitycod-settings" class="nav-tab <?php echo 'form' === $this->tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Formulaire', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=fraud" class="nav-tab <?php echo 'fraud' === $this->tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Anti-fraude', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=whatsapp" class="nav-tab <?php echo 'whatsapp' === $this->tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'WhatsApp', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=license" class="nav-tab <?php echo 'license' === $this->tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Licence', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=advanced" class="nav-tab <?php echo 'advanced' === $this->tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Avancé', 'infinitycod' ); ?></a>
			</nav>

			<?php if ( 'license' === $this->tab ) : ?>
				<?php $this->tab_license(); ?>
			<?php else : ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="icod_save_settings" />
				<input type="hidden" name="tab" value="<?php echo esc_attr( $this->tab ); ?>" />
				<?php wp_nonce_field( 'icod_save_settings' ); ?>

				<?php
				switch ( $this->tab ) {
					case 'fraud':
						$this->tab_fraud();
						break;
					case 'whatsapp':
						$this->tab_whatsapp();
						break;
					case 'advanced':
						$this->tab_advanced();
						break;
					default:
						$this->tab_form();
				}
				?>

				<p class="icod-submit">
					<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Enregistrer', 'infinitycod' ); ?></button>
				</p>
			</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Onglet licence : activation de clé.
	 *
	 * @return void
	 */
	private function tab_license() {
		$license = \InfinityCod\License\LicenseManager::stored();
		$premium = \InfinityCod\License\LicenseManager::is_premium();
		$msg     = isset( $_GET['icod_msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['icod_msg'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<?php if ( $msg ) : ?>
			<div class="notice <?php echo $premium ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>

		<div class="icod-card">
			<h2><?php esc_html_e( 'Licence InfinityCod', 'infinitycod' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'La version gratuite inclut le formulaire COD, les 58 wilayas et 1541 communes, les tarifs de livraison et l‘anti-fraude. La licence Premium débloque : WhatsApp automatique, transporteurs intégrés (création de colis + suivi), statistiques P&L et offres par quantité.', 'infinitycod' ); ?>
			</p>

			<p>
				<strong><?php esc_html_e( 'Statut :', 'infinitycod' ); ?></strong>
				<span class="icod-status <?php echo $premium ? 'icod-status-delivered' : 'icod-status-pending'; ?>"><?php echo esc_html( \InfinityCod\License\LicenseManager::status_label() ); ?></span>
				<?php if ( ! empty( $license['client'] ) ) : ?>
					— <?php echo esc_html( $license['client'] ); ?>
				<?php endif; ?>
				<?php if ( ! empty( $license['expires_at'] ) ) : ?>
					· <?php printf( /* translators: %s : date. */ esc_html__( 'expire le %s', 'infinitycod' ), esc_html( $license['expires_at'] ) ); ?>
				<?php endif; ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="icod_activate_license" />
				<?php wp_nonce_field( 'icod_activate_license' ); ?>
				<div class="icod-grid icod-grid-full">
					<label>
						<span><?php esc_html_e( 'Clé de licence', 'infinitycod' ); ?></span>
						<input type="text" name="icod_license_key" class="regular-text" dir="ltr" placeholder="INFINITY-XXXX-XXXX-XXXX" />
					</label>
				</div>
				<p class="icod-submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Activer la licence', 'infinitycod' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Traite l'activation de licence.
	 *
	 * @return void
	 */
	public function handle_license() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_activate_license' );

		$key = isset( $_POST['icod_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['icod_license_key'] ) ) : '';

		$manager = new \InfinityCod\License\LicenseManager();
		$result  = $manager->activate( $key );

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-settings&tab=license&icod_msg=' . rawurlencode( $result['message'] ) ) );
		exit;
	}

	/**
	 * Onglet formulaire.
	 *
	 * @return void
	 */
	private function tab_form() {
		?>
		<div class="icod-card">
			<h2><?php esc_html_e( 'Apparence du formulaire', 'infinitycod' ); ?></h2>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Titre du formulaire', 'infinitycod' ); ?></span>
					<input type="text" name="icod[form_title]" value="<?php echo esc_attr( Settings::get( 'form_title' ) ); ?>" class="regular-text" />
				</label>
				<label>
					<span><?php esc_html_e( 'Texte du bouton', 'infinitycod' ); ?></span>
					<input type="text" name="icod[button_text]" value="<?php echo esc_attr( Settings::get( 'button_text' ) ); ?>" class="regular-text" />
				</label>
				<label>
					<span><?php esc_html_e( 'Couleur d‘accent', 'infinitycod' ); ?></span>
					<input type="color" name="icod[accent_color]" value="<?php echo esc_attr( Settings::get( 'accent_color' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Thème', 'infinitycod' ); ?></span>
					<select name="icod[form_theme]">
						<option value="light" <?php selected( Settings::get( 'form_theme' ), 'light' ); ?>><?php esc_html_e( 'Clair', 'infinitycod' ); ?></option>
						<option value="dark" <?php selected( Settings::get( 'form_theme' ), 'dark' ); ?>><?php esc_html_e( 'Sombre', 'infinitycod' ); ?></option>
						<option value="auto" <?php selected( Settings::get( 'form_theme' ), 'auto' ); ?>><?php esc_html_e( 'Automatique (préférence du visiteur)', 'infinitycod' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Quantité maximale par commande', 'infinitycod' ); ?></span>
					<input type="number" min="1" max="999" name="icod[qty_max]" value="<?php echo esc_attr( Settings::get( 'qty_max' ) ); ?>" />
				</label>
			</div>
			<div class="icod-toggles">
				<label class="icod-toggle">
					<input type="checkbox" name="icod[show_qty_selector]" value="1" <?php checked( (int) Settings::get( 'show_qty_selector' ), 1 ); ?> />
					<span><?php esc_html_e( 'Afficher le sélecteur de quantité', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[sticky_bar]" value="1" <?php checked( (int) Settings::get( 'sticky_bar' ), 1 ); ?> />
					<span><?php esc_html_e( 'Barre récapitulative collante sur mobile', 'infinitycod' ); ?></span>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Onglet anti-fraude.
	 *
	 * @return void
	 */
	private function tab_fraud() {
		?>
		<div class="icod-card">
			<h2><?php esc_html_e( 'Bouclier anti-fraude (Shield)', 'infinitycod' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Aucun système n‘élimine 100 % des fausses commandes ; ces barrières réduisent fortement les abus sans gêner les vrais clients.', 'infinitycod' ); ?></p>
			<div class="icod-toggles">
				<label class="icod-toggle">
					<input type="checkbox" name="icod[shield_enabled]" value="1" <?php checked( (int) Settings::get( 'shield_enabled' ), 1 ); ?> />
					<span><?php esc_html_e( 'Activer le bouclier anti-fraude', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[phone_strict]" value="1" <?php checked( (int) Settings::get( 'phone_strict' ), 1 ); ?> />
					<span><?php esc_html_e( 'Exiger un numéro algérien valide (Mobilis, Djezzy, Ooredoo)', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[block_duplicate_phone]" value="1" <?php checked( (int) Settings::get( 'block_duplicate_phone' ), 1 ); ?> />
					<span><?php esc_html_e( 'Refuser un numéro ayant déjà une commande en attente', 'infinitycod' ); ?></span>
				</label>
			</div>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Temps minimum de remplissage (secondes)', 'infinitycod' ); ?></span>
					<input type="number" min="0" max="60" name="icod[min_submit_seconds]" value="<?php echo esc_attr( Settings::get( 'min_submit_seconds' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Commandes max. par IP et par heure', 'infinitycod' ); ?></span>
					<input type="number" min="1" max="100" name="icod[max_per_ip_hour]" value="<?php echo esc_attr( Settings::get( 'max_per_ip_hour' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Score de risque bloquant (0-100)', 'infinitycod' ); ?></span>
					<input type="number" min="0" max="100" name="icod[min_fraud_score_block]" value="<?php echo esc_attr( Settings::get( 'min_fraud_score_block' ) ); ?>" />
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Onglet WhatsApp.
	 *
	 * @return void
	 */
	private function tab_whatsapp() {
		?>
		<div class="icod-card">
			<h2><?php esc_html_e( 'WhatsApp automatique', 'infinitycod' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Envoyez automatiquement des messages aux clients et relancez les paniers abandonnés — ce que les concurrents ne font qu‘en manuel.', 'infinitycod' ); ?></p>
			<div class="icod-toggles">
				<label class="icod-toggle">
					<input type="checkbox" name="icod[whatsapp_enabled]" value="1" <?php checked( (int) Settings::get( 'whatsapp_enabled' ), 1 ); ?> />
					<span><?php esc_html_e( 'Activer les messages WhatsApp', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[abandoned_enabled]" value="1" <?php checked( (int) Settings::get( 'abandoned_enabled' ), 1 ); ?> />
					<span><?php esc_html_e( 'Relancer automatiquement les paniers abandonnés', 'infinitycod' ); ?></span>
				</label>
			</div>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Passerelle', 'infinitycod' ); ?></span>
					<select name="icod[whatsapp_gateway]">
						<option value="wame" <?php selected( Settings::get( 'whatsapp_gateway' ), 'wame' ); ?>><?php esc_html_e( 'Liens wa.me (ouverture manuelle, gratuit)', 'infinitycod' ); ?></option>
						<option value="cloud" <?php selected( Settings::get( 'whatsapp_gateway' ), 'cloud' ); ?>><?php esc_html_e( 'WhatsApp Cloud API (officiel Meta)', 'infinitycod' ); ?></option>
						<option value="ultramsg" <?php selected( Settings::get( 'whatsapp_gateway' ), 'ultramsg' ); ?>>UltraMsg</option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Numéro marchand (format international, ex. 213XXXXXXXXX)', 'infinitycod' ); ?></span>
					<input type="text" name="icod[whatsapp_number]" value="<?php echo esc_attr( Settings::get( 'whatsapp_number' ) ); ?>" class="regular-text" />
				</label>
				<label>
					<span><?php esc_html_e( 'Cloud API — Token permanent', 'infinitycod' ); ?></span>
					<input type="password" name="icod[whatsapp_cloud_token]" value="<?php echo esc_attr( Settings::get( 'whatsapp_cloud_token' ) ); ?>" class="regular-text" autocomplete="new-password" />
				</label>
				<label>
					<span><?php esc_html_e( 'Cloud API — Phone Number ID', 'infinitycod' ); ?></span>
					<input type="text" name="icod[whatsapp_phone_id]" value="<?php echo esc_attr( Settings::get( 'whatsapp_phone_id' ) ); ?>" class="regular-text" />
				</label>
				<label>
					<span><?php esc_html_e( 'UltraMsg — Instance', 'infinitycod' ); ?></span>
					<input type="text" name="icod[whatsapp_ultramsg_instance]" value="<?php echo esc_attr( Settings::get( 'whatsapp_ultramsg_instance' ) ); ?>" class="regular-text" />
				</label>
				<label>
					<span><?php esc_html_e( 'UltraMsg — Clé API', 'infinitycod' ); ?></span>
					<input type="password" name="icod[whatsapp_ultramsg_key]" value="<?php echo esc_attr( Settings::get( 'whatsapp_ultramsg_key' ) ); ?>" class="regular-text" autocomplete="new-password" />
				</label>
			</div>
		</div>

		<div class="icod-card">
			<h2><?php esc_html_e( 'Messages', 'infinitycod' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Variables disponibles : {nom}, {telephone}, {commande}, {total}, {suivi}, {wilaya}, {commune}, {produit}.', 'infinitycod' ); ?></p>
			<div class="icod-grid icod-grid-full">
				<label>
					<span><?php esc_html_e( 'Commande reçue', 'infinitycod' ); ?></span>
					<textarea name="icod[msg_order_received]" rows="3" class="large-text"><?php echo esc_textarea( Settings::get( 'msg_order_received' ) ); ?></textarea>
				</label>
				<label>
					<span><?php esc_html_e( 'Commande expédiée', 'infinitycod' ); ?></span>
					<textarea name="icod[msg_order_shipped]" rows="3" class="large-text"><?php echo esc_textarea( Settings::get( 'msg_order_shipped' ) ); ?></textarea>
				</label>
				<label>
					<span><?php esc_html_e( 'Relance panier abandonné', 'infinitycod' ); ?></span>
					<textarea name="icod[msg_abandoned]" rows="3" class="large-text"><?php echo esc_textarea( Settings::get( 'msg_abandoned' ) ); ?></textarea>
				</label>
			</div>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Relance paniers : délai avant la 1re relance (minutes)', 'infinitycod' ); ?></span>
					<input type="number" min="5" max="1440" name="icod[abandoned_delay]" value="<?php echo esc_attr( Settings::get( 'abandoned_delay' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Relance paniers : nombre de relances max', 'infinitycod' ); ?></span>
					<input type="number" min="1" max="5" name="icod[abandoned_max]" value="<?php echo esc_attr( Settings::get( 'abandoned_max' ) ); ?>" />
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Onglet avancé.
	 *
	 * @return void
	 */
	private function tab_advanced() {
		?>
		<div class="icod-card">
			<h2><?php esc_html_e( 'Avancé', 'infinitycod' ); ?></h2>
			<div class="icod-toggles">
				<label class="icod-toggle icod-toggle-danger">
					<input type="checkbox" name="icod[delete_on_uninstall]" value="1" <?php checked( (int) Settings::get( 'delete_on_uninstall' ), 1 ); ?> />
					<span><?php esc_html_e( 'Supprimer toutes les données (tables, réglages) à la désinstallation du plugin', 'infinitycod' ); ?></span>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Sauvegarde des réglages.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_save_settings' );

		$raw = isset( $_POST['icod'] ) && is_array( $_POST['icod'] ) ? wp_unslash( $_POST['icod'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitisé champ par champ.

		$clean = array();

		// Textes.
		foreach ( array( 'form_title', 'button_text' ) as $text_key ) {
			if ( isset( $raw[ $text_key ] ) ) {
				$clean[ $text_key ] = sanitize_text_field( $raw[ $text_key ] );
			}
		}
		foreach ( array( 'msg_order_received', 'msg_order_shipped', 'msg_abandoned' ) as $textarea_key ) {
			if ( isset( $raw[ $textarea_key ] ) ) {
				$clean[ $textarea_key ] = sanitize_textarea_field( $raw[ $textarea_key ] );
			}
		}

		// Couleur.
		if ( isset( $raw['accent_color'] ) && preg_match( '/^#[0-9a-fA-F]{6}$/', $raw['accent_color'] ) ) {
			$clean['accent_color'] = $raw['accent_color'];
		}

		// Énumérations.
		if ( isset( $raw['form_theme'] ) && in_array( $raw['form_theme'], array( 'light', 'dark', 'auto' ), true ) ) {
			$clean['form_theme'] = $raw['form_theme'];
		}
		if ( isset( $raw['whatsapp_gateway'] ) && in_array( $raw['whatsapp_gateway'], array( 'wame', 'cloud', 'ultramsg' ), true ) ) {
			$clean['whatsapp_gateway'] = $raw['whatsapp_gateway'];
		}

		// Numériques.
		foreach ( array(
			'qty_max'              => array( 1, 999 ),
			'min_submit_seconds'   => array( 0, 60 ),
			'max_per_ip_hour'      => array( 1, 100 ),
			'min_fraud_score_block' => array( 0, 100 ),
			'abandoned_delay'      => array( 5, 1440 ),
			'abandoned_max'        => array( 1, 5 ),
		) as $num_key => $range ) {
			if ( isset( $raw[ $num_key ] ) ) {
				$clean[ $num_key ] = max( $range[0], min( $range[1], (int) $raw[ $num_key ] ) );
			}
		}

		// Cases à cocher (absent = 0).
		foreach ( array( 'show_qty_selector', 'sticky_bar', 'shield_enabled', 'phone_strict', 'block_duplicate_phone', 'whatsapp_enabled', 'abandoned_enabled', 'delete_on_uninstall' ) as $toggle_key ) {
			$clean[ $toggle_key ] = empty( $raw[ $toggle_key ] ) ? 0 : 1;
		}

		// Clés / identifiants.
		foreach ( array( 'whatsapp_number', 'whatsapp_phone_id', 'whatsapp_ultramsg_instance' ) as $id_key ) {
			if ( isset( $raw[ $id_key ] ) ) {
				$clean[ $id_key ] = preg_replace( '/[^0-9a-zA-Z_\-.]/', '', $raw[ $id_key ] );
			}
		}
		foreach ( array( 'whatsapp_cloud_token', 'whatsapp_ultramsg_key' ) as $secret_key ) {
			if ( isset( $raw[ $secret_key ] ) ) {
				$clean[ $secret_key ] = sanitize_text_field( $raw[ $secret_key ] );
			}
		}

		Settings::set( $clean );

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-settings&tab=' . ( isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'form' ) . '&icod_msg=saved' ) );
		exit;
	}
}
