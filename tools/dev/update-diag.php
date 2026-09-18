<?php
/**
 * Diagnostic du flux de mise à jour InfinityCod sur le test bed.
 * Usage : wp eval-file tools/dev/update-diag.php
 */

defined( 'ABSPATH' ) || exit;

echo "== Etat actuel ==\n";
if ( ! defined( 'INFINITYCOD_VERSION' ) ) {
	echo "InfinityCod inactif ou absent\n";
	exit( 1 );
}
echo 'Installe : v' . INFINITYCOD_VERSION . "\n";

$t = get_site_transient( 'update_plugins' );
if ( $t && isset( $t->response['infinitycod/infinitycod.php'] ) ) {
	$r = $t->response['infinitycod/infinitycod.php'];
	echo "Offre : v{$r->new_version} -> {$r->package}\n";
} else {
	echo "Offre : aucune\n";
}

echo "\n== Sondage des sources (probe_sources) ==\n";
if ( class_exists( '\InfinityCod\License\Updater' ) ) {
	foreach ( \InfinityCod\License\Updater::probe_sources() as $probe ) {
		echo '  ' . str_pad( $probe['name'], 32 ) . ( $probe['ok'] ? 'OK   ' : 'ECHEC' ) . '  ' . $probe['detail'] . "\n";
	}
} else {
	echo "  (classe non chargee)\n";
}

echo "\n== Manifest + zip sur le miroir brut ==\n";
$manifest_url = 'https://raw.githubusercontent.com/derouicheoussama/infinitycod-releases/main/latest/update.json';
$res = wp_remote_get( $manifest_url, array( 'timeout' => 20 ) );
if ( is_wp_error( $res ) ) {
	echo '  manifest : ECHEC — ' . $res->get_error_message() . "\n";
	exit( 0 );
}
$json = json_decode( wp_remote_retrieve_body( $res ), true );
if ( ! is_array( $json ) ) {
	echo "  manifest : JSON invalide\n";
	exit( 0 );
}
echo '  manifest : v' . ( $json['version'] ?? '?' ) . ', sha256=' . substr( (string) ( $json['sha256'] ?? '?' ), 0, 12 ) . "…\n";
echo '  download : ' . ( $json['download_url'] ?? '?' ) . "\n";

$zip = wp_remote_get( (string) $json['download_url'], array( 'timeout' => 90 ) );
if ( is_wp_error( $zip ) ) {
	echo '  zip : ECHEC — ' . $zip->get_error_message() . "\n";
	exit( 0 );
}
$code = (int) wp_remote_retrieve_response_code( $zip );
$body = (string) wp_remote_retrieve_body( $zip );
echo "  zip : HTTP $code, " . strlen( $body ) . " octets\n";
if ( 200 !== $code ) {
	exit( 0 );
}
$sha = hash( 'sha256', $body );
echo '  sha256 local  : ' . substr( $sha, 0, 16 ) . "…\n";
$ok = hash_equals( (string) ( $json['sha256'] ?? '' ), $sha );
echo $ok ? "  HASH OK — paquet conforme au manifest\n" : "  HASH DIFFERENT — le zip servi n'est plus celui du manifest !\n";
echo ( 'PK' === substr( $body, 0, 2 ) ) ? "  Signature zip (PK) OK\n" : "  Le corps recu n'est PAS un zip !\n";
