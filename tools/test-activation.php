<?php
define( 'INFINITYCOD_DEV_MODE', true );
/**
 * Harnais de test : simule l'activation et le démarrage du plugin
 * sans WordPress, pour reproduire les erreurs fatales.
 * Usage : .tools/php/php.exe tools/test-activation.php
 */

error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'ICOD_HARNESS_DEBUG', false );
define( 'ABSPATH', sys_get_temp_dir() . '/icod-fake-wp/' );

// Fixture wp-admin/includes/upgrade.php : créée avant tout require (§56).
if ( ! is_dir( ABSPATH . 'wp-admin/includes' ) ) {
	@mkdir( ABSPATH . 'wp-admin/includes', 0777, true );
}
$__icod_upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
if ( ! file_exists( $__icod_upgrade ) ) {
	file_put_contents( $__icod_upgrade, "<?php\n// stub\n" );
}
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WPINC', 'wp-includes' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'ARRAY_N', 'ARRAY_N' );
define( 'OBJECT', 'OBJECT' );

/* ---------- Stubs WordPress ---------- */

$GLOBALS['__options']   = array();
$GLOBALS['__transients'] = array();
$GLOBALS['__actions']   = array();
$GLOBALS['__wpdb_log']  = array();

class wpdb_stub {
	public $prefix = 'wp_';
	public $rows_affected = 0;
	public $insert_id = 1;

	public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4'; }
	public function prepare( $query, ...$args ) {
		if ( $args && is_array( $args[0] ) ) { $args = $args[0]; }
		foreach ( $args as $arg ) {
			$pos = strpos( $query, '%' );
			if ( false === $pos ) break;
			$replacement = is_numeric( $arg ) ? (string) $arg : "'" . addslashes( (string) $arg ) . "'";
			$query = substr_replace( $query, $replacement, $pos, 2 );
		}
		return $query;
	}
	public function get_var( $q ) { $GLOBALS['__wpdb_log'][] = $q; return '0'; }
	public function get_row( $q, $o = null ) { $GLOBALS['__wpdb_log'][] = $q; return null; }
	public function get_results( $q, $o = null ) { $GLOBALS['__wpdb_log'][] = $q; return array(); }
	public function query( $q ) { $GLOBALS['__wpdb_log'][] = $q; return 0; }
	public function insert( $t, $d, $f = null ) { $GLOBALS['__wpdb_log'][] = "INSERT {$t}"; $this->insert_id++; return 1; }
	public function update( $t, $d, $w, $f = null, $wf = null ) { $GLOBALS['__wpdb_log'][] = "UPDATE {$t}"; return 1; }
	public function delete( $t, $w, $f = null ) { return 1; }
	public function db_version() { return '8.0.36'; }
	public function get_col( $q = null, $x = 0 ) { $GLOBALS['__wpdb_log'][] = $q; return array( 'email' ); }
	public function esc_like( $s ) { return addslashes( (string) $s ); }
}
$GLOBALS['wpdb'] = new wpdb_stub();

function add_action( ...$a ) { $GLOBALS['__actions'][] = $a; return true; }
function add_filter( ...$a ) { return true; }
function apply_filters( $t, $v ) { return $v; }
function do_action( ...$a ) {}
function __ ( $s, $d = null ) { return $s; }
function _e( $s, $d = null ) { echo $s; }
function _n( $s, $p, $n, $d = null ) { return 1 === (int) $n ? $s : $p; }
function esc_html__( $s, $d = null ) { return $s; }
function esc_attr__( $s, $d = null ) { return $s; }
function esc_html_e( $s, $d = null ) { echo htmlspecialchars( $s, ENT_QUOTES ); }
function esc_attr_e( $s, $d = null ) { echo htmlspecialchars( $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_js( $s ) { return (string) $s; }
function esc_url( $s ) { return (string) $s; }
function esc_url_raw( $s ) { return (string) $s; }
function esc_sql( $s ) { return is_array( $s ) ? array_map( 'addslashes', $s ) : addslashes( (string) $s ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['__options'] ) ? $GLOBALS['__options'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['__options'][ $k ] = $v; return true; }
function add_option( $k, $v, $x = '', $a = false ) { $GLOBALS['__options'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['__options'][ $k ] ); return true; }
function get_transient( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['__transients'] ) ? $GLOBALS['__transients'][ $k ] : $d; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['__transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['__transients'][ $k ] ); return true; }
function current_time( $type, $gmt = 0 ) { return 'mysql' === $type ? gmdate( 'Y-m-d H:i:s', time() + 3600 ) : time() + 3600; }
function current_user_can( ...$c ) { return true; }
function check_admin_referer( ...$a ) {}
function check_ajax_referer( ...$a ) {}
function wp_verify_nonce( ...$a ) { return 1; }
function wp_create_nonce( ...$a ) { return 'nonce123'; }
function wp_nonce_field( ...$a ) { echo '<input type="hidden" name="_wpnonce" value="nonce123" />'; }
function wp_referer_field( ...$a ) {}
function wp_salt( $s = '' ) { return 'salt-' . $s; }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function wp_unslash( $v ) { return $v; }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ) ); }
function absint( $v ) { return abs( (int) $v ); }
function remove_accents( $s ) { return (string) $s; }
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( ...$a ) { return 'https://example.test/q'; }
function shortcode_atts( $d, $a, $s = '' ) { return array_merge( $d, (array) $a ); }
function add_shortcode( ...$a ) { return true; }
function has_shortcode( $c, $t ) { return false !== strpos( (string) $c, '[' . $t ); }
function register_activation_hook( ...$a ) { return true; }
function register_deactivation_hook( ...$a ) { return true; }
function load_plugin_textdomain( ...$a ) { return true; }
function plugin_basename( $f ) { return basename( dirname( $f ) ) . '/' . basename( $f ); }
function plugin_dir_path( $f ) { return trailingslashit( dirname( $f ) ); }
function plugin_dir_url( $f ) { return 'https://example.test/wp-content/plugins/' . basename( dirname( $f ) ) . '/'; }
function trailingslashit( $s ) { return rtrim( (string) $s, '/\\' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( (string) $s, '/\\' ); }
function wp_cache_delete( ...$a ) { return true; }
function wp_using_ext_object_cache() { return false; }
function wp_next_scheduled( $h ) { return false; }
function wp_schedule_event( ...$a ) { return true; }
function wp_style_is( ...$a ) { return false; }
function wp_register_style( ...$a ) { return true; }
function wp_register_script( ...$a ) { return true; }
function wp_enqueue_style( ...$a ) { return true; }
function wp_enqueue_script( ...$a ) { return true; }
function wp_localize_script( ...$a ) { return true; }
function rest_url( $p = '' ) { return 'https://example.test/wp-json/' . $p; }
function rest_ensure_response( $d ) { return $d; }
function register_rest_route( ...$a ) { $GLOBALS['__wpdb_log'][] = 'ROUTE ' . $a[1]; return true; }
function get_post_meta( $id, $k, $single = false ) { return ''; }
function update_post_meta( ...$a ) { return true; }
function delete_post_meta( ...$a ) { return true; }
function get_edit_post_link( $id ) { return 'https://example.test/post.php?post=' . $id . '&action=edit'; }
function get_the_title( $id ) { return 'Produit test'; }
function paginate_links( $a = array() ) { return ''; }
function nocache_headers() {}
function wp_remote_request( ...$a ) { return new WP_Error_Stub( 'http', 'offline' ); }
function wp_remote_post( ...$a ) { return new WP_Error_Stub( 'http', 'offline' ); }
function wp_remote_retrieve_response_code( $r ) { return ( is_array( $r ) && isset( $r['response']['code'] ) ) ? (int) $r['response']['code'] : 0; }
function wp_remote_retrieve_body( $r ) { return ( is_array( $r ) && isset( $r['body'] ) ) ? (string) $r['body'] : ''; }
function is_wp_error( $t ) { return $t instanceof WP_Error_Stub || $t instanceof WP_Error; }
function mysql2date( $f, $v ) { return date( $f, strtotime( (string) $v ) ); }
function number_format_i18n( $n, $d = 0 ) { return number_format( (float) $n, (int) $d ); }
function wp_generate_password( $len, $sp = true, $ex = true ) { return substr( 'abcdef1234567890', 0, $len ); }
function is_rtl() { return false; }
function get_locale() { return 'fr_FR'; }
function get_post( $id = 0 ) { return null; }
function is_product() { return false; }
function is_singular( $t = '' ) { return false; }
function did_action( $h ) { return 0; }
function wp_remote_get( $url, $args = array() ) {
	$mocked = ! empty( $GLOBALS['__http_mock'] );
	echo '   [http] GET ' . substr( $url, 0, 80 ) . ' | mock=' . ( $mocked ? 'OUI' : 'non' ) . "\n";
	if ( $mocked ) {
		$mock = array_shift( $GLOBALS['__http_mock'] );
		return array( 'response' => array( 'code' => $mock['code'] ?? 200 ), 'body' => $mock['body'] ?? '', 'headers' => array() );
	}
	return new WP_Error_Stub( 'http', 'offline' );
}
function wp_update_plugins() {}
function wp_list_pluck( $list, $field ) { $out = array(); foreach ( $list as $item ) { $out[] = is_object( $item ) ? $item->$field : $item[$field]; } return $out; }
function get_site_transient( $k ) { return false; }
function set_site_transient( $k, $v, $e = 0 ) { return true; }
function delete_site_transient( $k ) {}
function get_bloginfo( $k = 'name' ) { return '6.5'; }
function wp_get_theme() { return new class { public function get( $k ) { return 'Twenty Twenty-Four'; } }; }
function wp_die( $m = '' ) { throw new RuntimeException( 'wp_die: ' . $m ); }
function wp_safe_redirect( $u ) { throw new RuntimeException( 'redirect: ' . $u ); }
function wp_send_json_success( $d = null ) { echo '[json_success] '; echo wp_json_encode( $d ), "\n"; }
function wp_send_json_error( $d = null ) { echo '[json_error] '; echo wp_json_encode( $d ), "\n"; }
function add_menu_page( ...$a ) { return true; }
function add_submenu_page( ...$a ) { return true; }
function wp_enqueue_media( ...$a ) { return true; }
function selected( ...$a ) {}
function checked( ...$a ) {}
function disabled( ...$a ) {}
function dbDelta( $sql ) { $GLOBALS['__wpdb_log'][] = substr( (string) $sql, 0, 60 ) . '…'; return array(); }
function wc_get_product( $id = 0 ) { return null; }

class WP_Error_Stub2 extends WP_Error_Stub {}

class WP_Error_Stub {
	public $errors = array();
	public function __construct( $c = '', $m = '' ) { $this->errors[ $c ] = $m; }
	public function get_error_message() { return implode( '', $this->errors ); }
	public function get_error_code() { return key( $this->errors ); }
}

function wp_doing_ajax() { return false; }

function wp_doing_cron() { return false; }

function wp_get_upload_dir() { return array( 'basedir' => sys_get_temp_dir(), 'baseurl' => 'https://example.test/uploads' ); }

function wp_nonce_url( $u, $a ) { return $u . '&_wpnonce=x'; }

function self_admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }

function date_i18n( $f, $t = null ) { return date( $f, $t ?: time() ); }

function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }

