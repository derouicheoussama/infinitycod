<?php
/**
 * Reproduction du « Forcer l'installation » (même code que la page Mises à
 * jour) : Plugin_Upgrader::install en overwrite, depuis l'URL de release.
 * Usage : wp eval-file tools/dev/repro-force-install.php
 */

defined( 'ABSPATH' ) || exit;

$url = 'https://github.com/derouicheoussama/infinitycod-releases/releases/download/v5.40.5/infinitycod.zip';

if ( ! function_exists( 'wp_handle_upload' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
}
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

echo "Téléchargement + installation : $url\n";
$upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
$result   = $upgrader->install( $url, array( 'overwrite' => true ) );

echo "Résultat : ";
if ( is_wp_error( $result ) ) {
	echo 'WP_Error — ' . $result->get_error_code() . ' : ' . $result->get_error_message() . "\n";
	// Messages intermédiaires du skin pour voir où ça casse.
	foreach ( $upgrader->skin->get_upgrade_messages() as $m ) {
		echo '  · ' . strip_tags( $m ) . "\n";
	}
} elseif ( is_array( $result ) ) {
	echo "OK — destination : " . ( $result['destination_name'] ?? '?' ) . "\n";
} else {
	echo var_export( $result, true ) . "\n";
}
echo "Version installée maintenant : " . get_plugin_data( WP_PLUGIN_DIR . '/infinitycod/infinitycod.php' )['Version'] . "\n";
