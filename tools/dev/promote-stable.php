<?php
/**
 * Promeut une release publiée vers le miroir stable (latest/) — usage :
 *
 *   wp eval-file tools/dev/promote-stable.php v5.40.7
 *
 * 1. Récupère la release (API GitHub, token du credential store Windows).
 * 2. Vérifie la signature Ed25519 de la paire update.json/.sig avec la clé
 *    publique embarquée (un miroir incohérent n'est JAMAIS poussé).
 * 3. Republie update.json + update.json.sig dans la branche main du dépôt
 *    des releases (via l'API Contents — pas besoin de clone ni de git).
 * 4. Purge le cache jsDelivr.
 *
 * Sert aussi de réparation : si un CDN sert une paire désynchronisée,
 * relancer cette commande re-propage la paire signée cohérente.
 */

defined( 'ABSPATH' ) || exit;

// wp-cli eval-file injecte les arguments positionnels dans $args (pas $argv).
$tag_arg = '';
if ( isset( $args[0] ) && '' !== (string) $args[0] ) {
	$tag_arg = (string) $args[0];
} elseif ( isset( $argv[1] ) && '' !== (string) $argv[1] ) {
	$tag_arg = (string) $argv[1];
}
if ( '' === $tag_arg ) {
	echo "Usage : wp eval-file tools/dev/promote-stable.php <tag>\n";
	exit( 1 );
}
$tag    = preg_replace( '/[^A-Za-z0-9._\-v]/', '', $tag_arg );
$repo   = 'derouicheoussama/infinitycod-releases';
$api    = 'https://api.github.com/repos/' . $repo;

// — Token depuis le credential store Windows -----------------------------------------
$cred  = shell_exec( 'printf "protocol=https\nhost=github.com\n\n" | git credential fill' );
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

function promote_get( string $url, string $token, $raw = false ) {
	$res = wp_remote_get( $url, array(
		'timeout' => 30,
		'headers' => array(
			'Authorization' => 'token ' . $token,
			'Accept'        => 'application/vnd.github+json',
			'User-Agent'    => 'infinitycod-promote',
		),
	) );
	if ( is_wp_error( $res ) ) {
		echo '✗ ' . $res->get_error_message() . "\n";
		exit( 1 );
	}
	$body = wp_remote_retrieve_body( $res );
	return $raw ? $body : json_decode( $body, true );
}

function promote_put( string $url, string $token, array $body ) {
	$res = wp_remote_request( $url, array(
		'method'  => 'PUT',
		'timeout' => 30,
		'headers' => array(
			'Authorization' => 'token ' . $token,
			'Accept'        => 'application/vnd.github+json',
			'Content-Type'  => 'application/json',
			'User-Agent'    => 'infinitycod-promote',
		),
		'body'    => wp_json_encode( $body ),
	) );
	if ( is_wp_error( $res ) ) {
		echo '✗ ' . $res->get_error_message() . "\n";
		exit( 1 );
	}
	return (int) wp_remote_retrieve_response_code( $res );
}

// — 1. La release ---------------------------------------------------------------------
$release = promote_get( $api . '/releases/tags/' . rawurlencode( $tag ), $token );
if ( empty( $release['assets'] ) ) {
	echo "✗ release {$tag} introuvable ou sans assets\n";
	exit( 1 );
}
echo "✓ release {$tag} trouvée (" . count( $release['assets'] ) . " assets)\n";

$manifest_url = '';
$sig_url      = '';
foreach ( $release['assets'] as $asset ) {
	if ( 'update.json' === $asset['name'] ) {
		$manifest_url = $asset['browser_download_url'];
	}
	if ( 'update.json.sig' === $asset['name'] ) {
		$sig_url = $asset['browser_download_url'];
	}
}
if ( '' === $manifest_url || '' === $sig_url ) {
	echo "✗ assets update.json / update.json.sig absents de la release\n";
	exit( 1 );
}

// — 2. Vérification de la signature AVANT toute propagation ---------------------------
$manifest = promote_get( $manifest_url, $token, true );
$sig      = trim( promote_get( $sig_url, $token, true ) );
if ( ! \InfinityCod\License\Updater::verify_manifest_signature( $manifest, $sig ) ) {
	echo "✗ SIGNATURE INVALIDE — la paire de cette release ne correspond pas à la clé embarquée. Abandon (aucune propagation).\n";
	exit( 1 );
}
$version = json_decode( $manifest, true )['version'] ?? '?';
echo "✓ signature valide : paire v{$version} (" . strlen( $manifest ) . " octets)\n";

// — 3. Republie la paire dans latest/ (branch main) ------------------------------------
foreach ( array(
	'update.json'     => $manifest,
	'update.json.sig' => $sig . "\n",
) as $file_name => $content ) {
	$existing = promote_get( $api . '/contents/latest/' . $file_name . '?ref=main', $token );
	$payload  = array(
		'message' => 'Promotion ' . $tag . ' : ' . $file_name . ' (v' . $version . ', signature vérifiée)',
		'content' => base64_encode( $content ),
		'branch'  => 'main',
	);
	if ( ! empty( $existing['sha'] ) ) {
		$payload['sha'] = $existing['sha'];
	}
	$code = promote_put( $api . '/contents/latest/' . $file_name, $token, $payload );
	if ( 200 !== $code && 201 !== $code ) {
		echo "✗ PUT {$file_name} : HTTP {$code}\n";
		exit( 1 );
	}
	echo "✓ latest/{$file_name} publié (HTTP {$code})\n";
}

// — 4. Purge jsDelivr ------------------------------------------------------------------
foreach ( array( 'update.json', 'update.json.sig', 'infinitycod.zip' ) as $file_name ) {
	wp_remote_get( 'https://purge.jsdelivr.net/gh/' . $repo . '@main/latest/' . $file_name, array( 'timeout' => 15 ) );
}
echo "✓ purge jsDelivr demandée\n";
echo "✓ Promotion terminée : v{$version} est le manifest stable servi par les miroirs.\n";
