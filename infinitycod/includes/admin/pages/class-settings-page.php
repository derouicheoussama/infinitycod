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
				<a href="?page=infinitycod-settings&tab=order" class="nav-tab <?php echo 'order' === $this->tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Commande', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=fraud" class="nav-tab <?php echo 'fraud' === $this->tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Anti-fraude', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=whatsapp" class="nav-tab <?php echo 'whatsapp' === $this->tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'WhatsApp', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=tracking" class="nav-tab <?php echo 'tracking' === $this->tab ? 'nav-tab-active' : ''; ?>">🎯 <?php esc_html_e( 'Tracking', 'infinitycod' ); ?></a>
				<a href="?page=infinitycod-settings&tab=payment" class="nav-tab <?php echo 'payment' === $this->tab ? 'nav-tab-active' : ''; ?>" ><?php esc_html_e( 'Paiement', 'infinitycod' ); ?></a>
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
	 * Onglet Tracking : pixels Meta / TikTok / Snapchat + Conversions API.
	 *
	 * @return void
	 */
	private function tab_tracking() {
		?>
		<div class="icod-card">
			<h2><?php esc_html_e( '🎯 Pixels publicitaires', 'infinitycod' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Mesurez votre funnel COD sur Meta (Facebook/Instagram), TikTok et Snapchat. Événements envoyés : ViewContent, InitiateCheckout (début du formulaire) et Purchase (commande enregistrée, avec le montant réel).', 'infinitycod' ); ?>
			</p>

			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Meta Pixel (Facebook / Instagram) — ID (15-16 chiffres)', 'infinitycod' ); ?></span>
					<input type="text" name="icod[pixel_fb_id]" dir="ltr" placeholder="1234567890123456" value="<?php echo esc_attr( Settings::get( 'pixel_fb_id' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'TikTok Pixel — ID', 'infinitycod' ); ?></span>
					<input type="text" name="icod[pixel_tiktok_id]" dir="ltr" placeholder="CXXXXXXXXXXXXXXXXXXX" value="<?php echo esc_attr( Settings::get( 'pixel_tiktok_id' ) ); ?>" />
				</label>
				<label>
					<span><?php esc_html_e( 'Snapchat Pixel — UUID', 'infinitycod' ); ?></span>
					<input type="text" name="icod[pixel_snap_id]" dir="ltr" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" value="<?php echo esc_attr( Settings::get( 'pixel_snap_id' ) ); ?>" />
				</label>
			</div>

			<div class="icod-toggles">
				<label class="icod-toggle">
					<input type="checkbox" name="icod[pixel_fb_enabled]" value="1" <?php checked( (int) Settings::get( 'pixel_fb_enabled' ), 1 ); ?> />
					<span><?php esc_html_e( 'Activer le pixel Meta (Facebook / Instagram)', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[pixel_tiktok_enabled]" value="1" <?php checked( (int) Settings::get( 'pixel_tiktok_enabled' ), 1 ); ?> />
					<span><?php esc_html_e( 'Activer le pixel TikTok', 'infinitycod' ); ?></span>
				</label>
				<label class="icod-toggle">
					<input type="checkbox" name="icod[pixel_snap_enabled]" value="1" <?php checked( (int) Settings::get( 'pixel_snap_enabled' ), 1 ); ?> />
					<span><?php esc_html_e( 'Activer le pixel Snapchat', 'infinitycod' ); ?></span>
				</label>
			</div>
		</div>

		<div class="icod-card">
			<h2><?php esc_html_e( 'Conversions API Meta — exigences 2026', 'infinitycod' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'La Conversions API (CAPI) envoie la commande directement depuis votre serveur vers Meta : plus fiable que le navigateur (bloqueurs, iOS). InfinityCod applique automatiquement les exigences Meta 2026 : event_id de déduplication navigateur/serveur, téléphone haché en SHA-256 (advanced matching), cookies first-party _fbp/_fbc transférés, action_source « website ». Récupérez votre token dans Events Manager → Paramètres → Conversions API → Générer le token d’accès.', 'infinitycod' ); ?>
			</p>
			<div class="icod-grid">
				<label>
					<span><?php esc_html_e( 'Token d’accès Conversions API (secret)', 'infinitycod' ); ?></span>
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

		<div class="icod-card">
			<h2><?php $premium ? esc_html_e( 'Changer de clé', 'infinitycod' ) : esc_html_e( 'Activer votre licence Premium', 'infinitycod' ); ?></h2>
			<?php if ( ! $premium ) : ?>
				<p class="description">
					<?php esc_html_e( 'Saisissez la clé reçue après votre achat pour débloquer immédiatement WhatsApp automatique, les transporteurs, les statistiques P&L et les offres par quantité — sans réinstaller quoi que ce soit.', 'infinitycod' ); ?>
				</p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="icod_activate_license" />
				<?php wp_nonce_field( 'icod_activate_license' ); ?>
				<div class="icod-grid">
					<label>
						<span><?php esc_html_e( 'Clé de licence', 'infinitycod' ); ?></span>
						<input type="text" name="icod_license_key" class="regular-text" dir="ltr" placeholder="INFINITY-XXXX-XXXX-XXXX" />
					</label>
				</div>
				<p class="icod-submit">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Activer la licence', 'infinitycod' ); ?></button>
					<a href="<?php echo esc_url( 'https://infinitycoder.app/infinitycod' ); ?>" class="button" target="_blank" rel="noopener"><?php esc_html_e( 'Acheter une licence', 'infinitycod' ); ?></a>
				</p>
			</form>
		</div>

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
		);
		?>
		<div class="icod-card">
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
			<p class="description"><?php esc_html_e( 'Algérie toujours incluse (58 wilayas + 1541 communes). La licence Premium ajoute les marchés Maroc, Tunisie, Égypte, Arabie Saoudite et Émirats : régions préchargées, ville saisie libre par le client, tarifs à définir par région dans Wilayas & Tarifs.', 'infinitycod' ); ?></p>
			<div class="icod-toggles">
				<?php
				$catalog      = \InfinityCod\Core\Activator::countries_catalog();
				$active       = Settings::active_countries();
				$is_premium_c = \InfinityCod\License\LicenseManager::is_premium();
				foreach ( $catalog as $code => $country ) :
					if ( 'DZ' === $code ) {
						continue;
					}
					?>
					<label class="icod-toggle">
						<input type="checkbox" name="icod[countries][]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $active, true ) ); ?> <?php disabled( ! $is_premium_c ); ?> />
						<span><?php echo esc_html( $country['fr'] . ' — ' . $country['ar'] . ' (' . count( $country['regions'] ) . ' régions)' ); ?></span>
					</label>
				<?php endforeach; ?>
				<label class="icod-toggle">
					<input type="checkbox" checked disabled />
					<span><?php esc_html_e( 'Algérie — 58 wilayas, 1541 communes (inclus)', 'infinitycod' ); ?></span>
				</label>
			</div>
			<?php if ( ! $is_premium_c ) : ?>
				<p class="description">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-settings&tab=license' ) ); ?>"><?php esc_html_e( '★ Activer une licence Premium pour livrer dans ces pays.', 'infinitycod' ); ?></a>
				</p>
			<?php endif; ?>
		</div>

		<div class="icod-card">
			<h2><?php esc_html_e( 'Mises à jour via GitHub', 'infinitycod' ); ?></h2>
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
				<label class="icod-toggle">
					<input type="checkbox" name="icod[license_lock_form]" value="1" <?php checked( (int) Settings::get( 'license_lock_form' ), 1 ); ?> />
					<span><?php esc_html_e( 'Verrouiller le formulaire COD tant qu‘aucune licence n‘est activée (les visiteurs ne voient pas le formulaire ; recommandé uniquement pour une distribution commerciale)', 'infinitycod' ); ?></span>
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
			'form_preset'        => array( 'tab' => 'form', 'type' => 'enum', 'choices' => array( 'modern', 'elegant', 'sunset', 'ocean', 'minimal', 'rose', 'royal', 'cafe', 'aqua' ) ),
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

			// ——— Onglet WhatsApp ———
			'whatsapp_enabled'         => array( 'tab' => 'whatsapp', 'type' => 'toggle' ),
			'abandoned_enabled'        => array( 'tab' => 'whatsapp', 'type' => 'toggle' ),
			'wa_order_enabled'         => array( 'tab' => 'whatsapp', 'type' => 'toggle' ),
			'whatsapp_gateway'         => array( 'tab' => 'whatsapp', 'type' => 'enum', 'choices' => array( 'wame', 'cloud', 'ultramsg' ) ),
			'whatsapp_number'          => array( 'tab' => 'whatsapp', 'type' => 'id' ),
			'whatsapp_phone_id'        => array( 'tab' => 'whatsapp', 'type' => 'id' ),
			'whatsapp_ultramsg_instance' => array( 'tab' => 'whatsapp', 'type' => 'id' ),
			'whatsapp_cloud_token'     => array( 'tab' => 'whatsapp', 'type' => 'secret' ),
			'whatsapp_ultramsg_key'    => array( 'tab' => 'whatsapp', 'type' => 'secret' ),
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
			'custom_update_url'    => array( 'tab' => 'advanced', 'type' => 'url' ),
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

		$raw    = isset( $_POST['icod'] ) && is_array( $_POST['icod'] ) ? wp_unslash( $_POST['icod'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitisé champ par champ.
		$clean  = array();
		$schema = $this->settings_schema();

		foreach ( $schema as $key => $def ) {
			$type = $def['type'];

			// Toggles : uniquement ceux de l'onglet soumis.
			if ( 'toggle' === $type ) {
				if ( $def['tab'] === $this->tab ) {
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
				case 'secret':
					$clean[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		Settings::set( $clean );

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'form';
		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-settings&tab=' . $tab . '&icod_msg=saved' ) );
		exit;
	}
}
