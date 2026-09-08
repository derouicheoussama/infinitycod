<?php
/**
 * Moteur d'offres : remises par quantité globales et par produit.
 *
 * Paliers : "quantité >= N => remise %" triés par N croissant.
 * Sources : meta produit `_icod_offers` prioritaire, sinon réglage global.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Form;

use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class OffersEngine {

	/**
	 * Paliers globaux par défaut (modifiables par filtre).
	 *
	 * @return array<int, float> quantité => remise %.
	 */
	public static function global_tiers() {
		/**
		 * Paliers globaux d'offres par quantité.
		 *
		 * @param array $tiers quantité minimale => remise en %.
		 */
		return apply_filters( 'infinitycod_offers_global_tiers', array( 2 => 10, 3 => 15, 5 => 20 ) );
	}

	/**
	 * Paliers applicables à un produit (meta prioritaire sur le global).
	 *
	 * @param int $product_id ID du produit.
	 * @return array<int, float>
	 */
	public static function tiers_for_product( $product_id ) {
		if ( ! \InfinityCod\License\LicenseManager::is_premium() ) {
			return array(); // Offres par quantité : fonctionnalité Premium.
		}

		$custom = get_post_meta( (int) $product_id, '_icod_offers', true );

		if ( is_string( $custom ) && '' !== $custom ) {
			$decoded = json_decode( $custom, true );
			if ( is_array( $decoded ) && $decoded ) {
				$tiers = array();
				foreach ( $decoded as $tier ) {
					$qty = isset( $tier['qty'] ) ? (int) $tier['qty'] : 0;
					$pct = isset( $tier['pct'] ) ? (float) $tier['pct'] : 0;
					if ( $qty >= 2 && $qty <= 99 && $pct > 0 && $pct <= 90 ) {
						$tiers[ $qty ] = $pct;
					}
				}
				if ( $tiers ) {
					ksort( $tiers );
					return $tiers;
				}
			}
			return array(); // Meta présente mais vide : offres désactivées pour ce produit.
		}

		return self::global_tiers();
	}

	/**
	 * Calcule la remise applicable.
	 *
	 * @param int   $product_id ID du produit.
	 * @param float $unit_price Prix unitaire.
	 * @param int   $quantity   Quantité.
	 * @return array{pct: float, amount: float} Remise en % et en DA.
	 */
	public static function discount( $product_id, $unit_price, $quantity ) {
		$tiers = self::tiers_for_product( $product_id );
		$pct   = 0.0;

		foreach ( $tiers as $min_qty => $tier_pct ) {
			if ( $quantity >= $min_qty ) {
				$pct = (float) $tier_pct;
			}
		}

		return array(
			'pct'    => $pct,
			'amount' => round( $unit_price * $quantity * $pct / 100, 2 ),
		);
	}
}
