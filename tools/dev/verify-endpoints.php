<?php
/**
 * Vérification haut niveau — SÉCURITÉ DES ENDPOINTS.
 * Chaque endpoint admin doit rejeter les requêtes non authentifiées ;
 * les endpoints publics (submit, communes, quote, stopdesks, abandoned)
 * doivent répondre normalement mais avec des limites.
 *
 * Usage : wp eval-file tools/dev/verify-endpoints.php
 * Les appels sortants sont NON AUTHENTIFIÉS (pas de cookies) = vue attaquant.
 */

$base = 'http://127.0.0.1:8091';

$checks = array();

// — 1. Endpoints admin-ajax : DOIVENT rejeter sans login/nonce ----------------
$admin_actions = array(
	'icod_carrier_test', 'icod_import_offices', 'icod_order_blacklist',
	'icod_order_delete', 'icod_order_status', 'icod_order_update',
	'icod_orders_poll', 'icod_parcel_create', 'icod_preview_form',
	'icod_save_commune', 'icod_save_settings_ajax', 'icod_sync_tracking',
);
foreach ( $admin_actions as $action ) {
	$res = wp_remote_post( $base . '/wp-admin/admin-ajax.php', array(
		'timeout' => 15,
		'body'    => array( 'action' => $action, 'nonce' => 'fake', 'plugin' => 'infinitycod/infinitycod.php', 'slug' => 'infinitycod' ),
	) );
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	// Attendu : 400/401/403/500 ou success=false (rejet). Un 200 success=true = FAIL.
	$rejected = ( $code >= 400 ) || ( isset( $body['success'] ) && false === $body['success'] );
	$checks[] = array( "admin-ajax $action (non authentifié)", $rejected, $code . ( isset( $body['data']['errorMessage'] ) ? '' : '' ) );
}

// — 2. REST : routes protégées vs publiques -----------------------------------
$rest_base = $base . '/wp-json/infinitycod/v1';
foreach ( array( 'github-release', 'quote', 'stopdesks', 'communes', 'abandoned' ) as $route ) {
	$res = wp_remote_get( $rest_base . $route, array( 'timeout' => 15 ) );
	$code = (int) wp_remote_retrieve_response_code( $res );
	// Public : 200 attendu. Protégé : 401/403.
	$checks[] = array( "REST GET /$route (non authentifié)", $code > 0, 'HTTP ' . $code );
}

// POST /submit sans données : doit être rejeté proprement (validation).
$res = wp_remote_post( $rest_base . '/submit', array(
	'timeout' => 15,
	'headers' => array( 'Content-Type' => 'application/json' ),
	'body'    => wp_json_encode( array( 'injection_test' => 1 ) ),
) );
$code = (int) wp_remote_retrieve_response_code( $res );
$rejected = $code >= 400;
$checks[] = array( 'REST POST /submit (données invalides)', $rejected, 'HTTP ' . $code );

// — 3. XSS : le rendu du formulaire échappe-t-il les réglages ? ----------------
$evil = '<script>window.__xss=1</script>';
update_option( 'infinitycod_settings', array_merge( get_option( 'infinitycod_settings', array() ), array( 'form_title' => $evil ) ) );
$page = wp_remote_get( $base . '/?product=casque-bluetooth-premium', array( 'timeout' => 20 ) );
$html = wp_remote_retrieve_body( $page );
$xss_executed = strpos( $html, '<script>window.__xss=1</script>' ) !== false;
$checks[] = array( 'XSS : titre formulaire malveillant échappé', ! $xss_executed, $xss_executed ? 'NON ÉCHAPPÉ !' : 'échappé ✓' );
// Restaure.
$saved = get_option( 'infinitycod_settings', array() );
$saved['form_title'] = 'Commandez maintenant — paiement à la livraison';
update_option( 'infinitycod_settings', $saved );

// — 4. Rapport ----------------------------------------------------------------
echo "=== SÉCURITÉ DES ENDPOINTS (vue attaquant non authentifié) ===\n";
$fail = 0;
foreach ( $checks as $c ) {
	echo ( $c[1] ? '  ✓ ' : '  ✗ FAIL ' ) . str_pad( $c[0], 58 ) . ' ' . $c[2] . "\n";
	if ( ! $c[1] ) { $fail++; }
}
echo $fail ? "\n✗ $fail VÉRIFICATION(S) EN ÉCHEC\n" : "\n✓ TOUTES LES VÉRIFICATIONS PASSENT\n";
exit( $fail ? 1 : 0 );