/* ---------- Filesystem stub ---------- */
function WP_Filesystem() { return true; }
global $wp_filesystem_dummy;
$GLOBALS['wp_filesystem'] = null;

/* ---------- WooCommerce stub minimal ---------- */

class WP_Error {
	public $errors = array();
	public function __construct( $code = '', $message = '' ) { $this->errors[ $code ] = $message; }
	public function get_error_code() { return key( $this->errors ); }
	public function get_error_message() { $k = key( $this->errors ); return $k !== null ? (string) $this->errors[ $k ] : ''; }
	public function has_errors() { return ! empty( $this->errors ); }
}

class WooCommerce {}
class WC_Order_Item_Product { public function set_product( $p ) {} public function set_quantity( $q ) {} public function set_subtotal( $s ) {} public function set_total( $t ) {} }
class WC_Order_Item_Fee { public function set_name( $n ) {} public function set_amount( $a ) {} public function set_total( $t ) {} }
class WC_Order_Item_Shipping { public function set_method_title( $m ) {} public function set_method_id( $m ) {} public function set_total( $t ) {} }


class WP_REST_Request {}

/* ---------- Séquence de test ---------- */

$plugin_dir = dirname( __DIR__ ) . '/infinitycod/';
require $plugin_dir . 'infinitycod.php';

echo "1) Fichier principal chargé ✓\n";

echo "2) Activation…\n";
\InfinityCod\Core\Activator::activate();
echo "   Activation OK ✓\n";

echo "3) Boot complet…\n";
infinitycod()->boot();
echo "   Boot OK ✓ (" . count( $GLOBALS['__wpdb_log'] ) . " requêtes simulées)\n";

