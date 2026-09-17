<?php
/**
 * Configuration du thème — ∞ Infinity Coder
 *
 * @package infinity-market
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supports du thème, menus, tailles d'images.
 */
add_action( 'after_setup_theme', 'inf_theme_setup' );
function inf_theme_setup() {
	load_theme_textdomain( 'infinity-market', INF_THEME_DIR . 'languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo', array( 'height' => 90, 'width' => 260, 'flex-height' => true, 'flex-width' => true ) );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'automatic-feed-links' );

	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	add_image_size( 'inf-product', 600, 600, true );
	add_image_size( 'inf-hero', 1200, 700, true );

	register_nav_menus(
		array(
			'primary' => __( 'Menu principal', 'infinity-market' ),
			'footer'  => __( 'Menu pied de page', 'infinity-market' ),
		)
	);
}

/**
 * Widgets : colonne latérale + 3 colonnes de pied de page.
 */
add_action( 'widgets_init', 'inf_widgets_init' );
function inf_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Colonne latérale', 'infinity-market' ),
			'id'            => 'sidebar-1',
			'before_widget' => '<section id="%1$s" class="inf-widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h3 class="inf-widget__title">',
			'after_title'   => '</h3>',
		)
	);
	for ( $i = 1; $i <= 3; $i++ ) {
		register_sidebar(
			array(
				/* translators: %d : numéro de colonne. */
				'name'          => sprintf( __( 'Pied de page — colonne %d', 'infinity-market' ), $i ),
				'id'            => 'footer-' . $i,
				'before_widget' => '<section id="%1$s" class="inf-widget %2$s">',
				'after_widget'  => '</section>',
				'before_title'  => '<h4 class="inf-widget__title">',
				'after_title'   => '</h4>',
			)
		);
	}
}

/**
 * Scripts et styles frontend.
 */
add_action( 'wp_enqueue_scripts', 'inf_enqueue_assets' );
function inf_enqueue_assets() {
	wp_enqueue_style( 'inf-main', get_stylesheet_uri(), array(), INF_THEME_VERSION );
	wp_style_add_data( 'inf-main', 'rtl', 'replace' );

	wp_enqueue_script( 'inf-main', INF_THEME_URI . 'assets/js/main.js', array(), INF_THEME_VERSION, true );

	$inf_active_wilayas = array();
	foreach ( inf_get_wilayas() as $inf_code => $inf_w ) {
		if ( ! empty( $inf_w['active'] ) ) {
			$inf_active_wilayas[ $inf_code ] = array(
				'name' => $inf_w['fr'],
				'ar'   => $inf_w['ar'],
				'home' => (int) $inf_w['home'],
				'desk' => (int) $inf_w['desk'],
			);
		}
	}

	wp_localize_script(
		'inf-main',
		'infData',
		array(
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => wp_create_nonce( 'inf_cod' ),
			'currency'         => __( 'DA', 'infinity-market' ),
			'freeShipping'     => (int) inf_get_setting( 'free_shipping', 0 ),
			'freeLabel'        => __( 'Livraison gratuite', 'infinity-market' ),
			'homeLabel'        => __( 'À domicile', 'infinity-market' ),
			'deskLabel'        => __( 'Bureau (Stopdesk)', 'infinity-market' ),
			'subtotalLabel'    => __( 'Sous-total', 'infinity-market' ),
			'deliveryLabel'    => __( 'Livraison', 'infinity-market' ),
			'totalLabel'       => __( 'Total à payer', 'infinity-market' ),
			'wilayas'          => $inf_active_wilayas,
		)
	);
}

/**
 * Classe body pour l'admin Infinity (dashboard bleu).
 */
add_filter( 'admin_body_class', 'inf_admin_body_class' );
function inf_admin_body_class( $classes ) {
	$inf_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 0 === strpos( $inf_page, 'inf-' ) ) {
		$classes .= ' inf-admin-page';
	}
	if ( get_current_screen() && 'inf_cod_order' === get_current_screen()->post_type ) {
		$classes .= ' inf-admin-orders';
	}
	return $classes;
}

/**
 * Longueur de l'extrait.
 */
add_filter( 'excerpt_length', fn() => 22 );
add_filter( 'excerpt_more', fn() => '…' );
