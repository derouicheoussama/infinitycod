<?php
/**
 * Tests d'intégration : vérification en direct des passerelles configurées
 * (WhatsApp, Chargily, Stripe, PayPal). Chaque test renvoie un verdict clair
 * {ok, message} — jamais d'exception, jamais de fuite de clé dans le message.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 */

namespace InfinityCod\Core;

use InfinityCod\License\LicenseManager;

defined( 'ABSPATH' ) || exit;

class IntegrationTests {

	/**
	 * Test WhatsApp : envoie un message court au numéro marchand.
	 *
	 * @return array{ok: bool, message: string}
	 */
	public static function whatsapp() {
		$phone = trim( (string) Settings::get( 'wa_owner_phone', '' ) );
		if ( '' === $phone ) {
			return array( 'ok' => false, 'message' => __( 'Renseignez d’abord votre numéro WhatsApp.', 'infinitycod' ) );
		}
		$gateway = Settings::get( 'whatsapp_gateway', 'wame' );
		if ( 'wame' === $gateway ) {
			return array( 'ok' => false, 'message' => __( 'Passerelle « wa.me » sélectionnée : elle génère des liens cliquables mais n’envoie rien automatiquement. Choisissez Cloud API ou Ultramsg pour les envois automatiques.', 'infinitycod' ) );
		}
		$plugin = infinitycod();
		$wa     = $plugin ? $plugin->module( 'whatsapp' ) : null;
		if ( ! $wa ) {
			return array( 'ok' => false, 'message' => __( 'Module WhatsApp indisponible.', 'infinitycod' ) );
		}
		$result = $wa->send(
			$phone,
			__( '✅ Test InfinityCod : votre passerelle WhatsApp fonctionne. Vous recevrez ici vos alertes de commandes.', 'infinitycod' )
		);
		if ( is_wp_error( $result ) ) {
			return array( 'ok' => false, 'message' => $result->get_error_message() );
		}
		return array( 'ok' => true, 'message' => __( 'Message de test envoyé — vérifiez votre WhatsApp.', 'infinitycod' ) );
	}

	/**
	 * Test d'une passerelle de paiement.
	 *
	 * @param string $gateway chargily|stripe|paypal.
	 * @return array{ok: bool, message: string}
	 */
	public static function payment( $gateway ) {
		switch ( $gateway ) {
			case 'chargily':
				return self::payment_chargily();
			case 'stripe':
				return self::payment_stripe();
			case 'paypal':
				return self::payment_paypal();
		}
		return array( 'ok' => false, 'message' => __( 'Passerelle inconnue.', 'infinitycod' ) );
	}

	private static function payment_chargily() {
		$secret = trim( (string) Settings::get( 'chargily_secret', '' ) );
		if ( '' === $secret ) {
			return array( 'ok' => false, 'message' => __( 'Renseignez d’abord votre clé secrète Chargily.', 'infinitycod' ) );
		}
		$mode = Settings::get( 'chargily_mode', 'test' );
		$url  = ( 'live' === $mode ) ? 'https://pay.chargily.net/api/v2/balance' : 'https://pay.chargily.net/test/api/v2/balance';
		$res  = wp_remote_get( $url, array(
			'timeout'  => 20,
			'headers'  => array( 'Authorization' => 'Bearer ' . $secret ),
		) );
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'message' => $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 401 === $code || 403 === $code ) {
			return array( 'ok' => false, 'message' => __( 'Clé refusée par Chargily (401) — vérifiez la clé et le mode test/production.', 'infinitycod' ) );
		}
		if ( 200 !== $code ) {
			return array( 'ok' => false, 'message' => 'Chargily a répondu HTTP ' . $code . ' — réessayez ou vérifiez le mode.' );
		}
		return array( 'ok' => true, 'message' => __( 'Connexion Chargily validée — la clé est acceptée.', 'infinitycod' ) );
	}

	private static function payment_stripe() {
		$secret = trim( (string) Settings::get( 'stripe_secret', '' ) );
		if ( '' === $secret ) {
			return array( 'ok' => false, 'message' => __( 'Renseignez d’abord votre clé secrète Stripe (sk_live_… / sk_test_…).', 'infinitycod' ) );
		}
		$res = wp_remote_get( 'https://api.stripe.com/v1/balance', array(
			'timeout' => 20,
			'headers' => array( 'Authorization' => 'Bearer ' . $secret ),
		) );
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'message' => $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( 401 === $code ) {
			return array( 'ok' => false, 'message' => __( 'Clé Stripe refusée (401) — vérifiez sk_live_ / sk_test_.', 'infinitycod' ) );
		}
		if ( 200 !== $code ) {
			return array( 'ok' => false, 'message' => 'Stripe a répondu HTTP ' . $code . '.' );
		}
		return array( 'ok' => true, 'message' => __( 'Connexion Stripe validée — la clé est acceptée.', 'infinitycod' ) );
	}

	private static function payment_paypal() {
		$client = trim( (string) Settings::get( 'paypal_client_id', '' ) );
		$secret = trim( (string) Settings::get( 'paypal_secret', '' ) );
		$mode   = Settings::get( 'paypal_mode', 'live' );
		if ( '' === $client || '' === $secret ) {
			return array( 'ok' => false, 'message' => __( 'Renseignez d’abord le Client ID et le Secret PayPal.', 'infinitycod' ) );
		}
		$host = ( 'sandbox' === $mode ) ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
		$res  = wp_remote_post( $host . '/v1/oauth2/token', array(
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $client . ':' . $secret ),
				'Content-Type'  => 'application/x-www-form-urlencoded',
			),
			'body'    => 'grant_type=client_credentials',
		) );
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'message' => $res->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( 200 !== $code || empty( $body['access_token'] ) ) {
			$detail = isset( $body['error_description'] ) ? ' — ' . $body['error_description'] : '';
			return array( 'ok' => false, 'message' => __( 'PayPal a refusé les identifiants', 'infinitycod' ) . $detail );
		}
		return array( 'ok' => true, 'message' => __( 'Connexion PayPal validée — token OAuth obtenu.', 'infinitycod' ) );
	}
}