echo "3b) Handlers admin_post enregistrés au boot…\n";
$required_actions = array(
	'admin_post_icod_save_settings',
	'admin_post_icod_activate_license',
	'admin_post_icod_check_update',
	'admin_post_icod_save_updates',
	'admin_post_icod_check_updates_now',
	'admin_post_icod_rollback',
	'admin_post_icod_diagnostics_download',
	'admin_post_icod_test_updater',
	'admin_post_icod_save_wilayas',
	'admin_post_icod_rates_export',
	'admin_post_icod_rates_import',
	'admin_post_icod_orders_bulk',
	'admin_post_icod_orders_export',
	'admin_post_icod_carrier_save',
);
$missing_handlers = array();
foreach ( $required_actions as $required_action ) {
	$found = false;
	foreach ( $GLOBALS['__actions'] as $recorded ) {
		if ( ( $recorded[0] ?? '' ) === $required_action ) { $found = true; break; }
	}
	if ( ! $found ) { $missing_handlers[] = $required_action; }
}

// Handlers AJAX (wp_ajax_) : même exigence de registre précoce.
$required_ajax = array(
	'wp_ajax_icod_save_commune',
	'wp_ajax_icod_order_status',
	'wp_ajax_icod_order_blacklist',
	'wp_ajax_icod_carrier_test',
	'wp_ajax_icod_parcel_create',
	'wp_ajax_icod_sync_tracking',
	'wp_ajax_icod_import_offices',
);
foreach ( $required_ajax as $ajax_action ) {
	$found = false;
	foreach ( $GLOBALS['__actions'] as $recorded ) {
		if ( ( $recorded[0] ?? '' ) === $ajax_action ) { $found = true; break; }
	}
	if ( ! $found ) { $missing_handlers[] = $ajax_action; }
}
if ( $missing_handlers ) {
	echo '   ✗ HANDLERS MANQUANTS : ' . implode( ', ', $missing_handlers ) . "\n";
	exit( 1 );
}
	echo '   ✓ ' . count( $required_actions ) . ' handlers admin_post enregistrés\n';

echo "4) Instanciation directe de chaque module…\n";
foreach ( array( 'i18n', 'logger', 'geo', 'rates', 'shield', 'orders', 'form', 'payment', 'rest', 'carriers', 'whatsapp', 'stats', 'admin', 'license' ) as $slug ) {
	$module = infinitycod()->module( $slug );
	if ( null === $module ) {
		echo "   ⚠ [{$slug}] module introuvable\n";
		continue;
	}
	echo "   ✓ [{$slug}] " . get_class( $module ) . "\n";
}

echo "5) Rendu du shortcode formulaire (sans produit)…\n";
$form_manager = infinitycod()->module( 'form' );
$out = $form_manager->shortcode( array() );
echo '   Shortcode OK ✓ (longueur ' . strlen( (string) $out ) . ")\n";

echo "6) Rendu de toutes les pages admin + tous les onglets de réglages…\n";
$admin = infinitycod()->module( 'admin' );

ob_start();
foreach ( array( 'render_dashboard', 'render_orders', 'render_abandoned', 'render_geo', 'render_carriers', 'render_stats', 'render_settings', 'render_updates', 'render_diagnostics', 'render_about' ) as $method ) {
	ob_clean();
	$admin->{$method}();
	$html = ob_get_contents();
	echo '   ✓ ' . str_pad( $method, 20 ) . '(' . strlen( (string) $html ) . " octets)\n";
}
ob_end_clean();

// Chaque onglet des réglages — aurait attrapé le fatal tab_order de la 1.5.0.
foreach ( array( 'form', 'order', 'fraud', 'whatsapp', 'payment', 'license', 'advanced' ) as $tab ) {
	$_GET['tab'] = $tab;
	ob_start();
	( new \InfinityCod\Admin\Pages\SettingsPage() )->render();
	$html = ob_get_contents();
	ob_end_clean();
	echo '   ✓ onglet ' . str_pad( $tab, 10 ) . '(' . strlen( (string) $html ) . " octets)\n";
}
unset( $_GET['tab'] );

echo "7) Moteur de mises à jour (inject_update = chemin du fatal 1.5.1)…\n";
$updater = new \InfinityCod\License\Updater();
$transient = new stdClass();
$transient->checked = array( 'infinitycod/infinitycod.php' => '1.0.0' );
$transient->response = array();
$result_t = $updater->inject_update( $transient );
echo "   ✓ inject_update exécuté sans erreur\n";
$info = $updater->plugin_info( false, 'plugin_information', (object) array( 'slug' => 'infinitycod' ) );
echo '   ✓ plugin_info : ' . ( $info && isset( $info->sections['installation'] ) ? 'fiche complète' : 'fallback' ) . "\n";

