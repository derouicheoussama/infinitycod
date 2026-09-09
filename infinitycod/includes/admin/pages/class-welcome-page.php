<?php
/**
 * Page admin : Bienvenue — écran d'accueil affiché après l'activation.
 *
 * Donnera une première impression professionnelle : hero de marque, parcours
 * de configuration en 3 étapes, aperçu Premium et état de l'installation.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Admin\Pages;

use InfinityCod\Core\Schema;

defined( 'ABSPATH' ) || exit;

class WelcomePage {

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public function render() {
		global $wpdb;

		$wilayas_table = Schema::table( 'wilayas' );
		$wilayas_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wilayas_table}" );
		$communes      = Schema::table( 'communes' );
		$communes_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$communes}" );
		$premium       = \InfinityCod\License\LicenseManager::is_premium();
		?>
		<div class="wrap icod-wrap icod-welcome">
			<div class="icod-welcome-hero">
				<div class="icod-welcome-hero-text">
					<span class="icod-welcome-badge">InfinityCod <?php echo esc_html( INFINITYCOD_VERSION ); ?></span>
					<h1><?php esc_html_e( 'Bienvenue dans InfinityCod 🎉', 'infinitycod' ); ?></h1>
					<p>
						<?php esc_html_e( 'La solution COD tout-en-un pour WooCommerce Algérie est installée et active : formulaire de commande rapide, 58 wilayas, 1541 communes, tarifs domicile & stopdesk, anti-fraude et mises à jour automatiques.', 'infinitycod' ); ?>
					</p>
					<p class="icod-welcome-author">
						<?php esc_html_e( 'Développé par Derouiche Oussama — Infinity Coder', 'infinitycod' ); ?>
					</p>
				</div>
				<div class="icod-welcome-hero-actions">
					<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-geo' ) ); ?>"><?php esc_html_e( 'Commencer la configuration', 'infinitycod' ); ?></a>
					<a class="button button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-settings&tab=license' ) ); ?>">★ <?php esc_html_e( 'Activer une licence Premium', 'infinitycod' ); ?></a>
				</div>
			</div>

			<div class="icod-welcome-steps">
				<div class="icod-welcome-step">
					<span class="icod-welcome-step-num">1</span>
					<h3><?php esc_html_e( 'Configurez vos tarifs', 'infinitycod' ); ?></h3>
					<p><?php esc_html_e( 'Prix de livraison domicile et stopdesk par wilaya, livraison gratuite, délais et commande minimale. Tout se règle en quelques minutes.', 'infinitycod' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-geo' ) ); ?>"><?php esc_html_e( 'Wilayas & Tarifs →', 'infinitycod' ); ?></a>
				</div>
				<div class="icod-welcome-step">
					<span class="icod-welcome-step-num">2</span>
					<h3><?php esc_html_e( 'Personnalisez le formulaire', 'infinitycod' ); ?></h3>
					<p><?php esc_html_e( '8 thèmes prêts à l’emploi, couleur d’accent, textes, position sur la fiche produit, mode sombre et RTL arabe.', 'infinitycod' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-settings' ) ); ?>"><?php esc_html_e( 'Réglages du formulaire →', 'infinitycod' ); ?></a>
				</div>
				<div class="icod-welcome-step">
					<span class="icod-welcome-step-num">3</span>
					<h3><?php esc_html_e( 'Recevez votre première commande', 'infinitycod' ); ?></h3>
					<p><?php esc_html_e( 'Le formulaire s’intègre automatiquement à vos fiches produit — ou via le shortcode [infinitycod_form]. Suivez tout depuis Commandes COD.', 'infinitycod' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-orders' ) ); ?>"><?php esc_html_e( 'Commandes COD →', 'infinitycod' ); ?></a>
				</div>
			</div>

			<div class="icod-card icod-welcome-status">
				<h2><?php esc_html_e( 'État de votre installation', 'infinitycod' ); ?></h2>
				<div class="icod-welcome-status-grid">
					<div>
						<span class="icod-welcome-status-value"><?php echo esc_html( $wilayas_count ); ?></span>
						<span class="icod-welcome-status-label"><?php esc_html_e( 'wilayas chargées', 'infinitycod' ); ?></span>
					</div>
					<div>
						<span class="icod-welcome-status-value"><?php echo esc_html( number_format_i18n( $communes_count ) ); ?></span>
						<span class="icod-welcome-status-label"><?php esc_html_e( 'communes chargées', 'infinitycod' ); ?></span>
					</div>
					<div>
						<span class="icod-welcome-status-value"><?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : '—' ); ?></span>
						<span class="icod-welcome-status-label"><?php esc_html_e( 'WooCommerce', 'infinitycod' ); ?></span>
					</div>
					<div>
						<span class="icod-status <?php echo $premium ? 'icod-status-delivered' : 'icod-status-pending'; ?>"><?php echo esc_html( \InfinityCod\License\LicenseManager::status_label() ); ?></span>
						<span class="icod-welcome-status-label"><?php esc_html_e( 'licence', 'infinitycod' ); ?></span>
					</div>
				</div>
			</div>

			<div class="icod-card icod-welcome-premium <?php echo $premium ? 'icod-welcome-premium-on' : ''; ?>">
				<div class="icod-welcome-premium-text">
					<h2>★ <?php $premium ? esc_html_e( 'Premium actif — profitez de tout', 'infinitycod' ) : esc_html_e( 'Débloquez la puissance de InfinityCod Premium', 'infinitycod' ); ?></h2>
					<p>
						<?php
						$premium
							? esc_html_e( 'WhatsApp automatique, transporteurs avec suivi, relances de paniers abandonnés, statistiques P&L et offres par quantité sont opérationnels.', 'infinitycod' )
							: esc_html_e( 'WhatsApp automatique (confirmations + relances de paniers abandonnés), transporteurs intégrés avec suivi de colis, statistiques P&L et offres par quantité.', 'infinitycod' );
						?>
					</p>
				</div>
				<?php if ( ! $premium ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-settings&tab=license' ) ); ?>"><?php esc_html_e( 'Découvrir les offres', 'infinitycod' ); ?></a>
				<?php endif; ?>
			</div>

			<p class="icod-welcome-footer">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-about' ) ); ?>"><?php esc_html_e( 'À propos & nos plugins', 'infinitycod' ); ?></a>
				·
				<a href="https://infinitycoder.app/infinitycod" target="_blank" rel="noopener"><?php esc_html_e( 'Documentation', 'infinitycod' ); ?></a>
				·
				<?php /* Lien masqué : l'écran reste accessible depuis À propos. */ ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-welcome&icod_hide_welcome=1' ) ); ?>"><?php esc_html_e( 'Ne plus afficher', 'infinitycod' ); ?></a>
			</p>
		</div>
		<?php
	}
}
