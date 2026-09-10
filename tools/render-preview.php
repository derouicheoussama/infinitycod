<?php
/**
 * Générateur d'aperçu visuel : rend le VRAI formulaire via le VRAI
 * FormManager::render() et l'écrit en HTML autonome (CSS inliné).
 *
 * Sortie : dist/preview/form-preview.html (desktop) — inspectable dans un
 * navigateur pour valider l'esthétique et l'absence de champs vides.
 *
 * Usage : .tools/php/php.exe tools/render-preview.php
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'INFINITYCOD_DEV_MODE', true );
define( 'ABSPATH', sys_get_temp_dir() . '/icod-fake-wp-preview/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['__options']    = array();
$GLOBALS['__transients'] = array();

class wpdb_stub {
	public $prefix = 'wp_';
	public function prepare( $q, ...$a ) { return $q; }
	public function get_var( $q ) { return null; }
	public function get_row( $q, $o = null ) { return null; }
	public function get_results( $q, $o = null ) { return array(); }
	public function get_col( $q = null, $x = 0 ) { return array(); }
	public function query( $q ) { return 0; }
	public function insert( $t, $d, $f = null ) { return 1; }
	public function update( $t, $d, $w, $f = null ) { return 1; }
	public function db_version() { return '8.0.36'; }
	public function get_charset_collate() { return ''; }
}
$GLOBALS['wpdb'] = new wpdb_stub();

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['__options'] ) ? $GLOBALS['__options'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['__options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['__options'][ $k ] ); return true; }
function get_transient( $k ) { return false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['__transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['__transients'][ $k ] ); return true; }
function add_action( ...$a ) { return true; }
function add_filter( ...$a ) { return true; }
function apply_filters( $t, $v ) { return $v; }
function do_action( ...$a ) {}
function __( $s, $d = null ) { return $s; }
function _e( $s, $d = null ) { echo $s; }
function esc_html__( $s, $d = null ) { return $s; }
function esc_attr__( $s, $d = null ) { return $s; }
function esc_html_e( $s, $d = null ) { echo htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr_e( $s, $d = null ) { echo htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_js( $s ) { return (string) $s; }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function wp_unslash( $v ) { return $v; }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ) ); }
function absint( $v ) { return abs( (int) $v ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function wp_generate_password( $len, $sp = true, $ex = true ) { return str_repeat( 'a', $len ); }
function wp_rand( $min = 0, $max = 0 ) { return $min + 1; }
function current_user_can( ...$c ) { return true; }
function check_admin_referer( ...$a ) {}
function check_ajax_referer( ...$a ) {}
function wp_create_nonce( ...$a ) { return 'nonce123'; }
function wp_nonce_field( ...$a ) { return true; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_shortcode( ...$a ) {}
function wp_register_style( ...$a ) { return true; }
function wp_register_script( ...$a ) { return true; }
function wp_enqueue_style( ...$a ) { return true; }
function wp_enqueue_script( ...$a ) { return true; }
function wp_style_is( ...$a ) { return false; }
function wp_script_is( ...$a ) { return false; }
function wp_localize_script( ...$a ) { return true; }
function did_action( $h ) { return 0; }
function is_rtl() { return false; }
function get_locale() { return 'fr_FR'; }
function get_post( $id = 0 ) { return null; }
function is_product() { return false; }
function is_singular( $t = '' ) { return false; }
function shortcode_atts( $d, $a, $s = '' ) { return array_merge( $d, (array) $a ); }
function wc_get_product( $id = 0 ) { return $id ? new FakeProduct() : null; }
function wc_get_products( $a = array() ) { return array(); }
function wc_attribute_label( $t ) { return $t; }
function wc_price( $n ) { return number_format( (float) $n, 0 ) . ' DA'; }
function wp_trim_words( $t, $n = 55, $m = '…' ) { $w = preg_split( '/\s+/', strip_tags( (string) $t ) ); return count( $w ) > $n ? implode( ' ', array_slice( $w, 0, $n ) ) . $m : $t; }
function wp_get_attachment_image_url( ...$a ) { return ''; }
function wp_next_scheduled( $h ) { return false; }
function register_activation_hook( ...$a ) { return true; }
function register_deactivation_hook( ...$a ) { return true; }
function load_plugin_textdomain( ...$a ) { return true; }
function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
function plugin_dir_path( $f ) { return trailingslashit( dirname( $f ) ); }
function plugin_dir_url( $f ) { return 'https://example.test/wp-content/plugins/' . basename( dirname( $f ) ) . '/'; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function selected( ...$a ) {}
function checked( ...$a ) {}
function disabled( ...$a ) {}
function add_menu_page( ...$a ) { return true; }
function add_submenu_page( ...$a ) { return true; }
function register_rest_route( ...$a ) { return true; }
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . $p; }
function wp_salt( $s = '' ) { return 'salt-' . $s; }
function get_bloginfo( $k = 'name' ) { return '6.5'; }

class WP_Error {}

class FakeProduct {
	public function get_id() { return 42; }
	public function get_name() { return 'Montre connectée Sport+ étanche IP68'; }
	public function get_price() { return 4500.0; }
	public function get_regular_price() { return 5200.0; }
	public function get_image_id() { return 0; }
	public function is_purchasable() { return true; }
	public function is_in_stock() { return true; }
	public function is_type( $t ) { return 'simple' === $t; }
	public function managing_stock() { return true; }
	public function get_stock_quantity() { return 7; }
	public function get_available_variations() { return array(); }
}

function infinitycod() {
	return new class {
		public function module( $slug ) { return null; }
	};
}

$plugin_dir = dirname( __DIR__ ) . '/infinitycod/';
define( 'INFINITYCOD_VERSION', 'preview' );
define( 'INFINITYCOD_DB_VERSION', 'preview' );
define( 'INFINITYCOD_PATH', $plugin_dir );
define( 'INFINITYCOD_URL', 'https://example.test/wp-content/plugins/infinitycod/' );
define( 'INFINITYCOD_BASENAME', 'infinitycod/infinitycod.php' );

require $plugin_dir . 'includes/Autoloader.php';
\InfinityCod\Autoloader::register();

/* Variantes d'aperçu. */
$variants = array(
	'default'    => array(),
	// Email + adresse + note activées, captcha + timer ON : cas complet.
	'complet'    => array(
		'captcha_enabled'          => 1,
		'captcha_provider'         => 'math',
		'timer_urgency_enabled'    => 1,
		'sticky_bar'               => 1,
		'show_stopdesk'            => 1,
		'checkout_fields'          => array(
			array( 'key' => 'name', 'type' => 'text', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'phone', 'type' => 'tel', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'email', 'type' => 'email', 'label' => '', 'on' => 1, 'req' => 0 ),
			array( 'key' => 'wilaya', 'type' => 'select', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'commune', 'type' => 'select', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'address', 'type' => 'text', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'cf_gouvernorat', 'type' => 'text', 'label' => 'Gouvernorat', 'on' => 1, 'req' => 0 ),
			array( 'key' => 'note', 'type' => 'textarea', 'label' => '', 'on' => 1, 'req' => 0 ),
		),
	),
	// Email masqué : vérifier AUCUN champ vide / espace résiduel.
	'complet_sans_email' => array(
		'checkout_fields' => array(
			array( 'key' => 'name', 'type' => 'text', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'phone', 'type' => 'tel', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'email', 'type' => 'email', 'label' => '', 'on' => 0, 'req' => 0 ),
			array( 'key' => 'wilaya', 'type' => 'select', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'commune', 'type' => 'select', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'address', 'type' => 'text', 'label' => '', 'on' => 0, 'req' => 0 ),
			array( 'key' => 'note', 'type' => 'textarea', 'label' => '', 'on' => 0, 'req' => 0 ),
		),
	),
	// Couleur d'accent personnalisée (rouge) : TOUT le formulaire se teinte.
	'accent_rouge' => array(
		'accent_color'             => '#D90429',
		'captcha_enabled'          => 1,
		'captcha_provider'         => 'math',
		'timer_urgency_enabled'    => 1,
		'checkout_fields'          => array(
			array( 'key' => 'name', 'type' => 'text', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'phone', 'type' => 'tel', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'wilaya', 'type' => 'select', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'commune', 'type' => 'select', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'note', 'type' => 'textarea', 'label' => '', 'on' => 0, 'req' => 0 ),
		),
	),
	// Mode sombre forcé.
	'sombre' => array(
		'form_theme'               => 'dark',
		'accent_color'             => '#1971C2',
		'timer_urgency_enabled'    => 1,
		'checkout_fields'          => array(
			array( 'key' => 'name', 'type' => 'text', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'phone', 'type' => 'tel', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'wilaya', 'type' => 'select', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'commune', 'type' => 'select', 'label' => '', 'on' => 1, 'req' => 1 ),
		),
	),
	// Personnalisation avancée : chaque valeur remplie doit s'appliquer.
	'personnalise' => array(
		'accent_color'      => '#0E7A4F',
		'button_color'      => '#111111',
		'text_color'        => '#3B2F2F',
		'border_color'      => '#C9B8A3',
		'background_color'  => '#F5EFE6',
		'border_radius'     => '0',
		'form_padding'      => '34',
		'checkout_fields'   => array(
			array( 'key' => 'name', 'type' => 'text', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'phone', 'type' => 'tel', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'wilaya', 'type' => 'select', 'label' => '', 'on' => 1, 'req' => 1 ),
			array( 'key' => 'commune', 'type' => 'select', 'label' => '', 'on' => 1, 'req' => 1 ),
		),
	),
);

$out_dir = dirname( __DIR__ ) . '/dist/preview/';
if ( ! is_dir( $out_dir ) ) { mkdir( $out_dir, 0777, true ); }

$css = file_get_contents( $plugin_dir . 'assets/front/css/form.css' );

$form = infinitycod_module();
foreach ( $variants as $name => $saved ) {
	$GLOBALS['__options']['infinitycod_settings'] = $saved ? array_merge( \InfinityCod\Core\Settings::defaults(), $saved ) : array();
	\InfinityCod\Core\Settings::setCache( null );

	$html = $form->render( 42, '', '' );

	$page = '<!doctype html><html lang="fr"><head><meta charset="utf-8" />'
		. '<meta name="viewport" content="width=device-width, initial-scale=1" />'
		. '<title>Aperçu ' . $name . '</title><style>' . $css . '</style></head>'
		. '<body style="margin:0;padding:24px;background:#e8ecf1">' . $html . '</body></html>';

	$file = $out_dir . 'form-' . $name . '.html';
	file_put_contents( $file, $page );
	echo "✓ {$file}\n";
}

function infinitycod_module() {
	static $form = null;
	if ( null === $form ) { $form = new \InfinityCod\Form\FormManager(); }
	return $form;
}
