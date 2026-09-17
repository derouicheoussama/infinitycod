<?php
/**
 * ∞ Infinity Coder — InfinityMarket
 *
 * Thème e-commerce pour le marché algérien : formulaire COD (paiement à la
 * livraison, 58 wilayas), tableau de bord admin bleu, mises à jour intégrées.
 *
 * Copyright © 2026 Derouiche Oussama — ∞ Infinity Coder
 * Site : https://www.derouicheoussama.com
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INF_THEME_VERSION', '2.0.0' );
define( 'INF_THEME_NAME', 'InfinityMarket' );
define( 'INF_THEME_SLUG', 'infinity-market' );
define( 'INF_THEME_LABEL', 'Infinity Market' );
define( 'INF_THEME_DIR', trailingslashit( get_template_directory() ) );
define( 'INF_THEME_URI', trailingslashit( get_template_directory_uri() ) );
define( 'INF_COPYRIGHT', '∞ Infinity Coder' );

/**
 * API des mises à jour (manifeste JSON) — voir updates/update.json à héberger.
 */
define( 'INF_UPDATES_API', 'https://raw.githubusercontent.com/derouicheoussama/infinity-themes/main/updates/update.json' );

/**
 * API de licence (optionnelle) — la clé est ajoutée à l'URL du package.
 */
define( 'INF_LICENSE_API', 'https://www.derouicheoussama.com/api/license/' );

require_once INF_THEME_DIR . 'inc/setup.php';
require_once INF_THEME_DIR . 'inc/template-tags.php';
require_once INF_THEME_DIR . 'inc/customizer.php';
require_once INF_THEME_DIR . 'inc/wilayas.php';
require_once INF_THEME_DIR . 'inc/dashboard.php';
require_once INF_THEME_DIR . 'inc/updates.php';
require_once INF_THEME_DIR . 'inc/cod-form.php';
