<?php
/**
 * Page admin : Réglages du plugin.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
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
		$this->tab = in_array( $tab, array( 'form', 'order', 'fraud', 'whatsapp', 'tracking', 'payment', 'license', 'advanced' ), true ) ? $tab : 'form';
		// phpcs:enable

		add_action( 'admin_post_icod_save_settings', array( $this, 'handle_save' ) );
		add_action( 'admin_post_icod_activate_license', array( $this, 'handle_license' ) );
		add_action( 'admin_post_icod_verify_license', array( $this, 'handle_license_verify' ) );
		add_action( 'admin_post_icod_deactivate_license', array( $this, 'handle_license_deactivate' ) );
		add_action( 'admin_post_icod_save_paypal', array( $this, 'handle_paypal_settings' ) );
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
				<a href="?page=infinitycod-settings" class="nav-tab <?php echo 'form' === $this->tab ? 'nav-tab-active' : ''; ?>">🎨 <?php esc_html_e( 'Formulaire', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=order" class="nav-tab <?php echo 'order' === $this->tab ? 'nav-tab-active' : ''; ?>">🧾 <?php esc_html_e( 'Commande', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=fraud" class="nav-tab <?php echo 'fraud' === $this->tab ? 'nav-tab-active' : ''; ?>">🛡️ <?php esc_html_e( 'Anti-fraude', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=whatsapp" class="nav-tab <?php echo 'whatsapp' === $this->tab ? 'nav-tab-active' : ''; ?>">💬 <?php esc_html_e( 'WhatsApp', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=tracking" class="nav-tab <?php echo 'tracking' === $this->tab ? 'nav-tab-active' : ''; ?>">🎯 <?php esc_html_e( 'Tracking', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=payment" class="nav-tab <?php echo 'payment' === $this->tab ? 'nav-tab-active' : ''; ?>" >💳 <?php esc_html_e( 'Paiement', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=license" class="nav-tab <?php echo 'license' === $this->tab ? 'nav-tab-active' : ''; ?>">🔑 <?php esc_html_e( 'Licence', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=advanced" class="nav-tab <?php echo 'advanced' === $this->tab ? 'nav-tab-active' : ''; ?>">⚙️ <?php esc_html_e( 'Avancé', 'infinitycod' ); ?></a>
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
					case 'order':
						$this->tab_order();
						break;
					case 'fraud':
						$this->tab_fraud();
						break;
					case 'whatsapp':
						$this->tab_whatsapp();
						break;
					case 'tracking':
						$this->tab_tracking();
						break;
					case 'payment':
						$this->tab_payment();
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
	 * Ouvre une zone « Premium verrouillé » : contenu grisé et non interactif
	 * tant qu'aucune licence active. Sans effet quand la licence est active.
	 *
	 * @return void
	 */
	private function premium_gate_open() {
		if ( \InfinityCod\License\LicenseManager::is_premium() ) {
			return;
		}
		$link = admin_url( 'admin.php?page=infinitycod-settings&tab=license' );
		echo '<div class="icod-premium-notice">★ <strong>' . esc_html__( 'Fonctionnalité Premium', 'infinitycod' ) . '</strong> — ' . esc_html__( 'activez votre licence pour utiliser cette section.', 'infinitycod' ) . ' <a href="' . esc_url( $link ) . '">' . esc_html__( 'Activer maintenant', 'infinitycod' ) . '</a></div>';
		echo '<div class="icod-premium-locked">';
	}

	/**
	 * Ferme la zone « Premium verrouillé » ouverte par premium_gate_open().
	 *
	 * @return void
	 */
	private function premium_gate_close() {
		if ( \InfinityCod\License\LicenseManager::is_premium() ) {
			return;
		}
		echo '</div>';
	}

	/**
	 * Logos officiels des réseaux publicitaires (SVG simples-icons).
	 *
	 * @param string $brand facebook|tiktok|snapchat.
	 * @return string
	 */
	private function social_logo( $brand ) {
		$paths = array(
			'facebook' => 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
			'tiktok'   => 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z',
			'snapchat' => 'M12.206.793c.99 0 4.347.276 5.93 3.821.529 1.193.403 3.219.299 4.847l-.003.06c-.012.18-.022.345-.03.51.075.045.203.09.401.09.3-.016.659-.12 1.033-.301.165-.088.344-.104.46-.104.182 0 .359.029.509.09.45.149.734.479.734.838.015.449-.39.839-1.213 1.168-.089.029-.209.075-.344.134-.45.135-1.139.45-1.139 1.008.015.149.06.299.135.464.027.062 1.626 3.373 5.31 3.985.254.044.439.27.424.509 0 .074-.015.149-.045.225-.24.569-1.273.988-3.146 1.271-.059.091-.12.375-.164.57-.029.179-.074.36-.134.553-.076.271-.27.405-.555.405h-.03c-.135 0-.313-.031-.538-.074-.36-.075-.765-.135-1.273-.135-.3 0-.599.015-.913.074-.6.104-1.123.464-1.723.884-.853.599-1.826 1.288-3.294 1.288-.06 0-.119-.015-.18-.015h-.149c-1.468 0-2.427-.675-3.279-1.288-.599-.42-1.107-.779-1.707-.884-.314-.045-.629-.074-.928-.074-.54 0-.958.089-1.272.149-.211.043-.391.074-.54.074-.374 0-.523-.224-.583-.42-.061-.192-.09-.389-.135-.567-.046-.181-.105-.494-.166-.57-1.918-.222-2.95-.642-3.189-1.226-.031-.063-.049-.137-.049-.215-.015-.253.17-.479.424-.523 3.683-.613 5.278-3.927 5.337-4.066.075-.149.121-.3.121-.464 0-.554-.688-.868-1.137-1.008-.136-.045-.256-.091-.346-.135-1.107-.435-1.257-.93-1.197-1.273.09-.479.674-.793 1.168-.793.146 0 .27.029.383.074.42.194.809.3 1.124.3.24 0 .389-.06.479-.105l-.046-.637c-.105-1.637-.231-3.676.312-4.877C7.392 1.077 10.739.807 11.727.807l.419-.015h.06z',
		);

		if ( ! isset( $paths[ $brand ] ) ) {
			return '';
		}

		return '<svg class="icod-social-logo icod-social-' . esc_attr( $brand ) . '" viewBox="0 0 24 24" width="30" height="30" aria-hidden="true" focusable="false"><path d="' . $paths[ $brand ] . '"/></svg>';
	}

	/**
	 * Onglet Tracking : pixels Meta / TikTok / Snapchat + Conversions API.
	 *
	 * @return void
	 */
	private function tab_tracking() {
		$brands = array(
			'facebook' => array(
				'name'    => __( 'Meta — Facebook & Instagram', 'infinitycod' ),
				'placeholder' => '1234567890123456',
				'toggle'  => 'pixel_fb_enabled',
				'id'      => 'pixel_fb_id',
				'idlabel' => __( 'ID du Pixel (15-16 chiffres)', 'infinitycod' ),
			),
			'tiktok'   => array(
				'name'    => __( 'TikTok', 'infinitycod' ),
				'placeholder' => 'CXXXXXXXXXXXXXXXXXXX',
				'toggle'  => 'pixel_tiktok_enabled',
				'id'      => 'pixel_tiktok_id',
				'idlabel' => __( 'ID du Pixel', 'infinitycod' ),
			),
			'snapchat' => array(
				'name'    => __( 'Snapchat', 'infinitycod' ),
				'placeholder' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
				'toggle'  => 'pixel_snap_enabled',
				'id'      => 'pixel_snap_id',
				'idlabel' => __( 'ID du Pixel (UUID)', 'infinitycod' ),
			),
		);
		?>
		<div class="icod-social-grid">
			<?php foreach ( $brands as $key => $brand ) : ?>
				<div class="icod-social-card" data-brand="<?php echo esc_attr( $key ); ?>">
					<div class="icod-social-head">
						<?php echo $this->social_logo( $key ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?>
						<strong><?php echo esc_html( $brand['name'] ); ?></strong>
					</div>
					<label class="icod-toggle">
						<input type="checkbox" name="icod[<?php echo esc_attr( $brand['toggle'] ); ?>]" value="1" <?php checked( (int) Settings::get( $brand['toggle'] ), 1 ); ?> />
						<span><?php esc_html_e( 'Activer ce pixel', 'infinitycod' ); ?></span>
					</label>
					<label class="icod-social-id">
						<span><?php echo esc_html( $brand['idlabel'] ); ?></span>
						<input type="text" name="icod[<?php echo esc_attr( $brand['id'] ); ?>]" dir="ltr" placeholder="<?php echo esc_attr( $brand['placeholder'] ); ?>" value="<?php echo esc_attr( Settings::get( $brand['id'] ) ); ?>" />
					</label>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="description icod-social-note">
			<?php esc_html_e( 'Événements trackés automatiquement : ViewContent (fiche produit) → InitiateCheckout (début du formulaire) → Purchase (commande confirmée, montant réel en DZD). Aucun code à ajouter sur votre site.', 'infinitycod' ); ?>
		</p>

		<div class="icod-card">
			<h2>🛡️ <?php esc_html_e( 'Conversions API Meta — exigences 2026', 'infinitycod' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'La Conversions API (CAPI) envoie la commande directement depuis votre serveur vers Meta : plus fiable que le navigateur (bloqueurs, iOS). InfinityCod applique automatiquement les exigences Meta 2026 : event_id de déduplication navigateur/serveur, téléphone haché en SHA-256 (advanced matching), cookies first-party _fbp/_fbc transférés, action_source « website ». Récupérez votre token dans Events Manager → Paramètres → Conversions API → Générer le token d’accès.', 'infinitycod' ); ?>
			</p>
			<div class="icod-grid">
				<label>
					<span><?php echo $this->social_logo( 'facebook' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?> <?php esc_html_e( 'Token d’accès Conversions API (secret)', 'infinitycod' ); ?></span>
					<input type="password" name="icod[pixel_fb_capi_token]" dir="ltr" autocomplete="new-password" value="<?php echo esc_attr( Settings::get( 'pixel_fb_capi_token' ) ); ?>" class="regular-text" />
				</label>
				<label>
					<span><?php esc_html_e( 'Code d’événements de test (optionnel, Events Manager)', 'infinitycod' ); ?></span>
					<input type="text" name="icod[pixel_fb_test_code]" dir="ltr" placeholder="TEST12345" value="<?php echo esc_attr( Settings::get( 'pixel_fb_test_code', '' ) ); ?>" />
				</label>
			</div>
			<div class="icod-toggles">
				<label class="icod-toggle">
					<input type="checkbox" name="icod[pixel_sitewide]" value="1" <?php checked( (int) Settings::get( 'pixel_sitewide' ), 1 ); ?> />
					<span><?php esc_html_e( 'Charger les pixels sur tout le site (retargeting) — sinon uniquement sur les fiches produit', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[pixel_consent_required]" value="1" <?php checked( (int) Settings::get( 'pixel_consent_required' ), 1 ); ?> />
					<span><?php esc_html_e( 'Consentement requis : ne charger les pixels qu’après window.icodConsent = true ou un cookie icod_consent=1 (posé par votre bandeau cookies)', 'infinitycod' ); ?></span>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Onglet paiement en ligne (Chargily Pay v2 — CIB / Edahabia).
	 *
	 * @return void
	 */
	private function tab_payment() {
		$pay_on = (int) Settings::get( 'payment_enabled' ) ? 'Actif' : 'Inactif';
		?>
		<div class="icod-card">
			<h2><?php esc_html_e( 'Paiement en ligne — Chargily Pay (CIB / Edahabia)', 'infinitycod' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Permet au client de payer immédiatement par carte CIB ou Edahabia via Chargily Pay. Sans cela, le formulaire reste en paiement à la livraison classique. Créez votre compte sur chargily.com, puis copiez votre clé secrète ici.', 'infinitycod' ); ?>
			</p>
			<div class="icod-paystrip">
				<div class="icod-paystrip-item <?php echo (int) Settings::get( 'payment_enabled' ) ? 'icod-paystrip-on' : ''; ?>">
					<?php echo \InfinityCod\Form\FormManager::payment_logo( 'cib' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?>
					<span>CIB <?php echo (int) Settings::get( 'payment_enabled' ) ? '· actif' : '· inactif'; ?></span>
				</div>
				<div class="icod-paystrip-item <?php echo (int) Settings::get( 'payment_enabled' ) ? 'icod-paystrip-on' : ''; ?>">
					<?php echo \InfinityCod\Form\FormManager::payment_logo( 'edahabia' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?>
					<span>Edahabia <?php echo (int) Settings::get( 'payment_enabled' ) ? '· actif' : '· inactif'; ?></span>
				</div>
				<div class="icod-paystrip-item icod-paystrip-on">
					<?php echo \InfinityCod\Form\FormManager::payment_logo( 'cash' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?>
					<span>Espèces à la livraison · toujours actif</span>
				</div>
				<div class="icod-paystrip-item">
					<?php echo \InfinityCod\Form\FormManager::payment_logo( 'baridimob' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?>
					<span>BaridiMob · virement manuel (bientôt)</span>
				</div>
				<div class="icod-paystrip-item">
					<?php echo \InfinityCod\Form\FormManager::payment_logo( 'ccp' ); // phpcs:ignore WordPress.Security.EscapeOutput -- SVG interne. ?>
					<span>CCP · virement manuel (bientôt)</span>
				</div>
			</div>
			<p class="description" style="margin-top:8px"><em><?php esc_html_e( 'Statut actuel :', 'infinitycod' ); ?> <strong><?php echo esc_html( (int) Settings::get( 'payment_enabled' ) ? 'Paiement en ligne activé' : 'COD uniquement' ); ?></strong></em></p>
			<div class="icod-toggles">
				<label class="icod-toggle">
					<input type="checkbox" name="icod[payment_enabled]" value="1" <?php checked( (int) Settings::get( 'payment_enabled' ), 1 ); ?> />
					<span><?php esc_html_e( 'Activer le paiement en ligne dans le formulaire', 'infinitycod' ); ?></span>
				</label>
			</div>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Environnement', 'infinitycod' ); ?></span>
					<select name="icod[chargily_mode]">
						<option value="test" <?php selected( Settings::get( 'chargily_mode' ), 'test' ); ?>><?php esc_html_e( 'Test (clés de test)', 'infinitycod' ); ?></option>
						<option value="live" <?php selected( Settings::get( 'chargily_mode' ), 'live' ); ?>><?php esc_html_e( 'Production (argent réel)', 'infinitycod' ); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e( 'Clé secrète API', 'infinitycod' ); ?></span>
					<input type="password" name="icod[chargily_secret]" value="<?php echo esc_attr( Settings::get( 'chargily_secret' ) ); ?>" dir="ltr" autocomplete="new-password" placeholder="sk_live_… / sk_test_…" />
				</label>
			</div>
			<p class="description" style="margin-top:10px">
				<?php esc_html_e( 'Webhook automatique :', 'infinitycod' ); ?>
				<code><?php echo esc_html( rest_url( 'infinitycod/v1/chargily/webhook' ) ); ?></code>
			</p>
		</div>

		<div class="icod-card">
			<h2><?php esc_html_e( 'Affichage dans le formulaire', 'infinitycod' ); ?></h2>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Libellé — paiement à la livraison', 'infinitycod' ); ?></span>
					<input type="text" name="icod[cod_label]" value="<?php echo esc_attr( Settings::get( 'cod_label' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Libellé — paiement en ligne', 'infinitycod' ); ?></span>
					<input type="text" name="icod[payment_label]" value="<?php echo esc_attr( Settings::get( 'payment_label' ) ); ?>" />
				</label>
			</div>
			<div class="icod-grid icod-grid-full">
				<label>
					<span><?php esc_html_e( 'Message affiché au client après un paiement réussi', 'infinitycod' ); ?></span>
					<textarea name="icod[payment_return_text]" rows="2" class="large-text"><?php echo esc_textarea( Settings::get( 'payment_return_text' ) ); ?></textarea>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Onglet licence : statut détaillé, comparatif Gratuit / Premium,
	 * activation, vérification, désactivation et aide.
	 *
	 * @return void
	 */
	private function tab_license() {
		$license = \InfinityCod\License\LicenseManager::stored();
		$premium = \InfinityCod\License\LicenseManager::is_premium();
		$has_key = ! empty( $license['key_hash'] );
		$msg     = isset( $_GET['icod_msg'] ) ? sanitize_text_field( rawurldecode( wp_unslash( $_GET['icod_msg'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$ok      = isset( $_GET['icod_ok'] ) ? ( '1' === sanitize_text_field( wp_unslash( $_GET['icod_ok'] ) ) ) : $premium; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Détails dérivés : expiration, jours restants, dernière vérification.
		$expires_ts  = ! empty( $license['expires_at'] ) ? strtotime( (string) $license['expires_at'] ) : false;
		$days_left   = ( false !== $expires_ts ) ? (int) ceil( ( $expires_ts - time() ) / DAY_IN_SECONDS ) : null;
		$checked_txt = '';
		if ( ! empty( $license['checked_at'] ) && false !== strtotime( (string) $license['checked_at'] ) ) {
			/* translators: %s : date de dernière vérification. */
			$checked_txt = sprintf( __( 'dernière vérification : %s', 'infinitycod' ), mysql2date( get_option( 'date_format', 'd/m/Y' ), $license['checked_at'] ) );
		}

		// Libellé du statut serveur brut.
		$raw_status   = isset( $license['status'] ) ? (string) $license['status'] : '';
		$status_names = array(
			'ACTIVE'  => __( 'Active', 'infinitycod' ),
			'UNKNOWN' => __( 'En grâce (serveur non joint)', 'infinitycod' ),
			'EXPIRED' => __( 'Expirée', 'infinitycod' ),
			'REVOKED' => __( 'Révoquée', 'infinitycod' ),
			'INVALID' => __( 'Invalide', 'infinitycod' ),
		);

		// Comparatif Gratuit / Premium (true = inclus dans la version gratuite).
		$rows = array(
			array( __( 'Formulaire COD one-page — 8 thèmes, mode sombre, RTL arabe, Elementor', 'infinitycod' ), true ),
			array( __( '58 wilayas & 1541 communes officielles (noms FR + AR)', 'infinitycod' ), true ),
			array( __( 'Tarifs domicile / stopdesk par wilaya et par commune', 'infinitycod' ), true ),
			array( __( 'Livraison gratuite, délais et commande minimale par wilaya', 'infinitycod' ), true ),
			array( __( 'Dashboard commandes : confirmation, statuts, export, blacklist', 'infinitycod' ), true ),
			array( __( 'Anti-fraude Shield : honeypot, empreinte, limites IP, score de risque', 'infinitycod' ), true ),
			array( __( 'Paiement en ligne CIB / Edahabia via Chargily Pay', 'infinitycod' ), true ),
			array( __( 'Mises à jour automatiques depuis GitHub + rollback + diagnostic', 'infinitycod' ), true ),
			array( __( 'WhatsApp automatique : remerciement, confirmation, expédition', 'infinitycod' ), false ),
			array( __( 'Relance automatique des paniers abandonnés', 'infinitycod' ), false ),
			array( __( 'Commande via WhatsApp (le client valide sur WhatsApp)', 'infinitycod' ), false ),
			array( __( 'Transporteurs intégrés : Yalidine, ZR Express, Maystro, Noest, E-COM, DHD', 'infinitycod' ), false ),
			array( __( 'Création de colis et suivi synchronisé (tracking)', 'infinitycod' ), false ),
			array( __( 'Statistiques P&L : CA, taux de confirmation, retours, marge nette', 'infinitycod' ), false ),
			array( __( 'Offres intelligentes par quantité (remises automatiques)', 'infinitycod' ), false ),
		);
		?>
		<?php if ( $msg ) : ?>
			<div class="notice <?php echo $ok ? 'notice-success' : 'notice-error'; ?>"><p><?php echo esc_html( $msg ); ?></p></div>
		<?php endif; ?>
		<?php if ( '1' === ( isset( $_GET['paypal_ok'] ) ? sanitize_text_field( wp_unslash( $_GET['paypal_ok'] ) ) : '' ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success"><p>
				<strong><?php esc_html_e( 'Paiement PayPal envoyé, merci !', 'infinitycod' ); ?></strong>
				<?php esc_html_e( 'Votre clé de licence vous est envoyée par email (référence de votre installation jointe au paiement). ' ); ?>
				<a href="#icod-key-input"><?php esc_html_e( 'Coller ma clé maintenant ↓', 'infinitycod' ); ?></a>
			</p></div>
		<?php endif; ?>

		<div class="icod-card">
			<h2><?php esc_html_e( 'Licence InfinityCod', 'infinitycod' ); ?></h2>

			<div class="icod-lic-hero">
				<div>
					<span class="icod-lic-badge <?php echo $premium ? 'icod-lic-badge-premium' : 'icod-lic-badge-free'; ?>">
						<?php echo $premium ? '★ ' . esc_html( \InfinityCod\License\LicenseManager::status_label() ) : esc_html( \InfinityCod\License\LicenseManager::status_label() ); ?>
					</span>
					<?php if ( ! empty( $license['client'] ) ) : ?>
						<span class="icod-lic-client">— <?php echo esc_html( $license['client'] ); ?></span>
					<?php endif; ?>
				</div>
				<?php if ( $has_key ) : ?>
					<div class="icod-lic-actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="icod_verify_license" />
							<?php wp_nonce_field( 'icod_verify_license' ); ?>
							<button type="submit" class="button"><?php esc_html_e( 'Vérifier maintenant', 'infinitycod' ); ?></button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Désactiver la licence sur ce site ? Les fonctionnalités Premium seront verrouillées. Vos données sont conservées.', 'infinitycod' ) ); ?>');">
							<input type="hidden" name="action" value="icod_deactivate_license" />
							<?php wp_nonce_field( 'icod_deactivate_license' ); ?>
							<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Désactiver la licence', 'infinitycod' ); ?></button>
						</form>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $has_key ) : ?>
				<dl class="icod-lic-details">
					<dt><?php esc_html_e( 'Titulaire', 'infinitycod' ); ?></dt>
					<dd><?php echo esc_html( $license['client'] ? $license['client'] : __( '—', 'infinitycod' ) ); ?></dd>

					<dt><?php esc_html_e( 'Email', 'infinitycod' ); ?></dt>
					<dd><?php echo esc_html( ! empty( $license['email'] ) ? $license['email'] : __( '—', 'infinitycod' ) ); ?></dd>

					<dt><?php esc_html_e( 'Statut serveur', 'infinitycod' ); ?></dt>
					<dd><?php echo esc_html( isset( $status_names[ $raw_status ] ) ? $status_names[ $raw_status ] : ( $raw_status ? $raw_status : __( '—', 'infinitycod' ) ) ); ?></dd>

					<dt><?php esc_html_e( 'Expiration', 'infinitycod' ); ?></dt>
					<dd>
						<?php if ( false === $expires_ts ) : ?>
							<?php esc_html_e( 'Illimitée', 'infinitycod' ); ?>
						<?php else : ?>
							<?php echo esc_html( mysql2date( get_option( 'date_format', 'd/m/Y' ), $license['expires_at'] ) ); ?>
							<?php if ( null !== $days_left ) : ?>
								—
								<?php
								if ( $days_left > 15 ) {
									/* translators: %d : nombre de jours restants. */
									printf( esc_html__( '%d jours restants', 'infinitycod' ), (int) $days_left );
								} elseif ( $days_left > 0 ) {
									/* translators: %d : nombre de jours restants. */
									printf( esc_html__( '⚠ %d jours restants', 'infinitycod' ), (int) $days_left );
								} else {
									esc_html_e( 'expirée', 'infinitycod' );
								}
								?>
							<?php endif; ?>
						<?php endif; ?>
					</dd>

					<dt><?php esc_html_e( 'Vérification', 'infinitycod' ); ?></dt>
					<dd><?php echo esc_html( $checked_txt ? $checked_txt : __( 'jamais vérifiée', 'infinitycod' ) ); ?></dd>

					<dt><?php esc_html_e( 'Installation', 'infinitycod' ); ?></dt>
					<dd><code dir="ltr"><?php echo esc_html( substr( (string) \InfinityCod\License\LicenseManager::install_id(), 0, 10 ) ); ?>…</code></dd>
				</dl>
				<p class="description icod-lic-note">
					<?php esc_html_e( 'Une licence est liée à ce site (une machine = un site). La vérification hebdomadaire est automatique ; en cas de coupure, une période de grâce conserve vos fonctionnalités Premium.', 'infinitycod' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<?php
		// ——— Achat Pro via PayPal (visible sans licence, si configuré) ———
		$paypal_email    = Settings::get( 'paypal_email', '' );
		$paypal_on       = (int) Settings::get( 'paypal_enabled' ) && is_email( $paypal_email );
		$paypal_currency = Settings::get( 'paypal_currency', 'USD' );
		$install_ref     = \InfinityCod\License\LicenseManager::install_id();

		if ( ! $premium && $paypal_on ) :
			$plans = array(
				'personal' => array(
					'name'  => __( 'Personal', 'infinitycod' ),
					'price' => (float) Settings::get( 'paypal_price_personal', 39 ),
					'desc'  => __( '1 site · WhatsApp, transporteurs, P&L, offres', 'infinitycod' ),
				),
				'business' => array(
					'name'  => __( 'Business', 'infinitycod' ),
					'price' => (float) Settings::get( 'paypal_price_business', 79 ),
					'desc'  => __( '3 sites · tout Personal + support prioritaire', 'infinitycod' ),
					'star'  => true,
				),
				'agency'   => array(
					'name'  => __( 'Agency', 'infinitycod' ),
					'price' => (float) Settings::get( 'paypal_price_agency', 149 ),
					'desc'  => __( '10 sites · tout Business + multi-pays', 'infinitycod' ),
				),
			);
			$return_url = admin_url( 'admin.php?page=infinitycod-settings&tab=license&paypal_ok=1' );
			?>
			<div class="icod-card">
				<h2>💳 <?php esc_html_e( 'Passer à InfinityCod Pro — paiement PayPal sécurisé', 'infinitycod' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Payez par PayPal, recevez votre clé par email, collez-la juste en dessous : Pro est actif immédiatement, sans réinstallation.', 'infinitycod' ); ?></p>
				<ol class="icod-steps-mini">
					<li><?php esc_html_e( 'Choisissez votre offre', 'infinitycod' ); ?></li>
					<li><?php esc_html_e( 'Payez sur PayPal', 'infinitycod' ); ?></li>
					<li><?php esc_html_e( 'Collez votre clé reçue par email', 'infinitycod' ); ?></li>
				</ol>
				<div class="icod-pricing">
					<?php foreach ( $plans as $plan_key => $plan ) : ?>
						<div class="icod-pricing-card<?php echo ! empty( $plan['star'] ) ? ' icod-pricing-star' : ''; ?>">
							<?php if ( ! empty( $plan['star'] ) ) : ?>
								<span class="icod-pricing-badge"><?php esc_html_e( 'Recommandé', 'infinitycod' ); ?></span>
							<?php endif; ?>
							<h3><?php echo esc_html( $plan['name'] ); ?></h3>
							<p class="icod-pricing-price">
								<?php echo esc_html( number_format_i18n( $plan['price'], 2 ) ); ?>
								<span><?php echo esc_html( $paypal_currency ); ?></span>
							</p>
							<p class="icod-pricing-desc"><?php echo esc_html( $plan['desc'] ); ?></p>
							<form method="post" action="<?php echo esc_url( 'https://www.paypal.com/cgi-bin/webscr' ); ?>" target="_blank" rel="noopener">
								<input type="hidden" name="cmd" value="_xclick" />
								<input type="hidden" name="business" value="<?php echo esc_attr( $paypal_email ); ?>" />
								<input type="hidden" name="item_name" value="<?php echo esc_attr( 'InfinityCod Pro — ' . $plan['name'] . ' (site ' . $install_ref . ')' ); ?>" />
								<input type="hidden" name="custom" value="<?php echo esc_attr( $install_ref ); ?>" />
								<input type="hidden" name="amount" value="<?php echo esc_attr( number_format( $plan['price'], 2, '.', '' ) ); ?>" />
								<input type="hidden" name="currency_code" value="<?php echo esc_attr( $paypal_currency ); ?>" />
								<input type="hidden" name="no_shipping" value="1" />
								<input type="hidden" name="charset" value="utf-8" />
								<input type="hidden" name="return" value="<?php echo esc_attr( $return_url ); ?>" />
								<input type="hidden" name="cancel_return" value="<?php echo esc_attr( $return_url ); ?>" />
								<button type="submit" class="button button-primary button-hero">
									<?php esc_html_e( 'Payer avec', 'infinitycod' ); ?>
									<span class="icod-paypal-word" dir="ltr"><span class="icod-paypal-pay">Pay</span><span class="icod-paypal-pal">Pal</span></span>
								</button>
							</form>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="icod-card">
			<h2><?php $premium ? esc_html_e( 'Changer de clé', 'infinitycod' ) : esc_html_e( 'Activer votre licence Premium', 'infinitycod' ); ?></h2>
			<?php if ( ! $premium ) : ?>
				<p class="description">
					<?php esc_html_e( 'Collez la clé reçue après votre achat : WhatsApp automatique, transporteurs, statistiques P&L et offres se débloquent immédiatement — sans réinstaller quoi que ce soit.', 'infinitycod' ); ?>
				</p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="icod-key-form">
				<input type="hidden" name="action" value="icod_activate_license" />
				<?php wp_nonce_field( 'icod_activate_license' ); ?>
				<div class="icod-key-row">
					<input type="text" id="icod-key-input" name="icod_license_key" class="regular-text" dir="ltr" placeholder="INFINITY-XXXX-XXXX-XXXX" />
					<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Activer la licence', 'infinitycod' ); ?></button>
				</div>
				<p class="description">
					<a href="<?php echo esc_url( 'https://infinitycoder.app/infinitycod' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Ou acheter une licence sur infinitycoder.app ↗', 'infinitycod' ); ?></a>
				</p>
			</form>
		</div>

		<div class="icod-card">
			<h2><?php esc_html_e( 'Ce que débloque la licence Premium', 'infinitycod' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'La version gratuite couvre déjà tout le nécessaire pour vendre en paiement à la livraison. La licence Premium ajoute l’automatisation (WhatsApp, transporteurs, relances) et le pilotage (statistiques P&L, offres).', 'infinitycod' ); ?>
			</p>
			<table class="icod-lic-compare">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Fonctionnalité', 'infinitycod' ); ?></th>
						<th class="icod-lic-c"><?php esc_html_e( 'Gratuit', 'infinitycod' ); ?></th>
						<th class="icod-lic-c"><?php esc_html_e( 'Premium', 'infinitycod' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row[0] ); ?></td>
							<?php if ( $row[1] ) : ?>
								<td class="icod-lic-c icod-lic-yes">✓</td>
								<td class="icod-lic-c icod-lic-yes">✓</td>
							<?php else : ?>
								<td class="icod-lic-c icod-lic-no">—</td>
								<td class="icod-lic-c icod-lic-yes">✓</td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<div class="icod-card">
				<h2>⚙️ <?php esc_html_e( 'Vente Pro via PayPal — configuration vendeur', 'infinitycod' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Renseignez votre email PayPal et vos tarifs : les cartes d‘achat apparaissent automatiquement pour vos clients sans licence. La référence unique de leur installation est jointe au paiement pour identifier l‘acheteur.', 'infinitycod' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="icod_save_paypal" />
					<?php wp_nonce_field( 'icod_save_paypal' ); ?>
					<div class="icod-toggles">
						<label class="icod-toggle">
							<input type="checkbox" name="icod[paypal_enabled]" value="1" <?php checked( (int) Settings::get( 'paypal_enabled' ), 1 ); ?> />
							<span><?php esc_html_e( 'Afficher les cartes d‘achat PayPal aux clients sans licence', 'infinitycod' ); ?></span>
						</label>
					</div>
					<div class="icod-grid">
						<label>
							<span><?php esc_html_e( 'Email PayPal du vendeur', 'infinitycod' ); ?></span>
							<input type="email" name="icod[paypal_email]" dir="ltr" value="<?php echo esc_attr( $paypal_email ); ?>" class="regular-text" />
						</label>
						<label>
							<span><?php esc_html_e( 'Devise de vente', 'infinitycod' ); ?></span>
							<select name="icod[paypal_currency]">
								<option value="USD" <?php selected( $paypal_currency, 'USD' ); ?>><?php esc_html_e( 'USD — Dollar', 'infinitycod' ); ?></option>
								<option value="EUR" <?php selected( $paypal_currency, 'EUR' ); ?>><?php esc_html_e( 'EUR — Euro', 'infinitycod' ); ?></option>
							</select>
						</label>
						<label>
							<span><?php esc_html_e( 'Prix Personal', 'infinitycod' ); ?></span>
							<input type="text" name="icod[paypal_price_personal]" dir="ltr" value="<?php echo esc_attr( Settings::get( 'paypal_price_personal', 39 ) ); ?>" />
						</label>
						<label>
							<span><?php esc_html_e( 'Prix Business', 'infinitycod' ); ?></span>
							<input type="text" name="icod[paypal_price_business]" dir="ltr" value="<?php echo esc_attr( Settings::get( 'paypal_price_business', 79 ) ); ?>" />
						</label>
						<label>
							<span><?php esc_html_e( 'Prix Agency', 'infinitycod' ); ?></span>
							<input type="text" name="icod[paypal_price_agency]" dir="ltr" value="<?php echo esc_attr( Settings::get( 'paypal_price_agency', 149 ) ); ?>" />
						</label>
					</div>
					<p class="icod-submit">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Enregistrer la configuration PayPal', 'infinitycod' ); ?></button>
					</p>
				</form>
			</div>
		<?php endif; ?>

		<div class="icod-card icod-lic-faq">
			<h2><?php esc_html_e( 'Questions fréquentes', 'infinitycod' ); ?></h2>
			<details>
				<summary><?php esc_html_e( 'Où obtenir ma clé de licence ?', 'infinitycod' ); ?></summary>
				<p><?php esc_html_e( 'Après votre achat sur infinitycoder.app, la clé vous est envoyée par email et WhatsApp. Elle ressemble à INFINITY-XXXX-XXXX-XXXX.', 'infinitycod' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'Puis-je utiliser ma licence sur plusieurs sites ?', 'infinitycod' ); ?></summary>
				<p><?php esc_html_e( 'Une licence couvre un site (une installation). Pour transférer la licence vers un nouveau site, désactivez-la ici puis activez-la sur l’autre, ou contactez le support.', 'infinitycod' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'Que se passe-t-il si le serveur de licences est injoignable ?', 'infinitycod' ); ?></summary>
				<p><?php esc_html_e( 'Rien ne se coupe : votre statut local est conservé en période de grâce. La vérification est retentée automatiquement chaque semaine, ou via le bouton « Vérifier maintenant ».', 'infinitycod' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'Que devient ma boutique si la licence expire ou est désactivée ?', 'infinitycod' ); ?></summary>
				<p><?php esc_html_e( 'Le plugin repasse en version gratuite : le formulaire, les tarifs, l’anti-fraude et le dashboard continuent de fonctionner. Seules les fonctions Premium se verrouillent. Toutes vos données restent sur votre site, rien n’est supprimé.', 'infinitycod' ); ?></p>
			</details>
			<details>
				<summary><?php esc_html_e( 'Mes données sont-elles envoyées quelque part ?', 'infinitycod' ); ?></summary>
				<p><?php esc_html_e( 'Non. Commandes, clients et statistiques restent 100 % dans votre WordPress. Seule la licence échange avec le serveur (empreinte du site, statut) — jamais vos données commerciales.', 'infinitycod' ); ?></p>
			</details>
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

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-settings&tab=license&icod_msg=' . rawurlencode( $result['message'] ) . '&icod_ok=' . ( $result['ok'] ? '1' : '0' ) ) );
		exit;
	}

	/**
	 * Vérification manuelle de la licence (bouton « Vérifier maintenant »).
	 *
	 * @return void
	 */
	public function handle_license_verify() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_verify_license' );

		$manager = new \InfinityCod\License\LicenseManager();
		$result  = $manager->manual_check();

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-settings&tab=license&icod_msg=' . rawurlencode( $result['message'] ) . '&icod_ok=' . ( $result['ok'] ? '1' : '0' ) ) );
		exit;
	}

	/**
	 * Désactivation de la licence sur ce site.
	 *
	 * @return void
	 */
	public function handle_license_deactivate() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_deactivate_license' );

		$manager = new \InfinityCod\License\LicenseManager();
		$result  = $manager->deactivate();

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-settings&tab=license&icod_msg=' . rawurlencode( $result['message'] ) . '&icod_ok=' . ( $result['ok'] ? '1' : '0' ) ) );
		exit;
	}

	/**
	 * Onglet formulaire.
	 *
	 * @return void
	 */
	private function tab_form() {
		$presets = array(
			'modern'  => array( 'label' => __( 'Moderne', 'infinitycod' ), 'color' => '#0e7a4f' ),
			'elegant' => array( 'label' => __( 'Élégant', 'infinitycod' ), 'color' => '#1d3557' ),
			'sunset'  => array( 'label' => __( 'Sunset', 'infinitycod' ), 'color' => '#e8590c' ),
			'ocean'   => array( 'label' => __( 'Océan', 'infinitycod' ), 'color' => '#1971c2' ),
			'minimal' => array( 'label' => __( 'Minimal', 'infinitycod' ), 'color' => '#1a1d21' ),
			'rose'   => array( 'label' => __( 'Rose', 'infinitycod' ), 'color' => '#d6336c' ),
			'royal'  => array( 'label' => __( 'Royal', 'infinitycod' ), 'color' => '#6d28d9' ),
			'cafe'   => array( 'label' => __( 'Café', 'infinitycod' ), 'color' => '#7c4a21' ),
			'aqua'   => array( 'label' => __( 'Aqua', 'infinitycod' ), 'color' => '#0891b2' ),
				'pro'    => array( 'label' => __( 'Pro (Yaxii+)', 'infinitycod' ), 'color' => '#7c3aed' ),
		);
		?>
		<div class="icod-card">
		<div class="icod-card">
			<h2>🧱 <?php esc_html_e( 'Checkout Builder — champs du formulaire', 'infinitycod' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Activez, ordonnez (▲▼), rendez obligatoire et renommez chaque champ. Ajoutez vos propres champs personnalisés.', 'infinitycod' ); ?></p>
			<?php
			$fields = (array) Settings::get( 'checkout_fields', array() );
			$move = isset( $_GET['cfmove'] ) ? sanitize_text_field( wp_unslash( $_GET['cfmove'] ) ) : '';
			if ( $move && strpos( $move, ':' ) !== false && current_user_can( 'manage_woocommerce' ) ) {
				$parts = explode( ':', $move );
				$i = absint( $parts[1] );
				$dir = ( $parts[0] === 'up' ) ? -1 : 1;
				if ( isset( $fields[ $i ] ) && isset( $fields[ $i + $dir ] ) ) {
					$tmp = $fields[ $i ];
					$fields[ $i ] = $fields[ $i + $dir ];
					$fields[ $i + $dir ] = $tmp;
					Settings::set( 'checkout_fields', array_values( $fields ) );
					$fields = array_values( Settings::get( 'checkout_fields', array() ) );
				}
			}
			foreach ( $fields as $i => $fld ) : ?>
			<div style="display:flex;gap:8px;align-items:center;border-bottom:1px solid #f0f0f1;padding:8px 0;flex-wrap:wrap">
				<a href="?page=infinitycod-settings&tab=form&cfmove=up:<?php echo (int) $i; ?>" class="button" style="padding:2px 8px">▲</a>
				<a href="?page=infinitycod-settings&tab=form&cfmove=down:<?php echo (int) $i; ?>" class="button" style="padding:2px 8px">▼</a>
				<code dir="ltr" style="width:110px"><?php echo esc_html( $fld['key'] ); ?></code>
				<select name="icod[checkout_fields][<?php echo (int) $i; ?>][type]">
				<?php foreach ( array( 'text', 'tel', 'email', 'select', 'radio', 'checkbox', 'textarea', 'date', 'number' ) as $t ) : ?>
				<option <?php selected( $fld['type'], $t ); ?>><?php echo esc_html( $t ); ?></option>
				<?php endforeach; ?></select>
				<input type="text" name="icod[checkout_fields][<?php echo (int) $i; ?>][label]" placeholder="Label personnalisé…" value="<?php echo esc_attr( $fld['label'] ); ?>" style="flex:1;min-width:150px" />
				<label style="white-space:nowrap"><input type="checkbox" name="icod[checkout_fields][<?php echo (int) $i; ?>][on]" value="1" <?php checked( ! empty( $fld['on'] ) ); ?> /> Actif</label>
				<label style="white-space:nowrap"><input type="checkbox" name="icod[checkout_fields][<?php echo (int) $i; ?>][req]" value="1" <?php checked( ! empty( $fld['req'] ) ); ?> /> Obligatoire</label>
				<input type="hidden" name="icod[checkout_fields][<?php echo (int) $i; ?>][key]" value="<?php echo esc_attr( $fld['key'] ); ?>" />
				<input type="hidden" name="icod[checkout_fields][<?php echo (int) $i; ?>][order]" value="<?php echo (int) $i; ?>" />
			</div>
			<?php endforeach; ?>
			<div style="display:flex;gap:8px;align-items:center;border-top:2px solid #f0f0f1;padding-top:10px;margin-top:6px;flex-wrap:wrap">
				<strong style="font-size:12px"><?php esc_html_e( 'Ajouter un champ personnalisé :', 'infinitycod' ); ?></strong>
				<input type="text" name="icod[checkout_fields_new][key]" placeholder="clé (ex: ville2)" dir="ltr" style="width:120px" />
				<select name="icod[checkout_fields_new][type]"><option>text</option><option>tel</option><option>email</option><option>select</option><option>checkbox</option><option>textarea</option><option>date</option><option>number</option></select>
				<input type="text" name="icod[checkout_fields_new][label]" placeholder="Label" style="width:150px" />
			</div>
			<p class="description"><?php esc_html_e( 'Les nouveaux champs sont ajoutés en fin de liste après enregistrement.', 'infinitycod' ); ?></p>
		</div>

			<h2><?php esc_html_e( 'Thème du formulaire', 'infinitycod' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Choisissez un thème visuel — la couleur d‘accent ci-dessous le personnalise encore.', 'infinitycod' ); ?></p>
			<div class="icod-preset-grid">
				<?php foreach ( $presets as $preset_key => $preset ) : ?>
					<label class="icod-preset <?php checked( Settings::get( 'form_preset', 'modern' ), $preset_key ); ?>">
						<input type="radio" name="icod[form_preset]" value="<?php echo esc_attr( $preset_key ); ?>" data-accent="<?php echo esc_attr( $preset['color'] ); ?>" <?php checked( Settings::get( 'form_preset', 'modern' ), $preset_key ); ?> />
						<span class="icod-preset-swatch" style="background:<?php echo esc_attr( $preset['color'] ); ?>;border-radius:<?php echo 'elegant' === $preset_key ? '4px' : ( 'sunset' === $preset_key ? '14px' : ( 'minimal' === $preset_key ? '3px' : '10px' ) ); ?>"></span>
						<span class="icod-preset-label"><?php echo esc_html( $preset['label'] ); ?></span>
					</label>
				<?php endforeach; ?>
			</div>

			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Titre du formulaire', 'infinitycod' ); ?></span>
					<input type="text" name="icod[form_title]" value="<?php echo esc_attr( Settings::get( 'form_title' ) ); ?>" class="regular-text" />
				</label>
				<label>
					<span><?php esc_html_e( 'Icône de l’en-tête (emoji)', 'infinitycod' ); ?></span>
					<input type="text" name="icod[form_icon]" value="<?php echo esc_attr( Settings::get( 'form_icon' ) ); ?>" maxlength="8" />
				</label>
				<label>
					<span><?php esc_html_e( 'Sous-titre (facultatif)', 'infinitycod' ); ?></span>
					<input type="text" name="icod[form_subtitle]" value="<?php echo esc_attr( Settings::get( 'form_subtitle' ) ); ?>" class="regular-text" />
				</label>
				<label>
					<span><?php esc_html_e( 'Texte du bouton', 'infinitycod' ); ?></span>
					<input type="text" name="icod[button_text]" value="<?php echo esc_attr( Settings::get( 'button_text' ) ); ?>" class="regular-text" />
				</label>
				<label>
					<span><?php esc_html_e( 'Indication du champ téléphone', 'infinitycod' ); ?></span>
					<input type="text" name="icod[phone_placeholder]" value="<?php echo esc_attr( Settings::get( 'phone_placeholder' ) ); ?>" dir="ltr" />
				</label>
				<label>
					<span><?php esc_html_e( 'Largeur du formulaire (px)', 'infinitycod' ); ?></span>
					<input type="number" min="400" max="900" step="20" name="icod[form_max_width]" value="<?php echo esc_attr( (int) Settings::get( 'form_max_width', 680 ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Couleur d‘accent', 'infinitycod' ); ?></span>
					<input type="color" name="icod[accent_color]" value="<?php echo esc_attr( Settings::get( 'accent_color' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Mode sombre', 'infinitycod' ); ?></span>
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
			<label>
				<span><?php esc_html_e( 'Position du formulaire sur la fiche produit', 'infinitycod' ); ?></span>
				<select name="icod[form_position]">
					<option value="before_summary" <?php selected( Settings::get( 'form_position' ), 'before_summary' ); ?>><?php esc_html_e( 'Avant le résumé produit', 'infinitycod' ); ?></option>
					<option value="after_price" <?php selected( Settings::get( 'form_position' ), 'after_price' ); ?>><?php esc_html_e( 'Après le prix', 'infinitycod' ); ?></option>
					<option value="after_excerpt" <?php selected( Settings::get( 'form_position' ), 'after_excerpt' ); ?>><?php esc_html_e( 'Après la description courte', 'infinitycod' ); ?></option>
					<option value="before_cart" <?php selected( Settings::get( 'form_position' ), 'before_cart' ); ?>><?php esc_html_e( 'Avant le bouton Ajouter au panier', 'infinitycod' ); ?></option>
					<option value="after_cart" <?php selected( Settings::get( 'form_position' ), 'after_cart' ); ?>><?php esc_html_e( 'Après le bouton Ajouter au panier', 'infinitycod' ); ?></option>
					<option value="after_summary" <?php selected( Settings::get( 'form_position' ), 'after_summary' ); ?>><?php esc_html_e( 'Après le résumé produit (défaut)', 'infinitycod' ); ?></option>
					<option value="end_product" <?php selected( Settings::get( 'form_position' ), 'end_product' ); ?>><?php esc_html_e( 'Fin de la fiche produit', 'infinitycod' ); ?></option>
				</select>
			</label>
			<div class="icod-toggles">
				<label class="icod-toggle">
					<input type="checkbox" name="icod[show_email]" value="1" <?php checked( (int) Settings::get( 'show_email' ), 1 ); ?> />
					<span><?php esc_html_e( 'Afficher un champ email (facultatif, sert aux restrictions anti-abus)', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[show_qty_selector]" value="1" <?php checked( (int) Settings::get( 'show_qty_selector' ), 1 ); ?> />
					<span><?php esc_html_e( 'Afficher le sélecteur de quantité', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[show_stopdesk]" value="1" <?php checked( (int) Settings::get( 'show_stopdesk' ), 1 ); ?> />
					<span><?php esc_html_e( 'Proposer la livraison au bureau (Stopdesk)', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[show_note]" value="1" <?php checked( (int) Settings::get( 'show_note' ), 1 ); ?> />
					<span><?php esc_html_e( 'Champ « Note » libre pour le client', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle<?php echo \InfinityCod\License\LicenseManager::is_premium() ? '' : ' icod-premium-locked icod-premium-item'; ?>">
					<input type="checkbox" name="icod[show_offers]" value="1" <?php checked( (int) Settings::get( 'show_offers' ), 1 ); ?> <?php disabled( ! \InfinityCod\License\LicenseManager::is_premium() ); ?> />
					<span><?php esc_html_e( 'Afficher les paliers d‘offres par quantité', 'infinitycod' ); ?> <span class="icod-premium-mini">★ Premium</span></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[show_reassurance]" value="1" <?php checked( (int) Settings::get( 'show_reassurance' ), 1 ); ?> />
					<span><?php esc_html_e( 'Bandeau de réassurance (COD, 58 wilayas, vérification colis)', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[sticky_bar]" value="1" <?php checked( (int) Settings::get( 'sticky_bar' ), 1 ); ?> />
					<span><?php esc_html_e( 'Barre « Commander maintenant » collante sur mobile (récapitulatif + total + bouton toujours visibles)', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[captcha_enabled]" value="1" <?php checked( (int) Settings::get( 'captcha_enabled' ), 1 ); ?> />
					<span><?php esc_html_e( 'Activer le captcha mathématique anti-bot (ex: 5+6=?)', 'infinitycod' ); ?></span>
				</label>
			</div>
		</div>

			<div class="icod-card">
				<h2><?php esc_html_e( 'Écran de remerciement', 'infinitycod' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Le style de la fenêtre de remerciement affichée après une commande réussie — chaque style présente le récapitulatif détaillé (n°, produit, livraison, total).', 'infinitycod' ); ?></p>
				<div class="icod-preset-grid">
					<?php
					$success_styles = array(
						'classic'     => array( 'label' => __( 'Classique', 'infinitycod' ), 'icon' => '✓' ),
						'confetti'    => array( 'label' => __( 'Confettis', 'infinitycod' ), 'icon' => '🎉' ),
						'minimal'     => array( 'label' => __( 'Minimal', 'infinitycod' ), 'icon' => '◦' ),
						'ticket'      => array( 'label' => __( 'Ticket', 'infinitycod' ), 'icon' => '🎟️' ),
						'celebration' => array( 'label' => __( 'Célébration', 'infinitycod' ), 'icon' => '🥳' ),
					);
					foreach ( $success_styles as $style_key => $style ) :
						?>
						<label class="icod-preset">
							<input type="radio" name="icod[success_style]" value="<?php echo esc_attr( $style_key ); ?>" <?php checked( Settings::get( 'success_style', 'classic' ), $style_key ); ?> />
							<span class="icod-preset-swatch" style="background:linear-gradient(135deg,#1d5fa8,#0e7a4f);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px"><?php echo esc_html( $style['icon'] ); ?></span>
							<span class="icod-preset-label"><?php echo esc_html( $style['label'] ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="icod-card">
				<h2><?php esc_html_e( 'Libellés des champs', 'infinitycod' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Personnalisez le texte affiché devant chaque champ (utile en arabe ou pour votre ton de marque).', 'infinitycod' ); ?></p>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Champ nom', 'infinitycod' ); ?></span>
					<input type="text" name="icod[label_name]" value="<?php echo esc_attr( Settings::get( 'label_name' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Champ téléphone', 'infinitycod' ); ?></span>
					<input type="text" name="icod[label_phone]" value="<?php echo esc_attr( Settings::get( 'label_phone' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Champ wilaya', 'infinitycod' ); ?></span>
					<input type="text" name="icod[label_wilaya]" value="<?php echo esc_attr( Settings::get( 'label_wilaya' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Champ commune', 'infinitycod' ); ?></span>
					<input type="text" name="icod[label_commune]" value="<?php echo esc_attr( Settings::get( 'label_commune' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Champ email', 'infinitycod' ); ?></span>
					<input type="text" name="icod[label_email]" value="<?php echo esc_attr( Settings::get( 'label_email' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Champ note', 'infinitycod' ); ?></span>
					<input type="text" name="icod[label_note]" value="<?php echo esc_attr( Settings::get( 'label_note' ) ); ?>" />
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Onglet commande : remerciement, redirection, upsell.
	 *
	 * @return void
	 */
	private function tab_order() {
		?>
		<div class="icod-card">
			<h2><?php esc_html_e( 'Message de remerciement', 'infinitycod' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Affiché après une commande réussie. Variable disponible : {num} (numéro de commande).', 'infinitycod' ); ?></p>
			<div class="icod-grid icod-grid-full">
				<label>
					<span><?php esc_html_e( 'Titre', 'infinitycod' ); ?></span>
					<input type="text" name="icod[success_title]" value="<?php echo esc_attr( Settings::get( 'success_title' ) ); ?>" class="regular-text" />
				</label>
				<label>
					<span><?php esc_html_e( 'Texte', 'infinitycod' ); ?></span>
					<textarea name="icod[success_text]" rows="2" class="large-text"><?php echo esc_textarea( Settings::get( 'success_text' ) ); ?></textarea>
				</label>
			</div>
		</div>

		<div class="icod-card">
			<h2><?php esc_html_e( 'Redirection après la commande', 'infinitycod' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Exemples : page de remerciement, page Facebook, autre produit… Pendant le délai, le client voit le message de remerciement et les upsells éventuels.', 'infinitycod' ); ?></p>
			<div class="icod-toggles">
				<label class="icod-toggle">
					<input type="checkbox" name="icod[redirect_enabled]" value="1" <?php checked( (int) Settings::get( 'redirect_enabled' ), 1 ); ?> />
					<span><?php esc_html_e( 'Rediriger le client après sa commande', 'infinitycod' ); ?></span>
				</label>
			</div>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Url de redirection', 'infinitycod' ); ?></span>
					<input type="url" name="icod[redirect_url]" value="<?php echo esc_attr( Settings::get( 'redirect_url' ) ); ?>" dir="ltr" placeholder="https://…" />
				</label>
				<label>
					<span><?php esc_html_e( 'Délai avant redirection (secondes)', 'infinitycod' ); ?></span>
					<input type="number" min="3" max="60" name="icod[redirect_delay]" value="<?php echo esc_attr( (int) Settings::get( 'redirect_delay', 8 ) ); ?>" />
				</label>
			</div>
		</div>

		<?php $this->premium_gate_open(); ?>
		<div class="icod-card">
			<h2><?php esc_html_e( 'Upsell — produits suggérés', 'infinitycod' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Jusqu‘à 3 produits proposés sur l‘écran de succès (photo, prix, bouton Commander). Le client les commande via leur fiche produit.', 'infinitycod' ); ?></p>
			<div class="icod-toggles">
				<label class="icod-toggle">
					<input type="checkbox" name="icod[upsell_enabled]" value="1" <?php checked( (int) Settings::get( 'upsell_enabled' ), 1 ); ?> />
					<span><?php esc_html_e( 'Afficher des produits suggérés après la commande', 'infinitycod' ); ?></span>
				</label>
			</div>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Titre de la section', 'infinitycod' ); ?></span>
					<input type="text" name="icod[upsell_title]" value="<?php echo esc_attr( Settings::get( 'upsell_title' ) ); ?>" class="regular-text" />
				</label>
				<?php
				$product_choices = array();
				if ( function_exists( 'wc_get_products' ) ) {
					foreach ( (array) wc_get_products( array( 'limit' => 300, 'status' => 'publish', 'orderby' => 'title', 'order' => 'ASC', 'return' => 'objects' ) ) as $p ) {
						$product_choices[ $p->get_id() ] = $p->get_name();
					}
				}
				$selected_ids = array_pad( array_slice( array_filter( array_map( 'absint', (array) Settings::get( 'upsell_ids', array() ) ) ), 0, 3 ), 3, 0 );
				foreach ( $selected_ids as $slot => $pid ) :
					?>
					<label>
						<span><?php printf( esc_html__( 'Produit suggéré %d', 'infinitycod' ), $slot + 1 ); ?></span>
						<select name="icod[upsell_ids][]">
							<option value="0"><?php esc_html_e( '— Aucun —', 'infinitycod' ); ?></option>
							<?php foreach ( $product_choices as $cid => $cname ) : ?>
								<option value="<?php echo esc_attr( $cid ); ?>" <?php selected( $pid, $cid ); ?>><?php echo esc_html( $cname ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<?php
				endforeach;
				?>
				</div>
			</div>
			<?php $this->premium_gate_close(); ?>
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
			<h2><?php esc_html_e( 'Restrictions de commande', 'infinitycod' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Limitez les abus : horaires d\'ouverture, volume par IP, téléphone et email sur 24 h.', 'infinitycod' ); ?></p>
			<div class="icod-toggles">
				<label class="icod-toggle">
					<input type="checkbox" name="icod[restrict_hours_enabled]" value="1" <?php checked( (int) Settings::get( 'restrict_hours_enabled' ), 1 ); ?> />
					<span><?php esc_html_e( 'Limiter les commandes à certaines heures', 'infinitycod' ); ?></span>
				</label>
			</div>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Heure d\'ouverture', 'infinitycod' ); ?></span>
					<input type="number" min="0" max="23" name="icod[restrict_hours_from]" value="<?php echo esc_attr( Settings::get( 'restrict_hours_from' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Heure de fermeture', 'infinitycod' ); ?></span>
					<input type="number" min="0" max="23" name="icod[restrict_hours_to]" value="<?php echo esc_attr( Settings::get( 'restrict_hours_to' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Commandes max / IP / 24 h (0 = illimité)', 'infinitycod' ); ?></span>
					<input type="number" min="0" max="100" name="icod[max_per_ip_day]" value="<?php echo esc_attr( Settings::get( 'max_per_ip_day' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Commandes max / téléphone / 24 h', 'infinitycod' ); ?></span>
					<input type="number" min="0" max="20" name="icod[max_per_phone_day]" value="<?php echo esc_attr( Settings::get( 'max_per_phone_day' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Commandes max / email / 24 h', 'infinitycod' ); ?></span>
					<input type="number" min="0" max="20" name="icod[max_per_email_day]" value="<?php echo esc_attr( Settings::get( 'max_per_email_day' ) ); ?>" />
				</label>
			</div>
		</div>

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
				<label class="icod-toggle">
					<input type="checkbox" name="icod[block_disposable_email]" value="1" <?php checked( (int) Settings::get( 'block_disposable_email' ), 1 ); ?> />
					<span><?php esc_html_e( 'Rejeter les emails jetables (yopmail, tempmail, mailinator…)', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[max_orders_hour_global]" value="1" <?php checked( (int) Settings::get( 'max_orders_hour_global' ) > 0 ); ?> />
					<span><?php esc_html_e( 'Limiter le nombre total de commandes par heure (toutes IP confondues)', 'infinitycod' ); ?></span>
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
		$this->premium_gate_open();
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
				<label class="icod-toggle">
					<input type="checkbox" name="icod[wa_on_confirm]" value="1" <?php checked( (int) Settings::get( 'wa_on_confirm' ), 1 ); ?> />
					<span><?php esc_html_e( 'Notifier le client par WhatsApp à la confirmation de la commande', 'infinitycod' ); ?></span>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[email_on_confirm]" value="1" <?php checked( (int) Settings::get( 'email_on_confirm' ), 1 ); ?> />
					<span><?php esc_html_e( 'Envoyer le résumé de la commande par email à la confirmation', 'infinitycod' ); ?></span>
				</label>
				</label>
				</label>
			</div>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Passerelle', 'infinitycod' ); ?></span>
					<select name="icod[whatsapp_gateway]">
						<option value="wame" <?php selected( Settings::get( 'whatsapp_gateway' ), 'wame' ); ?>><?php esc_html_e( 'Liens wa.me (ouverture manuelle, gratuit)', 'infinitycod' ); ?></option>
						<option value="cloud" <?php selected( Settings::get( 'whatsapp_gateway' ), 'cloud' ); ?>><?php esc_html_e( 'WhatsApp Cloud API (officiel Meta)', 'infinitycod' ); ?></option>
						<option value="ultramsg" <?php selected( Settings::get( 'whatsapp_gateway' ), 'ultramsg' ); ?>>UltraMsg</option>
						<option value="textmebot" <?php selected( Settings::get( 'whatsapp_gateway' ), 'textmebot' ); ?>>TextMeBot API</option>
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
				<label>
					<span><?php esc_html_e( 'TextMeBot — Clé API (textmebot.com)', 'infinitycod' ); ?></span>
					<input type="password" name="icod[wa_textmebot_key]" value="<?php echo esc_attr( Settings::get( 'wa_textmebot_key' ) ); ?>" class="regular-text" autocomplete="new-password" />
				</label>
			</div>
		</div>

		<div class="icod-card">
			<h2><?php esc_html_e( 'Commande via WhatsApp', 'infinitycod' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Ajoute un bouton « Commander via WhatsApp » : la commande est créée normalement puis WhatsApp s‘ouvre avec le résumé pré-rempli, envoyé à votre numéro marchand ci-dessus.', 'infinitycod' ); ?></p>
			<div class="icod-toggles">
				<label class="icod-toggle">
					<input type="checkbox" name="icod[wa_order_enabled]" value="1" <?php checked( (int) Settings::get( 'wa_order_enabled' ), 1 ); ?> />
					<span><?php esc_html_e( 'Activer le bouton « Commander via WhatsApp »', 'infinitycod' ); ?></span>
				</label>
			</div>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Libellé du bouton', 'infinitycod' ); ?></span>
					<input type="text" name="icod[wa_order_label]" value="<?php echo esc_attr( Settings::get( 'wa_order_label' ) ); ?>" class="regular-text" />
				</label>
			</div>
			<div class="icod-grid icod-grid-full">
				<label>
					<span><?php esc_html_e( 'Message pré-rempli (variables : {num}, {nom}, {telephone}, {produit}, {total}, {wilaya}, {commune})', 'infinitycod' ); ?></span>
					<textarea name="icod[msg_wa_order]" rows="3" class="large-text"><?php echo esc_textarea( Settings::get( 'msg_wa_order' ) ); ?></textarea>
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
		$this->premium_gate_close();
	}

	/**
	 * Onglet avancé.
	 *
	 * @return void
	 */
	private function tab_advanced() {
		?>
		<div class="icod-card">
			<h2><?php esc_html_e( 'Devise', 'infinitycod' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Appliquée partout : formulaire, récapitulatif, commandes WooCommerce, pixels et tableaux de bord. Marché arabe : DZD, MAD, TND, EGP, SAR, AED…', 'infinitycod' ); ?></p>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Devise', 'infinitycod' ); ?></span>
					<select name="icod[currency]">
						<?php
						$currencies = array(
							'DZD' => __( 'Dinar algérien (DA)', 'infinitycod' ),
							'MAD' => __( 'Dirham marocain (DH)', 'infinitycod' ),
							'TND' => __( 'Dinar tunisien (DT)', 'infinitycod' ),
							'EGP' => __( 'Livre égyptienne (EGP)', 'infinitycod' ),
							'SAR' => __( 'Riyal saoudien (SAR)', 'infinitycod' ),
							'AED' => __( 'Dirham des Émirats (AED)', 'infinitycod' ),
							'QAR' => __( 'Riyal qatari (QAR)', 'infinitycod' ),
							'KWD' => __( 'Dinar koweïtien (KWD)', 'infinitycod' ),
							'JOD' => __( 'Dinar jordanien (JOD)', 'infinitycod' ),
							'IQD' => __( 'Dinar irakien (IQD)', 'infinitycod' ),
							'LYD' => __( 'Dinar libyen (LYD)', 'infinitycod' ),
							'OMR' => __( 'Rial omanais (OMR)', 'infinitycod' ),
							'BHD' => __( 'Dinar de Bahreïn (BHD)', 'infinitycod' ),
							'MRU' => __( 'Ouguiya mauritanienne (MRU)', 'infinitycod' ),
							'SDG' => __( 'Livre soudanaise (SDG)', 'infinitycod' ),
							'SYP' => __( 'Livre syrienne (SYP)', 'infinitycod' ),
							'YER' => __( 'Rial yéménite (YER)', 'infinitycod' ),
							'EUR' => __( 'Euro (€)', 'infinitycod' ),
							'USD' => __( 'Dollar américain ($)', 'infinitycod' ),
						);
						foreach ( $currencies as $code => $label ) :
							?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( Settings::currency(), $code ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
		</div>

		<div class="icod-card">
			<h2><?php esc_html_e( 'Pays livrés', 'infinitycod' ); ?> <span class="icod-premium-mini">★ Premium</span></h2>
			<p class="description"><?php esc_html_e( 'Votre pays, détecté automatiquement à l‘installation, est toujours actif et affiché en premier. Cochez d‘autres pays pour livrer simultanément (régions préchargées, ville saisie libre).', 'infinitycod' ); ?></p>
			<div class="icod-toggles">
				<?php
				$catalog      = \InfinityCod\Core\Activator::countries_catalog();
				$active       = Settings::active_countries();
				$main_country = Settings::default_country();
				$is_premium_c = \InfinityCod\License\LicenseManager::is_premium();

				// Le pays principal du marchand TOUJOURS en premier.
				$order = array( $main_country );
				foreach ( $catalog as $code => $country ) {
					if ( $code !== $main_country ) {
						$order[] = $code;
					}
				}

				foreach ( $order as $code ) :
					$country = $catalog[ $code ];
					$is_main = ( $code === $main_country );
					?>
					<label class="icod-toggle <?php echo $is_main ? 'icod-country-main' : ''; ?>">
						<input type="checkbox" name="icod[countries][]" value="<?php echo esc_attr( $code ); ?>"
							<?php checked( in_array( $code, $active, true ) ); ?>
							<?php disabled( $is_main || ! $is_premium_c ); ?> />
						<span>
							<?php
							echo esc_html( $country['fr'] . ' — ' . $country['ar'] . ' (' . ( 'DZ' === $code ? '58' : count( $country['regions'] ) ) . ')' );
							if ( $is_main ) {
								echo ' <strong class="icod-country-main-label">· ' . esc_html__( 'votre pays, toujours actif', 'infinitycod' ) . '</strong>';
							}
							?>
						</span>
					</label>
				<?php endforeach; ?>
			</div>
			<?php if ( ! $is_premium_c ) : ?>
				<p class="description">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-settings&tab=license' ) ); ?>"><?php esc_html_e( '★ Activer une licence Premium pour livrer dans plusieurs pays simultanément.', 'infinitycod' ); ?></a>
				</p>
			<?php endif; ?>
			<p class="description"><?php esc_html_e( 'Après activation, activez les régions dans Wilayas & Tarifs et définissez leurs prix. Le champ ville devient libre pour ces pays (saisie directe par le client).', 'infinitycod' ); ?></p>
		</div>

		<div class="icod-card">
			<h2><?php esc_html_e( 'Mises à jour via GitHub', 'infinitycod' ); ?></h2>
			<div class="icod-notice-ok">✅ <?php esc_html_e( 'Automatique — aucune configuration requise. Votre site vérifie GitHub toutes les heures et propose (ou installe) les mises à jour tout seul. Les champs ci-dessous sont optionnels.', 'infinitycod' ); ?></div>
			<p class="description"><?php esc_html_e( 'Recommandé : créez un dépôt PUBLIC « releases » contenant uniquement les zips — les clients reçoivent les mises à jour sans aucun token, et vos sources restent privées. Si vous laissez ce champ vide, le plugin consulte le dépôt des sources (token alors obligatoire s‘il est privé).', 'infinitycod' ); ?></p>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Miroir personnalisé (secours ultime) — URL d‘un dossier contenant update.json + infinitycod.zip, consulté avant les miroirs GitHub', 'infinitycod' ); ?></span>
					<input type="url" name="icod[custom_update_url]" dir="ltr" placeholder="https://mon-cdn.exemple/updates/" value="<?php echo esc_attr( Settings::get( 'custom_update_url', '' ) ); ?>" class="regular-text" />
				</label>
			</div>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Serveur de licences (API d‘activation)', 'infinitycod' ); ?></span>
					<input type="text" name="icod[license_server]" value="<?php echo esc_attr( Settings::get( 'license_server' ) ); ?>" dir="ltr" />
				</label>
				<label>
					<span><?php esc_html_e( 'Dépôt PUBLIC des releases (recommandé)', 'infinitycod' ); ?></span>
					<input type="text" name="icod[releases_repo]" value="<?php echo esc_attr( Settings::get( 'releases_repo' ) ); ?>" dir="ltr" placeholder="derouicheoussama/infinitycod-releases" />
				</label>
				<label>
					<span><?php esc_html_e( 'Dépôt des sources (avec token si privé)', 'infinitycod' ); ?></span>
					<input type="text" name="icod[github_repo]" value="<?php echo esc_attr( Settings::get( 'github_repo' ) ); ?>" dir="ltr" placeholder="derouicheoussama/infinitycod" />
				</label>
				<label>
					<span><?php esc_html_e( 'Token GitHub (dépôt privé uniquement)', 'infinitycod' ); ?></span>
					<input type="password" name="icod[github_token]" value="<?php echo esc_attr( Settings::get( 'github_token' ) ); ?>" dir="ltr" autocomplete="new-password" placeholder="ghp_…" />
				</label>
			</div>
			<p class="description" style="margin-top:10px">
				<?php esc_html_e( 'Les clients vérifient les mises à jour toutes les heures directement sur les GitHub Releases du dépôt public. Aucun site web intermédiaire, aucun token requis côté client.', 'infinitycod' ); ?>
			</p>
		</div>

		<div class="icod-card">
			<h2><?php esc_html_e( 'Avancé', 'infinitycod' ); ?></h2>
			<div class="icod-toggles">
				<label class="icod-toggle">
					<input type="checkbox" name="icod[menu_badge]" value="1" <?php checked( (int) Settings::get( 'menu_badge' ), 1 ); ?> />
					<span><?php esc_html_e( 'Badge de commandes en attente sur le menu admin', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[auto_update]" value="1" <?php checked( (int) Settings::get( 'auto_update' ), 1 ); ?> />
					<span><?php esc_html_e( 'Mise à jour automatique du plugin (sans clic, dès qu‘une version GitHub est publiée)', 'infinitycod' ); ?></span>
				</label>
				<div class="icod-grid">
					<label>
						<span><?php esc_html_e( 'Sauvegardes conservées avant purge (1-10)', 'infinitycod' ); ?></span>
						<input type="number" min="1" max="10" name="icod[backup_retention]" value="<?php echo esc_attr( Settings::get( 'backup_retention', 3 ) ); ?>" />
					</label>
				</div>
				<div class="icod-toggles">
					<label class="icod-toggle">
						<input type="checkbox" name="icod[log_enabled]" value="1" <?php checked( (int) Settings::get( 'log_enabled', 1 ), 1 ); ?> />
						<span><?php esc_html_e( 'Journal InfinityCod (logs techniques, sans données clients)', 'infinitycod' ); ?></span>
					</label>
cod-toggle-danger">
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
	/**
	 * Schéma déclaratif des réglages : UNE seule source de vérité utilisée
	 * par l'enregistrement. Chaque clé décrit son onglet, son type et ses
	 * contraintes — impossible d'oublier une clé à la sauvegarde (le bug
	 * historique des thèmes rose/royal/café jamais enregistrés vient de là).
	 *
	 * Types : text, textarea, color, enum(+choices), int(+min/max),
	 *         url, ids, id, secret, toggle (traité par onglet).
	 *
	 * @return array<string, array{tab:string, type:string, ...}>
	 */
	private function settings_schema() {
		return array(
			// ——— Onglet Formulaire ———
			'form_title'         => array( 'tab' => 'form', 'type' => 'text' ),
			'form_subtitle'      => array( 'tab' => 'form', 'type' => 'text' ),
			'form_icon'          => array( 'tab' => 'form', 'type' => 'text' ),
			'button_text'        => array( 'tab' => 'form', 'type' => 'text' ),
			'phone_placeholder'  => array( 'tab' => 'form', 'type' => 'text' ),
			'label_name'         => array( 'tab' => 'form', 'type' => 'text' ),
			'label_phone'        => array( 'tab' => 'form', 'type' => 'text' ),
			'label_wilaya'       => array( 'tab' => 'form', 'type' => 'text' ),
			'label_commune'      => array( 'tab' => 'form', 'type' => 'text' ),
			'label_note'         => array( 'tab' => 'form', 'type' => 'text' ),
			'label_email'        => array( 'tab' => 'form', 'type' => 'text' ),
			'accent_color'       => array( 'tab' => 'form', 'type' => 'color' ),
			'form_theme'         => array( 'tab' => 'form', 'type' => 'enum', 'choices' => array( 'light', 'dark', 'auto' ) ),
			'success_style'      => array( 'tab' => 'form', 'type' => 'enum', 'choices' => array( 'classic', 'confetti', 'minimal', 'ticket', 'celebration' ) ),
			'checkout_fields'    => array( 'tab' => 'form', 'type' => 'json' ),
			'form_preset'        => array( 'tab' => 'form', 'type' => 'enum', 'choices' => array( 'modern', 'elegant', 'sunset', 'ocean', 'minimal', 'rose', 'royal', 'cafe', 'aqua', 'pro' ) ),
			'form_position'      => array( 'tab' => 'form', 'type' => 'enum', 'choices' => array( 'before_summary', 'after_price', 'after_excerpt', 'before_cart', 'after_cart', 'after_summary', 'end_product' ) ),
			'form_max_width'     => array( 'tab' => 'form', 'type' => 'int', 'min' => 400, 'max' => 900 ),
			'qty_max'            => array( 'tab' => 'form', 'type' => 'int', 'min' => 1, 'max' => 999 ),
			'show_qty_selector'  => array( 'tab' => 'form', 'type' => 'toggle' ),
			'show_stopdesk'      => array( 'tab' => 'form', 'type' => 'toggle' ),
			'show_note'          => array( 'tab' => 'form', 'type' => 'toggle' ),
			'show_offers'        => array( 'tab' => 'form', 'type' => 'toggle' ),
			'show_reassurance'   => array( 'tab' => 'form', 'type' => 'toggle' ),
			'show_email'         => array( 'tab' => 'form', 'type' => 'toggle' ),
			'sticky_bar'         => array( 'tab' => 'form', 'type' => 'toggle' ),

			// ——— Onglet Commande ———
			'success_title'      => array( 'tab' => 'order', 'type' => 'text' ),
			'success_text'       => array( 'tab' => 'order', 'type' => 'textarea' ),
			'upsell_title'       => array( 'tab' => 'order', 'type' => 'text' ),
			'redirect_enabled'   => array( 'tab' => 'order', 'type' => 'toggle' ),
			'redirect_url'       => array( 'tab' => 'order', 'type' => 'url' ),
			'redirect_delay'     => array( 'tab' => 'order', 'type' => 'int', 'min' => 3, 'max' => 60 ),
			'upsell_enabled'     => array( 'tab' => 'order', 'type' => 'toggle' ),
			'upsell_ids'         => array( 'tab' => 'order', 'type' => 'ids' ),

			// ——— Onglet Anti-fraude ———
			'shield_enabled'          => array( 'tab' => 'fraud', 'type' => 'toggle' ),
			'phone_strict'            => array( 'tab' => 'fraud', 'type' => 'toggle' ),
			'block_duplicate_phone'   => array( 'tab' => 'fraud', 'type' => 'toggle' ),
			'restrict_hours_enabled'  => array( 'tab' => 'fraud', 'type' => 'toggle' ),
			'restrict_hours_from'     => array( 'tab' => 'fraud', 'type' => 'int', 'min' => 0, 'max' => 23 ),
			'restrict_hours_to'       => array( 'tab' => 'fraud', 'type' => 'int', 'min' => 0, 'max' => 23 ),
			'max_per_ip_day'          => array( 'tab' => 'fraud', 'type' => 'int', 'min' => 0, 'max' => 100 ),
			'max_per_ip_hour'         => array( 'tab' => 'fraud', 'type' => 'int', 'min' => 1, 'max' => 100 ),
			'max_per_phone_day'       => array( 'tab' => 'fraud', 'type' => 'int', 'min' => 0, 'max' => 20 ),
			'max_per_email_day'       => array( 'tab' => 'fraud', 'type' => 'int', 'min' => 0, 'max' => 20 ),
			'min_submit_seconds'      => array( 'tab' => 'fraud', 'type' => 'int', 'min' => 0, 'max' => 60 ),
			'min_fraud_score_block'   => array( 'tab' => 'fraud', 'type' => 'int', 'min' => 0, 'max' => 100 ),
			'block_disposable_email'  => array( 'tab' => 'fraud', 'type' => 'toggle' ),
			'captcha_enabled'         => array( 'tab' => 'form', 'type' => 'toggle' ),
			'max_orders_hour_global'  => array( 'tab' => 'fraud', 'type' => 'int', 'min' => 0, 'max' => 500 ),

			// ——— Onglet WhatsApp ———
			'whatsapp_enabled'         => array( 'tab' => 'whatsapp', 'type' => 'toggle' ),
			'wa_on_confirm'            => array( 'tab' => 'whatsapp', 'type' => 'toggle' ),
			'email_on_confirm'          => array( 'tab' => 'whatsapp', 'type' => 'toggle' ),
			'abandoned_enabled'        => array( 'tab' => 'whatsapp', 'type' => 'toggle' ),
			'wa_order_enabled'         => array( 'tab' => 'whatsapp', 'type' => 'toggle' ),
			'whatsapp_gateway'         => array( 'tab' => 'whatsapp', 'type' => 'enum', 'choices' => array( 'wame', 'cloud', 'ultramsg', 'textmebot' ) ),
			'whatsapp_number'          => array( 'tab' => 'whatsapp', 'type' => 'id' ),
			'whatsapp_phone_id'        => array( 'tab' => 'whatsapp', 'type' => 'id' ),
			'whatsapp_ultramsg_instance' => array( 'tab' => 'whatsapp', 'type' => 'id' ),
			'whatsapp_cloud_token'     => array( 'tab' => 'whatsapp', 'type' => 'secret' ),
			'whatsapp_ultramsg_key'    => array( 'tab' => 'whatsapp', 'type' => 'secret' ),
			'wa_textmebot_key'         => array( 'tab' => 'whatsapp', 'type' => 'secret' ),
			'wa_order_label'           => array( 'tab' => 'whatsapp', 'type' => 'text' ),
			'msg_wa_order'             => array( 'tab' => 'whatsapp', 'type' => 'textarea' ),
			'msg_order_received'       => array( 'tab' => 'whatsapp', 'type' => 'textarea' ),
			'msg_order_shipped'        => array( 'tab' => 'whatsapp', 'type' => 'textarea' ),
			'msg_abandoned'            => array( 'tab' => 'whatsapp', 'type' => 'textarea' ),
			'abandoned_delay'          => array( 'tab' => 'whatsapp', 'type' => 'int', 'min' => 5, 'max' => 1440 ),
			'abandoned_max'            => array( 'tab' => 'whatsapp', 'type' => 'int', 'min' => 1, 'max' => 5 ),

			// ——— Onglet Paiement ———
			'payment_enabled'    => array( 'tab' => 'payment', 'type' => 'toggle' ),
			'chargily_mode'      => array( 'tab' => 'payment', 'type' => 'enum', 'choices' => array( 'test', 'live' ) ),
			'chargily_secret'    => array( 'tab' => 'payment', 'type' => 'secret' ),
			'cod_label'          => array( 'tab' => 'payment', 'type' => 'text' ),
			'payment_label'      => array( 'tab' => 'payment', 'type' => 'text' ),
			'payment_return_text' => array( 'tab' => 'payment', 'type' => 'textarea' ),

			// ——— Onglet Tracking ———
			'pixel_fb_enabled'       => array( 'tab' => 'tracking', 'type' => 'toggle' ),
			'pixel_fb_id'            => array( 'tab' => 'tracking', 'type' => 'id' ),
			'pixel_fb_capi_token'    => array( 'tab' => 'tracking', 'type' => 'secret' ),
			'pixel_fb_test_code'     => array( 'tab' => 'tracking', 'type' => 'id' ),
			'pixel_tiktok_enabled'   => array( 'tab' => 'tracking', 'type' => 'toggle' ),
			'pixel_tiktok_id'        => array( 'tab' => 'tracking', 'type' => 'id' ),
			'pixel_snap_enabled'     => array( 'tab' => 'tracking', 'type' => 'toggle' ),
			'pixel_snap_id'          => array( 'tab' => 'tracking', 'type' => 'id' ),
			'pixel_consent_required' => array( 'tab' => 'tracking', 'type' => 'toggle' ),
			'pixel_sitewide'         => array( 'tab' => 'tracking', 'type' => 'toggle' ),

			// ——— Onglet Avancé ———
			'currency'             => array( 'tab' => 'advanced', 'type' => 'enum', 'choices' => array( 'DZD', 'MAD', 'TND', 'EGP', 'SAR', 'AED', 'QAR', 'KWD', 'JOD', 'IQD', 'LYD', 'OMR', 'BHD', 'MRU', 'SDG', 'SYP', 'YER', 'EUR', 'USD' ) ),
			'currency_position'    => array( 'tab' => 'advanced', 'type' => 'enum', 'choices' => array( 'right', 'left' ) ),
			'default_country'      => array( 'tab' => 'advanced', 'type' => 'country' ),
			'countries'            => array( 'tab' => 'advanced', 'type' => 'countries' ),

			// ——— Vente PayPal (configurée depuis l'onglet Licence) ———
			'paypal_enabled'        => array( 'tab' => 'license', 'type' => 'toggle' ),
			'paypal_email'          => array( 'tab' => 'license', 'type' => 'email' ),
			'paypal_currency'       => array( 'tab' => 'license', 'type' => 'enum', 'choices' => array( 'USD', 'EUR' ) ),
			'paypal_price_personal' => array( 'tab' => 'license', 'type' => 'price' ),
			'paypal_price_business' => array( 'tab' => 'license', 'type' => 'price' ),
			'paypal_price_agency'   => array( 'tab' => 'license', 'type' => 'price' ),
			'custom_update_url'    => array( 'tab' => 'advanced', 'type' => 'url' ),
			'github_webhook_secret' => array( 'tab' => 'advanced', 'type' => 'secret' ),
			'github_repo'          => array( 'tab' => 'advanced', 'type' => 'id' ),
			'releases_repo'        => array( 'tab' => 'advanced', 'type' => 'id' ),
			'license_server'       => array( 'tab' => 'advanced', 'type' => 'id' ),
			'github_token'         => array( 'tab' => 'advanced', 'type' => 'secret' ),
			'menu_badge'           => array( 'tab' => 'advanced', 'type' => 'toggle' ),
			'auto_update'          => array( 'tab' => 'advanced', 'type' => 'toggle' ),
			'license_lock_form'    => array( 'tab' => 'advanced', 'type' => 'toggle' ),
			'log_enabled'          => array( 'tab' => 'advanced', 'type' => 'toggle' ),
			'delete_on_uninstall'  => array( 'tab' => 'advanced', 'type' => 'toggle' ),
			'backup_retention'     => array( 'tab' => 'advanced', 'type' => 'int', 'min' => 1, 'max' => 10 ),
		);
	}

	/**
	 * Traite la sauvegarde : le schéma déclaratif filtre et nettoie chaque
	 * clé selon son type. Les toggles sont bornés à l'onglet soumis (une
	 * case absente du POST = 0) — les autres onglets sont préservés.
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_save_settings' );

		$raw   = isset( $_POST['icod'] ) && is_array( $_POST['icod'] ) ? wp_unslash( $_POST['icod'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitisé champ par champ.
		$clean = $this->sanitize_fields( $raw, $this->tab );

		// Checkout Builder : ajout des champs personnalisés soumis.
		if ( 'form' === $tab && isset( $clean['checkout_fields'] ) && isset( $_POST['icod']['checkout_fields_new']['key'] ) ) {
			$nk = sanitize_key( $_POST['icod']['checkout_fields_new']['key'] );
			if ( $nk !== '' ) {
				$fields = $clean['checkout_fields'];
				$fields[] = array(
					'key'   => substr( $nk, 0, 30 ),
					'type'  => in_array( $_POST['icod']['checkout_fields_new']['type'] ?? 'text', array( 'text','tel','email','select','radio','checkbox','textarea','date','number' ), true ) ? $_POST['icod']['checkout_fields_new']['type'] : 'text',
					'label' => sanitize_text_field( $_POST['icod']['checkout_fields_new']['label'] ?? '' ),
					'on'    => 1,
					'req'   => 0,
				);
				$clean['checkout_fields'] = $fields;
			}
		}

		Settings::set( $clean );

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'form';
		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-settings&tab=' . $tab . '&icod_msg=saved' ) );
		exit;
	}

	/**
	 * Sauvegarde de la configuration vendeur PayPal (onglet Licence).
	 *
	 * @return void
	 */
	public function handle_paypal_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_save_paypal' );

		$raw   = isset( $_POST['icod'] ) && is_array( $_POST['icod'] ) ? wp_unslash( $_POST['icod'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitisé champ par champ.
		$clean = $this->sanitize_fields( $raw, 'license' );

		Settings::set( $clean );

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-settings&tab=license&icod_msg=paypal-saved' ) );
		exit;
	}

	/**
	 * Nettoie un POST selon le schéma déclaratif, borné à un onglet.
	 *
	 * @param array  $raw Données brutes.
	 * @param string $tab Onglet courant (les toggles de cet onglet only).
	 * @return array
	 */
	private function sanitize_fields( array $raw, string $tab ) {
		$clean  = array();
		$schema = $this->settings_schema();

		foreach ( $schema as $key => $def ) {
			$type = $def['type'];

			// Toggles : uniquement ceux de l'onglet soumis.
			if ( 'toggle' === $type ) {
				if ( $def['tab'] === $tab ) {
					$clean[ $key ] = empty( $raw[ $key ] ) ? 0 : 1;
				}
				continue;
			}

			if ( ! isset( $raw[ $key ] ) ) {
				continue;
			}
			$value = $raw[ $key ];

			switch ( $type ) {
				case 'text':
					$clean[ $key ] = sanitize_text_field( $value );
					break;
				case 'textarea':
					$clean[ $key ] = sanitize_textarea_field( $value );
					break;
				case 'color':
					if ( preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $value ) ) {
						$clean[ $key ] = (string) $value;
					}
					break;
				case 'enum':
					if ( in_array( $value, $def['choices'], true ) ) {
						$clean[ $key ] = (string) $value;
					}
					break;
				case 'int':
					$clean[ $key ] = max( (int) $def['min'], min( (int) $def['max'], absint( $value ) ) );
					break;
				case 'url':
					$clean[ $key ] = esc_url_raw( trim( (string) $value ) );
					break;
				case 'email':
					$email         = sanitize_email( $value );
					$clean[ $key ] = is_email( $email ) ? $email : '';
					break;
				case 'price':
					$price         = round( (float) str_replace( ',', '.', (string) $value ), 2 );
					$clean[ $key ] = max( 0, min( 100000, $price ) );
					break;
				case 'ids':
					if ( is_array( $value ) ) {
						$ids           = array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
						$clean[ $key ] = array_slice( $ids, 0, isset( $def['max_items'] ) ? (int) $def['max_items'] : 3 );
					}
					break;
				case 'countries':
					// Multi-pays simultané : option Premium. Hors Premium,
					// le pays principal (détecté) est préservé.
					$codes = array();
					if ( is_array( $value ) ) {
						foreach ( $value as $code ) {
							$code = strtoupper( sanitize_text_field( (string) $code ) );
							if ( preg_match( '/^[A-Z]{2}$/', $code ) ) {
								$codes[] = $code;
							}
						}
					}
					$premium = \InfinityCod\License\LicenseManager::is_premium();
					$clean[ $key ] = $premium ? array_values( array_unique( $codes ) ) : array( Settings::default_country() );
					break;
				case 'country':
					$code = strtoupper( sanitize_text_field( (string) $value ) );
					if ( preg_match( '/^[A-Z]{2}$/', $code ) ) {
						$clean[ $key ] = $code;
					}
					break;
				case 'id':
					$clean[ $key ] = preg_replace( '/[^0-9a-zA-Z_\-.\/]/', '', (string) $value );
					break;
				case 'json':
					$fields = json_decode( wp_unslash( $value ), true );
					$out = array();
					if ( is_array( $fields ) ) {
						foreach ( $fields as $f ) {
							if ( ! is_array( $f ) || empty( $f['key'] ) ) { continue; }
							$out[] = array(
								'key'  => substr( preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $f['key'] ) ), 0, 30 ),
								'type' => in_array( $f['type'] ?? '', array( 'text','tel','email','select','radio','checkbox','textarea','date','number' ), true ) ? $f['type'] : 'text',
								'label' => sanitize_text_field( (string) ( $f['label'] ?? '' ) ),
								'on' => empty( $f['on'] ) ? 0 : 1,
								'req' => empty( $f['req'] ) ? 0 : 1,
							);
						}
						usort( $out, fn( $a, $b ) => ( $a['order'] ?? 99 ) <=> ( $b['order'] ?? 99 ) );
					}
					$clean[ $key ] = $out;
					break;
				case 'secret':
					$clean[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $clean;
	}
}
