<?php
/**
 * Infinity Update Server — à téléverser sur votre hébergement
 * (infinitycoder.app ou tout autre serveur accessible).
 *
 * Fichiers nécessaires dans le même dossier :
 *   - update.json      (généré par node tools/build.js)
 *   - infinitycod.zip  (généré par node tools/build.js)
 *
 * Le plugin InfinityCOD vérifie automatiquement ce fichier et propose
 * la mise à jour si une nouvelle version est disponible.
 */

header( 'Content-Type: application/json; charset=utf-8' );
header( 'Access-Control-Allow-Origin: *' );
header( 'Cache-Control: no-cache, must-revalidate' );

$dir     = __DIR__;
$manifest = $dir . '/update.json';
$zip      = $dir . '/infinitycod.zip';

if ( ! file_exists( $manifest ) ) {
	http_response_code( 404 );
	echo json_encode( array( 'error' => 'update.json not found in ' . basename( $dir ) ) );
	exit;
}

$data = json_decode( file_get_contents( $manifest ), true );
if ( ! is_array( $data ) ) {
	http_response_code( 500 );
	echo json_encode( array( 'error' => 'invalid manifest' ) );
	exit;
}

// Construire l'URL de téléchargement depuis ce serveur.
$protocol = isset( $_SERVER['HTTPS'] ) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http';
$host     = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : 'localhost';
$path     = str_replace( '\\', '/', dirname( $_SERVER['SCRIPT_NAME'] ) );
$base     = $protocol . '://' . $host . $path;

$data['download_url'] = $base . '/infinitycod.zip';
$data['server_url']   = $base;

// Servir le ZIP si demandé.
if ( isset( $_GET['download'] ) ) {
	$zip_path = $dir . '/infinitycod.zip';
	if ( file_exists( $zip_path ) ) {
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="infinitycod.zip"' );
		header( 'Content-Length: ' . filesize( $zip_path ) );
		readfile( $zip_path );
		exit;
	}
	http_response_code( 404 );
	echo 'ZIP not found';
	exit;
}

header( 'X-Content-Type-Options: nosniff' );
echo json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
