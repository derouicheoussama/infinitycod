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
use InfinityCod\Core\Settings;
use InfinityCod\Form\OffersEngine;
use InfinityCod\Form\Validator;
use InfinityCod\Orders\Coupon;
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
			$this->ns(),
			'/github-release',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'github_release_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
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
	 * Webhook GitHub (Release published) — signature HMAC vérifiée.
	 *
	 * @param \WP_REST_Request $request Requête.
	 * @return \WP_REST_Response
	 */
	public function github_release_webhook( $request ) {
		$secret = (string) Settings::get( 'github_webhook_secret', '' );
		$sig    = isset( $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ) ) : '';
		$raw    = $request->get_body();
		if ( '' === $secret || '' === $sig ) { return new \WP_Error( 'icod_webhook', 'missing secret', array( 'status' => 400 ) ); }
		$expected = 'sha256=' . hash_hmac( 'sha256', (string) $raw, $secret );
		if ( ! hash_equals( $expected, $sig ) ) { return new \WP_Error( 'icod_webhook', 'invalid signature', array( 'status' => 401 ) ); }
		$payload = json_decode( (string) $raw, true );
		$release = $payload['release'] ?? $payload;
		$tag     = isset( $release['tag_name'] ) ? ltrim( (string) $release['tag_name'], 'vV' ) : '';
		if ( '' === $tag ) { return new \WP_Error( 'icod_webhook', 'no tag', array( 'status' => 400 ) ); }
		update_option( 'infinitycod_gh_push', array(
			'version'   => $tag,
			'changelog' => isset( $release['body'] ) ? wp_kses_post( $release['body'] ) : '',
			'url'       => isset( $release['html_url'] ) ? esc_url_raw( $release['html_url'] ) : '',
			'at'        => current_time( 'mysql' ),
		), false );
		delete_transient( 'icod_update_gh' );
		return rest_ensure_response( array( 'received' => true, 'version' => $tag ) );
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
		$wilaya_code = isset( $body['wilaya'] ) ? $this->normalize_wilaya_code( sanitize_text_field( $body['wilaya'] ) ) : '';
		$commune     = isset( $body['commune'] ) ? sanitize_text_field( $body['commune'] ) : '';
		$mode        = ( isset( $body['mode'] ) && 'desk' === $body['mode'] ) ? RatesManager::MODE_DESK : RatesManager::MODE_HOME;

		$product = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
		if ( ! $product ) {
			return new \WP_Error( 'icod_product', __( 'Produit introuvable.', 'infinitycod' ), array( 'status' => 404 ) );
		}

		$rates = infinitycod()->module( 'rates' );

		$price_home = $rates ? $rates->price( $wilaya_code, $commune, RatesManager::MODE_HOME ) : -1;
		$price_desk = $rates ? $rates->price( $wilaya_code, $commune, RatesManager::MODE_DESK ) : -1;

		$unit_price = (float) $product->get_price();
		$subtotal   = round( $unit_price * $quantity, 2 );
		$free       = $rates ? $rates->is_free( $wilaya_code, $quantity, $subtotal ) : false;
		$discount   = OffersEngine::discount( $product->get_id(), $unit_price, $quantity );

		// Supplément poids (si pas de livraison gratuite).
		$weight     = \InfinityCod\Shipping\RatesManager::order_weight( $product->get_id(), $quantity );
		$weight_fee = \InfinityCod\Shipping\RatesManager::weight_fee( $weight );

		$shipping = ( RatesManager::MODE_DESK === $mode ? $price_desk : $price_home );
		$shipping = $free ? 0 : $shipping + $weight_fee;

		// Barre « Ajoutez encore X DA » (montant manquant pour la gratuité).
		$free_remaining = ( $free || ! \InfinityCod\Core\Settings::get( 'free_amount_enabled' ) )
			? 0
			: max( 0, (float) \InfinityCod\Core\Settings::get( 'free_amount_threshold', 0 ) - $subtotal );

		// Code promo : validé côté serveur sur la base après remises quantité.
		$coupon_code = isset( $body['coupon'] ) ? sanitize_text_field( (string) $body['coupon'] ) : '';
		$base        = round( $subtotal - (float) $discount['amount'], 2 );
		$coupon      = $coupon_code ? Coupon::evaluate( $coupon_code, $base, $quantity ) : array( 'valid' => false, 'amount' => 0.0, 'label' => '' );
		$coupon_out  = array(
			'code'   => $coupon_code,
			'valid'  => ! empty( $coupon['valid'] ) ? 1 : 0,
			'amount' => (float) ( $coupon['amount'] ?? 0 ),
			'label'  => (string) ( $coupon['label'] ?? '' ),
			'error'  => (string) ( $coupon['error'] ?? '' ),
		);

		return rest_ensure_response( array(
			'unit'          => $unit_price,
			'subtotal'      => $subtotal,
			'discount_pct'  => $discount['pct'],
			'discount'      => $discount['amount'],
			'coupon'        => $coupon_out,
			'shipping'      => $shipping,
			'price_home'    => $price_home,
			'price_desk'    => $price_desk,
			'free'          => $free ? 1 : 0,
			'free_remaining'=> $free_remaining,
			'weight_fee'    => $weight_fee,
			'total'         => max( 0, $subtotal - $discount['amount'] - $coupon_out['amount'] + max( 0, $shipping ) ),
		) );
	}

	/**
	 * POST /submit — création de commande COD complète.
	 *
	 * @param \WP_REST_Request $request Requête.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit( $request ) {
		try {
			return $this->do_submit( $request );
		} catch ( \Throwable $e ) {
			\InfinityCod\Logging\Logger::log( 'error', 'submit : ' . $e->getMessage() );
			return new \WP_Error(
				'icod_server_error',
				__( 'Une erreur technique est survenue lors de l\'enregistrement. Votre commande n a pas été perdue si vous aviez payé — contactez-nous.', 'infinitycod' ),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Normalise un code de région : « 1 » → « 01 » (Algérie), « MA-1 » →
	 * « MA-01 » (pays du catalogue), rien d'autre n'est altéré.
	 *
	 * @param string $code Code brut.
	 * @return string
	 */
	private function normalize_wilaya_code( $code ) {
		$code = trim( (string) $code );
		if ( preg_match( '/^([A-Z]{2})-(\d{1,2})$/i', $code, $m ) ) {
			return strtoupper( $m[1] ) . '-' . str_pad( $m[2], 2, '0', STR_PAD_LEFT );
		}
		if ( preg_match( '/^\d{1,2}$/', $code ) ) {
			return str_pad( $code, 2, '0', STR_PAD_LEFT );
		}
		return $code;
	}

	/**
	 * Corps effectif de la soumission.
	 *
	 * @param \WP_REST_Request $request Requête.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function do_submit( $request ) {
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

		// 1. Champs — MÊMES RÈGLES QUE LE RENDU : le plan du Checkout Builder
		// décide de ce qui est affiché, requis ou ignoré. Un champ caché n'est
		// jamais exigé ni enregistré ; un champ requis est validé ICI (serveur),
		// pas seulement en JavaScript.
		$plan  = \InfinityCod\Form\FormManager::fields_plan();
		$state = array();
		foreach ( $plan as $plan_field ) {
			$state[ $plan_field['key'] ] = $plan_field;
		}
		$fld = static function ( $key ) use ( $state ) {
			return isset( $state[ $key ] )
				? $state[ $key ]
				: array( 'key' => $key, 'on' => false, 'req' => false, 'custom' => 0 === strpos( $key, 'cf_' ) );
		};

		$name      = isset( $body['name'] ) ? Validator::clean_name( $body['name'] ) : '';
		$phone_raw = isset( $body['phone'] ) ? (string) $body['phone'] : '';
		$address   = isset( $body['address'] ) ? sanitize_text_field( $body['address'] ) : '';
		$email_raw = isset( $body['email'] ) ? sanitize_email( $body['email'] ) : '';
		$email     = is_email( $email_raw ) ? $email_raw : '';
		$note      = isset( $body['note'] ) ? sanitize_textarea_field( (string) $body['note'] ) : '';

		$name_def   = $fld( 'name' );
		$phone_def  = $fld( 'phone' );
		$email_def  = $fld( 'email' );
		$wilaya_def = $fld( 'wilaya' );
		$commune_def = $fld( 'commune' );
		$address_def = $fld( 'address' );
		$note_def    = $fld( 'note' );

		if ( $name_def['on'] && $name_def['req'] && ! Validator::is_valid_name( $name ) ) {
			return new \WP_Error( 'icod_name', __( 'Veuillez saisir votre nom complet.', 'infinitycod' ), array( 'status' => 400 ) );
		}
		if ( $name_def['on'] && '' !== $name && ! Validator::is_valid_name( $name ) ) {
			return new \WP_Error( 'icod_name', __( 'Veuillez saisir votre nom complet.', 'infinitycod' ), array( 'status' => 400 ) );
		}

		// Validation souple ici (longueur) ; la règle stricte algérienne est
		// appliquée après résolution du pays de livraison.
		$phone_digits = preg_replace( '/\D/', '', $phone_raw );
		if ( $phone_def['on'] && $phone_def['req'] && ( strlen( $phone_digits ) < 6 || strlen( $phone_digits ) > 15 ) ) {
			return new \WP_Error( 'icod_phone', __( 'Numéro de téléphone invalide.', 'infinitycod' ), array( 'status' => 400 ) );
		}

		// Champ email : caché → la donnée postée est IGNORÉE (jamais stockée).
		if ( ! $email_def['on'] ) {
			$email = '';
		} elseif ( $email_def['req'] && '' === $email ) {
			return new \WP_Error( 'icod_email', __( 'Veuillez indiquer une adresse email valide.', 'infinitycod' ), array( 'status' => 400 ) );
		} elseif ( $email_def['req'] || '' !== $email_raw ) {
			if ( '' !== $email_raw && ! is_email( $email_raw ) ) {
				return new \WP_Error( 'icod_email', __( 'Adresse email invalide.', 'infinitycod' ), array( 'status' => 400 ) );
			}
		}

		// Adresse / note : requis si configuré, ignorées si champ caché.
		if ( $address_def['on'] && $address_def['req'] && '' === trim( $address ) ) {
			return new \WP_Error( 'icod_address', __( 'Veuillez indiquer votre adresse.', 'infinitycod' ), array( 'status' => 400 ) );
		}
		if ( ! $address_def['on'] ) {
			$address = '';
		}
		if ( ! $note_def['on'] ) {
			$note = '';
		}

		$geo        = infinitycod()->module( 'geo' );
		$wilaya_code = isset( $body['wilaya'] ) ? $this->normalize_wilaya_code( sanitize_text_field( $body['wilaya'] ) ) : '';
		$wilaya     = ( $wilaya_def['on'] && '' !== $wilaya_code && $geo ) ? $geo->wilaya( $wilaya_code ) : null;
		if ( $wilaya_def['on'] && ( ! $wilaya || empty( $wilaya['active'] ) ) ) {
			return new \WP_Error( 'icod_wilaya', __( 'Wilaya non desservie, veuillez en choisir une autre.', 'infinitycod' ), array( 'status' => 400 ) );
		}

		$commune_name = isset( $body['commune'] ) ? sanitize_text_field( $body['commune'] ) : '';
		$commune      = ( $geo && $wilaya ) ? $geo->commune( $wilaya_code, $commune_name ) : null;
		// Hors Algérie (régions sans communes en base) : la commune est un texte libre.
		$is_foreign   = $wilaya && isset( $wilaya['country_code'] ) && 'DZ' !== $wilaya['country_code'];
		if ( $commune_def['on'] && $wilaya_def['on'] && ! $commune && ! $is_foreign ) {
			return new \WP_Error( 'icod_commune', __( 'Commune introuvable pour cette wilaya.', 'infinitycod' ), array( 'status' => 400 ) );
		}
		if ( $commune_def['on'] && $is_foreign && '' === trim( $commune_name ) ) {
			return new \WP_Error( 'icod_commune', __( 'Veuillez indiquer votre ville.', 'infinitycod' ), array( 'status' => 400 ) );
		}

		// Numéro final : normalisé DZ si possible, sinon chiffres bruts
		// (commandes hors Algérie).
		$phone = $phone_raw ? ( Validator::normalize_phone( $phone_raw ) ?? preg_replace( '/[^\d+]/', '', $phone_raw ) ) : '';

		// Règle stricte algérienne (Mobilis/Djezzy/Ooredoo) : uniquement pour
		// une livraison en Algérie, et si le marchand l'a activée.
		if ( $phone_def['on'] && ! $is_foreign && Settings::get( 'phone_strict', 1 ) && '' !== $phone_raw && ! Validator::is_valid_phone( $phone_raw ) ) {
			return new \WP_Error( 'icod_phone', __( 'Numéro de téléphone algérien invalide (ex. 0555123456).', 'infinitycod' ), array( 'status' => 400 ) );
		}

		$mode     = ( isset( $body['mode'] ) && 'desk' === $body['mode'] ) ? RatesManager::MODE_DESK : RatesManager::MODE_HOME;
		$stopdesk = isset( $body['stopdesk'] ) ? sanitize_text_field( $body['stopdesk'] ) : '';
		$payment  = ( isset( $body['payment'] ) && 'online' === $body['payment'] && \InfinityCod\Payment\PaymentManager::enabled() ) ? 'online' : 'cod';
		if ( RatesManager::MODE_DESK === $mode && '' === $stopdesk ) {
			return new \WP_Error( 'icod_desk', __( 'Veuillez choisir un bureau de retrait.', 'infinitycod' ), array( 'status' => 400 ) );
		}

		// 1b. Champs personnalisés requis (cf_*) — validation serveur réelle.
		foreach ( $plan as $plan_field ) {
			if ( ! $plan_field['custom'] || ! $plan_field['on'] || ! $plan_field['req'] ) {
				continue;
			}
			$cf_key   = isset( $body['cfields'] ) && is_array( $body['cfields'] ) && isset( $body['cfields'][ $plan_field['key'] ] ) ? (string) $body['cfields'][ $plan_field['key'] ] : '';
			$cf_value = '' !== $cf_key ? $cf_key : (string) ( isset( $body[ $plan_field['key'] ] ) ? $body[ $plan_field['key'] ] : '' );
			if ( '' === trim( $cf_value ) ) {
				return new \WP_Error(
					'icod_cf_' . $plan_field['key'],
					sprintf( __( 'Le champ « %s » est obligatoire.', 'infinitycod' ), $plan_field['label'] ),
					array( 'status' => 400 )
				);
			}
		}

		// 1c. Captcha (si activé) : réponse + token par formulaire, lus dans
		// le corps JSON (jamais $_POST — ce endpoint reçoit du JSON).
		$captcha_provider = \InfinityCod\Form\FormManager::captcha_provider();
		if ( 'math' === $captcha_provider ) {
			$cap_token  = isset( $body['icod_cap_token'] ) ? preg_replace( '/[^a-zA-Z0-9]/', '', (string) $body['icod_cap_token'] ) : '';
			$cap_key    = 'icod_cap_' . $cap_token;
			$expected   = '' !== $cap_token ? get_transient( $cap_key ) : false;
			$user_answer = isset( $body['icod_captcha'] ) ? absint( $body['icod_captcha'] ) : -1;
			if ( false === $expected || (int) $user_answer !== (int) $expected ) {
				return new \WP_Error( 'icod_captcha', __( 'Captcha incorrect. Réessayez.', 'infinitycod' ), array( 'status' => 400 ) );
			}
			delete_transient( $cap_key );
		} elseif ( 'recaptcha_v3' === $captcha_provider ) {
			$cap_token = isset( $body['icod_captcha'] ) ? sanitize_text_field( (string) $body['icod_captcha'] ) : '';
			$verified  = $this->verify_recaptcha( $cap_token );
			if ( ! $verified ) {
				return new \WP_Error( 'icod_captcha', __( 'Vérification anti-bot échouée. Réessayez.', 'infinitycod' ), array( 'status' => 400 ) );
			}
		}

		// 2. Bouclier anti-fraude.
		$shield = infinitycod()->module( 'shield' );
		$assessment = $shield ? $shield->assess( array(
			'name'        => $name,
			'phone'       => $phone_raw,
			'wilaya'      => $wilaya_code,
			'commune'     => $commune_name,
			'mode'        => $mode,
			'quantity'    => isset( $body['quantity'] ) ? absint( $body['quantity'] ) : 1,
			'product_id'  => isset( $body['product_id'] ) ? absint( $body['product_id'] ) : 0,
			'email'       => $email,
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

		// 2b. Protections additionnelles (email jetable, limite globale).
		if ( '' !== $email && Settings::get( 'block_disposable_email' ) ) {
			$disposable = array( 'mailinator.com', 'yopmail.com', 'tempmail', '10minutemail', 'guerrillamail', 'trashmail', 'getnada', 'dispostable' );
			foreach ( $disposable as $domain ) {
				if ( false !== strpos( $email, $domain ) ) {
					return new \WP_Error( 'icod_email', __( 'Email non accepté. Veuillez utiliser un email valide.', 'infinitycod' ), array( 'status' => 400 ) );
				}
			}
		}
		$global_max = (int) Settings::get( 'max_orders_hour_global', 0 );
		if ( $global_max > 0 ) {
			global $wpdb;
			$orders_t  = \InfinityCod\Core\Schema::table( 'orders' );
			$hour_ago  = gmdate( 'Y-m-d H:i:s', time() - 3600 );
			$recent    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$orders_t} WHERE created_at >= %s", $hour_ago ) );
			if ( $recent >= $global_max ) {
				return new \WP_Error( 'icod_hour_limit', __( 'Trop de commandes en ce moment. Veuillez réessayer dans quelques minutes.', 'infinitycod' ), array( 'status' => 429 ) );
			}
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
			'address'      => $address,
			'wilaya_code'  => $wilaya_code,
			'commune'      => $commune_name,
			'mode'         => $mode,
			'stopdesk'     => $stopdesk,
			'payment'      => $payment,
			'note'         => $note,
			'coupon'       => isset( $body['coupon'] ) ? $body['coupon'] : '',
			'cfields'      => array_filter( (array) ( $body['cfields'] ?? array() ), 'is_string' ),
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
					'total'     => Settings::format_price( (float) $result['total'] ),
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
	 * Vérifie un token reCAPTCHA v3 auprès de Google.
	 *
	 * @param string $token Token grecaptcha reçu du front.
	 * @return bool
	 */
	private function verify_recaptcha( $token ) {
		$secret = (string) Settings::get( 'recaptcha_v3_secret_key', '' );
		if ( '' === $secret || '' === $token ) {
			return false;
		}
		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 8,
				'body'    => array(
					'secret'   => $secret,
					'response' => $token,
					'remoteip' => Shield::client_ip(),
				),
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['success'] ) ) {
			return false;
		}
		// v3 : score de confiance 0-1. En dessous de 0.3 → refusé.
		return ! isset( $data['score'] ) || (float) $data['score'] >= 0.3;
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
