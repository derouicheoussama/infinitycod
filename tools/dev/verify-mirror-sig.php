<?php
/**
 * Vérifie la signature du manifest sur le miroir réel — via la pile HTTP WordPress.
 * Usage : wp eval-file tools/dev/verify-mirror-sig.php
 */

defined( 'ABSPATH' ) || exit;

$bases = array(
	'raw'      => 'https://raw.githubusercontent.com/derouicheoussama/infinitycod-releases/main/latest/',
	'jsdelivr' => 'https://cdn.jsdelivr.net/gh/derouicheoussama/infinitycod-releases@main/latest/',
);

foreach ( $bases as $kind => $base ) {
	echo "== Miroir $kind ==\n";
	$r1 = wp_remote_get( $base . 'update.json', array( 'timeout' => 15 ) );
	$c1 = is_wp_error( $r1 ) ? 0 : (int) wp_remote_retrieve_response_code( $r1 );
	$raw = is_wp_error( $r1 ) ? '' : (string) wp_remote_retrieve_body( $r1 );
	echo "  update.json     : HTTP $c1, " . strlen( $raw ) . " octets\n";
	if ( is_wp_error( $r1 ) ) {
		echo '  (erreur : ' . $r1->get_error_message() . ")\n";
		continue;
	}

	$r2 = wp_remote_get( $base . 'update.json.sig', array( 'timeout' => 15 ) );
	$c2 = is_wp_error( $r2 ) ? 0 : (int) wp_remote_retrieve_response_code( $r2 );
	$sig = is_wp_error( $r2 ) ? '' : trim( (string) wp_remote_retrieve_body( $r2 ) );
	echo "  update.json.sig : HTTP $c2, corps = '" . substr( $sig, 0, 60 ) . ( strlen( $sig ) > 60 ? "…'" : "'" ) . "\n";

	if ( 200 !== $c2 || '' === $sig ) {
		echo "  => SIG ABSENT/404 : le plugin reçoit le corps d'erreur, le prend pour une signature,\n";
		echo "     vérification impossible => 'bad_signature' => miroir écarté => AUCUNE mise à jour.\n";
		continue;
	}

	$decoded = base64_decode( $sig, true );
	if ( false === $decoded || 64 !== strlen( $decoded ) ) {
		echo "  => SIG PRÉSENT MAIS DÉCODAGE INVALIDE (pas du base64 64 octets)\n";
		continue;
	}
	$pk = base64_decode( \InfinityCod\License\Updater::SIGNING_PUBLIC_KEY, true );
	$ok = false;
	try {
		$ok = sodium_crypto_sign_detached_verify( $raw, $decoded, $pk );
	} catch ( \SodiumException $e ) {
		$ok = false;
	}
	echo $ok ? "  => SIGNATURE VALIDE ✓\n" : "  => SIGNATURE INVALIDE ✗ (.sig périmé ou autre clé)\n";
}
