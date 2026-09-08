<?php
/**
 * Endpoints REST du front : communes, bureaux, devis, soumission, abandons.
 *
 * Les visiteurs sont anonymes : pas de nonce WP (le bouclier Shield
 * protège la soumission). Toute la tarification est calculée ici,
 * côté serveur — jamais dans le navigateur.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Rest;

use InfinityCod\AntiFraud\Shield;
use InfinityCod\Form\OffersEngine;
use InfinityCod\Form\Validator;
use InfinityCod\Shipping\RatesManager;

defined( 'ABSPATH' ) || exit;

class Routes {

	const NAMESPACE_V1 = 'infinitycod/v1';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Déclare les routes.
	 *
	 * @return void
	 */
	public function routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/communes',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'communes' ),
				'permission_callback' => '__return_true', // Données publiques (localités).
				'args'                => array(
					'wilaya' => array(
						'required'          => true,
						'validate_callback' => function ( $value ) {
							return (bool) preg_match( '/^\d{1,2}$/', (string) $value );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/stopdesks',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'stopdesks' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'wilaya' => array(
						'required'          => true,
						'validate_callback' => function ( $value ) {
							return (bool) preg_match( '/^\d{1,2}$/', (string) $value );
						},
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/quote',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'quote' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/submit',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'submit' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/chargily/webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'chargily_webhook' ),
				'permission_callback' => '__return_true', // authentifiée par signature HMAC.
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/abandoned',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'abandoned' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * GET /communes?wilaya=16
	 *
	 * @param \WP_REST_Request $request Requête.
	 * @return \WP_REST_Response
	 */
	public function communes( $request ) {
		$geo      = infinitycod()->module( 'geo' );
		$wilaya   = str_pad( sanitize_text_field( $request->get_param( 'wilaya' ) ), 2, '0', STR_PAD_LEFT );
		$communes = $geo ? $geo->communes( $wilaya, true ) : array();

		$out = array();
		foreach ( $communes as $commune ) {
			$out[] = array(
				'fr'      => $commune['name_fr'],
				'ar'      => $commune['name_ar'],
				'has_desk' => ! empty( $commune['has_desk'] ) ? 1 : 0,
			);
		}

		return rest_ensure_response( array( 'communes' => $out ) );
	}

	/**
	 * GET /stopdesks?wilaya=16
	 *
	 * @param \WP_REST_Request $request Requête.
	 * @return \WP_REST_Response
	 */
	public function stopdesks( $request ) {
		$geo     = infinitycod()->module( 'geo' );
		$wilaya  = str_pad( sanitize_text_field( $request->get_param( 'wilaya' ) ), 2, '0', STR_PAD_LEFT );
		$desks   = $geo ? $geo->stopdesks( $wilaya ) : array();

		$out = array();
		foreach ( $desks as $desk ) {
			$out[] = array(
				'name'    => $desk['name'],
				'commune' => $desk['commune'],
				'carrier' => $desk['carrier'],
			);
		}

		return rest_ensure_response( array( 'stopdesks' => $out ) );
	}

	/**
	 * POST /quote — prix officiels (produit, remise, livraison, total).
	 *
	 * @param \WP_REST_Request $request Requête.
	 * @return \WP_REST_Response
	 */
	public function quote( $request ) {
		$body = $this->body( $request );

		$product_id  = absint( $body['product_id'] );
		$variation_id = isset( $body['variation_id'] ) ? absint( $body['variation_id'] ) : 0;
		$quantity    = max( 1, min( 99, isset( $body['quantity'] ) ? absint( $body['quantity'] ) : 1 ) );
		$wilaya_code = isset( $body['wilaya'] ) ? str_pad( sanitize_text_field( $body['wilaya'] ), 2, '0', STR_PAD_LEFT ) : '';
		$commune     = isset( $body['commune'] ) ? sanitize_text_field( $body['commune'] ) : '';
		$mode        = ( isset( $body['mode'] ) && 'desk' === $body['mode'] ) ? RatesManager::MODE_DESK : RatesManager::MODE_HOME;

		$product = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
		if ( ! $product ) {
			return new \WP_Error( 'icod_product', __( 'Produit introuvable.', 'infinitycod' ), array( 'status' => 404 ) );
		}

		$rates = infinitycod()->module( 'rates' );

		$price_home = $rates ? $rates->price( $wilaya_code, $commune, RatesManager::MODE_HOME ) : -1;
		$price_desk = $rates ? $rates->price( $wilaya_code, $commune, RatesManager::MODE_DESK ) : -1;
		$free       = $rates ? $rates->is_free( $wilaya_code, $quantity ) : false;

		$unit_price = (float) $product->get_price();
		$discount   = OffersEngine::discount( $product->get_id(), $unit_price, $quantity );
		$shipping   = ( RatesManager::MODE_DESK === $mode ? $price_desk : $price_home );
		$shipping   = $free ? 0 : $shipping;

		$subtotal = round( $unit_price * $quantity, 2 );

		return rest_ensure_response( array(
			'unit'          => $unit_price,
			'subtotal'      => $subtotal,
			'discount_pct'  => $discount['pct'],
			'discount'      => $discount['amount'],
			'shipping'      => $shipping,
			'price_home'    => $price_home,
			'price_desk'    => $price_desk,
			'free'          => $free ? 1 : 0,
			'total'         => max( 0, $subtotal - $discount['amount'] + max( 0, $shipping ) ),
		) );
	}

	/**
	 * POST /submit — création de commande COD complète.
	 *
	 * @param \WP_REST_Request $request Requête.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit( $request ) {
		$body = $this->body( $request );

		// 0. Restriction horaire des commandes.
		if ( Settings::get( 'restrict_hours_enabled' ) ) {
			$from = max( 0, min( 23, (int) Settings::get( 'restrict_hours_from', 9 ) ) );
			$to   = max( 0, min( 23, (int) Settings::get( 'restrict_hours_to', 22 ) ) );
			$h    = (int) current_time( 'G' );
			$open = ( $from <= $to ) ? ( $h >= $from && $h < $to ) : ( $h >= $from || $h < $to );
			if ( ! $open ) {
				return new \WP_Error(
					'icod_hours',
					sprintf(
						/* translators: 1 : heure d'ouverture, 2 : heure de fermeture. */
						__( 'Les commandes sont acceptées entre %1$02dh et %2$02dh. Revenez pendant ces horaires !', 'infinitycod' ),
						$from,
						$to
					),
					array( 'status' => 400 )
				);
			}
		}

		// 1. Champs obligatoires.
		$name  = isset( $body['name'] ) ? Validator::clean_name( $body['name'] ) : '';
		$phone = isset( $body['phone'] ) ? Validator::normalize_phone( $body['phone'] ) : null;

		if ( ! Validator::is_valid_name( $name ) ) {
			return new \WP_Error( 'icod_name', __( 'Veuillez saisir votre nom complet.', 'infinitycod' ), array( 'status' => 400 ) );
		}

		if ( null === $phone || ( \InfinityCod\Core\Settings::get( 'phone_strict', 1 ) && ! Validator::is_valid_phone( $body['phone'] ) ) ) {
			return new \WP_Error( 'icod_phone', __( 'Numéro de téléphone algérien invalide (ex. 0555123456).', 'infinitycod' ), array( 'status' => 400 ) );
		}

		$geo        = infinitycod()->module( 'geo' );
		$wilaya_code = isset( $body['wilaya'] ) ? str_pad( sanitize_text_field( $body['wilaya'] ), 2, '0', STR_PAD_LEFT ) : '';
		$wilaya     = $geo ? $geo->wilaya( $wilaya_code ) : null;
		if ( ! $wilaya || empty( $wilaya['active'] ) ) {
			return new \WP_Error( 'icod_wilaya', __( 'Wilaya non desservie, veuillez en choisir une autre.', 'infinitycod' ), array( 'status' => 400 ) );
		}

		$commune_name = isset( $body['commune'] ) ? sanitize_text_field( $body['commune'] ) : '';
		$commune      = $geo ? $geo->commune( $wilaya_code, $commune_name ) : null;
		if ( ! $commune || empty( $commune['active'] ) ) {
			return new \WP_Error( 'icod_commune', __( 'Commune introuvable pour cette wilaya.', 'infinitycod' ), array( 'status' => 400 ) );
		}

		$mode     = ( isset( $body['mode'] ) && 'desk' === $body['mode'] ) ? RatesManager::MODE_DESK : RatesManager::MODE_HOME;
		$stopdesk = isset( $body['stopdesk'] ) ? sanitize_text_field( $body['stopdesk'] ) : '';
		$payment  = ( isset( $body['payment'] ) && 'online' === $body['payment'] && \InfinityCod\Payment\PaymentManager::enabled() ) ? 'online' : 'cod';
		$email    = isset( $body['email'] ) ? sanitize_email( $body['email'] ) : '';
		if ( $email && ! is_email( $email ) ) {
			$email = '';
		}
		if ( RatesManager::MODE_DESK === $mode && '' === $stopdesk ) {
			return new \WP_Error( 'icod_desk', __( 'Veuillez choisir un bureau de retrait.', 'infinitycod' ), array( 'status' => 400 ) );
		}

		// 2. Bouclier anti-fraude.
		$shield = infinitycod()->module( 'shield' );
		$assessment = $shield ? $shield->assess( array(
			'name'        => $name,
			'phone'       => isset( $body['phone'] ) ? $body['phone'] : '',
			'wilaya'      => $wilaya_code,
			'commune'     => $commune_name,
			'mode'        => $mode,
			'quantity'    => isset( $body['quantity'] ) ? absint( $body['quantity'] ) : 1,
			'product_id'  => isset( $body['product_id'] ) ? absint( $body['product_id'] ) : 0,
			'email'       => isset( $body['email'] ) ? sanitize_email( $body['email'] ) : '',
			'honeypot'    => isset( $body['honeypot'] ) ? sanitize_text_field( $body['honeypot'] ) : '',
			'ts'          => isset( $body['ts'] ) ? absint( $body['ts'] ) : 0,
			'sig'         => isset( $body['sig'] ) ? sanitize_text_field( $body['sig'] ) : '',
			'fingerprint' => isset( $body['fingerprint'] ) ? sanitize_text_field( $body['fingerprint'] ) : '',
		) ) : array( 'score' => 0, 'flags' => array(), 'blocked' => false );

		if ( ! empty( $assessment['blocked'] ) ) {
			// Message volontairement générique : ne pas révéler les barrières.
			return rest_ensure_response( array(
				'ok'    => false,
				'code'  => 'blocked',
				'message' => __( 'Impossible d‘enregistrer la commande pour le moment. Contactez-nous directement par téléphone.', 'infinitycod' ),
			) );
		}

		// 3. Produit.
		$product_id   = isset( $body['product_id'] ) ? absint( $body['product_id'] ) : 0;
		$variation_id = isset( $body['variation_id'] ) ? absint( $body['variation_id'] ) : 0;
		$product      = wc_get_product( $variation_id ? $variation_id : $product_id );

		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return new \WP_Error( 'icod_product', __( 'Ce produit n‘est plus disponible.', 'infinitycod' ), array( 'status' => 400 ) );
		}

		// 4. Création effective.
		$orders = infinitycod()->module( 'orders' );

		$result = $orders->create_from_form( array(
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'quantity'     => isset( $body['quantity'] ) ? absint( $body['quantity'] ) : 1,
			'name'         => $name,
			'phone'        => $phone,
			'email'        => $email,
			'wilaya_code'  => $wilaya_code,
			'commune'      => $commune_name,
			'mode'         => $mode,
			'stopdesk'     => $stopdesk,
			'payment'      => $payment,
			'note'         => isset( $body['note'] ) ? $body['note'] : '',
			'fraud_score'  => isset( $assessment['score'] ) ? $assessment['score'] : 0,
			'fraud_flags'  => isset( $assessment['flags'] ) ? $assessment['flags'] : array(),
			'ip'           => Shield::client_ip(),
			'fingerprint'  => isset( $body['fingerprint'] ) ? sanitize_text_field( $body['fingerprint'] ) : '',
		) );

		if ( empty( $result['ok'] ) ) {
			$messages = array(
				'product_unavailable' => __( 'Ce produit n‘est plus disponible.', 'infinitycod' ),
				'insufficient_stock'  => __( 'Stock insuffisant pour cette quantité.', 'infinitycod' ),
				'invalid_wilaya'      => __( 'Wilaya non desservie.', 'infinitycod' ),
				'no_shipping_rate'    => __( 'Aucun tarif de livraison pour cette destination.', 'infinitycod' ),
			);
			$code = $result['error'];
			return new \WP_Error( 'icod_' . $code, isset( $messages[ $code ] ) ? $messages[ $code ] : __( 'Une erreur est survenue, réessayez.', 'infinitycod' ), array( 'status' => 400 ) );
		}

		// 5. Paiement en ligne : création du checkout Chargily et redirection.
		if ( 'online' === $payment ) {
			$pay  = infinitycod()->module( 'payment' );
			$res2 = $pay ? $pay->create_checkout( $result, $body ) : array( 'ok' => false );

			if ( ! empty( $res2['ok'] ) ) {
				return rest_ensure_response( array(
					'ok'       => true,
					'redirect' => $res2['redirect'],
					'order_id' => (int) $result['order_id'],
					'total'    => (float) $result['total'],
				) );
			}
			// Échec checkout : la commande reste COD (dégradation propre).
		}

		// 6. Le panier abandonné éventuel est considéré récupéré.
		if ( class_exists( '\\InfinityCod\\Orders\\Abandoned' ) ) {
			\InfinityCod\Orders\Abandoned::mark_recovered_by_phone( $phone );
		}

		$wa_url = '';
		if ( ! empty( $body['via_whatsapp'] ) && Settings::get( 'wa_order_enabled' ) && Settings::get( 'whatsapp_number' ) ) {
			$wa_to  = (string) preg_replace( '/\D/', '', (string) Settings::get( 'whatsapp_number' ) );
			$wa_msg = \InfinityCod\Whatsapp\WhatsappManager::render_template(
				Settings::get( 'msg_wa_order' ),
				array(
					'num'       => (int) $result['order_id'],
					'nom'       => $name,
					'telephone' => $phone,
					'produit'   => $product ? wp_strip_all_tags( $product->get_name() ) : '',
					'total'     => number_format_i18n( (float) $result['total'], 0 ) . ' DA',
					'wilaya'    => $wilaya['name_fr'],
					'commune'   => $commune_name,
				)
			);
			$wa_url = 'https://wa.me/' . $wa_to . '?text=' . rawurlencode( $wa_msg );
		}

		return rest_ensure_response( array(
			'ok'       => true,
			'order_id' => (int) $result['order_id'],
			'total'    => (float) $result['total'],
			'wa_url'   => $wa_url,
		) );
	}

	/**
	 * POST /abandoned — suivi léger des sessions incomplètes.
	 *
	 * @param \WP_REST_Request $request Requête.
	 * @return \WP_REST_Response
	 */
	public function abandoned( $request ) {
		$body = $this->body( $request );

		$name   = isset( $body['name'] ) ? Validator::clean_name( $body['name'] ) : '';
		$phone  = isset( $body['phone'] ) ? Validator::normalize_phone( $body['phone'] ) : '';
		$wilaya = isset( $body['wilaya'] ) ? str_pad( sanitize_text_field( $body['wilaya'] ), 2, '0', STR_PAD_LEFT ) : '';

		if ( '' === $phone && '' === $name ) {
			return rest_ensure_response( array( 'ok' => true, 'skipped' => 1 ) );
		}

		// Anti-abus : 10 mises à jour max par session et par minute.
		$session = isset( $_COOKIE['icod_sid'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) wp_unslash( $_COOKIE['icod_sid'] ) ) : '';
		if ( '' === $session ) {
			$session = substr( hash( 'sha256', wp_salt( 'auth' ) . Shield::client_ip() . ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '' ) ), 0, 32 );
		}
		$throttle_key = 'icod_ab_' . $session;
		$hits         = (int) get_transient( $throttle_key );
		if ( $hits >= 10 ) {
			return rest_ensure_response( array( 'ok' => true, 'skipped' => 1 ) );
		}
		set_transient( $throttle_key, $hits + 1, MINUTE_IN_SECONDS );

		global $wpdb;
		$table = \InfinityCod\Core\Schema::table( 'abandoned' );
		$now   = current_time( 'mysql' );

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table} WHERE session_key = %s AND status = 'open' LIMIT 1", $session ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

		$values = array(
			'product_id'   => isset( $body['product_id'] ) ? absint( $body['product_id'] ) : 0,
			'customer_name' => $name,
			'phone'        => $phone,
			'wilaya_code'  => $wilaya,
			'commune'      => isset( $body['commune'] ) ? sanitize_text_field( $body['commune'] ) : '',
			'cart_total'   => isset( $body['total'] ) ? (float) $body['total'] : 0,
			'progress'     => isset( $body['progress'] ) ? min( 100, absint( $body['progress'] ) ) : 0,
			'updated_at'   => $now,
		);

		if ( $row ) {
			$wpdb->update( $table, $values, array( 'id' => (int) $row['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		} else {
			$values['session_key'] = $session;
			$values['created_at']  = $now;
			$values['status']      = 'open';
			$wpdb->insert( $table, $values ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}

		return rest_ensure_response( array( 'ok' => true ) );
	}

	/**
	 * POST /chargily/webhook — notifications de paiement (signature HMAC).
	 *
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response
	 */
	public function chargily_webhook( $request ) {
		$raw  = (string) $request->get_body();
		$sig  = (string) $request->get_header( 'signature' );
		$pay  = infinitycod()->module( 'payment' );
		$ok   = $pay ? $pay->handle_webhook( $raw, $sig ) : false;

		return rest_ensure_response( array( 'ok' => $ok ) );
	}

	/**
	 * Corps JSON ou form-data de la requête.
	 *
	 * @param \WP_REST_Request $request Requête.
	 * @return array
	 */
	private function body( $request ) {
		$body = $request->get_json_params();
		return is_array( $body ) ? array_map( function ( $value ) {
			return is_string( $value ) ? trim( $value ) : $value;
		}, $body ) : array();
	}
}
