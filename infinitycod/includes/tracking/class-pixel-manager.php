<?php
/**
 * Tracking : pixels publicitaires + Conversions API (Meta) côté serveur.
 *
 * Module complet de mesure du funnel COD :
 *   - Meta (Facebook) : pixel navigateur + Conversions API serveur,
 *     conforme aux exigences 2026 : event_id de déduplication navigateur/
 *     serveur, hachage SHA-256 du téléphone (advanced matching), transfert
 *     des cookies _fbp/_fbc, action_source et event_source_url.
 *   - TikTok : pixel navigateur (ViewContent, InitiateCheckout, CompletePayment).
 *   - Snapchat : pixel navigateur (PAGE_VIEW, VIEW_CONTENT, PURCHASE).
 *
 * Respect de la vie privée : aucun pixel n'est chargé tant qu'aucun ID
 * n'est configuré ; option « consentement requis » (ne charge qu'après
 * window.icodConsent = true ou un cookie icod_consent=1, posé par un CMP).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Tracking;

use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class PixelManager {

	/**
	 * Hooks front + serveur.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'infinitycod_order_created', array( $this, 'send_capi_purchase' ), 10, 3 );
	}

	/**
	 * Au moins un pixel est-il actif ?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return Settings::get( 'pixel_fb_enabled' )
			|| Settings::get( 'pixel_tiktok_enabled' )
			|| Settings::get( 'pixel_snap_enabled' );
	}

	/**
	 * Charge le script pixels sur les pages utiles (fiches produit, ou tout
	 * le site si l'option est activée).
	 *
	 * @return void
	 */
	public function assets() {
		if ( ! self::enabled() ) {
			return;
		}

		$is_product = function_exists( 'is_product' ) && is_product();
		if ( ! Settings::get( 'pixel_sitewide' ) && ! $is_product ) {
			return;
		}

		// Données produit pour ViewContent.
		$product_data = array(
			'id'    => 0,
			'name'  => '',
			'price' => 0,
		);
		if ( $is_product ) {
			global $product;
			if ( $product instanceof \WC_Product ) {
				$product_data = array(
					'id'    => (int) $product->get_id(),
					'name'  => wp_strip_all_tags( $product->get_name() ),
					'price' => (float) $product->get_price(),
				);
			}
		}

		wp_enqueue_script(
			'icod-pixels',
			INFINITYCOD_URL . 'assets/front/js/pixels.js',
			array(),
			INFINITYCOD_VERSION,
			false // Head : le pixel doit être chargé tôt.
		);

		wp_localize_script( 'icod-pixels', 'icodPixels', array(
			'fb'        => Settings::get( 'pixel_fb_enabled' ) ? array(
				'id'     => (string) Settings::get( 'pixel_fb_id' ),
				'test'   => (string) Settings::get( 'pixel_fb_test_code', '' ),
			) : null,
			'tiktok'    => Settings::get( 'pixel_tiktok_enabled' ) ? array(
				'id' => (string) Settings::get( 'pixel_tiktok_id' ),
			) : null,
			'snap'      => Settings::get( 'pixel_snap_enabled' ) ? array(
				'id' => (string) Settings::get( 'pixel_snap_id' ),
			) : null,
			'consent'   => (int) Settings::get( 'pixel_consent_required' ),
			'currency'  => Settings::currency(),
			'product'   => $product_data,
			'eventId'   => 'icod-' . uniqid(),
		) );
	}

	/**
	 * Purchase côté serveur — Conversions API Meta.
	 *
	 * Dédupliqué avec l'événement navigateur via le même event_id
	 * (« icod-{commande} »). Données utilisateur hachées SHA-256.
	 *
	 * @param \WC_Order $order  Commande WooCommerce créée.
	 * @param array     $data   Données validées du formulaire.
	 * @param int       $icod_id Ligne icod_orders.
	 * @return void
	 */
	public function send_capi_purchase( $order, $data, $icod_id ) {
		if ( ! Settings::get( 'pixel_fb_enabled' ) ) {
			return;
		}

		$pixel_id = trim( (string) Settings::get( 'pixel_fb_id' ) );
		$token    = trim( (string) Settings::get( 'pixel_fb_capi_token' ) );
		if ( '' === $pixel_id || '' === $token ) {
			return; // CAPI non configurée : le pixel navigateur suffit.
		}

		// Téléphone : chiffres seuls, indicateur 213, puis SHA-256.
		$phone = isset( $data['phone'] ) ? (string) $data['phone'] : '';
		$phone = preg_replace( '/\D/', '', $phone );
		if ( 0 === strpos( $phone, '0' ) ) {
			$phone = '213' . substr( $phone, 1 );
		}

		$user_data = array(
			'ph'                   => array( hash( 'sha256', $phone ) ),
			'client_ip_address'    => isset( $data['ip'] ) ? (string) $data['ip'] : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' ),
			'client_user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
		);

		// Cookies first-party Meta (non hachés, prévus par la spec CAPI).
		if ( ! empty( $_COOKIE['_fbp'] ) ) {
			$user_data['fbp'] = sanitize_text_field( wp_unslash( $_COOKIE['_fbp'] ) );
		}
		if ( ! empty( $_COOKIE['_fbc'] ) ) {
			$user_data['fbc'] = sanitize_text_field( wp_unslash( $_COOKIE['_fbc'] ) );
		}

		$product_id = isset( $data['product_id'] ) ? (int) $data['product_id'] : 0;
		$quantity   = isset( $data['quantity'] ) ? (int) $data['quantity'] : 1;

		$event = array(
			'event_name'       => 'Purchase',
			'event_time'       => time(),
			'event_id'         => 'icod-' . max( 0, (int) $icod_id ),
			'event_source_url' => home_url( '/' ),
			'action_source'    => 'website',
			'user_data'        => array_filter( $user_data ),
			'custom_data'      => array(
				'currency'     => Settings::currency(),
				'value'        => (float) $order->get_total(),
				'content_type' => 'product',
				'content_ids'  => array( (string) $product_id ),
				'content_name' => $order instanceof \WC_Order ? wp_strip_all_tags( implode( ', ', $order->get_item_names() ) ) : '',
				'num_items'    => $quantity,
				'order_id'     => (int) $icod_id,
			),
		);

		$body = array( 'data' => array( $event ) );
		$test = trim( (string) Settings::get( 'pixel_fb_test_code', '' ) );
		if ( '' !== $test ) {
			$body['test_event_code'] = $test;
		}

		$response = wp_remote_post(
			'https://graph.facebook.com/v19.0/' . rawurlencode( $pixel_id ) . '/events?access_token=' . rawurlencode( $token ),
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			\InfinityCod\Logging\Logger::log( 'api', 'CAPI Purchase échec : ' . $response->get_error_message() );
			return;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		\InfinityCod\Logging\Logger::log( 'api', 'CAPI Purchase envoyée (commande #' . (int) $icod_id . ') : HTTP ' . $code );
	}
}
