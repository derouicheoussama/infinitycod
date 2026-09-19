<?php
/**
 * Diagnostic d'une installation E2E conservée (--keep).
 * Usage : php tools/dev/e2e-debug.php <dossier-wordpress>
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( "CLI uniquement.\n" );
}
$wp_dir = rtrim( $argv[1] ?? '', '/\\' );
if ( ! is_file( $wp_dir . '/wp-load.php' ) ) {
	exit( "wp-load introuvable dans {$wp_dir}\n" );
}

define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST']       = 'e2e.local';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
require $wp_dir . '/wp-load.php';

$data = get_plugin_data( WP_PLUGIN_DIR . '/infinitycod/infinitycod.php' );
echo 'version installée : ' . ( $data['Version'] ?? '?' ) . "\n";
echo 'active_plugins : ' . json_encode( get_option( 'active_plugins', array() ) ) . "\n";
echo 'network active : ' . json_encode( get_site_option( 'active_sitewide_plugins', array() ) ) . "\n";

$transient = get_site_transient( 'update_plugins' );
echo 'offre : ' . ( ! empty( $transient->response['infinitycod/infinitycod.php'] )
	? 'OUI v' . $transient->response['infinitycod/infinitycod.php']->new_version
	: 'non' ) . "\n";
echo 'checked : ' . ( isset( $transient->checked['infinitycod/infinitycod.php'] )
	? $transient->checked['infinitycod/infinitycod.php']
	: '(vide)' ) . "\n";
echo 'no_update infinitycod : ' . ( isset( $transient->no_update['infinitycod/infinitycod.php'] ) ? 'OUI (bloque remote())' : 'non' ) . "\n";

if ( class_exists( '\InfinityCod\License\Updater' ) ) {
	$ref = new ReflectionMethod( 'InfinityCod\License\Updater', 'remote' );
	$ref->setAccessible( true );
	$remote = $ref->invoke( new \InfinityCod\License\Updater() );
	echo 'remote() : ' . json_encode(
		is_array( $remote ) ? array_intersect_key( $remote, array_flip( array( 'version', 'source', 'unreachable', 'reason' ) ) ) : $remote
	) . "\n";
} else {
	echo "classe Updater absente\n";
}
