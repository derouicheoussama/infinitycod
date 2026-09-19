<?php
/**
 * Crée un tag léger (ref) sur GitHub pointant vers le head de main.
 * Usage : wp eval-file tools/dev/api-tag.php <tag>
 */

defined( 'ABSPATH' ) || exit;

$tag = preg_replace( '/[^A-Za-z0-9.\-v]/', '', isset( $args[0] ) ? $args[0] : '' );
if ( '' === $tag ) {
	echo "Usage : wp eval-file tools/dev/api-tag.php <tag>\n";
	exit( 1 );
}
$repo = 'derouicheoussama/infinitycod';
$api  = 'https://api.github.com/repos/' . $repo;

$cred  = shell_exec( 'printf "protocol=https\nhost=github.com\n\n" | git credential fill' );
$token = '';
foreach ( (array) explode( "\n", (string) $cred ) as $line ) {
	if ( 0 === strpos( trim( $line ), 'password=' ) ) {
		$token = trim( substr( trim( $line ), 9 ) );
	}
}
if ( '' === $token ) {
	echo "✗ token introuvable\n";
	exit( 1 );
}

$head = wp_remote_get( $api . '/git/ref/heads/main', array(
	'timeout' => 30,
	'headers' => array( 'Authorization' => 'token ' . $token, 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'infinitycod-api-push' ),
) );
$head = json_decode( wp_remote_retrieve_body( $head ), true );
$sha  = $head['object']['sha'] ?? '';
if ( '' === $sha ) {
	echo "✗ head main introuvable\n";
	exit( 1 );
}

$res = wp_remote_request( $api . '/git/refs', array(
	'method'  => 'POST',
	'timeout' => 30,
	'headers' => array(
		'Authorization' => 'token ' . $token,
		'Accept'        => 'application/vnd.github+json',
		'Content-Type'  => 'application/json',
		'User-Agent'    => 'infinitycod-api-push',
	),
	'body'    => wp_json_encode( array( 'ref' => 'refs/tags/' . $tag, 'sha' => $sha ) ),
) );
$code = (int) wp_remote_retrieve_response_code( $res );
echo ( 201 === $code )
	? "✓ tag {$tag} créé sur " . substr( $sha, 0, 10 ) . " — la CI de release se déclenche\n"
	: "✗ HTTP {$code} : " . substr( wp_remote_retrieve_body( $res ), 0, 200 ) . "\n";
exit( 201 === $code ? 0 : 1 );
