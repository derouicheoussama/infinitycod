<?php
/**
 * Paiement en ligne — multi-passereelles : Chargily (CIB / Edahabia),
 * Stripe (Visa / Mastercard) et PayPal.
 *
 * Chargily : https://pay.chargily.net/api/v2/ (live) | /test/api/v2/ (test)
 * Stripe   : https://api.stripe.com/v1/checkout/sessions (Checkout Hosted)
 * PayPal   : https://api-m.paypal.com | https://api-m.sandbox.paypal.com (v2)
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Payment;

use InfinityCod\Core\Schema;
use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders, PluginCheck.Security.DirectDB
// Tables custom InfinityCod : noms de tables issus de Schema::table() (constantes internes,
// jamais d'entree utilisateur) et valeurs toujours liees via $wpdb->prepare(). Requetes
// directes volontaires sur nos propres tables (pas d'equivalent WP_Query), avec caches
// applicatifs la ou c'est chaud (compteurs, tarifs).


class PaymentManager {

	/**
	 * Hook : page de retour après paiement.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'handle_return' ) );
	}

	/**
	 * Passerelle active : chargily | stripe | paypal.
	 *
	 * @return string
	 */
	public static function gateway() {
		$mode = (string) Settings::get( 'payment_mode', 'chargily' );
		return in_array( $mode, array( 'chargily', 'stripe', 'paypal' ), true ) ? $mode : 'chargily';
	}

	/**
	 * Paiement en ligne actif et configuré pour la passerelle choisie ?
	 *
	 * @return bool
	 */
	public static function enabled() {
		if ( ! (bool) Settings::get( 'payment_enabled' ) ) {
			return false;
		}

		switch ( self::gateway() ) {
			case 'stripe':
				return '' !== trim( (string) Settings::get( 'stripe_secret' ) );
			case 'paypal':
				return '' !== trim( (string) Settings::get( 'paypal_client_id' ) )
					&& '' !== trim( (string) Settings::get( 'paypal_secret' ) );
			default:
				return '' !== trim( (string) Settings::get( 'chargily_secret' ) )
					&& 'chargily' === Settings::get( 'payment_mode', 'chargily' );
		}
	}

	/**
	 * Libellé public de l'option « payer en ligne » (formulaire).
	 *
	 * @return string
	 */
	public static function gateway_label() {
		switch ( self::gateway() ) {
			case 'stripe':
				return __( 'Payer par carte', 'infinitycod' );
			case 'paypal':
				return __( 'Payer avec PayPal', 'infinitycod' );
			default:
				return __( 'Payer en ligne', 'infinitycod' );
		}
	}

	/**
	 * Sous-libellé public selon la passerelle.
	 *
	 * @return string
	 */
	public static function gateway_sub_label() {
		switch ( self::gateway() ) {
			case 'stripe':
				return __( 'Paiement sécurisé Visa / Mastercard', 'infinitycod' );
			case 'paypal':
				return __( 'Paiement sécurisé via votre compte PayPal', 'infinitycod' );
			default:
				return __( 'Paiement sécurisé CIB / Edahabia', 'infinitycod' );
		}
	}

	/* =====================================================
	 * Chargily (CIB / Edahabia)
	 * ===================================================== */

	/**
	 * Url de base de l'API selon le mode.
	 *
	 * @return string
	 */
	private function base_url() {
		return 'live' === Settings::get( 'chargily_mode', 'test' )
			? 'https://pay.chargily.net/api/v2/'
			: 'https://pay.chargily.net/test/api/v2/';
	}

	/**
	 * En-têtes d'authentification Chargily.
	 *
	 * @return array
	 */
	private function headers() {
		return array(
			'Authorization' => 'Bearer ' . trim( (string) Settings::get( 'chargily_secret' ) ),
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Crée un checkout chez la passerelle active.
	 *
	 * @param array $result Résultat OrderStore::create_from_form (ok, order_id, icod_id, total).
	 * @param array $input  Données du formulaire (produit, client…).
	 * @return array{ok: bool, redirect: string, message: string}
	 */
	public function create_checkout( array $result, array $input ) {
		if ( empty( $result['ok'] ) || ! self::enabled() ) {
			return array( 'ok' => false, 'redirect' => '', 'message' => __( 'Paiement en ligne indisponible.', 'infinitycod' ) );
		}

		switch ( self::gateway() ) {
			case 'stripe':
				return $this->create_stripe( $result, $input );
			case 'paypal':
				return $this->create_paypal( $result, $input );
			default:
				return $this->create_chargily( $result, $input );
		}
	}

	/**
	 * Checkout Chargily (CIB / Edahabia).
	 *
	 * @param array $result Résultat de création.
	 * @param array $input  Données du formulaire.
	 * @return array{ok: bool, redirect: string, message: string}
	 */
	private function create_chargily( array $result, array $input ) {
		$return_url = add_query_arg(
			array(
				'icod_checkout' => 'CKOUT',
				'icod_order'    => (int) $result['icod_id'],
			),
			home_url( '/' )
		);

		$product_name = ! empty( $input['product_id'] ) ? get_the_title( (int) $input['product_id'] ) : __( 'Commande', 'infinitycod' );

		$payload = array(
			'amount'           => (int) round( (float) $result['total'] ),
			'currency'         => 'dzd',
			'success_url'      => $return_url,
			'failure_url'      => $return_url,
			'webhook_endpoint' => rest_url( 'infinitycod/v1/chargily/webhook' ),
			'description'      => sprintf( '#%1$s — %2$s', $result['order_id'], wp_strip_all_tags( $product_name ) ),
			'metadata'         => array(
				'icod_id'  => (int) $result['icod_id'],
				'wc_order' => (int) $result['order_id'],
			),
		);

		$response = wp_remote_post(
			$this->base_url() . 'checkouts',
			array(
				'timeout' => 25,
				'headers' => $this->headers(),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'redirect' => '', 'message' => $response->get_error_message() );
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( $status < 200 || $status >= 300 || empty( $body['checkout_url'] ) || empty( $body['id'] ) ) {
			$detail = isset( $body['message'] ) ? (string) $body['message'] : '';
			return array( 'ok' => false, 'redirect' => '', 'message' => sprintf( 'Chargily HTTP %d %s', $status, $detail ) );
		}

		// Lie le checkout à la commande COD.
		global $wpdb;
		$wpdb->update(
			Schema::table( 'orders' ),
			array( 'checkout_id' => (string) $body['id'] ),
			array( 'id' => (int) $result['icod_id'] )
		);

		if ( ! empty( $result['order_id'] ) ) {
			$wc_order = wc_get_order( (int) $result['order_id'] );
			if ( $wc_order ) {
				$wc_order->update_meta_data( '_icod_checkout_id', (string) $body['id'] );
				$wc_order->save();
			}
		}

		return array(
			'ok'       => true,
			'redirect' => (string) $body['checkout_url'],
			'message'  => '',
		);
	}

	/**
	 * Vérifie le statut d'un checkout Chargily auprès de l'API.
	 *
	 * @param string $checkout_id ID du checkout.
	 * @return string statut ('paid', 'pending', 'failed', '') ou '' si erreur.
	 */
	public function get_status( $checkout_id ) {
		$response = wp_remote_get(
			$this->base_url() . 'checkouts/' . rawurlencode( (string) $checkout_id ),
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . trim( (string) Settings::get( 'chargily_secret' ) ),
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return isset( $body['status'] ) ? (string) $body['status'] : '';
	}

	/* =====================================================
	 * Stripe Checkout (Visa / Mastercard)
	 * ===================================================== */

	/**
	 * Convertit le total boutique vers la devise Stripe (taux marchand),
	 * en unités mineures.
	 *
	 * @param float $total Total en devise de la boutique.
	 * @return array{currency:string,amount:int}
	 */
	private function stripe_amount( $total ) {
		$currency = strtolower( trim( (string) Settings::get( 'stripe_currency', 'usd' ) ) );
		if ( '' === $currency ) {
			$currency = 'usd';
		}
		$rate   = (float) Settings::get( 'stripe_rate', 1 );
		$amount = round( max( 0.5, (float) $total * ( $rate > 0 ? $rate : 1 ) ) * 100 );
		return array(
			'currency' => $currency,
			'amount'   => (int) $amount,
		);
	}

	/**
	 * Checkout Stripe Hosted (cartes Visa / Mastercard, wallets inclus).
	 *
	 * @param array $result Résultat de création.
	 * @param array $input  Données du formulaire.
	 * @return array{ok: bool, redirect: string, message: string}
	 */
	private function create_stripe( array $result, array $input ) {
		$secret = trim( (string) Settings::get( 'stripe_secret' ) );
		if ( '' === $secret ) {
			return array( 'ok' => false, 'redirect' => '', 'message' => __( 'Clé Stripe manquante.', 'infinitycod' ) );
		}

		$product_name = ! empty( $input['product_id'] ) ? get_the_title( (int) $input['product_id'] ) : __( 'Commande', 'infinitycod' );
		$money        = $this->stripe_amount( (float) $result['total'] );

		$return_ok = add_query_arg(
			array(
				'icod_gateway' => 'stripe',
				'icod_order'   => (int) $result['icod_id'],
			),
			home_url( '/' )
		) . '&session_id={CHECKOUT_SESSION_ID}';
		$return_ko = add_query_arg(
			array(
				'icod_gateway' => 'stripe',
				'icod_order'   => (int) $result['icod_id'],
				'cancel'       => '1',
			),
			home_url( '/' )
		);

		$body = array(
			'mode'                                          => 'payment',
			'success_url'                                   => $return_ok,
			'cancel_url'                                    => $return_ko,
			'client_reference_id'                           => (string) $result['icod_id'],
			'metadata[icod_id]'                             => (string) $result['icod_id'],
			'line_items[0][quantity]'                       => 1,
			'line_items[0][price_data][currency]'           => $money['currency'],
			'line_items[0][price_data][unit_amount]'        => $money['amount'],
			'line_items[0][price_data][product_data][name]' => wp_strip_all_tags( $product_name ),
		);

		$response = wp_remote_post(
			'https://api.stripe.com/v1/checkout/sessions',
			array(
				'timeout' => 25,
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'redirect' => '', 'message' => $response->get_error_message() );
		}

		$json   = json_decode( wp_remote_retrieve_body( $response ), true );
		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( $status < 200 || $status >= 300 || empty( $json['url'] ) || empty( $json['id'] ) ) {
			$detail = isset( $json['error']['message'] ) ? (string) $json['error']['message'] : '';
			return array( 'ok' => false, 'redirect' => '', 'message' => sprintf( 'Stripe HTTP %d %s', $status, $detail ) );
		}

		global $wpdb;
		$wpdb->update(
			Schema::table( 'orders' ),
			array( 'checkout_id' => (string) $json['id'] ),
			array( 'id' => (int) $result['icod_id'] )
		);

		return array(
			'ok'       => true,
			'redirect' => (string) $json['url'],
			'message'  => '',
		);
	}

	/**
	 * Statut d'une session Stripe Checkout.
	 *
	 * @param string $session_id ID de session.
	 * @return string 'paid' | 'pending' | 'failed' | ''
	 */
	private function stripe_session_status( $session_id ) {
		$secret = trim( (string) Settings::get( 'stripe_secret' ) );
		if ( '' === $secret || '' === (string) $session_id ) {
			return '';
		}

		$response = wp_remote_get(
			'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode( (string) $session_id ),
			array(
				'timeout' => 20,
				'headers' => array( 'Authorization' => 'Bearer ' . $secret ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $json['payment_status'] ) ) {
			return '';
		}

		$map = array( 'paid' => 'paid', 'unpaid' => 'pending', 'no_payment_required' => 'paid' );
		return isset( $map[ $json['payment_status'] ] ) ? $map[ $json['payment_status'] ] : 'pending';
	}

	/* =====================================================
	 * PayPal (Orders v2)
	 * ===================================================== */

	/**
	 * Base API PayPal selon le mode.
	 *
	 * @return string
	 */
	private function paypal_base() {
		return 'sandbox' === Settings::get( 'paypal_mode', 'live' )
			? 'https://api-m.sandbox.paypal.com/'
			: 'https://api-m.paypal.com/';
	}

	/**
	 * Jeton OAuth2 PayPal.
	 *
	 * @return string '' si échec.
	 */
	private function paypal_token() {
		$client = trim( (string) Settings::get( 'paypal_client_id' ) );
		$secret = trim( (string) Settings::get( 'paypal_secret' ) );
		if ( '' === $client || '' === $secret ) {
			return '';
		}

		$response = wp_remote_post(
			$this->paypal_base() . 'v1/oauth2/token',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( $client . ':' . $secret ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- auth OAuth2 PayPal.
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => 'grant_type=client_credentials',
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		return isset( $json['access_token'] ) ? (string) $json['access_token'] : '';
	}

	/**
	 * Convertit le total boutique vers la devise PayPal (taux marchand).
	 *
	 * @param float $total Total en devise de la boutique.
	 * @return array{currency:string,value:string}
	 */
	private function paypal_amount( $total ) {
		$currency = strtoupper( trim( (string) Settings::get( 'paypal_currency', 'USD' ) ) );
		if ( '' === $currency ) {
			$currency = 'USD';
		}
		$rate  = (float) Settings::get( 'paypal_rate', 1 );
		$value = round( max( 0.5, (float) $total * ( $rate > 0 ? $rate : 1 ) ), 2 );
		return array(
			'currency' => $currency,
			'value'    => number_format( $value, 2, '.', '' ),
		);
	}

	/**
	 * Commande PayPal (Orders v2) + url d'approbation.
	 *
	 * @param array $result Résultat de création.
	 * @param array $input  Données du formulaire.
	 * @return array{ok: bool, redirect: string, message: string}
	 */
	private function create_paypal( array $result, array $input ) {
		$token = $this->paypal_token();
		if ( '' === $token ) {
			return array( 'ok' => false, 'redirect' => '', 'message' => __( 'Connexion PayPal impossible (identifiants ?).', 'infinitycod' ) );
		}

		$product_name = ! empty( $input['product_id'] ) ? get_the_title( (int) $input['product_id'] ) : __( 'Commande', 'infinitycod' );
		$money        = $this->paypal_amount( (float) $result['total'] );

		$return_url = add_query_arg(
			array(
				'icod_gateway' => 'paypal',
				'icod_order'   => (int) $result['icod_id'],
			),
			home_url( '/' )
		);
		$cancel_url = add_query_arg(
			array(
				'icod_gateway' => 'paypal',
				'icod_order'   => (int) $result['icod_id'],
				'cancel'       => '1',
			),
			home_url( '/' )
		);

		$payload = array(
			'intent'              => 'CAPTURE',
			'purchase_units'      => array(
				array(
					'custom_id'   => (string) $result['icod_id'],
					'description' => wp_strip_all_tags( $product_name ),
					'amount'      => array(
						'currency_code' => $money['currency'],
						'value'         => $money['value'],
					),
				),
			),
			'application_context' => array(
				'brand_name'  => get_bloginfo( 'name' ),
				'user_action' => 'PAY_NOW',
				'return_url'  => $return_url,
				'cancel_url'  => $cancel_url,
			),
		);

		$response = wp_remote_post(
			$this->paypal_base() . 'v2/checkout/orders',
			array(
				'timeout' => 25,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'redirect' => '', 'message' => $response->get_error_message() );
		}

		$json   = json_decode( wp_remote_retrieve_body( $response ), true );
		$status = (int) wp_remote_retrieve_response_code( $response );

		$approve = '';
		if ( is_array( $json ) && ! empty( $json['links'] ) ) {
			foreach ( $json['links'] as $link ) {
				if ( isset( $link['rel'] ) && 'approve' === $link['rel'] && ! empty( $link['href'] ) ) {
					$approve = (string) $link['href'];
					break;
				}
			}
		}

		if ( $status < 200 || $status >= 300 || '' === $approve || empty( $json['id'] ) ) {
			$detail = isset( $json['message'] ) ? (string) $json['message'] : '';
			return array( 'ok' => false, 'redirect' => '', 'message' => sprintf( 'PayPal HTTP %d %s', $status, $detail ) );
		}

		global $wpdb;
		$wpdb->update(
			Schema::table( 'orders' ),
			array( 'checkout_id' => (string) $json['id'] ),
			array( 'id' => (int) $result['icod_id'] )
		);

		return array(
			'ok'       => true,
			'redirect' => $approve,
			'message'  => '',
		);
	}

	/**
	 * Capture une commande PayPal approuvée.
	 *
	 * @param string $order_id ID commande PayPal.
	 * @return string 'paid' (COMPLETED) | 'pending' | 'failed' | ''
	 */
	private function paypal_capture( $order_id ) {
		$token = $this->paypal_token();
		if ( '' === $token || '' === (string) $order_id ) {
			return '';
		}

		$response = wp_remote_post(
			$this->paypal_base() . 'v2/checkout/orders/' . rawurlencode( (string) $order_id ) . '/capture',
			array(
				'timeout' => 25,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$json = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $json['status'] ) ) {
			return '';
		}
		if ( 'COMPLETED' === $json['status'] ) {
			return 'paid';
		}
		return 'pending';
	}

	/* =====================================================
	 * Retour client + confirmation
	 * ===================================================== */

	/**
	 * Page de retour du client après paiement (toutes passerelles).
	 *
	 * Vérifie le statut réel auprès de l'API avant de marquer la commande.
	 *
	 * @return void
	 */
	public function handle_return() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- retour PUBLIC de la passerelle (redirection navigateur, aucun nonce possible) : chaque paiement est revérifié côté API avant validation.
		$icod_id = isset( $_GET['icod_order'] ) ? absint( $_GET['icod_order'] ) : 0;
		$gateway = isset( $_GET['icod_gateway'] ) ? sanitize_key( wp_unslash( $_GET['icod_gateway'] ) ) : '';

		if ( $icod_id < 1 ) {
			return;
		}

		$paid = false;
		$ref  = '';

		if ( 'stripe' === $gateway ) {
			$session = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- vérifié contre l'API Stripe.
			$paid    = 'paid' === $this->stripe_session_status( $session );
			$ref     = $session;
		} elseif ( 'paypal' === $gateway ) {
			$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- vérifié contre l'API PayPal.
			$paid  = 'paid' === $this->paypal_capture( $token );
			$ref   = $token;
		} elseif ( isset( $_GET['icod_checkout'] ) ) {
			$checkout_id = sanitize_text_field( wp_unslash( $_GET['icod_checkout'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- vérifié contre l'API Chargily.
			$paid        = self::enabled() && 'paid' === $this->get_status( $checkout_id );
			$ref         = $checkout_id;
			$gateway     = 'chargily';
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $paid ) {
			self::mark_paid( $icod_id, $ref, $gateway );
			$message = (string) Settings::get( 'payment_return_text' );
			$success = true;
		} else {
			$message = __( 'Le paiement n‘a pas été finalisé. Vous pouvez réessayer ou nous contacter pour confirmer votre commande.', 'infinitycod' );
			$success = false;
		}

		wp_load_translations_early();

		nocache_headers();
		$title = $success ? __( 'Paiement confirmé — InfinityCod', 'infinitycod' ) : __( 'Paiement non finalisé — InfinityCod', 'infinitycod' );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( $title ); ?></title>
	<style>
		body{margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f6f8f8;display:flex;min-height:100vh;align-items:center;justify-content:center;color:#16191d}
		.icod-thanks{background:#fff;border:1px solid #dfe3e8;border-radius:16px;box-shadow:0 6px 24px rgba(16,24,40,.08);max-width:480px;margin:24px;padding:40px 32px;text-align:center}
		.icod-thanks .emoji{font-size:3rem;line-height:1}
		.icod-thanks h1{font-size:1.35rem;margin:14px 0 8px}
		.icod-thanks p{margin:0 0 22px;color:#5f6670;line-height:1.6}
		.icod-thanks a{display:inline-block;padding:12px 26px;border-radius:10px;background:#0e7a4f;color:#fff;text-decoration:none;font-weight:700}
	</style>
</head>
<body>
	<div class="icod-thanks">
		<div class="emoji"><?php echo $success ? '✅' : '⚠️'; ?></div>
		<h1><?php echo esc_html( $title ); ?></h1>
		<p><?php echo esc_html( $message ); ?></p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Retour à la boutique', 'infinitycod' ); ?></a>
	</div>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Marque une commande COD comme payée (flag + statuts + WooCommerce).
	 *
	 * @param int    $icod_id     Ligne commande.
	 * @param string $checkout_id ID checkout (optionnel, re-lié si fourni).
	 * @param string $gateway     Passerelle (chargily | stripe | paypal).
	 * @return bool
	 */
	public static function mark_paid( $icod_id, $checkout_id = '', $gateway = 'chargily' ) {
		global $wpdb;

		$gateway = in_array( (string) $gateway, array( 'chargily', 'stripe', 'paypal' ), true ) ? (string) $gateway : 'chargily';
		$table   = Schema::table( 'orders' );
		$order   = Schema::get_order( (int) $icod_id );

		if ( ! $order ) {
			return false;
		}

		$updated = $wpdb->update(
			$table,
			array(
				'payment'     => $gateway,
				'paid'        => 1,
				'paid_at'     => current_time( 'mysql' ),
				'checkout_id' => $checkout_id ? (string) $checkout_id : $order['checkout_id'],
			),
			array( 'id' => (int) $icod_id )
		);

		if ( false === $updated ) {
			return false;
		}

		// Flux COD : payée = confirmée.
		$orders = infinitycod()->module( 'orders' );
		if ( $orders && ! in_array( $order['status'], array( 'delivered', 'cancelled' ), true ) ) {
			$orders->set_status( (int) $icod_id, 'confirmed' );
		}

		// WooCommerce : paiement complété (→ processing).
		if ( $order['wc_order_id'] ) {
			$wc_order = wc_get_order( (int) $order['wc_order_id'] );
			if ( $wc_order && ! $wc_order->is_paid() ) {
				$wc_order->update_meta_data( '_icod_checkout_id', $checkout_id ? (string) $checkout_id : $order['checkout_id'] );
				$wc_order->payment_complete( $gateway . '_' . ( $checkout_id ? $checkout_id : $order['checkout_id'] ) );
				$wc_order->add_order_note( sprintf( /* translators: %s : passerelle. */ __( 'Paiement en ligne reçu via %s (InfinityCod).', 'infinitycod' ), ucfirst( $gateway ) ) );
				$wc_order->save();
			}
		}

		/**
		 * Après confirmation d'un paiement en ligne.
		 *
		 * @param int    $icod_id Ligne commande.
		 * @param string $checkout_id ID checkout passerelle.
		 */
		do_action( 'infinitycod_payment_paid', (int) $icod_id, (string) $checkout_id );

		return true;
	}

	/**
	 * Traite un webhook Chargily (appelé par Rest\Routes).
	 *
	 * @param string $raw_body  Corps brut de la requête.
	 * @param string $signature En-tête « Signature ».
	 * @return bool
	 */
	public function handle_webhook( $raw_body, $signature ) {
		$secret = trim( (string) Settings::get( 'chargily_secret' ) );

		if ( '' === $secret || '' === (string) $signature ) {
			return false;
		}

		$computed = hash_hmac( 'sha256', (string) $raw_body, $secret );

		if ( ! hash_equals( $computed, (string) $signature ) ) {
			return false;
		}

		$event = json_decode( (string) $raw_body, true );

		if ( ! is_array( $event ) || empty( $event['type'] ) || empty( $event['data']['id'] ) ) {
			return false;
		}

		// Paiement finalisé : checkout.paid (checkout.failed = rien à faire).
		if ( 'checkout.paid' !== $event['type'] ) {
			return true;
		}

		$checkout_id = (string) $event['data']['id'];
		$icod_id     = $this->find_order_by_checkout( $checkout_id );

		if ( $icod_id ) {
			return self::mark_paid( $icod_id, $checkout_id, 'chargily' );
		}

		return false;
	}

	/**
	 * Trouve la ligne commande liée à un checkout.
	 *
	 * @param string $checkout_id ID checkout.
	 * @return int 0 si introuvable.
	 */
	private function find_order_by_checkout( $checkout_id ) {
		global $wpdb;

		$table = Schema::table( 'orders' );
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE checkout_id = %s LIMIT 1", (string) $checkout_id ) // phpcs:ignore WordPress.DB.PreparedSQL
		);
	}
}
