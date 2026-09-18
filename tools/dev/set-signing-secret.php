<?php
/**
 * Aligne le secret GitHub INFINITYCOD_SIGNING_KEY (dépôt derouicheoussama/infinitycod)
 * sur la clé privée locale .tools/signing-private.key — celle dont la moitié
 * publique est embarquée dans le plugin (constante SIGNING_PUBLIC_KEY).
 * Sans cela, le CI signe avec une autre clé et chaque plugin déployé rejette
 * la signature du manifest (bad_signature) → plus aucune mise à jour proposée.
 *
 * Usage : wp eval-file tools/dev/set-signing-secret.php
 * Le token vient du credential store Windows (git credential fill) et n'est
 * jamais affiché. La valeur est scellée (sealed box) comme l'exige l'API.
 */

defined( 'ABSPATH' ) || exit;

const REPO      = 'derouicheoussama/infinitycod';
const SECRET    = 'INFINITYCOD_SIGNING_KEY';
const EMBEDDED  = 'beDoIoaR5hZvEA2U93fiu80Bzg2uz78MT0n0EydFEKk=';
const KEY_FILE  = '.tools/signing-private.key';

// 1. Token depuis le credential store Windows.
$cred = shell_exec( 'printf "protocol=https\nhost=github.com\n\n" | git credential fill' );
$token = '';
foreach ( (array) explode( "\n", (string) $cred ) as $line ) {
	if ( 0 === strpos( trim( $line ), 'password=' ) ) {
		$token = trim( substr( trim( $line ), 9 ) );
	}
}
if ( '' === $token ) {
	echo "✗ aucun token GitHub dans le credential store\n";
	exit( 1 );
}
echo "✓ token credential store récupéré (masqué)\n";

// 2. Clé privée locale — vérifie via sodium_compat que la moitié publique
// correspond à la clé embarquée.
$key_b64 = trim( (string) file_get_contents( KEY_FILE ) );
if ( ! class_exists( 'Paragonie_Sodium_Compat' ) ) {
	require_once ABSPATH . WPINC . '/sodium_compat/autoload.php';
}
$seed      = base64_decode( $key_b64, true );
$keypair   = Paragonie_Sodium_Compat::crypto_sign_seed_keypair( substr( $seed, 0, 32 ) );
$pub_b64   = base64_encode( Paragonie_Sodium_Compat::crypto_sign_publickey( $keypair ) );
if ( $pub_b64 !== EMBEDDED ) {
	echo "✗ la clé locale correspond à $pub_b64 ≠ clé embarquée\n";
	exit( 1 );
}
echo "✓ clé locale = clé publique embarquée du plugin\n";

// 3. Clé publique de scellement du dépôt.
$res = wp_remote_get( "https://api.github.com/repos/" . REPO . "/actions/secrets/public-key", array(
	'timeout' => 20,
	'headers' => array(
		'Authorization' => 'token ' . $token,
		'Accept'        => 'application/vnd.github+json',
		'User-Agent'    => 'infinitycod-maint',
	),
) );
if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
	echo '✗ clé publique du dépôt : ' . ( is_wp_error( $res ) ? $res->get_error_message() : wp_remote_retrieve_response_code( $res ) ) . "\n";
	exit( 1 );
}
$info = json_decode( wp_remote_retrieve_body( $res ), true );
echo "✓ clé publique du dépôt récupérée (key_id {$info['key_id']})\n";

// 4. Scelle la valeur et publie le secret.
$sealed = sodium_crypto_box_seal( $key_b64, base64_decode( $info['key'] ) );
$body   = wp_json_encode( array(
	'encrypted_value' => base64_encode( $sealed ),
	'key_id'          => $info['key_id'],
) );
$put = wp_remote_request( "https://api.github.com/repos/" . REPO . "/actions/secrets/" . SECRET, array(
	'method'  => 'PUT',
	'timeout' => 20,
	'headers' => array(
		'Authorization' => 'token ' . $token,
		'Accept'        => 'application/vnd.github+json',
		'Content-Type'  => 'application/json',
		'User-Agent'    => 'infinitycod-maint',
	),
	'body'    => $body,
) );
if ( is_wp_error( $put ) ) {
	echo '✗ PUT secret : ' . $put->get_error_message() . "\n";
	exit( 1 );
}
$code = (int) wp_remote_retrieve_response_code( $put );
if ( 201 === $code || 204 === $code ) {
	echo "✓ secret " . SECRET . " mis à jour (HTTP $code) — le CI signera désormais avec la clé des clients.\n";
} else {
	echo "✗ PUT secret : HTTP $code — " . substr( wp_remote_retrieve_body( $put ), 0, 200 ) . "\n";
	exit( 1 );
}
