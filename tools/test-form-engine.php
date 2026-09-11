<?php
define( 'INFINITYCOD_DEV_MODE', true );
/**
 * Harnais de test : Form Engine — Checkout Builder → Rendu → Validation.
 *
 * Vérifie la chaîne complète DASHBOARD → SETTINGS → CONFIG → FORM ENGINE :
 * plan des champs (ordre / visibilité / requis / libellés), captcha par
 * fournisseur, prix multi-devises, sanitization, migration idempotente,
 * tarification wilaya vide, et anti-régressions de source (les bugs
 * historiques : $tab utilisé avant définition, captcha lu dans $_POST,
 * $rtl utilisé avant définition, regex /D/g).
 *
 * Usage : .tools/php/php.exe tools/test-form-engine.php
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'ABSPATH', sys_get_temp_dir() . '/icod-fake-wp-engine/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

/* ---------- Stubs WordPress ---------- */

$GLOBALS['__options']    = array();
$GLOBALS['__transients'] = array();
$GLOBALS['__captured_json'] = null;

class wpdb_stub {
	public $prefix = 'wp_';
	public function prepare( $q, ...$a ) { return $q; }
	public function get_var( $q ) { return null; }
	public function get_row( $q, $o = null ) { return null; }
	public function get_results( $q, $o = null ) { return array(); }
	public function query( $q ) { return 0; }
}
$GLOBALS['wpdb'] = new wpdb_stub();

