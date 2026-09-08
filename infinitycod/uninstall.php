<?php
/**
 * Désinstallation : supprime les données seulement si le marchand l'a demandé.
 *
 * @package InfinityCod
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$infinitycod_settings = get_option( 'infinitycod_settings', array() );
$infinitycod_settings = is_array( $infinitycod_settings ) ? $infinitycod_settings : array();

if ( empty( $infinitycod_settings['delete_on_uninstall'] ) ) {
	return; // Conservation par défaut : les données du marchand sont précieuses.
}

global $wpdb;

// Tables du plugin.
$infinitycod_tables = array(
	$wpdb->prefix . 'icod_wilayas',
	$wpdb->prefix . 'icod_communes',
	$wpdb->prefix . 'icod_stopdesks',
	$wpdb->prefix . 'icod_orders',
	$wpdb->prefix . 'icod_abandoned',
	$wpdb->prefix . 'icod_blacklist',
	$wpdb->prefix . 'icod_fraud_logs',
);

foreach ( $infinitycod_tables as $infinitycod_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$infinitycod_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

// Options et transients.
delete_option( 'infinitycod_settings' );
delete_option( 'infinitycod_db_version' );
delete_option( 'infinitycod_installed_at' );
delete_option( 'infinitycod_license' );

wp_clear_scheduled_hook( 'infinitycod_sync_tracking' );
wp_clear_scheduled_hook( 'infinitycod_recover_abandoned' );

// Méta des commandes WooCommerce liées au plugin.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_infinitycod\\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL
