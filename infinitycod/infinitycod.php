<?php
/**
 * Plugin Name:       InfinityCod — Paiement à la livraison (COD Algérie)
 * Plugin URI:        https://infinitycoder.app/infinitycod
 * Description:       Solution COD tout-en-un pour WooCommerce Algérie : formulaire de commande rapide, 58 wilayas & 1541 communes, tarifs domicile/stopdesk, anti-fraude, transporteurs intégrés (Yalidine, ZR Express, Maystro, Noest, Guepex…), WhatsApp automatique, offres par quantité et statistiques P&L.
 * Version:           5.21.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:      9.4
 * Elementor tested up to: 3.25
 * Requires Plugins:      woocommerce
 * Author:            Derouiche Oussama
 * Author URI:        https://derouicheoussama.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       infinitycod
 * Domain Path:       /languages
 * Copyright:         © Derouiche Oussama
 * Protection:        DMCA — toute copie ou distribution non autorisée fera l’objet d’une plainte.
 */

defined( 'ABSPATH' ) || exit;

define( 'INFINITYCOD_VERSION', '5.21.1' );
define( 'INFINITYCOD_DB_VERSION', '1.7.0' );
define( 'INFINITYCOD_AUTHOR', 'Derouiche Oussama' );
define( 'INFINITYCOD_AUTHOR_URL', 'https://derouicheoussama.com' );
define( 'INFINITYCOD_FILE', __FILE__ );
define( 'INFINITYCOD_PATH', plugin_dir_path( __FILE__ ) );
define( 'INFINITYCOD_URL', plugin_dir_url( __FILE__ ) );
define( 'INFINITYCOD_BASENAME', plugin_basename( __FILE__ ) );

require_once INFINITYCOD_PATH . 'includes/Autoloader.php';

\InfinityCod\Autoloader::register();

/**
 * Instance principale (singleton).
 *
 * @return \InfinityCod\Core\Plugin
 */
function infinitycod() {
	return \InfinityCod\Core\Plugin::instance();
}

// Hébergement trop ancien : arrêt propre avec message (jamais de fatal).
if ( version_compare( PHP_VERSION, '7.4.0', '<' ) ) {
	add_action( 'admin_notices', function () {
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'InfinityCod :', 'infinitycod' ),
			esc_html__( 'PHP 7.4 ou supérieur est requis. Demandez à votre hébergeur de mettre à jour PHP, puis réactivez le plugin.', 'infinitycod' )
		);
	} );
	return;
}

add_action( 'plugins_loaded', function () {
	// WooCommerce obligatoire : on charge quand même pour afficher l'avis,
	// mais les modules fonctionnels restent inactifs.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'InfinityCod :', 'infinitycod' ),
				esc_html__( 'WooCommerce doit être installé et activé pour utiliser le plugin.', 'infinitycod' )
			);
		} );
		return;
	}

	infinitycod()->boot();
}, 5 );

register_activation_hook( __FILE__, array( '\InfinityCod\Core\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( '\InfinityCod\Core\Activator', 'deactivate' ) );

// Migrations de base de données et de réglages à CHAQUE requête admin :
// applique les montées de version quand INFINITYCOD_DB_VERSION change.
// Compatibilité HPOS ( WooCommerce High-Performance Order Storage ).
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_action( 'admin_init', array( '\InfinityCod\Core\Activator', 'maybe_upgrade' ) );
add_action( 'admin_init', array( '\InfinityCod\Core\Activator', 'apply_detected_defaults' ) );