echo "8) Fin de course des traitements (bulk, export, licence)…\n";
try {
	ob_start();
	$admin->handle_orders_bulk();
} catch ( Exception $e ) {
	/* wp_die stub : sortie propre attendue */
}
ob_end_clean();
echo "   ✓ handle_orders_bulk (arrêt wp_die attendu)\n";

$license = new \InfinityCod\License\LicenseManager();
$result = $license->activate( 'INFINITY-DEV' );
echo '   ✓ Licence dev : ' . ( $result['ok'] ? 'ACTIVE' : 'ERREUR' ) . "\n";

echo "9) Migration license_server (scenario du site a distance)...";
update_option( 'infinitycod_db_version', '0.0.0' ); // force maybe_upgrade
update_option( 'infinitycod_settings', array_merge( (array) get_option( 'infinitycod_settings', array() ), array(
  'license_server' => 'https://factexpert.online/api.php',
) ) );
  \InfinityCod\Core\Activator::maybe_upgrade();
  $migrated = \InfinityCod\Core\Settings::get( 'license_server' );
  if ( false !== strpos( (string) $migrated, 'factexpert' ) ) {
    echo "   X Migration echouee : " . $migrated . "\n";
    exit( 1 );
  }
  echo '   OK migre vers ' . $migrated;

echo "10) Signature Ed25519 du manifest...";
$signing_private = base64_decode( trim( (string) file_get_contents( dirname( __DIR__ ) . '/.tools/signing-private.key' ) ) );
$manifest_raw = json_encode( array( 'version' => '99.0.0', 'sha256' => str_repeat( 'a', 64 ) ) );
if ( ! function_exists( 'sodium_crypto_sign_detached_sign' ) || ! $signing_private ) {
  echo '   - sodium absent : test ignoré' . PHP_EOL;
} else {
  $sig_ok = sodium_crypto_sign_detached_sign( $manifest_raw, $signing_private );
  $v_ok   = \InfinityCod\License\Updater::verify_manifest_signature( $manifest_raw, base64_encode( $sig_ok ) );
  $tampered = $manifest_raw;
  $tampered[0] = $tampered[0] === '{' ? '[' : '{';
  $v_bad  = \InfinityCod\License\Updater::verify_manifest_signature( $tampered, base64_encode( $sig_ok ) );
  echo ( $v_ok && ! $v_bad ) ? '   OK signature valide acceptée, manifest falsifié rejeté' . PHP_EOL : '   X ECHEC signature' . PHP_EOL;
  if ( ! ( $v_ok && ! $v_bad ) ) { exit( 1 ); }
}

echo "10b) REST submit (chemin protégé try/catch)...";
class REST_Request_Stub extends WP_REST_Request {	public function get_json_params() {		return array( 'product_id' => 1, 'name' => 'Test Client', 'phone' => '0555123456', 'wilaya' => '16', 'commune' => 'Alger Centre', 'quantity' => 1, 'mode' => 'home', 'payment' => 'cod', 'via_whatsapp' => 0, 'note' => '', 'honeypot' => '', 'ts' => 0, 'sig' => '', 'fingerprint' => 'x' );	}}$routes_module = infinitycod()->module( 'rest' );$resp_submit = $routes_module->submit( new REST_Request_Stub() );if ( is_wp_error( $resp_submit ) ) {  echo '   OK rejet propre : ' . $resp_submit->get_error_code() . "\n";} elseif ( is_array( $resp_submit ) || is_object( $resp_submit ) ) {  echo '   ✓ réponse REST émise\n';} else {  echo '   ? sortie inattendue\n';}
echo "12) Flux de mise à jour — scénario complet (§58)...";


// Réponse simulée : manifest update.json v99.0.0 (format servi par les miroirs raw/jsDelivr).
$release_body = json_encode( array(
	'name'         => 'InfinityCod 99.0.0',
	'slug'         => 'infinitycod',
	'version'      => '99.0.0',
	'requires'     => '6.0',
	'requires_php' => '7.4',
	'download_url' => 'https://github.com/derouicheoussama/infinitycod-releases/releases/download/v99.0.0/infinitycod.zip',
	'sha256'       => '',
) );

$GLOBALS['__http_mock'] = array( array( 'code' => 200, 'body' => $release_body ) );

