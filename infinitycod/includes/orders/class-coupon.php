<?php
/**
 * Code promo du formulaire COD : réutilise les coupons WooCommerce natifs.
 *
 * Le marchand crée ses codes dans Marketing → Coupons ; le formulaire les
 * valide côté serveur (existence, expiration, limite d'utilisation, montant
 * minimum) et applique la remise au total, sans jamais faire confiance au
 * client sur le montant.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Orders;

defined( 'ABSPATH' ) || exit;

class Coupon {

	/**
	 * Évalue un code promo sur une base (sous-total après remises quantité).
	 *
	 * @param string $code  Code saisi par le client.
	 * @param float  $base  Base de calcul (DA).
	 * @param int    $qty   Quantité commandée (coupons par produit).
	 * @return array{code:string, valid:bool, amount:float, label:string, error:string}
	 */
	public static function evaluate( $code, $base, $qty = 1 ) {
		$out = array(
			'code'   => '',
			'valid'  => false,
			'amount' => 0.0,
			'label'  => '',
			'error'  => '',
		);

		$code = trim( (string) $code );
		if ( '' === $code ) {
			return $out;
		}
		$out['code'] = $code;

		if ( ! class_exists( 'WC_Coupon' ) ) {
			$out['error'] = 'unavailable';
			return $out;
		}

		$coupon = new \WC_Coupon( $code );

		if ( ! $coupon || ! $coupon->get_id() ) {
			$out['error'] = 'not_found';
			return $out;
		}

		// Expiration.
		$expires = $coupon->get_date_expires();
		if ( $expires && $expires->getTimestamp() < time() ) {
			$out['error'] = 'expired';
			return $out;
		}

		// Limite d'utilisation globale.
		$limit = (int) $coupon->get_usage_limit();
		if ( $limit > 0 && (int) $coupon->get_usage_count() >= $limit ) {
			$out['error'] = 'used_up';
			return $out;
		}

		// Montant minimum de panier.
		$min = (float) $coupon->get_minimum_amount();
		if ( $min > 0 && (float) $base < $min ) {
			$out['error'] = 'min_spend';
			return $out;
		}

		// Types pris en charge : pourcentage et montant fixe.
		$type   = $coupon->get_discount_type();
		$amount = 0.0;

		if ( 'percent' === $type ) {
			$amount = round( (float) $base * min( 100, (float) $coupon->get_amount() ) / 100, 2 );
		} elseif ( 'fixed_cart' === $type || 'fixed_product' === $type ) {
			$per    = (float) $coupon->get_amount();
			$amount = ( 'fixed_product' === $type ) ? $per * max( 1, (int) $qty ) : $per;
			$amount = round( min( $amount, (float) $base ), 2 );
		} else {
			$out['error'] = 'unsupported';
			return $out;
		}

		if ( $amount <= 0 ) {
			$out['error'] = 'invalid';
			return $out;
		}

		$out['valid']  = true;
		$out['amount'] = $amount;
		$out['label']  = ( 'percent' === $type )
			/* translators: %s : pourcentage de remise. */
			? sprintf( __( '-%s%%', 'infinitycod' ), (float) $coupon->get_amount() )
			/* translators: %s : montant en DA. */
			: sprintf( __( '-%s DA', 'infinitycod' ), number_format_i18n( $amount, 0 ) );

		return $out;
	}
}
