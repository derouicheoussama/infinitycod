<?php
/**
 * Sonde de l'updater : contenu réel de la transient + résultat de remote().
 * Usage : wp eval-file tools/dev/update-probe.php
 */

defined( 'ABSPATH' ) || exit;

$t = get_site_transient( 'update_plugins' );
echo "== Transient update_plugins ==\n";
if ( ! $t ) {
	echo "  (vide/false)\n";
} else {
	echo '  response : ' . implode( ', ', array_keys( (array) $t->response ) ) . "\n";
	echo '  no_update : ' . implode( ', ', array_keys( (array) $t->no_update ) ) . "\n";
	if ( isset( $t->no_update['infinitycod/infinitycod.php'] ) ) {
		echo "  !! no_update contient infinitycod — bloque remote()\n";
		$n = $t->no_update['infinitycod/infinitycod.php'];
		echo '     new_version : ' . ( is_object( $n ) ? ( $n->new_version ?? '?' ) : '?' ) . "\n";
	}
	echo '  last_checked : ' . ( isset( $t->last_checked ) ? ( time() - (int) $t->last_checked ) . 's' : '?' ) . "\n";
}

echo "\n== remote() via Reflection ==\n";
$ref  = new ReflectionMethod( 'InfinityCod\License\Updater', 'remote' );
$ref->setAccessible( true );
$instance = new \InfinityCod\License\Updater();
$data = $ref->invoke( $instance );
if ( is_array( $data ) ) {
	echo '  version : ' . ( $data['version'] ?? '?' ) . "\n";
	echo '  download_url : ' . ( $data['download_url'] ?? '?' ) . "\n";
	echo '  source : ' . ( $data['source'] ?? '(non indiqué)' ) . "\n";
	echo '  sha256 : ' . substr( (string) ( $data['sha256'] ?? '?' ), 0, 12 ) . "…\n";
} else {
	echo "  remote() => " . var_export( $data, true ) . " — AUCUNE MISE À JOUR VUE\n";
}

echo "\n== Canal / réglages ==\n";
echo '  channel : ' . \InfinityCod\License\Updater::channel() . "\n";
echo '  releases_repo : ' . \InfinityCod\License\Updater::releases_repo() . "\n";
echo '  version installée : ' . INFINITYCOD_VERSION . "\n";

echo "\n== Journal récent (update) ==\n";
global $wpdb;
$table = $wpdb->prefix . 'icod_logs';
// Le journal peut avoir sa propre table ou option — affiche ce qui existe.
$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', '%' . $wpdb->esc_like( 'icod' ) . '%' ) );
if ( $found ) {
	foreach ( $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', '%' . $wpdb->esc_like( 'icod_log' ) . '%' ) ) as $log_table ) {
		$rows = $wpdb->get_results( "SELECT * FROM {$log_table} ORDER BY 1 DESC LIMIT 6", ARRAY_A );
		foreach ( (array) $rows as $row ) {
			echo '  ' . substr( json_encode( $row, JSON_UNESCAPED_UNICODE ), 0, 200 ) . "\n";
		}
	}
} else {
	echo "  (aucune table de journal)\n";
}