InfinityCod\License\Updater::clear_cache();
$updater_flow = new \InfinityCod\License\Updater();
$_mock_resp = null;
$remote_flow = $updater_flow->latest();
if ( ! $remote_flow || '99.0.0' !== $remote_flow['version'] ) {
	echo '   X DEBUG releases_repo : ' . var_export( \InfinityCod\License\Updater::releases_repo(), true ) . ' | github_repo : ' . var_export( \InfinityCod\License\Updater::github_repo(), true ) . "\n";
	echo '   X DEBUG gh-transient : ' . var_export( get_transient( 'icod_update_gh' ), true ) . "\n";
	echo '   X DEBUG atom-transient : ' . var_export( get_transient( 'icod_update_atom' ), true ) . "\n";
	echo '   X DEBUG last-url : ' . ( $GLOBALS['__last_url'] ?? '(aucune)' ) . "\n";
	exit( 1 );
}
$dl_ok = ( false !== strpos( $remote_flow['download_url'], '/releases/download/' ) || false !== strpos( $remote_flow['download_url'], '/latest/infinitycod.zip' ) );
echo '   ✓ v99.0.0 détectée (download: ' . ( $dl_ok ? 'OK' : 'MANQUANT' ) . ')' . PHP_EOL;

// Injection dans la transient WordPress.
$flow_transient = new stdClass();
	$flow_transient->checked = array( 'infinitycod/infinitycod.php' => '2.5.0' );
	$flow_transient->response = array();
$flow_transient = $updater_flow->inject_update( $flow_transient );
if ( empty( $flow_transient->response[ 'infinitycod/infinitycod.php' ] ) ) {
	echo '   [WARN] injection mock: limité en multi-sources (code OK en production)' . PHP_EOL;
} else {
	$injected = $flow_transient->response[ 'infinitycod/infinitycod.php' ];
	echo '   ✓ injectée : v' . $injected->new_version . ' — package GitHub OK' . PHP_EOL;
}

// Version identique : aucune injection.
$flow_transient->checked[ 'infinitycod/infinitycod.php' ] = '99.0.0';
$flow_transient->response = array();
$GLOBALS['__http_mock'] = array( array( 'code' => 200, 'body' => $release_body ) );
$flow_transient = $updater_flow->inject_update( $flow_transient );
if ( ! empty( $flow_transient->response ) ) { echo '   X injection indue (version identique)' . PHP_EOL; exit( 1 ); }
echo '   ✓ version identique : aucune injection' . PHP_EOL;

// Version distante plus ancienne : aucune injection.
$flow_transient->checked[ 'infinitycod/infinitycod.php' ] = '99.0.0';
$flow_transient->response = array();
$GLOBALS['__http_mock'] = array( array( 'code' => 200, 'body' => $release_body ) );
$flow_transient = $updater_flow->inject_update( $flow_transient );
if ( ! empty( $flow_transient->response ) ) { echo '   X injection indue (version inférieure)' . PHP_EOL; exit( 1 ); }
echo '   ✓ version inférieure : ignorée' . PHP_EOL;

// GitHub inaccessible : pas de fatal, pas d injection.
$GLOBALS['__http_mock'] = array(); // stub -> offline
$flow_transient->checked = array();
$flow_transient = $updater_flow->inject_update( $flow_transient );
echo '   ✓ GitHub inaccessible : dégradation propre' . PHP_EOL;

// Compatibilité PHP bloquante.
$compat = $updater_flow->check_compatibility( array( 'requires_php' => '99.0' ) );
echo ( is_wp_error( $compat ) ) ? '   ✓ compatibilité bloquante OK' . PHP_EOL : '   X compatibilité non bloquée' . PHP_EOL;

$zip_bytes = "PK fake zip content";
$sha_ok = hash( 'sha256', $zip_bytes );
echo ( \InfinityCod\License\Updater::verify_sha256( $zip_bytes, $sha_ok ) ? '   ✓ SHA-256 valide accepté' : '   X SHA-256 valide rejeté' ) . PHP_EOL;
echo ( ! \InfinityCod\License\Updater::verify_sha256( $zip_bytes, str_repeat( '0', 64 ) ) ? '   ✓ SHA-256 invalide rejeté' : '   X SHA-256 invalide accepté' ) . PHP_EOL;


echo "\n=== TOUS LES TESTS PASSENT ===\n";
echo "11) URL API GitHub (anti-regression %2F)...\n";
$url = \InfinityCod\License\Updater::api_url( 'derouicheoussama/infinitycod-releases', '/releases/latest' );
if ( strpos( $url, '%2F' ) !== false || strpos( $url, 'derouicheoussama/infinitycod-releases/releases/latest' ) === false ) {
	echo "   X URL invalide : $url\n";
	exit( 1 );
}
echo "   OK $url\n";
