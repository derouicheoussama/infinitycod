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
use InfinityCod\Shipping\Zones;

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders, PluginCheck.Security.DirectDB
// Tables custom InfinityCod : noms de tables issus de Schema::table() (constantes internes,
// jamais d'entree utilisateur) et valeurs toujours liees via $wpdb->prepare(). Requetes
// directes volontaires sur nos propres tables (pas d'equivalent WP_Query), avec caches
// applicatifs la ou c'est chaud (compteurs, tarifs).


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

		// 3. Repli zone régionale (wilaya en mode « hérite »).
		$zone_price = Zones::price( $wilaya_code, $mode );
		if ( null !== $zone_price ) {
			return $zone_price;
		}

		// 4. Défaut global.
		$fallback = ( self::MODE_DESK === $mode ) ? 'default_price_desk' : 'default_price_home';
		return (float) Settings::get( $fallback, 0 );
	}

	/**
	 * Tarif avec paliers de poids par wilaya (0-1 / 1-5 / 5-10 kg, puis /kg).
	 *
	 * Retourne null quand aucun palier n'est configuré : l'appelant applique
	 * alors le tarif de base + le supplément poids global (comportement
	 * historique). Un palier configuré REMPLACE le tarif de base.
	 *
	 * @param string $wilaya_code Code wilaya.
	 * @param string $commune     Nom de commune (override éventuel).
	 * @param string $mode        'home' ou 'desk'.
	 * @param float  $weight      Poids total (kg).
	 * @return float|null
	 */
	public function price_weighted( $wilaya_code, $commune, $mode, $weight ) {
		$weight = max( 0.0, (float) $weight );
		if ( $weight <= 0 ) {
			return null;
		}

		$tiers = $this->weight_tiers( $wilaya_code );
		if ( null === $tiers ) {
			return null;
		}

		// Base : cascade commune > wilaya > zone > défaut (sans poids).
		$base = $this->price( $wilaya_code, $commune, $mode );
		if ( $base < 0 ) {
			return null;
		}

		if ( $weight <= 1 ) {
			return $tiers['w1'] >= 0 ? $tiers['w1'] : $base;
		}
		if ( $weight <= 5 ) {
			return $tiers['w5'] >= 0 ? $tiers['w5'] : ( $tiers['w1'] >= 0 ? $tiers['w1'] : $base );
		}
		if ( $weight <= 10 ) {
			return $tiers['w10'] >= 0 ? $tiers['w10'] : ( $tiers['w5'] >= 0 ? $tiers['w5'] : $base );
		}
		// Au-delà de 10 kg : tarif du palier 5-10 (ou base) + prix par kg.
		$over_base = $tiers['w10'] >= 0 ? $tiers['w10'] : ( $tiers['w5'] >= 0 ? $tiers['w5'] : $base );
		return round( $over_base + ( ( $weight - 10 ) * $tiers['w_over'] ), 2 );
	}

	/**
	 * Paliers de poids d'une wilaya (null si aucun palier défini).
	 *
	 * @param string $wilaya_code Code wilaya.
	 * @return array{w1:float,w5:float,w10:float,w_over:float}|null
	 */
	public function weight_tiers( $wilaya_code ) {
		global $wpdb;
		$table = Schema::table( 'wilayas' );
		static $cache_tiers = array();
		if ( ! isset( $cache_tiers[ $wilaya_code ] ) ) {
			$cache_tiers[ $wilaya_code ] = $wpdb->get_row(
				$wpdb->prepare( "SELECT w5, w10, w_over FROM {$table} WHERE code = %s", $wilaya_code ), // phpcs:ignore WordPress.DB.PreparedSQL
				ARRAY_A
			);
		}
		$row = $cache_tiers[ $wilaya_code ];
		if ( ! is_array( $row ) ) {
			return null;
		}
		$tiers = array(
			'w1'     => -1.0, // Palier 0-1 kg : hérite toujours du tarif de base.
			'w5'     => (float) $row['w5'],
			'w10'    => (float) $row['w10'],
			'w_over' => (float) $row['w_over'],
		);
		if ( $tiers['w5'] < 0 && $tiers['w10'] < 0 && $tiers['w_over'] <= 0 ) {
			return null;
		}
		return $tiers;
	}

	/**
	 * Frais de retour configurés pour une wilaya (provision P&L).
	 *
	 * @param string $wilaya_code Code wilaya.
	 * @return float
	 */
	public function return_fee( $wilaya_code ) {
		global $wpdb;
		$table = Schema::table( 'wilayas' );
		static $cache_ret = array();
		if ( ! isset( $cache_ret[ $wilaya_code ] ) ) {
			$cache_ret[ $wilaya_code ] = $wpdb->get_var( $wpdb->prepare( "SELECT return_fee FROM {$table} WHERE code = %s", $wilaya_code ) );
		}
		return max( 0, (float) $cache_ret[ $wilaya_code ] );
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
		static $cache_free = array();
		if ( ! isset( $cache_free[ $wilaya_code ] ) ) {
			$cache_free[ $wilaya_code ] = $wpdb->get_var( $wpdb->prepare( "SELECT free_shipping FROM {$table} WHERE code = %s", $wilaya_code ) );
		}
		$free  = $cache_free[ $wilaya_code ];

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
		static $cache_days = array();
		if ( ! isset( $cache_days[ $wilaya_code ] ) ) {
			$cache_days[ $wilaya_code ] = (string) $wpdb->get_var( $wpdb->prepare( "SELECT delivery_days FROM {$table} WHERE code = %s", $wilaya_code ) );
		}
		return $cache_days[ $wilaya_code ];
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
		static $cache_min = array();
		if ( ! isset( $cache_min[ $wilaya_code ] ) ) {
			$cache_min[ $wilaya_code ] = (float) $wpdb->get_var( $wpdb->prepare( "SELECT min_order FROM {$table} WHERE code = %s", $wilaya_code ) );
		}
		return $cache_min[ $wilaya_code ];
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
					'min_order'     => isset( $data['min'] ) ? (float) $data['min'] : 0,
					'delivery_days' => isset( $data['days'] ) ? sanitize_text_field( (string) $data['days'] ) : '',
					'w5'            => isset( $data['w5'] ) ? (float) $data['w5'] : -1,
					'w10'           => isset( $data['w10'] ) ? (float) $data['w10'] : -1,
					'w_over'        => isset( $data['w_over'] ) ? (float) $data['w_over'] : 0,
					'return_fee'    => isset( $data['return_fee'] ) ? (float) $data['return_fee'] : 0,
				),
				array( 'code' => $code ),
				array( '%f', '%f', '%d', '%d', '%f', '%s', '%f', '%f', '%f', '%f' ),
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
