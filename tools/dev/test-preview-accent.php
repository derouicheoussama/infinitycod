<?php
/**
 * Test serveur du handler d'aperçu : POST icod[accent_color]=#e91e63
 * puis rendu de la page jeton pour vérifier que --icod-accent est rose.
 * Usage : wp eval-file tools/dev/test-preview-accent.php
 */

$auth_cookie = file_get_contents( get_temp_dir() . 'icod-ck.txt' );
$nonce       = file_get_contents( get_temp_dir() . 'icod-nonce.txt' );
$cookies     = array( 'wordpress_' . COOKIEHASH => $auth_cookie );

// 1. POST de l'aperçu avec la couleur rose.
$res = wp_remote_post( admin_url( 'admin-ajax.php' ), array(
	'timeout' => 30,
	'cookies' => $cookies,
	'body'    => array(
		'action'      => 'icod_preview_form',
		'nonce'       => $nonce,
		'product_id'  => 13,
		'icod'        => array( 'accent_color' => '#e91e63' ),
	),
) );
$body = json_decode( wp_remote_retrieve_body( $res ), true );
echo 'succès AJAX : ', ( ! empty( $body['success'] ) ? 'oui' : 'non' ), "\n";
$url = $body['data']['url'] ?? '';
echo 'url jeton : ', ( $url ? substr( $url, 0, 80 ) : '(aucune)' ), "\n";
if ( ! $url ) {
	exit( 1 );
}

// 2. Rendu de la page jeton.
$page = wp_remote_get( $url, array( 'timeout' => 30 ) );
$html = wp_remote_retrieve_body( $page );
if ( preg_match( '/--icod-accent:([^;"]+)/', $html, $m ) ) {
	echo '--icod-accent rendu : ', trim( $m[1] ), "\n";
	echo ( stripos( trim( $m[1] ), '#e91e63' ) === 0 || stripos( trim( $m[1] ), '225,30,99' ) !== false ) ? "=> BROILLON APPLIQUÉ ✓\n" : "=> Brouillon PAS appliqué ✗\n";
} else {
	echo "--icod-accent absent du rendu\n";
}
