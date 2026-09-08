<?php
/**
 * Page admin : À propos — informations, statut système, mise à jour.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Admin\Pages;

use InfinityCod\Core\Schema;
use InfinityCod\License\Updater;

defined( 'ABSPATH' ) || exit;

class AboutPage {

	/**
	 * Constructeur : handler « Vérifier les mises à jour ».
	 */
	public function __construct() {
		add_action( 'admin_post_icod_check_update', array( $this, 'handle_check_update' ) );
	}

	/**
	 * Affiche la page.
	 *
	 * @return void
	 */
	public function render() {
		global $wpdb;

		$msg = isset( $_GET['icod_msg'] ) ? sanitize_key( wp_unslash( $_GET['icod_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Statut système.
		$theme        = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$wc_active    = class_exists( 'WooCommerce' );
		$wc_version   = defined( 'WC_VERSION' ) ? WC_VERSION : '';
		$orders_table = Schema::table( 'orders' );
		$orders_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$orders_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		$desks_table  = Schema::table( 'stopdesks' );
		$desks_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$desks_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery

		// Mise à jour distante ?
		$remote = get_transient( 'icod_update_info' );
		$remote = is_array( $remote ) ? $remote : array();
		$newer  = ! empty( $remote['version'] ) && version_compare( INFINITYCOD_VERSION, (string) $remote['version'], '<' );

		$carriers = infinitycod()->module( 'carriers' );
		$active_carriers = array();
		foreach ( \InfinityCod\Carriers\CarrierManager::catalog() as $entry ) {
			if ( $carriers && $carriers->is_configured( $entry['code'] ) ) {
				$active_carriers[] = $entry['name'];
			}
		}
		?>
		<div class="wrap icod-wrap">
			<h1 class="icod-title"><?php esc_html_e( 'À propos d‘InfinityCod', 'infinitycod' ); ?></h1>

			<?php if ( 'checking' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cache des mises à jour vidé — WordPress vérifiera une nouvelle version dès maintenant.', 'infinitycod' ); ?></p></div>
			<?php endif; ?>

			<div class="icod-about-hero icod-card">
				<div class="icod-about-badge">∞</div>
				<div>
					<h2>InfinityCod <span class="icod-about-version">v<?php echo esc_html( INFINITYCOD_VERSION ); ?></span></h2>
					<p>
						<?php esc_html_e( 'La solution de paiement à la livraison pensée pour l‘Algérie : formulaire COD rapide, 58 wilayas & 1541 communes, transporteurs intégrés, WhatsApp automatique et statistiques P&L.', 'infinitycod' ); ?>
					</p>
					<p class="icod-about-meta">
						<span class="icod-status icod-status-delivered"><?php echo esc_html( \InfinityCod\License\LicenseManager::status_label() ); ?></span>
						<?php if ( $newer ) : ?>
							<span class="icod-about-update">
								<?php printf( /* translators: %s : version disponible. */ esc_html__( 'Version %s disponible !', 'infinitycod' ), '<strong>' . esc_html( $remote['version'] ) . '</strong>' ); ?>
								<a href="<?php echo esc_url( admin_url( 'update-core.php' ) ); ?>" class="button button-primary button-small"><?php esc_html_e( 'Mettre à jour', 'infinitycod' ); ?></a>
							</span>
						<?php endif; ?>
					</p>
				</div>
			</div>

			<div class="icod-dashboard-cols">
				<div class="icod-card">
					<h2><?php esc_html_e( 'Statut du système', 'infinitycod' ); ?></h2>
					<ul class="icod-sysinfo">
						<li><span>PHP</span><strong><?php echo esc_html( PHP_VERSION ); ?></strong></li>
						<li><span>WordPress</span><strong><?php echo esc_html( get_bloginfo( 'version' ) ); ?></strong></li>
						<li><span>WooCommerce</span><strong><?php echo $wc_active ? esc_html( $wc_version ? $wc_version : __( 'actif', 'infinitycod' ) ) : '<em>' . esc_html__( 'non actif !', 'infinitycod' ) . '</em>'; ?></strong></li>
						<li><span><?php esc_html_e( 'Thème actif', 'infinitycod' ); ?></span><strong><?php echo $theme ? esc_html( $theme->get( 'Name' ) ) : '—'; ?></strong></li>
						<li><span><?php esc_html_e( 'Tables du plugin', 'infinitycod' ); ?></span><strong>OK (DB v<?php echo esc_html( get_option( 'infinitycod_db_version', '?' ) ); ?>)</strong></li>
						<li><span><?php esc_html_e( 'Commandes COD enregistrées', 'infinitycod' ); ?></span><strong><?php echo (int) $orders_count; ?></strong></li>
						<li><span><?php esc_html_e( 'Bureaux Stopdesk', 'infinitycod' ); ?></span><strong><?php echo (int) $desks_count; ?></strong></li>
						<li>
							<span><?php esc_html_e( 'Transporteurs connectés', 'infinitycod' ); ?></span>
							<strong><?php echo $active_carriers ? esc_html( implode( ', ', $active_carriers ) ) : __( 'aucun', 'infinitycod' ); ?></strong>
						</li>
						<li>
							<span><?php esc_html_e( 'WhatsApp', 'infinitycod' ); ?></span>
							<strong><?php echo \InfinityCod\Core\Settings::get( 'whatsapp_enabled' ) ? esc_html( strtoupper( (string) \InfinityCod\Core\Settings::get( 'whatsapp_gateway' ) ) ) : __( 'désactivé', 'infinitycod' ); ?></strong>
						</li>
					</ul>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:14px">
						<input type="hidden" name="action" value="icod_check_update" />
						<?php wp_nonce_field( 'icod_check_update' ); ?>
						<button type="submit" class="button">🔄 <?php esc_html_e( 'Vérifier les mises à jour', 'infinitycod' ); ?></button>
					</form>
				</div>

				<div>
					<div class="icod-card">
						<h2><?php esc_html_e( 'Fonctionnalités', 'infinitycod' ); ?></h2>
						<ul class="icod-features">
							<li>✅ <?php esc_html_e( 'Formulaire COD responsive — 5 thèmes, mode sombre, RTL arabe', 'infinitycod' ); ?></li>
							<li>🗺️ <?php esc_html_e( '58 wilayas + 1541 communes officielles (FR/AR)', 'infinitycod' ); ?></li>
							<li>🛡️ <?php esc_html_e( 'Bouclier anti-fraude avec score de risque', 'infinitycod' ); ?></li>
							<li>📊 <?php esc_html_e( 'Dashboard commandes + export Excel', 'infinitycod' ); ?></li>
							<li>🚚 <?php esc_html_e( 'Transporteurs : Yalidine, ZR Express, Maystro, Noest, E-COM, DHD', 'infinitycod' ); ?></li>
							<li>💬 <?php esc_html_e( 'WhatsApp automatique + relance des paniers abandonnés', 'infinitycod' ); ?></li>
							<li>🏷️ <?php esc_html_e( 'Offres par quantité et livraison gratuite', 'infinitycod' ); ?></li>
							<li>📈 <?php esc_html_e( 'Statistiques P&L par wilaya, transporteur et produit', 'infinitycod' ); ?></li>
						</ul>
					</div>

					<div class="icod-card">
						<h2><?php esc_html_e( 'Liens', 'infinitycod' ); ?></h2>
						<p class="icod-about-links">
							<a class="button" href="https://infinitycoder.app" target="_blank" rel="noopener">🌐 <?php esc_html_e( 'Site officiel', 'infinitycod' ); ?></a>
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=infinitycod-settings&tab=license' ) ); ?>">🔑 <?php esc_html_e( 'Licence', 'infinitycod' ); ?></a>
						</p>
						<p class="icod-sub">
							<?php printf( /* translators: %s : date. */ esc_html__( 'Installé le %s par Infinity Coder (Oussama Derouiche).', 'infinitycod' ), esc_html( mysql2date( 'd/m/Y', get_option( 'infinitycod_installed_at', current_time( 'mysql' ) ) ) ) ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Vide le cache de mise à jour et relance la vérification.
	 *
	 * @return void
	 */
	public function handle_check_update() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_check_update' );

		Updater::clear_cache();

		// Force la transient WordPress à revérifier immédiatement.
		delete_site_transient( 'update_plugins' );

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-about&icod_msg=checking' ) );
		exit;
	}
}
