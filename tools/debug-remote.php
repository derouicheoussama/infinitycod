<?php
/* Debug ciblé : remote_github avec mock, sans WordPress. */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'ABSPATH', __DIR__ . '/../.tools/fake-wp/' );
define( 'INFINITYCOD_VERSION', '2.7.0' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

$GLOBALS['__options'] = array();
$GLOBALS['__transients'] = array();
$GLOBALS['__last_url'] = '';
$GLOBALS['__http_body'] = file_get_contents( __DIR__ . '/../.tools/mock-release.json' );

function get_option( $k, $d = false ) { return $GLOBALS['__options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['__options'][ $k ] = $v; return true; }
function get_transient( $k ) { return $GLOBALS['__transients'][ $k ] ?? false; }
function set_transient( $k, $v, $e = 0 ) { $GLOBALS['__transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['__transients'][ $k ] ); return true; }
function add_filter( ...$a ) {}
function add_action( ...$a ) {}
function apply_filters( $t, $v ) { return $v; }
function wp_next_scheduled( $h ) { return true; }
function wp_schedule_event( ...$a ) { return true; }
function get_bloginfo( $k = '' ) { return '6.5'; }
function __( $s, $d = null ) { return $s; }
function esc_html__( $s, $d = null ) { return $s; }
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['__last_url'] = $url;
	return array( 'response' => array( 'code' => 200 ), 'body' => $GLOBALS['__http_body'], 'headers' => array() );
}
function is_wp_error( $t ) { return false; }
function wp_remote_retrieve_response_code( $r ) { return is_array( $r ) && isset( $r['response']['code'] ) ? (int) $r['response']['code'] : 0; }
function wp_remote_retrieve_body( $r ) { return is_array( $r ) && isset( $r['body'] ) ? (string) $r['body'] : ''; }

require dirname( __DIR__ ) . '/infinitycod/includes/Autoloader.php';
\InfinityCod\Autoloader::register();

$updater = new \InfinityCod\License\Updater();
\InfinityCod\License\Updater::clear_cache();

$method = new ReflectionMethod( $updater, 'remote_github' );
$method->setAccessible( true );
$result = $method->invoke( $updater );

echo "URL appelée : " . $GLOBALS['__last_url'] . "\n";
echo "Résultat   : " . var_export( $result, true ) . "\n";
echo "Transients  : " . var_export( $GLOBALS['__transients'], true ) . "\n";