function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['__options'] ) ? $GLOBALS['__options'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['__options'][ $k ] = $v; return true; }
function add_option( $k, $v ) { $GLOBALS['__options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['__options'][ $k ] ); return true; }
function get_transient( $k ) { return array_key_exists( $k, $GLOBALS['__transients'] ) ? $GLOBALS['__transients'][ $k ] : false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['__transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['__transients'][ $k ] ); return true; }
function wp_cache_delete( ...$a ) { return true; }
function wp_using_ext_object_cache() { return false; }
function add_action( ...$a ) { return true; }
function add_filter( ...$a ) { return true; }
function remove_filter( ...$a ) { return true; }
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
function sanitize_email( $v ) { return filter_var( (string) $v, FILTER_SANITIZE_EMAIL ); }
function is_email( $v ) { return false !== filter_var( (string) $v, FILTER_VALIDATE_EMAIL ); }
function absint( $v ) { return abs( (int) $v ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function wp_generate_password( $len, $sp = true, $ex = true ) { return str_repeat( 'a', $len ); }
function wp_rand( $min = 0, $max = 0 ) { return $min; }
function current_user_can( ...$c ) { return true; }
function check_admin_referer( ...$a ) {}
function check_ajax_referer( ...$a ) {}
function wp_create_nonce( ...$a ) { return 'nonce123'; }
function wp_nonce_field( ...$a ) { return true; }
function wp_nonce_url( $u, $a ) { return $u . '&_wpnonce=x'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_safe_redirect( $u ) { throw new RuntimeException( 'redirect: ' . $u ); }
function wp_send_json_success( $d = null ) { $GLOBALS['__captured_json'] = array( 'success' => true, 'data' => $d ); }
function wp_send_json_error( $d = null ) { $GLOBALS['__captured_json'] = array( 'success' => false, 'data' => $d ); }
function add_shortcode( ...$a ) {}
function wp_register_style( ...$a ) { return true; }
function wp_register_script( ...$a ) { return true; }
function wp_enqueue_style( ...$a ) { return true; }
function wp_enqueue_script( ...$a ) { return true; }
function wp_style_is( ...$a ) { return false; }
function wp_localize_script( ...$a ) { return true; }
function did_action( $h ) { return 0; }
function is_rtl() { return false; }
function get_locale() { return 'fr_FR'; }
function get_post( $id = 0 ) { return null; }
function is_product() { return false; }
function is_singular( $t = '' ) { return false; }
function shortcode_atts( $d, $a, $s = '' ) { return array_merge( $d, (array) $a ); }
function wc_get_product( $id = 0 ) { return null; }
function wc_get_products( $a = array() ) { return array(); }
function wp_get_attachment_image_url( ...$a ) { return ''; }
function wp_next_scheduled( $h ) { return false; }
function wp_schedule_event( ...$a ) { return true; }
function register_activation_hook( ...$a ) { return true; }
function register_deactivation_hook( ...$a ) { return true; }
function load_plugin_textdomain( ...$a ) { return true; }
function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
function plugin_dir_path( $f ) { return trailingslashit( dirname( $f ) ); }
function plugin_dir_url( $f ) { return 'https://example.test/wp-content/plugins/' . basename( dirname( $f ) ) . '/'; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function wp_die( $m = '' ) { throw new RuntimeException( 'wp_die: ' . $m ); }
function selected( ...$a ) {}
function checked( ...$a ) {}
function disabled( ...$a ) {}
function add_menu_page( ...$a ) { return true; }
function add_submenu_page( ...$a ) { return true; }
$GLOBALS['__routes'] = array();
function register_rest_route( $ns, $route, $args = array() ) { $GLOBALS['__routes'][] = array( 'ns' => $ns, 'route' => $route, 'args' => $args ); return true; }
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . $p; }
function rest_ensure_response( $d ) { return $d; }
function wp_remote_post( ...$a ) { return array( 'response' => array( 'code' => 200 ), 'body' => '{}' ); }
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) ? (int) $r['response']['code'] : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) ? (string) $r['body'] : ''; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function get_bloginfo( $k = 'name' ) { return '6.5'; }

class WP_Error {
	public $errors = array();
	public function __construct( $code = '', $message = '' ) { $this->errors[ $code ] = $message; }
	public function get_error_code() { return key( $this->errors ); }
	public function get_error_message() { return (string) reset( $this->errors ); }
}

class FakeProduct {
	public function get_id() { return 42; }
}

class FakeRequest {
	public function get_param( $k ) { return '16'; }
	public function get_json_params() { return array(); }
}

function infinitycod() {
	return new class {
		public function module( $slug ) { return null; }
	};
}

/* ---------- Chargement du plugin ---------- */

$plugin_dir = dirname( __DIR__ ) . '/infinitycod/';
define( 'INFINITYCOD_VERSION', 'test' );
define( 'INFINITYCOD_DB_VERSION', 'test' );
define( 'INFINITYCOD_PATH', $plugin_dir );
define( 'INFINITYCOD_URL', 'https://example.test/wp-content/plugins/infinitycod/' );
define( 'INFINITYCOD_BASENAME', 'infinitycod/infinitycod.php' );
define( 'INFINITYCOD_AUTHOR', 'Derouiche Oussama' );

require $plugin_dir . 'includes/Autoloader.php';
\InfinityCod\Autoloader::register();

/* ---------- Helpers ---------- */

$pass = 0;
$fail = 0;

function check( $label, $condition ) {
	global $pass, $fail;
	if ( $condition ) {
		echo "   ✓ {$label}\n";
		$pass++;
	} else {
		echo "   ✗ {$label}\n";
		$fail++;
	}
}

$refm = static function ( $class, $method ) {
	$m = new ReflectionMethod( $class, $method );
	$m->setAccessible( true );
	return $m;
};

$set_saved = static function ( array $saved ) {
	$GLOBALS['__options']['infinitycod_settings'] = $saved;
	\InfinityCod\Core\Settings::setCache( null );
};

echo "=== InfinityCod — Form Engine (Checkout Builder → Rendu → Validation) ===\n\n";

/* ---------- 1. Plan des champs (défauts) ---------- */

echo "1) Plan des champs par défaut\n";
$plan = \InfinityCod\Form\FormManager::fields_plan();
check( '7 champs standard présents', array( 'name','phone','email','wilaya','commune','address','note' ) === array_column( $plan, 'key' ) );
$by_key = array_column( $plan, null, 'key' );
check( 'nom + téléphone actifs et requis', $by_key['name']['on'] && $by_key['name']['req'] && $by_key['phone']['on'] && $by_key['phone']['req'] );
check( 'wilaya + commune actives et requises', $by_key['wilaya']['on'] && $by_key['wilaya']['req'] && $by_key['commune']['on'] && $by_key['commune']['req'] );
check( 'email / adresse / note masqués par défaut', ! $by_key['email']['on'] && ! $by_key['address']['on'] && ! $by_key['note']['on'] );
check( 'field_state(email) = masqué', ! \InfinityCod\Form\FormManager::field_state( 'email' )['on'] );

/* ---------- 2. Rendu piloté par le plan ---------- */

echo "\n2) Rendu HTML piloté par le plan (reflexion render_fields_html)\n";
$product = new FakeProduct();
$render_fields = $refm( '\InfinityCod\Form\FormManager', 'render_fields_html' );
$html = $render_fields->invoke( null, $plan, $product, '<option value="01">Adrar</option>', '' );
check( 'nom + téléphone appariés (icod-duo)', false !== strpos( $html, 'icod-duo' ) );
check( 'email ABSENT du HTML (champ masqué)', false === strpos( $html, 'name="icod_email"' ) );
check( 'adresse ABSENTE du HTML (champ masqué)', false === strpos( $html, 'name="icod_address"' ) );
check( 'lien « Ajouter une note » ABSENT (note masquée)', false === strpos( $html, 'data-note-toggle' ) );
check( 'options wilaya injectées', false !== strpos( $html, '<option value="01">Adrar</option>' ) );

/* Visibilité email ON */
$saved = $GLOBALS['__options']['infinitycod_settings'] ?? array();
$plan2 = json_decode( wp_json_encode( $plan ), true );
foreach ( $plan2 as $i => $f ) { if ( 'email' === $f['key'] ) { $plan2[ $i ]['on'] = 1; } }
$html2 = $render_fields->invoke( null, $plan2, $product, '', '' );
check( 'email ACTIVÉ → champ réel dans le HTML', false !== strpos( $html2, 'name="icod_email"' ) );

/* Adresse ON + note ON */
$plan3 = $plan2;
foreach ( $plan3 as $i => $f ) {
	if ( 'address' === $f['key'] ) { $plan3[ $i ]['on'] = 1; $plan3[ $i ]['req'] = 1; }
	if ( 'note' === $f['key'] ) { $plan3[ $i ]['on'] = 1; }
}
$html3 = $render_fields->invoke( null, $plan3, $product, '', '' );
check( 'adresse ACTIVÉE requise → required + data-req="1" + astérisque CSS', false !== strpos( $html3, 'name="icod_address"' ) && false !== strpos( $html3, 'data-req="1"' ) && false !== strpos( $html3, 'icod-required' ) );
check( 'note ACTIVÉE → lien toggle + textarea rendus', false !== strpos( $html3, 'data-note-toggle' ) && false !== strpos( $html3, 'name="icod_note"' ) );

/* Ordre inversé : téléphone AVANT nom */
$plan4 = array( $by_key['phone'], $by_key['name'] );
$html4 = $render_fields->invoke( null, $plan4, $product, '', '' );
check( 'ordre du builder respecté (phone avant name)', strpos( $html4, 'icod_phone' ) < strpos( $html4, 'icod_name' ) );

/* Champ personnalisé requis */
$plan5 = array( array( 'key' => 'cf_gouvernorat', 'type' => 'text', 'label' => 'Gouvernorat', 'on' => 1, 'req' => 1, 'custom' => true ) );
$html5 = $render_fields->invoke( null, $plan5, $product, '', '' );
check( 'champ cf_* requis rendu avec required', false !== strpos( $html5, 'name="cf_gouvernorat"' ) && false !== strpos( $html5, 'required data-req="1"' ) );

/* ---------- 3. Captcha par fournisseur ---------- */

echo "\n3) Captcha : OFF = rien ; ON = chaîne complète\n";
check( 'captcha désactivé → provider "off"', 'off' === \InfinityCod\Form\FormManager::captcha_provider() );
$set_saved( array( 'captcha_enabled' => 1, 'captcha_provider' => 'math' ) );
check( 'activé + math → "math"', 'math' === \InfinityCod\Form\FormManager::captcha_provider() );
$set_saved( array( 'captcha_enabled' => 1, 'captcha_provider' => 'recaptcha_v3' ) );
check( 'reCAPTCHA sans clés → repli "off" (aucune commande bloquée)', 'off' === \InfinityCod\Form\FormManager::captcha_provider() );
$set_saved( array( 'captcha_enabled' => 1, 'captcha_provider' => 'recaptcha_v3', 'recaptcha_v3_site_key' => 'SITE', 'recaptcha_v3_secret_key' => 'SECRET' ) );
check( 'reCAPTCHA avec clés → "recaptcha_v3"', 'recaptcha_v3' === \InfinityCod\Form\FormManager::captcha_provider() );
$set_saved( array() );

/* ---------- 4. Prix multi-devises ---------- */

echo "\n4) Prix multi-devises (format_price)\n";
check( 'DZD après montant → « 600 DA »', '600 DA' === \InfinityCod\Core\Settings::format_price( 600 ) );
$set_saved( array( 'currency' => 'USD', 'currency_position' => 'left' ) );
check( 'USD avant montant → « $ 19.99 »', '$ 19.99' === \InfinityCod\Core\Settings::format_price( 19.99 ) );
$set_saved( array() );

/* ---------- 5. Sanitization (schéma déclaratif) ---------- */

echo "\n5) Sanitization des réglages (schéma)\n";
$page = new \InfinityCod\Admin\Pages\SettingsPage();
$sanitize = $refm( '\InfinityCod\Admin\Pages\SettingsPage', 'sanitize_fields' );
$clean = $sanitize->invoke( $page, array(
	'accent_color'     => '<script>alert(1)</script>',
	'captcha_provider' => 'evil',
	'qty_min'          => '500',
	'form_preset'      => 'aqua',
), 'form' );
check( 'couleur invalide rejetée (pas de XSS en CSS)', ! isset( $clean['accent_color'] ) || '#0e7a4f' === $clean['accent_color'] );
check( 'enum invalide rejetée', ! isset( $clean['captcha_provider'] ) || 'math' === $clean['captcha_provider'] );
check( 'entier borné (qty_min 500 → 99)', isset( $clean['qty_min'] ) && 99 === $clean['qty_min'] );
check( 'enum valide acceptée', isset( $clean['form_preset'] ) && 'aqua' === $clean['form_preset'] );

/* ---------- 6. Migration idempotente legacy → builder ---------- */

echo "\n6) Migration idempotente (show_email legacy → builder)\n";
delete_option( 'infinitycod_builder_migrated' );
$defaults = \InfinityCod\Core\Settings::defaults();
$saved = $defaults;
foreach ( $saved['checkout_fields'] as $i => $f ) { if ( 'email' === $f['key'] ) { $saved['checkout_fields'][ $i ]['on'] = 0; } }
$saved['show_email'] = 1; // Ancien site : email affiché via l'ancien toggle.
$set_saved( $saved );
$all = \InfinityCod\Core\Settings::all();
$email_on = null;
foreach ( $all['checkout_fields'] as $f ) { if ( 'email' === $f['key'] ) { $email_on = $f['on']; } }
check( 'show_email=1 historique → email ACTIF dans le builder', 1 === $email_on );
check( 'marker de migration posé', (bool) get_option( 'infinitycod_builder_migrated' ) );
// Re-exécution : ne change plus rien (idempotence).
$saved2 = $all;
$saved2['show_email'] = 0;
$set_saved( $saved2 );
$all2 = \InfinityCod\Core\Settings::all();
$email_on2 = null;
foreach ( $all2['checkout_fields'] as $f ) { if ( 'email' === $f['key'] ) { $email_on2 = $f['on']; } }
check( 'migration JAMAIS réexécutée (idempotente)', 1 === $email_on2 );

/* ---------- 7. Tarification wilaya vide (champ masqué) ---------- */

echo "\n7) Tarifs avec wilaya masquée (code vide → tarif par défaut)\n";
$rates = new \InfinityCod\Shipping\RatesManager();
check( 'domicile → 600 DA (défaut)', 600.0 === $rates->price( '' ) );
check( 'stopdesk → 350 DA (défaut)', 350.0 === $rates->price( '', '', \InfinityCod\Shipping\RatesManager::MODE_DESK ) );
check( 'pas de gratuité accidentelle', false === $rates->is_free( '', 10, 99999 ) );

/* ---------- 8. Anti-régressions de source (bugs historiques) ---------- */

echo "\n8) Anti-régressions de source\n";
$routes_src = file_get_contents( $plugin_dir . 'includes/rest/class-routes.php' );
check( 'captcha lu dans le corps JSON (pas $_POST)', false !== strpos( $routes_src, '$body[\'icod_captcha\']' ) && false === strpos( $routes_src, '$_POST[\'icod_captcha\']' ) );
check( 'WP_Error qualifié pour le captcha (pas d\'erreur fatale)', false !== strpos( $routes_src, "new \\WP_Error( 'icod_captcha'" ) );

$sp_src = file_get_contents( $plugin_dir . 'includes/admin/pages/class-settings-page.php' );
$tab_pos = strpos( $sp_src, '$tab = isset( $_POST[' . "'tab']" . ' )' );
$use_pos = strpos( $sp_src, 'sanitize_fields( $raw, $tab )' );
check( '$tab défini AVANT usage (champs customs sauvegardés)', false !== $tab_pos && false !== $use_pos && $tab_pos < $use_pos );

$fm_src = file_get_contents( $plugin_dir . 'includes/form/class-form-manager.php' );
$rtl_def = strpos( $fm_src, '$rtl = \InfinityCod\Core\I18n::is_rtl();' );
$rtl_use = strpos( $fm_src, 'if ( $rtl ) {' );
check( '$rtl défini AVANT usage (police arabe chargée)', false !== $rtl_def && false !== $rtl_use && $rtl_def < $rtl_use );

$js_src = file_get_contents( $plugin_dir . 'assets/front/js/form.js' );
check( 'form.js envoie le captcha + token + adresse', false !== strpos( $js_src, 'icod_captcha:' ) && false !== strpos( $js_src, 'icod_cap_token:' ) && false !== strpos( $js_src, 'address:' ) );
check( 'regex téléphone JS correcte (/\\D/g, pas /D/g)', false !== strpos( $js_src, "replace(/\\D/g, '')" ) && false === strpos( $js_src, "replace(/D/g,'')" ) );

/* ---------- 9. Handler aperçu (nonce + capability + brouillon) ---------- */

echo "\n9) Aperçu en direct (handler AJAX, VRAI renderer)\n";
$_GET = array();
$GLOBALS['__captured_json'] = null;
$page2 = new \InfinityCod\Admin\Pages\SettingsPage();
$_POST = array(
	'nonce'      => 'nonce123',
	'product_id' => '0',
	'icod'       => array( 'accent_color' => '#ff0000', 'form_title' => 'Aperçu test' ),
);
$page2->handle_preview_form();
check( 'handler exécutable : JSON succès capturé', is_array( $GLOBALS['__captured_json'] ) && true === $GLOBALS['__captured_json']['success'] );
check( 'brouillon NON enregistré en base', ! isset( $GLOBALS['__options']['infinitycod_settings']['form_title'] ) || 'Aperçu test' !== $GLOBALS['__options']['infinitycod_settings']['form_title'] );

/* ---------- 10. Enregistrement REST + endpoint communes ---------- */

echo "\n10) Routes REST : enregistrement exécuté + callback communes (anti-régression ns())\n";
try {
	$routes_obj = new \InfinityCod\Rest\Routes();
	$routes_obj->routes();
	check( 'routes() exécuté sans fatal (bug ns() éliminé)', count( $GLOBALS['__routes'] ) >= 6 );
	$paths = array_column( $GLOBALS['__routes'], 'route' );
	check( 'communes / stopdesks / quote / submit enregistrées', in_array( '/communes', $paths, true ) && in_array( '/quote', $paths, true ) && in_array( '/submit', $paths, true ) );
	$communes_args = $GLOBALS['__routes'][ array_search( '/communes', $paths, true ) ]['args'];
	$out = call_user_func( $communes_args['callback'], new FakeRequest() );
	check( 'callback communes exécutable et retourne un tableau', is_array( $out ) && isset( $out['communes'] ) );
} catch ( \Throwable $e ) {
	check( 'routes()/communes : ' . $e->getMessage(), false );
}

/* ---------- 11. Journal des modifications local (CHANGELOG.md embarqué) ---------- */

echo "\n11) Onglet « Journal des modifications » : parseur local embarqué\n";
$fixture = "# Changelog\n\n## 9.9.9 — 2026-01-01\n\n### Corrigé\n- **Gras** et texte <script>alert(1)</script>\n- deuxième item\n\n## 1.0.0 — ancienne version\n\ntexte\n";
file_put_contents( $plugin_dir . 'CHANGELOG.md', $fixture );
try {
	$cl_parser = $refm( '\InfinityCod\License\Updater', 'changelog_local' );
	$html_cl   = $cl_parser->invoke( new \InfinityCod\License\Updater() );
	check( 'versions et puces converties en HTML', false !== strpos( $html_cl, '9.9.9' ) && false !== strpos( $html_cl, '<li>' ) );
	check( 'XSS échappé (script neutralisé)', false === strpos( $html_cl, '<script>' ) && false !== strpos( $html_cl, '&lt;script&gt;' ) );
	check( 'gras **x** converti en <strong>', false !== strpos( $html_cl, '<strong>Gras</strong>' ) );
	check( 'plusieurs versions affichées', false !== strpos( $html_cl, '1.0.0' ) );
} catch ( \Throwable $e ) {
	check( 'parseur changelog : ' . $e->getMessage(), false );
} finally {
	@unlink( $plugin_dir . 'CHANGELOG.md' );
}

/* ---------- 12. Palette d'accent + thème sombre + surcharge Elementor ---------- */

echo "\n12) Couleur d'accent réelle + mode sombre + presets\n";
$pal = \InfinityCod\Core\Settings::accent_palette( '#FF0000' );
check( 'palette : nuances hexadécimales valides', preg_match( '/^#[0-9A-F]{6}$/', $pal['accent'] ) && preg_match( '/^#[0-9A-F]{6}$/', $pal['dark'] ) && in_array( $pal['ink'], array( '#FFFFFF', '#1D2327' ), true ) );
check( 'nuance foncée distincte de la base', $pal['dark'] !== $pal['accent'] );
check( 'contraste : accent clair → texte sombre', '#1D2327' === \InfinityCod\Core\Settings::accent_palette( '#FFFF00' )['ink'] );

$css = file_get_contents( $plugin_dir . 'assets/front/css/form.css' );
check( 'CSS : mode sombre forcé + auto (préférence système)', false !== strpos( $css, '.icod-root[data-theme="dark"]' ) && false !== strpos( $css, 'prefers-color-scheme:dark' ) );
check( 'CSS : bouton piloté par variables (plus de dégradé codé en dur)', false !== strpos( $css, 'var(--icod-btn-bg)' ) && false === strpos( $css, 'linear-gradient(135deg,#1877c2,#0e7a4f)' ) );
check( 'CSS : les 5 styles d’écran de succès existent', false !== strpos( $css, '.icod-success-confetti' ) && false !== strpos( $css, '.icod-success-ticket' ) && false !== strpos( $css, '.icod-success-minimal' ) && false !== strpos( $css, '.icod-success-celebration' ) );

$fm_now = file_get_contents( $plugin_dir . 'includes/form/class-form-manager.php' );
check( 'PHP : data-theme + palette inline + surcharge Elementor appliquée', false !== strpos( $fm_now, 'data-theme=' ) && false !== strpos( $fm_now, "apply_filters( 'infinitycod_widget_preset'" ) && false !== strpos( $fm_now, 'accent_palette(' ) );

/* ---------- 13. Badge commandes + réglages Avancé branchés ---------- */

echo "\n13) Badge commandes en attente + réglages Avancé réellement lus\n";
$am_src = file_get_contents( $plugin_dir . 'includes/admin/class-admin-manager.php' );
check( 'badge présent sur le menu InfinityCod ET Commandes COD', false !== strpos( $am_src, "'infinitycod-orders' === \$sitem[2]" ) && false !== strpos( $am_src, "icod-menu-badge" ) );
check( 'badge = comptage des commandes pending', false !== strpos( $am_src, "status = 'pending'" ) );

$logger_src = file_get_contents( $plugin_dir . 'includes/logging/class-logger.php' );
check( 'log_enabled respecté par le Logger (plus d\'option morte)', false !== strpos( $logger_src, "Settings::get( 'log_enabled', 1 )" ) );

$shield_src = file_get_contents( $plugin_dir . 'includes/anti-fraud/class-shield.php' );
check( 'anti-fraude : limites IP/téléphone/email par jour branchées', false !== strpos( $shield_src, 'max_per_ip_day' ) && false !== strpos( $shield_src, 'max_per_phone_day' ) && false !== strpos( $shield_src, 'max_per_email_day' ) && false !== strpos( $shield_src, 'block_duplicate_phone' ) );

/* ---------- 14. Verrou licence : option développeur, invisible marchand ---------- */

echo "\n14) Verrou de formulaire : réservé au vendeur, absent du dashboard client\n";
$sp2 = file_get_contents( $plugin_dir . 'includes/admin/pages/class-settings-page.php' );
check( 'AUCUNE case à cocher du verrou dans l\'UI marchand', false === strpos( $sp2, 'name="icod[license_lock_form]"' ) );
check( 'verrou absent du schéma de sauvegarde', false === strpos( $sp2, "'license_lock_form'    => array" ) );
$set_src = file_get_contents( $plugin_dir . 'includes/core/class-settings.php' );
check( 'priorité constante wp-config (INFINITYCOD_LOCK_FORM)', false !== strpos( $set_src, "defined( 'INFINITYCOD_LOCK_FORM' )" ) );
$set_saved( array( 'license_lock_form' => 1 ) );
check( 'valeur stockée (masquée) toujours honorée', \InfinityCod\Core\Settings::lock_form_enabled() );
check( 'FormManager lit le verrou centralisé', false !== strpos( $fm_now, 'Settings::lock_form_enabled()' ) );
$set_saved( array() );

/* ---------- 15. Persistance + menu renommé ---------- */

echo "\n15) Persistance + menu « Commandes »\n";
$fm_hud = file_get_contents( $plugin_dir . 'includes/form/class-form-manager.php' );
check( 'HUD admin SUPPRIMÉ du formulaire (demande marchand)', false === strpos( $fm_hud, 'icod-admin-hud' ) );
$css_hud = file_get_contents( $plugin_dir . 'assets/front/css/form.css' );
check( 'CSS du HUD supprimée', false === strpos( $css_hud, 'icod-admin-hud' ) );
$diag_src = file_get_contents( $plugin_dir . 'includes/admin/pages/class-diagnostics-page.php' );
check( 'Diagnostics : test d\'écriture → lecture (persistance)', false !== strpos( $diag_src, 'icod_write_test' ) );
check( 'Diagnostics : réglages stockés + dernière sauvegarde', false !== strpos( $diag_src, 'icod_settings_saved_at' ) );
$sp3 = file_get_contents( $plugin_dir . 'includes/admin/pages/class-settings-page.php' );
check( 'Sauvegarde horodatée (icod_settings_saved_at)', false !== strpos( $sp3, "update_option( 'icod_settings_saved_at'" ) );
check( 'Sous-menu renommé « Commandes » (sans « COD »)', false !== strpos( $am_src, "__( 'Commandes', 'infinitycod' )" ) && false === strpos( $am_src, "__( 'Commandes COD', 'infinitycod' )" ) );
$css_src = file_get_contents( $plugin_dir . 'assets/front/css/form.css' );
check( 'CSS du HUD supprimée', false === strpos( $css_src, 'icod-admin-hud' ) );

/* ---------- 16. Personnalisation avancée (couleurs par élément, arrondi, espacement) ---------- */

echo "\n16) Personnalisation avancée : chaque valeur remplie s'applique\n";
$fm_src2 = file_get_contents( $plugin_dir . 'includes/form/class-form-manager.php' );
check( 'render() lit les 6 nouveaux réglages', false !== strpos( $fm_src2, "Settings::get( 'button_color'" ) && false !== strpos( $fm_src2, "Settings::get( 'text_color'" ) && false !== strpos( $fm_src2, "Settings::get( 'border_color'" ) && false !== strpos( $fm_src2, "Settings::get( 'background_color'" ) && false !== strpos( $fm_src2, "Settings::get( 'border_radius'" ) && false !== strpos( $fm_src2, "Settings::get( 'form_padding'" ) );
$sp4 = file_get_contents( $plugin_dir . 'includes/admin/pages/class-settings-page.php' );
check( 'schéma : int_opt optionnel présent', false !== strpos( $sp4, "'int_opt'" ) );
check( 'UI : 4 palettes + 2 mesures présentes', false !== strpos( $sp4, 'color_swatches(' ) && false !== strpos( $sp4, 'icod[border_radius]' ) );
$css2 = file_get_contents( $plugin_dir . 'assets/front/css/form.css' );
check( 'CSS : arrondi/espacement pilotés par variables', false !== strpos( $css2, 'var(--icod-radius,18px)' ) && false !== strpos( $css2, 'var(--icod-pad,20px)' ) && false !== strpos( $css2, 'var(--icod-radius-sm,12px)' ) );
$sanitize2 = $refm( '\InfinityCod\Admin\Pages\SettingsPage', 'sanitize_fields' );
$clean2 = $sanitize2->invoke( $page, array( 'border_radius' => '99', 'form_padding' => '' ), 'form' );
check( 'arrondi borné (99 → 40) et vide préservé', isset( $clean2['border_radius'] ) && 40 === $clean2['border_radius'] && isset( $clean2['form_padding'] ) && '' === $clean2['form_padding'] );

/* ---------- 17. Réglages Geo : livraison gratuite / poids sauvegardés ---------- */

echo "\n17) Page Wilayas & Tarifs : toggles Geo réellement sauvegardés\n";
$geo_src = file_get_contents( $plugin_dir . 'includes/admin/pages/class-geo-page.php' );
check( 'plus de mismatch de noms (icod[...] vs icod_...)', false === strpos( $geo_src, 'name="icod[free_amount_enabled]"' ) && false === strpos( $geo_src, 'name="icod[weight_fee_enabled]"' ) );
check( 'champs rendus au format plat lu par le handler', false !== strpos( $geo_src, 'name="icod_free_amount_enabled"' ) && false !== strpos( $geo_src, 'name="icod_weight_fee_enabled"' ) && false !== strpos( $geo_src, 'name="icod_free_amount_threshold"' ) );
$am2 = file_get_contents( $plugin_dir . 'includes/admin/class-admin-manager.php' );
check( 'handler lit et sauvegarde les 6 clés Geo', false !== strpos( $am2, "'free_amount_enabled'" ) && false !== strpos( $am2, "'free_amount_threshold'" ) && false !== strpos( $am2, "'weight_fee_enabled'" ) && false !== strpos( $am2, "'weight_fee_per_kg'" ) );
$post_scan = shell_exec( 'node ' . escapeshellarg( dirname( __DIR__ ) . '/tools/check-post-fields.js' ) . ' 2>&1' );
check( 'scan POST des écrans admin : aucun mismatch', false !== strpos( (string) $post_scan, 'OK :' ) );

/* ---------- 18. Délai + commande minimum par wilaya (chaîne complète) ---------- */

echo "\n18) Délai de livraison + commande minimum par wilaya\n";
$geo2 = file_get_contents( $plugin_dir . 'includes/admin/pages/class-geo-page.php' );
check( 'cellules Délai/Min réellement présentes dans les lignes', false !== strpos( $geo2, "][days]" ) && false !== strpos( $geo2, "][min]" ) );
$am3 = file_get_contents( $plugin_dir . 'includes/admin/class-admin-manager.php' );
check( 'handler Geo lit min/days', false !== strpos( $am3, "'min'" ) && false !== strpos( $am3, "'days'" ) );
$rates_src = file_get_contents( $plugin_dir . 'includes/shipping/class-rates-manager.php' );
check( 'save_wilaya_prices persiste min_order + delivery_days', false !== strpos( $rates_src, "'min_order'" ) && false !== strpos( $rates_src, "'delivery_days'" ) );
$rates2 = new \InfinityCod\Shipping\RatesManager();
check( 'min_order()/delivery_estimate() lisibles (0/vide sans DB)', 0.0 === $rates2->min_order( '16' ) && '' === $rates2->delivery_estimate( '16' ) );
$routes2 = file_get_contents( $plugin_dir . 'includes/rest/class-routes.php' );
check( 'quote retourne estimate + min_order', false !== strpos( $routes2, "'estimate'" ) && false !== strpos( $routes2, "'min_order'" ) );
check( 'submit applique la commande minimum (blocage serveur)', false !== strpos( $routes2, 'icod_min_order' ) && false !== strpos( $routes2, 'min_order(' ) );
$fm3 = file_get_contents( $plugin_dir . 'includes/form/class-form-manager.php' );
$js2 = file_get_contents( $plugin_dir . 'assets/front/js/form.js' );
check( 'formulaire : zones délai + min-order rendues et alimentées', false !== strpos( $fm3, 'data-delivery-estimate' ) && false !== strpos( $fm3, 'data-min-order-warn' ) && false !== strpos( $js2, 'data-delivery-estimate' ) && false !== strpos( $js2, 'data-min-order-warn' ) );

/* ---------- 19. Interface réorganisée + purge des caches ---------- */

echo "\n19) Interface organisée + purge automatique des caches\n";
$sp5 = file_get_contents( $plugin_dir . 'includes/admin/pages/class-settings-page.php' );
check( 'sections numérotées 1-8 dans l’onglet Formulaire', false !== strpos( $sp5, '1 — 📝' ) && false !== strpos( $sp5, '2 — 🧱' ) && false !== strpos( $sp5, '3 — 🎨' ) && false !== strpos( $sp5, '4 — ⚙️' ) && false !== strpos( $sp5, '5 — 🤖' ) && false !== strpos( $sp5, '6 — ⏳' ) && false !== strpos( $sp5, '7 — 🎉' ) && false !== strpos( $sp5, '8 — 🏷️' ) );
check( 'show_offers présent exactement UNE fois (plus de disparition silencieuse)', 1 === substr_count( $sp5, 'name="icod[show_offers]"' ) );
check( 'sticky_bar présent exactement UNE fois (doublon supprimé)', 1 === substr_count( $sp5, 'name="icod[sticky_bar]"' ) );
check( 'bandeau 3 étapes + bouton Enregistrer collant', false !== strpos( $sp5, 'icod-steps' ) && false !== strpos( $sp5, 'icod-save-sticky' ) );
check( 'purge des caches appelée à la sauvegarde', false !== strpos( $sp5, 'purge_page_caches' ) );
check( 'purge couvre LiteSpeed / WP Rocket / W3TC / Autoptimize', false !== strpos( $sp5, 'litespeed_purge_all' ) && false !== strpos( $sp5, 'rocket_clean_domain' ) && false !== strpos( $sp5, 'w3tc_flush_all' ) && false !== strpos( $sp5, 'autoptimizeCache' ) );

/* ---------- 20. Presets : couleur de départ réelle, même sans JS ---------- */

echo "\n20) Preset = couleur de départ quand l'accent n'est pas personnalisé\n";
check( 'rendu : repli preset si accent au défaut (sans JS)', false !== strpos( $fm3, "=== '#0E7A4F'" ) );
check( 'rendu : accent personnalisé gagne sur le preset', false !== strpos( $fm3, "Settings::get( 'accent_color', '#0e7a4f' )" ) );
$adminjs = file_get_contents( $plugin_dir . 'assets/admin/js/admin.js' );
check( 'admin : le clic preset déclenche input+change (aperçu immédiat)', false !== strpos( $adminjs, "accent.dispatchEvent" ) );

/* ---------- 21. CRUD commandes complet + synchro WC ---------- */

echo "\n21) Commandes : Create ✓ / Read ✓ / Update ✓ / Delete ✓ + synchro WC\n";
$orders_src = file_get_contents( $plugin_dir . 'includes/orders/class-order-store.php' );
check( 'Delete : OrderStore::delete existe (corbeille WC, hook)', false !== strpos( $orders_src, 'public function delete(' ) && false !== strpos( $orders_src, 'infinitycod_order_deleted' ) );
$am4 = file_get_contents( $plugin_dir . 'includes/admin/class-admin-manager.php' );
check( 'handler AJAX de suppression enregistré + capability', false !== strpos( $am4, 'wp_ajax_icod_order_delete' ) && false !== strpos( $am4, 'handle_order_delete' ) );
check( 'édition : synchro WC complète (adresse + quantité + note + total)', false !== strpos( $am4, "set_address( \$wc_address, 'billing' )" ) && false !== strpos( $am4, 'set_quantity( $qty )' ) && false !== strpos( $am4, 'set_customer_note' ) );
$orders_page = file_get_contents( $plugin_dir . 'includes/admin/pages/class-orders-page.php' );
check( 'bulk : Expédier + Supprimer proposés', false !== strpos( $orders_page, "value=\"shipped\"" ) && false !== strpos( $orders_page, 'value="delete"' ) );
check( 'bouton suppression par ligne', false !== strpos( $orders_page, 'icod-del' ) );
$adminjs2 = file_get_contents( $plugin_dir . 'assets/admin/js/admin.js' );
check( 'JS : suppression branchée avec confirmation + icod_order_delete', false !== strpos( $adminjs2, "post('icod_order_delete'" ) && false !== strpos( $adminjs2, 'window.confirm' ) );

/* ---------- 22. Sauvegarde AJAX + vérification après écriture ---------- */

echo "\n22) Sauvegarde infaillible : AJAX + vérification après écriture\n";
$sp5 = file_get_contents( $plugin_dir . 'includes/admin/pages/class-settings-page.php' );
check( 'endpoint AJAX de sauvegarde enregistré', false !== strpos( $sp5, 'wp_ajax_icod_save_settings_ajax' ) );
check( 'JS : priorité admin-ajax + repli natif', false !== strpos( $sp5, 'icod_save_settings_ajax' ) && false !== strpos( $sp5, 'form.submit()' ) );
check( 'vérification après écriture (relecture + comparaison)', false !== strpos( $sp5, 'save_failed' ) && false !== strpos( $sp5, 'Settings::setCache( null )' ) );
check( 'no-cache sur les écrans Réglages', false !== strpos( $sp5, 'nocache_headers()' ) );

/* ---------- 23. Styles du timer + aperçu collant à droite ---------- */

echo "
23) Timer : 3 styles au choix + aperçu en colonne collante
";
check( 'schéma : timer_style enum', false !== strpos( $sp5, "'timer_style'" ) );
check( 'UI : sélecteur des 3 styles', false !== strpos( $sp5, 'value="pill"' ) && false !== strpos( $sp5, 'value="ribbon"' ) );
check( 'rendu : classe de style appliquée au timer', false !== strpos( $fm3, 'icod-timer-' ) );
check( 'CSS : les 3 styles existent', false !== strpos( $css2, '.icod-timer-pill' ) && false !== strpos( $css2, '.icod-timer-ribbon' ) && false !== strpos( $css2, '.icod-timer-bar' ) );
check( 'disposition : aperçu collant à droite (écrans larges)', false !== strpos( $sp5, 'icod-form-layout' ) && false !== strpos( $sp5, 'icod-form-preview' ) );
check( 'rafraîchissement quasi instantané (250 ms)', false !== strpos( $sp5, 'setTimeout(refresh, 250)' ) );

/* ---------- 24. Styles de formulaire + options d'affichage + sticky pleine largeur ---------- */

echo "\n24) Styles de formulaire (classic/moderne/tech/ecommerce) + options d'affichage\n";
$sp6 = file_get_contents( $plugin_dir . 'includes/admin/pages/class-settings-page.php' );
check( 'schéma : form_style enum + 5 toggles d’affichage', false !== strpos( $sp6, "'form_style'" ) && false !== strpos( $sp6, "'show_head_thumb'" ) && false !== strpos( $sp6, "'show_stock_badge'" ) && false !== strpos( $sp6, "'show_progress_bar'" ) && false !== strpos( $sp6, "'show_summary_coupon'" ) && false !== strpos( $sp6, "'hide_when_sold_out'" ) );
check( 'UI : sélecteur des 4 styles de formulaire', false !== strpos( $sp6, 'value="moderne"' ) && false !== strpos( $sp6, 'value="tech"' ) && false !== strpos( $sp6, 'value="ecommerce"' ) );
$fm4 = file_get_contents( $plugin_dir . 'includes/form/class-form-manager.php' );
check( 'rendu : classe icod-fs-* + garde épuisé', false !== strpos( $fm4, 'icod-fs-' ) && false !== strpos( $fm4, 'hide_when_sold_out' ) );
$css3 = file_get_contents( $plugin_dir . 'assets/front/css/form.css' );
check( 'CSS : les 4 styles structurels existent', false !== strpos( $css3, '.icod-fs-moderne' ) && false !== strpos( $css3, '.icod-fs-tech' ) && false !== strpos( $css3, '.icod-fs-ecommerce' ) );
check( 'barre collante pleine largeur (plus de max-width 680px)', false === strpos( $css3, 'max-width:680px;margin-inline:auto' ) );

/* ---------- 25. Personnalisation étendue + PayPal Premium unique ---------- */

echo "
25) Extension : 8 styles timer, police/hauteur/largeur, PayPal Premium unique
";
check( 'schéma : 8 styles de timer', false !== strpos( $sp5, "'flip'" ) && false !== strpos( $sp5, "'neon'" ) && false !== strpos( $sp5, "'minimal'" ) && false !== strpos( $sp5, "'banner'" ) && false !== strpos( $sp5, "'boxes'" ) );
check( 'UI : sélecteur 8 options', false !== strpos( $sp5, 'value="flip"' ) && false !== strpos( $sp5, 'value="boxes"' ) );
check( 'CSS : les 5 nouveaux styles existent', false !== strpos( $css2, '.icod-timer-flip' ) && false !== strpos( $css2, '.icod-timer-neon' ) && false !== strpos( $css2, '.icod-timer-minimal' ) && false !== strpos( $css2, '.icod-timer-banner' ) && false !== strpos( $css2, '.icod-timer-boxes' ) );
check( 'largeur jusqu à 1400 px (schéma + UI)', false !== strpos( $sp5, "=> 1400" ) && false !== strpos( $sp5, 'max="1400"' ) );
check( 'police + hauteur bouton variables', false !== strpos( $sp5, "'form_font_size'" ) && false !== strpos( $sp5, "'button_height'" ) && false !== strpos( $css2, 'var(--icod-fs,14px)' ) && false !== strpos( $css2, 'var(--icod-btn-h,56px)' ) );
check( 'licence : carte Premium unique avec PayPal direct', false !== strpos( $sp5, 'paypal_price' ) && false === strpos( $sp5, "'Personal', 'infinitycod'" ) );
check( 'quantité élargie + blindée thèmes', false !== strpos( $css2, '.icod-qty-input' ) && false !== strpos( $css2, 'border:0!important' ) );

/* ---------- 26. Champs harmonisés + FLAT + captcha en bas + UX clavier ---------- */

echo "\n26) Champs harmonisés + style FLAT + captcha en bas + navigation Entrée\n";
$css4 = file_get_contents( $plugin_dir . 'assets/front/css/form.css' );
$fm5  = file_get_contents( $plugin_dir . 'includes/form/class-form-manager.php' );
$js3  = file_get_contents( $plugin_dir . 'assets/front/js/form.js' );
$sp7  = file_get_contents( $plugin_dir . 'includes/admin/pages/class-settings-page.php' );
check( 'champs harmonisés 46 px + coins uniformes', false !== strpos( $css4, 'min-height:46px;padding:10px 13px' ) );
check( 'textarea dédié (hauteur libre)', false !== strpos( $css4, 'textarea.icod-input{min-height:0' ) );
check( 'liseré vert champ rempli', false !== strpos( $css4, '.icod-input.is-filled' ) );
check( 'compteur de note (CSS + JS)', false !== strpos( $css4, '.icod-note-count' ) && false !== strpos( $js3, 'icod-note-count' ) );
check( 'style FLAT : schéma + UI + CSS + whitelist', false !== strpos( $sp7, "'ecommerce', 'flat'" ) && false !== strpos( $sp7, 'value="flat"' ) && false !== strpos( $css4, '.icod-fs-flat' ) && false !== strpos( $fm5, "'ecommerce', 'flat'" ) );
$captcha_pos = strrpos( $fm5, 'echo $captcha_html;' );
$aside_pos   = strpos( $fm5, 'icod-aside' );
$timer_pos   = strpos( $fm5, 'echo $timer_html;' );
check( 'captcha rendu EN BAS (dans l’aside, avant le bouton)', false !== $captcha_pos && false !== $aside_pos && $captcha_pos > $aside_pos );
check( 'timer rendu EN HAUT (avant le captcha)', false !== $timer_pos && $timer_pos < $captcha_pos );
check( 'navigation Entrée = champ suivant', false !== strpos( $js3, 'focusables' ) );

/* ---------- 27. Tracking statut + Licence améliorée + timer perso ---------- */

echo "
27) Tracking statut, Licence améliorée, timer personnalisable
";
check( 'Tracking : panneau de statut par plateforme', false !== strpos( $sp7, 'icod-track-status' ) && false !== strpos( $sp7, 'icod-track-dot' ) );
check( 'Licence : barre de temps restant', false !== strpos( $sp7, 'icod-lic-bar' ) );
check( 'timer : position/taille/couleurs en schéma', false !== strpos( $sp7, "'timer_position'" ) && false !== strpos( $sp7, "'timer_font_size'" ) && false !== strpos( $sp7, "'timer_bg_color'" ) );
check( 'rendu : couleurs/taille inline sur le timer', false !== strpos( $fm5, 'timer_style_attr' ) );
check( 'stats P&L actives sans licence', false === strpos( $am4, 'Fonctionnalité Premium' ) );
check( 'licence : P&L marqué gratuit', false !== strpos( $sp7, "taux de confirmation, retours, marge nette', 'infinitycod' ), true" ) );

/* ---------- 28. Templates Builder + drag & drop + compact ---------- */

echo "\n28) Modèles de formulaire + drag & drop + mode compact\n";
$sanitize3 = $refm( '\InfinityCod\Admin\Pages\SettingsPage', 'sanitize_fields' );
$clean3 = $sanitize3->invoke( $page, array( 'checkout_template' => 'simple', 'checkout_fields' => array() ), 'form' );
$tpl_keys = array_column( (array) ( $clean3['checkout_fields'] ?? array() ), 'key' );
check( 'modèle Simple appliqué (4 champs, sans adresse)', $tpl_keys === array( 'name', 'phone', 'wilaya', 'commune' ) );
$clean4 = $sanitize3->invoke( $page, array( 'checkout_template' => 'pro', 'checkout_fields' => array() ), 'form' );
$tpl_keys2 = array_column( (array) ( $clean4['checkout_fields'] ?? array() ), 'key' );
check( 'modèle Pro appliqué (adresse + note)', $tpl_keys2 === array( 'name', 'phone', 'wilaya', 'commune', 'address', 'note' ) );
$clean5 = $sanitize3->invoke( $page, array( 'checkout_template' => '', 'checkout_fields' => array( array( 'key' => 'name', 'type' => 'text', 'label' => 'Nom complet', 'on' => 1, 'req' => 1, 'order' => 0 ) ) ), 'form' );
check( 'sans modèle : lignes natives acceptées (label préservé)', ( $clean5['checkout_fields'][0]['label'] ?? '' ) === 'Nom complet' );
$builder = file_get_contents( $plugin_dir . 'includes/admin/pages/class-settings-page.php' );
check( 'Builder : poignée drag + modèle select', false !== strpos( $builder, 'icod-bdrag' ) && false !== strpos( $builder, 'icod[checkout_template]' ) );
$admjs = file_get_contents( $plugin_dir . 'assets/admin/js/admin.js' );
check( 'JS drag & drop + renumérotation', false !== strpos( $admjs, 'icod-builder-row' ) && false !== strpos( $admjs, 'renumber' ) );
$orders_css = file_get_contents( $plugin_dir . 'assets/front/css/form.css' );
check( 'mode de livraison compact + qty neutralisée', false !== strpos( $css3, 'padding:10px 12px' ) && false !== strpos( $css3, 'border:0!important;background:transparent!important' ) );

/* ---------- 29. Protection autorisation + DMCA + purge ---------- */

echo "\n29) Protection sans autorisation + DMCA + purge\n";
check( 'REST : garde licence avant création de commande', false !== strpos( $routes_src, 'lock_form_enabled' ) && false !== strpos( $routes_src, 'icod_locked' ) );
check( 'nag admin si verrou actif sans licence', false !== strpos( $am4, 'license_nag' ) );
$set_src2 = file_get_contents( $plugin_dir . 'includes/core/class-settings.php' );
check( 'constante wp-config : INFINITYCOD_LOCK_FORM documentée', false !== strpos( $set_src2, 'INFINITYCOD_LOCK_FORM' ) );
check( 'DMCA : mention dans README + header plugin', false !== strpos( file_get_contents( dirname( $plugin_dir ) . '/README.md' ), 'DMCA' ) && false !== strpos( file_get_contents( $plugin_dir . 'infinitycod.php' ), 'Protection:' ) );
check( 'purge : règles admin mortes retirées du CSS front', false === strpos( $css4, '.icod-lic-hero' ) && false === strpos( $css4, '.icod-kpi-grid' ) );

/* ---------- 30. Autosync transporteurs + add-to-cart + logos + signature + stock faible ---------- */

echo "\n30) Autosync, add-to-cart, logos officiels, signature, stock faible\n";
$geo2 = file_get_contents( $plugin_dir . 'includes/admin/pages/class-geo-page.php' );
$am5  = file_get_contents( $plugin_dir . 'includes/admin/class-admin-manager.php' );
$fm6  = file_get_contents( $plugin_dir . 'includes/form/class-form-manager.php' );
$carr = file_get_contents( $plugin_dir . 'includes/carriers/class-carrier-manager.php' );
check( 'autosync transporteurs ON par défaut (toggle + gate + handler)', false !== strpos( $geo2, 'icod_carrier_autosync' ) && false !== strpos( $am5, "'carrier_autosync'" ) && false !== strpos( $carr, 'carrier_autosync' ) );
check( 'add-to-cart WC désactivable (3 hooks retirés)', false !== strpos( $fm6, 'maybe_disable_add_to_cart' ) && false !== strpos( $fm6, 'woocommerce_template_single_add_to_cart' ) );
	check( 'add-to-cart + quantité thème masqués dès l’installation (défaut = 1)', false !== strpos( file_get_contents( $plugin_dir . 'includes/core/class-settings.php' ), "'disable_add_to_cart'   => 1" ) && false !== strpos( $fm6, 'sticky-add-to-cart' ) );
check( 'signature Infinity Coder sous le formulaire', false !== strpos( $fm6, 'icod-signed' ) && false !== strpos( $fm6, 'infinitycoder.app' ) );
check( 'stock faible : pilule rouge « Seulement X restants »', false !== strpos( $fm6, 'icod-stock-low' ) && false !== strpos( $fm6, 'Seulement %d restants' ) );
check( 'logos CIB/Edahabia téléversables prioritaires', false !== strpos( $fm6, 'logo_cib_id' ) && false !== strpos( $fm6, 'logo_edahabia_id' ) );
$sp7 = file_get_contents( $plugin_dir . 'includes/admin/pages/class-settings-page.php' );
check( 'réglages Paiement : champs ID logos', false !== strpos( $sp7, 'icod[logo_cib_id]' ) && false !== strpos( $sp7, 'icod[logo_edahabia_id]' ) );

/* ---------- 31. Stepper quantité redessiné + arrondi des champs réglable + À propos ---------- */

echo "\n31) Stepper repensé, arrondi des champs (field_radius), page À propos\n";
$css5 = file_get_contents( $plugin_dir . 'assets/front/css/form.css' );
$sp8  = file_get_contents( $plugin_dir . 'includes/admin/pages/class-settings-page.php' );
$set3 = file_get_contents( $plugin_dir . 'includes/core/class-settings.php' );
$fm7  = file_get_contents( $plugin_dir . 'includes/form/class-form-manager.php' );
$about = file_get_contents( $plugin_dir . 'includes/admin/pages/class-about-page.php' );
check( 'stepper : pilule + boutons circulaires + focus visible', false !== strpos( $css5, '.icod-qty-btn{width:30px;height:30px' ) && false !== strpos( $css5, 'border-radius:50%' ) && false !== strpos( $css5, '.icod-qty:focus-within{border-color:var(--icod-accent)' ) );
check( 'arrondi des champs : schéma + défaut + UI', false !== strpos( $sp8, "'field_radius'" ) && false !== strpos( $sp8, 'icod[field_radius]' ) && false !== strpos( $set3, "'field_radius'" ) );
check( 'arrondi des champs : variable CSS appliquée aux inputs', false !== strpos( $fm7, '--icod-radius-fields' ) && false !== strpos( $css5, 'border-radius:var(--icod-radius-fields,var(--icod-radius-sm,12px))' ) );
check( 'À propos : bloc Nouveautés depuis le CHANGELOG embarqué', false !== strpos( $about, 'recent_changelog' ) && false !== strpos( $about, 'CHANGELOG.md' ) );
check( 'À propos : fonctionnalités actualisées (Builder, promos, 8 timers, DMCA)', false !== strpos( $about, 'Checkout Builder' ) && false !== strpos( $about, 'Codes promo InfinityCod' ) && false !== strpos( $about, '8 styles' ) && false !== strpos( $about, 'DMCA' ) );
check( 'mobile : stepper reste compact (max-width conservé)', false !== strpos( $css5, '.icod-qty{max-width:150px}' ) );
$js4 = file_get_contents( $plugin_dir . 'assets/front/js/form.js' );
check( 'stepper blindé thèmes : pilule max-content + largeur input bornée', false !== strpos( $css5, '.icod-qty{display:inline-flex;align-items:center;gap:2px;width:max-content' ) && false !== strpos( $css5, '.icod-qty-input{width:38px!important;min-width:38px;max-width:38px' ) );
check( 'stepper : chiffre exactement centré (padding/text-align verrouillés)', false !== strpos( $css5, 'text-align:center!important;padding:0!important' ) );
check( 'mode livraison : aucun tiret avant devis (span vide + :empty masqué)', false === strpos( $fm7, 'data-price-home>—' ) && false === strpos( $fm7, 'data-price-desk>—' ) && false !== strpos( $css5, '.icod-mode-price:empty{display:none}' ) );
check( 'barre collante : total initial chiffré (jamais de tiret vide)', false !== strpos( $js4, 'updateSticky(state.quote ? state.quote.total : state.unitPrice * currentQty())' ) );

/* ---------- 32. Audit d'application : timer personnalisé réellement rendu ---------- */

echo "\n32) Timer : apparence personnalisée appliquée + 8 styles rendus\n";
$fm8 = file_get_contents( $plugin_dir . 'includes/form/class-form-manager.php' );
check( 'timer : attribut style construit (taille + fond + couleur du texte)', false !== strpos( $fm8, "\$timer_style_attr .= 'font-size:'" ) && false !== strpos( $fm8, "\$timer_style_attr .= 'background:'" ) && false !== strpos( $fm8, "\$timer_style_attr .= 'color:'" ) );
check( 'timer : les 8 styles rendus (garde élargie aux 5 styles 2025)', false !== strpos( $fm8, "'flip', 'neon', 'minimal', 'banner', 'boxes'" ) );
check( 'timer : plus de variable indéfinie $timer_style_attr', false !== strpos( $fm8, '$timer_style_attr         = \'\';' ) || false !== strpos( $fm8, "\$timer_style_attr = '';" ) );
check( 'audit-apply : présent dans le pipeline npm check', false !== strpos( file_get_contents( dirname( $plugin_dir ) . '/package.json' ), 'audit-apply.js' ) );
check( 'schéma : trio PayPal 3 offres retiré (licence unique)', false === strpos( $sp8, 'paypal_price_personal' ) && false === strpos( $sp8, 'paypal_price_agency' ) );

/* ---------- 33. Rapidité : badge menu en cache, scripts defer ---------- */

echo "\n33) Rapidité : badge menu caché + invalidation, scripts non bloquants\n";
$am6 = file_get_contents( $plugin_dir . 'includes/admin/class-admin-manager.php' );
$os1 = file_get_contents( $plugin_dir . 'includes/orders/class-order-store.php' );
$sp9 = file_get_contents( $plugin_dir . 'includes/admin/pages/class-stats-page.php' );
check( 'badge : COUNT(*) mis en cache (transient + TTL)', false !== strpos( $am6, "get_transient( 'icod_pending_count' )" ) && false !== strpos( $am6, 'set_transient' ) );
check( 'badge : invalidation sur created/status_changed/deleted', false !== strpos( $am6, 'infinitycod_order_created' ) && false !== strpos( $am6, 'infinitycod_order_status_changed' ) && false !== strpos( $am6, 'infinitycod_order_deleted' ) && false !== strpos( $os1, "do_action( 'infinitycod_order_status_changed'" ) );
check( 'scripts : defer sur form, admin et chart (jamais bloquants)', false !== strpos( $fm8, "'icod-form', 'strategy', 'defer'" ) && false !== strpos( $am6, "'icod-admin', 'strategy', 'defer'" ) && false !== strpos( $sp9, "'icod-chart', 'strategy', 'defer'" ) );
check( 'reCAPTCHA : preconnect www.google.com', false !== strpos( $fm8, 'recaptcha_resource_hints' ) );
check( 'vignette produit : décodage asynchrone', false !== strpos( $fm8, 'loading="lazy" decoding="async"' ) );
check( 'logos cartes : fichiers vectoriels embarqués (CIB + Edahabia)', file_exists( $plugin_dir . 'assets/front/img/pay/cib.svg' ) && file_exists( $plugin_dir . 'assets/front/img/pay/edahabia.svg' ) );
check( 'logos cartes : rendu prioritaire depuis les fichiers embarqués', false !== strpos( file_get_contents( $plugin_dir . 'includes/form/class-form-manager.php' ), "array( 'svg', 'png', 'webp' )" ) );

/* ---------- 34. Essai Premium 7 jours ---------- */

echo "\n34) Essai Premium : débloque tout pendant 7 jours, une fois par site\n";
$lm1 = file_get_contents( $plugin_dir . 'includes/license/class-license-manager.php' );
check( 'essai : option dédiée + démarrage une seule fois', false !== strpos( $lm1, "const TRIAL_OPTION = 'infinitycod_trial'" ) && false !== strpos( $lm1, 'function start_trial' ) && false !== strpos( $lm1, 'trial_used' ) );
check( 'essai : is_premium() débloqué pendant l’essai', false !== strpos( $lm1, 'if ( self::trial_active() )' ) && false !== strpos( $lm1, 'return true;' ) );
check( 'essai : statut avec jours restants', false !== strpos( $lm1, 'trial_days_left' ) && false !== strpos( $lm1, 'Essai Premium — %d j restants' ) );
check( 'essai : handler admin_post + nonce + capacité', false !== strpos( $sp8, 'icod_start_trial' ) && false !== strpos( $sp8, 'handle_trial_start' ) && false !== strpos( $sp8, 'check_admin_referer( \'icod_start_trial\' )' ) );
check( 'essai : bouton « Démarrer mon essai de 7 jours » visible sans licence', false !== strpos( $sp8, 'Démarrer mon essai de 7 jours' ) && false !== strpos( $sp8, '! \\InfinityCod\\License\\LicenseManager::trial_used()' ) );

echo "\n=== BILAN : {$pass} OK, {$fail} échec(s) ===\n";
exit( $fail > 0 ? 1 : 0 );
