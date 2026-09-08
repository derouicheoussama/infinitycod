<?php
/**
 * Plugin Name:       InfinityCod — Paiement à la livraison (COD Algérie)
 * Plugin URI:        https://infinitycoder.app/infinitycod
 * Description:       Solution COD tout-en-un pour WooCommerce Algérie : formulaire de commande rapide, 58 wilayas & 1541 communes, tarifs domicile/stopdesk, anti-fraude, transporteurs intégrés (Yalidine, ZR Express, Maystro, Noest, Guepex…), WhatsApp automatique, offres par quantité et statistiques P&L.
 * Version:           1.3.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * Author:            Infinity Coder
 * Author URI:        https://infinitycoder.app
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       infinitycod
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'INFINITYCOD_VERSION', '1.3.0' );
define( 'INFINITYCOD_DB_VERSION', '1.1.0' );
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
