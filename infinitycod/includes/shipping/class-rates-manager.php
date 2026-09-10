<?php
/**
 * Calcul des frais de livraison : commune > wilaya > défaut global.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Shipping;

use InfinityCod\Core\Schema;
use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class RatesManager {

	const MODE_HOME = 'home';
	const MODE_DESK = 'desk';

	/**
	 * Aucun hook : service appelé par les autres modules.
	 *
	 * @return void
	 */
	public function register() {}

	/**
	 * Calcule le prix de livraison pour une commune donnée.
	 *
	 * Résolution en cascade : tarif commune (-1 = hérite) → tarif wilaya
	 * (-1 = hérite) → défaut global des réglages.
	 *
	 * @param string $wilaya_code  Code wilaya.
	 * @param string $commune_name Nom de la commune (optionnel).
	 * @param string $mode         'home' ou 'desk'.
	 * @return float Prix en DZD, ou -1 si wilaya inconnue.
	 */
	public function price( $wilaya_code, $commune_name = '', $mode = self::MODE_HOME ) {
		$column = ( self::MODE_DESK === $mode ) ? 'price_desk' : 'price_home';

		// Champ wilaya désactivé dans le Checkout Builder (code vide) :
		// tarif par défaut global, la commande reste créable.
		if ( '' === trim( (string) $wilaya_code ) ) {
			return (float) Settings::get( ( self::MODE_DESK === $mode ) ? 'default_price_desk' : 'default_price_home', 0 );
		}

		// 1. Override par commune.
		if ( '' !== $commune_name ) {
			global $wpdb;
			$table = Schema::table( 'communes' );
			$price = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT {$column} FROM {$table} WHERE wilaya_code = %s AND LOWER(name_fr) = LOWER(%s) LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL
					$wilaya_code,
					$commune_name
				)
			);
			if ( null !== $price && (float) $price >= 0 ) {
				return (float) $price;
			}
		}

		// 2. Tarif de la wilaya.
		global $wpdb;
		$wtable = Schema::table( 'wilayas' );
		$wilaya = $wpdb->get_row( $wpdb->prepare( "SELECT {$column} AS price, free_shipping FROM {$wtable} WHERE code = %s", $wilaya_code ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

		if ( ! $wilaya ) {
			return -1;
		}

		if ( (float) $wilaya['price'] >= 0 ) {
			return (float) $wilaya['price'];
		}

		// 3. Défaut global.
		$fallback = ( self::MODE_DESK === $mode ) ? 'default_price_desk' : 'default_price_home';
		return (float) Settings::get( $fallback, 0 );
	}

	/**
	 * Livraison gratuite ? Selon la wilaya ou le palier de quantité.
	 *
	 * @param string $wilaya_code Code wilaya.
	 * @param int    $quantity    Quantité commandée.
	 * @return bool
	 */
	public function is_free( $wilaya_code, $quantity = 1, $subtotal = 0.0 ) {
		$qty_threshold = (int) Settings::get( 'free_shipping_qty', 0 );
		if ( $qty_threshold > 0 && $quantity >= $qty_threshold ) {
			return true;
		}

		// Livraison gratuite par montant du panier.
		$amount_threshold = (float) Settings::get( 'free_amount_threshold', 0 );
		if ( Settings::get( 'free_amount_enabled' ) && $amount_threshold > 0 && (float) $subtotal >= $amount_threshold ) {
			return true;
		}

		// Wilaya inconnue ou champ désactivé : pas de gratuité par wilaya.
		if ( '' === trim( (string) $wilaya_code ) ) {
			return false;
		}

		global $wpdb;
		$table = Schema::table( 'wilayas' );
		$free  = $wpdb->get_var( $wpdb->prepare( "SELECT free_shipping FROM {$table} WHERE code = %s", $wilaya_code ) );

		return (bool) ( $free && (int) $free );
	}

	/**
	 * Délai de livraison estimé pour une wilaya.
	 *
	 * @param string $wilaya_code Code wilaya.
	 * @return string Ex: « 2-4 jours » ou vide.
	 */
	public function delivery_estimate( $wilaya_code ) {
		global $wpdb;
		$table = Schema::table( 'wilayas' );
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT delivery_days FROM {$table} WHERE code = %s", $wilaya_code ) );
	}

	/**
	 * Montant minimum de commande pour une wilaya.
	 *
	 * @param string $wilaya_code Code wilaya.
	 * @return float 0 si pas de minimum.
	 */
	public function min_order( $wilaya_code ) {
		global $wpdb;
		$table = Schema::table( 'wilayas' );
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT min_order FROM {$table} WHERE code = %s", $wilaya_code ) );
	}

	/**
	 * Poids total d'une ligne de commande (produit x quantité).
	 *
	 * @param int $product_id Produit ou variation.
	 * @param int $quantity   Quantité.
	 * @return float Poids en kg.
	 */
	public static function order_weight( $product_id, $quantity ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return 0.0;
		}
		return (float) $product->get_weight() * max( 1, (int) $quantity );
	}

	/**
	 * Supplément poids (produits lourds) selon les réglages globaux.
	 *
	 * @param float $total_weight Poids total en kg.
	 * @return float Frais en DA.
	 */
	public static function weight_fee( $total_weight ) {
		if ( ! Settings::get( 'weight_fee_enabled' ) ) {
			return 0.0;
		}
		$per_kg   = (float) Settings::get( 'weight_fee_per_kg', 0 );
		$free_kg  = (float) Settings::get( 'weight_fee_free_kg', 0 );
		$billable = max( 0, ( (float) $total_weight ) - $free_kg );
		return round( $billable * $per_kg, 2 );
	}

	/**
	 * Montant restant avant livraison gratuite par montant.
	 *
	 * @param float $subtotal Sous-total.
	 * @return float 0 si gratuit déjà atteint ou désactivé.
	 */
	public function free_remaining( $subtotal ) {
		if ( ! Settings::get( 'free_amount_enabled' ) ) {
			return 0.0;
		}
		$threshold = (float) Settings::get( 'free_amount_threshold', 0 );
		return max( 0, $threshold - (float) $subtotal );
	}

	/**
	 * Sauvegarde en masse des tarifs depuis l'écran admin.
	 *
	 * @param array $wilaya_prices Tableau code => array('home' => x, 'desk' => y, 'active' => 0|1, 'free' => 0|1).
	 * @return int Nombre de wilayas mises à jour.
	 */
	public function save_wilaya_prices( array $wilaya_prices ) {
		global $wpdb;

		$table = Schema::table( 'wilayas' );
		$count = 0;

		foreach ( $wilaya_prices as $code => $data ) {
			$updated = $wpdb->update(
				$table,
				array(
					'price_home'    => isset( $data['home'] ) ? (float) $data['home'] : -1,
					'price_desk'    => isset( $data['desk'] ) ? (float) $data['desk'] : -1,
					'active'        => empty( $data['active'] ) ? 0 : 1,
					'free_shipping' => empty( $data['free'] ) ? 0 : 1,
				),
				array( 'code' => $code ),
				array( '%f', '%f', '%d', '%d' ),
				array( '%s' )
			);
			if ( false !== $updated ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Active/désactive une commune et gère son override de prix.
	 *
	 * @param int   $commune_id ID de la commune.
	 * @param array $data       Champs à mettre à jour (active, price_home, price_desk).
	 * @return bool
	 */
	public function save_commune( $commune_id, array $data ) {
		global $wpdb;

		$fields = array();
		$format = array();

		if ( isset( $data['active'] ) ) {
			$fields['active'] = empty( $data['active'] ) ? 0 : 1;
			$format[]         = '%d';
		}
		foreach ( array( 'price_home', 'price_desk' ) as $price_key ) {
			if ( isset( $data[ $price_key ] ) ) {
				$fields[ $price_key ] = ( '' === $data[ $price_key ] || null === $data[ $price_key ] ) ? -1 : (float) $data[ $price_key ];
				$format[]             = '%f';
			}
		}

		if ( ! $fields ) {
			return false;
		}

		$updated = $wpdb->update( Schema::table( 'communes' ), $fields, array( 'id' => (int) $commune_id ), $format, array( '%d' ) );
		return false !== $updated;
	}
}
