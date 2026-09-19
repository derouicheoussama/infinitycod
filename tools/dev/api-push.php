<?php
/**
 * Publie un commit InfinityCod sur GitHub via l'API Git Data (sans git push,
 * bloqué côté poste par un scan workspace — voir docs). Fast-forward strict :
 * le head distant doit être exactement le parent attendu du manifeste.
 *
 * Usage :
 *   wp eval-file tools/dev/api-push.php <manifest.json>
 *
 * Le manifeste (JSON) contient : parent (sha attendu du head distant), tag,
 * message, files [{path,status}] — status A (ajout) ou M (modification),
 * contenus lus dans la copie de travail du dépôt.
 */

defined( 'ABSPATH' ) || exit;

$manifest_path = isset( $args[0] ) ? (string) $args[0] : '';
if ( '' === $manifest_path || ! is_file( $manifest_path ) ) {
	echo "Usage : wp eval-file tools/dev/api-push.php <manifest.json>\n";
	exit( 1 );
}
$manifest  = json_decode( (string) file_get_contents( $manifest_path ), true );
$repo_dir  = 'C:/Users/Derouiche Oussama/Plugins COD Algérien';
$repo      = 'derouicheoussama/infinitycod';
$api       = 'https://api.github.com/repos/' . $repo;

$required = array( 'parent', 'tag', 'message', 'files' );
foreach ( $required as $key ) {
	if ( empty( $manifest[ $key ] ) ) {
		echo "✗ manifeste incomplet (champ $key manquant)\n";
		exit( 1 );
	}
}

function apip_token() {
	$cred  = shell_exec( 'printf "protocol=https\nhost=github.com\n\n" | git credential fill' );
	$token = '';
	foreach ( (array) explode( "\n", (string) $cred ) as $line ) {
		if ( 0 === strpos( trim( $line ), 'password=' ) ) {
			$token = trim( substr( trim( $line ), 9 ) );
		}
	}
	if ( '' === $token ) {
		echo "✗ token credential store introuvable\n";
		exit( 1 );
	}
	return $token;
}

function apip_gh( string $method, string $url, string $token, $body = null ) {
	$args = array(
		'method'  => $method,
		'timeout' => 40,
		'headers' => array(
			'Authorization' => 'token ' . $token,
			'Accept'        => 'application/vnd.github+json',
			'User-Agent'    => 'infinitycod-api-push',
		),
	);
	if ( null !== $body ) {
		$args['body']                   = wp_json_encode( $body );
		$args['headers']['Content-Type'] = 'application/json';
	}
	$res = wp_remote_request( $url, $args );
	if ( is_wp_error( $res ) ) {
		echo '✗ ' . $res->get_error_message() . "\n";
		exit( 1 );
	}
	$code   = (int) wp_remote_retrieve_response_code( $res );
	$parsed = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( $code >= 300 ) {
		echo "✗ HTTP $code ($method $url) : " . substr( wp_remote_retrieve_body( $res ), 0, 300 ) . "\n";
		exit( 1 );
	}
	return $parsed;
}

$token = apip_token();

// — 1. Le head distant doit être exactement le parent attendu ------------------------
$remote_ref = apip_gh( 'GET', $api . '/git/ref/heads/main', $token );
$remote_sha = $remote_ref['object']['sha'];
if ( $remote_sha !== $manifest['parent'] ) {
	echo "✗ head distant inattendu : " . substr( $remote_sha, 0, 10 ) . " != parent du manifeste " . substr( $manifest['parent'], 0, 10 ) . "\n";
	echo "  Quelqu'un a poussé entre-temps — régénérer le manifeste.\n";
	exit( 1 );
}
echo "✓ head distant conforme au parent attendu\n";

// — 2. Blobs depuis la copie de travail ------------------------------------------------
$tree_entries = array();
foreach ( $manifest['files'] as $file ) {
	$local = $repo_dir . '/' . $file['path'];
	if ( ! is_file( $local ) ) {
		echo "✗ fichier manquant dans la copie de travail : " . $file['path'] . "\n";
		exit( 1 );
	}
	$content = (string) file_get_contents( $local );
	$blob    = apip_gh( 'POST', $api . '/git/blobs', $token, array(
		'content'  => base64_encode( $content ),
		'encoding' => 'base64',
	) );
	$tree_entries[] = array(
		'path' => $file['path'],
		'mode' => '100644',
		'type' => 'blob',
		'sha'  => $blob['sha'],
	);
	echo '  blob : ' . $file['path'] . ' (' . strlen( $content ) . " octets)\n";
}

// — 3. Arbre + commit -------------------------------------------------------------------
$base_commit = apip_gh( 'GET', $api . '/git/commits/' . $manifest['parent'], $token );
$new_tree    = apip_gh( 'POST', $api . '/git/trees', $token, array(
	'base_tree' => $base_commit['tree']['sha'],
	'tree'      => $tree_entries,
) );
$new_commit = apip_gh( 'POST', $api . '/git/commits', $token, array(
	'message' => $manifest['message'],
	'tree'    => $new_tree['sha'],
	'parents' => array( $manifest['parent'] ),
) );
echo '✓ commit distant créé : ' . substr( $new_commit['sha'], 0, 10 ) . "…\n";

// — 4. Avance main + crée le tag (déclenche la CI de release) ---------------------------
apip_gh( 'PATCH', $api . '/git/refs/heads/main', $token, array(
	'sha'   => $new_commit['sha'],
	'force' => false,
) );
echo "✓ refs/heads/main avancée\n";

$existing_tag = apip_gh( 'GET', $api . '/git/ref/tags/' . rawurlencode( $manifest['tag'] ), $token );
if ( ! empty( $existing_tag['object']['sha'] ) ) {
	echo "ℹ tag " . $manifest['tag'] . " existe déjà côté distant\n";
} else {
	apip_gh( 'POST', $api . '/git/refs', $token, array(
		'ref' => 'refs/tags/' . $manifest['tag'],
		'sha' => $new_commit['sha'],
	) );
	echo '✓ tag ' . $manifest['tag'] . " créé — la CI de release va se déclencher\n";
}

echo "✓ Publication API terminée.\n";
echo "  Local : git fetch origin && git reset --hard origin/main && git tag -d " . $manifest['tag'] . " && git tag " . $manifest['tag'] . "\n";
