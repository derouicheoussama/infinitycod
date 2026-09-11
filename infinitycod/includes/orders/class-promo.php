<?php
/**
 * Codes promo personnalisés InfinityCod : validation, remise, statistiques.
 *
 * Les codes vivent dans la table {prefix}icod_promos et prennent la
 * priorité sur les coupons WooCommerce. Chaque code supporte : dates de
 * début/fin, sélection de produits, minimum de commande et limite
 * d'utilisation. Les statistiques (utilisations, remise totale, CA généré)
 * sont agrégées sur la table elle-même + l'historique des commandes
 * (icod_orders.coupon).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Orders;

use InfinityCod\Core\Schema;

defined( 'ABSPATH' ) || exit;

class Promo {

	/**
	 * Cherche un code promo actif par son code.
	 *
	 * @param string $code Code saisi.
	 * @return array|null Ligne promo ou null.
	 */
	public static function find( $code ) {
		global $wpdb;

		$code  = trim( strtoupper( (string) $code ) );
		$table = Schema::table( 'promos' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE UPPER(code) = %s AND active = 1 LIMIT 1", $code ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		return $row ? $row : null;
	}

	/**
	 * Valide une ligne promo contre le contexte d'achat.
	 *
	 * @param array $row        Ligne icod_promos.
	 * @param float $base       Sous-total auquel la remise s'applique.
	 * @param int   $product_id Produit commandé (0 = inconnu).
	 * @param int   $now        Timestamp courant.
	 * @return array{valid:bool,amount:float,error:string}
	 */
	public static function validate_row( $row, $base, $product_id, $now = 0 ) {
		$out = array(
			'valid'  => false,
			'amount' => 0.0,
			'error'  => '',
		);

		if ( ! is_array( $row ) || empty( $row['active'] ) ) {
			$out['error'] = 'not_found';
			return $out;
		}

		$now = $now ?: time();

		// Fenêtre de validité.
		if ( ! empty( $row['starts_at'] ) && strtotime( (string) $row['starts_at'] ) > $now ) {
			$out['error'] = 'not_started';
			return $out;
		}
		if ( ! empty( $row['ends_at'] ) && strtotime( (string) $row['ends_at'] ) < $now ) {
			$out['error'] = 'expired';
			return $out;
		}

		// Limite d'utilisation.
		$limit = (int) ( $row['usage_limit'] ?? 0 );
		$used  = (int) ( $row['used_count'] ?? 0 );
		if ( $limit > 0 && $used >= $limit ) {
			$out['error'] = 'used_up';
			return $out;
		}

		// Minimum de commande.
		$min_total = (float) ( $row['min_total'] ?? 0 );
		if ( $min_total > 0 && (float) $base < $min_total ) {
			$out['error'] = 'min_total';
			return $out;
		}

		// Sélection de produits : vide = tous ; sinon le produit doit figurer.
		$product_ids = array_filter( array_map( 'absint', explode( ',', (string) ( $row['product_ids'] ?? '' ) ) ) );
		$product_id  = absint( $product_id );
		if ( ! empty( $product_ids ) && ( ! $product_id || ! in_array( $product_id, $product_ids, true ) ) ) {
			$out['error'] = 'product';
			return $out;
		}

		// Montant de la remise.
		$type  = ( 'fixed' === (string) $row['discount_type'] ) ? 'fixed' : 'percent';
		$value = (float) $row['discount_value'];
		if ( $value <= 0 ) {
			$out['error'] = 'invalid';
			return $out;
		}

		$amount = ( 'percent' === $type )
			? round( $base * $value / 100, 2 )
			: min( $value, $base ); // Un code fixe ne rend jamais le total négatif.

		$out['valid']  = $amount > 0;
		$out['amount'] = $amount;
		if ( ! $out['valid'] ) {
			$out['error'] = 'invalid';
		}
		return $out;
	}

	/**
	 * Évalue un code promo InfinityCod sur une commande.
	 *
	 * @param string $code       Code saisi.
	 * @param float  $base       Sous-total (DA).
	 * @param int    $product_id Produit commandé.
	 * @return array{valid:bool,found:bool,amount:float,promo_id:int,error:string}
	 */
	public static function evaluate( $code, $base, $product_id = 0 ) {
		$out = array(
			'valid'    => false,
			'found'    => false,
			'amount'   => 0.0,
			'promo_id' => 0,
			'error'    => '',
		);

		$code = trim( (string) $code );
		if ( '' === $code ) {
			return $out;
		}

		$row = self::find( $code );
		if ( ! $row ) {
			return $out; // Pas un code InfinityCod → l'appelant retombe sur WC.
		}

		$out['found']    = true;
		$out['promo_id'] = (int) $row['id'];

		$check = self::validate_row( $row, $base, $product_id );
		$out['error'] = $check['error'];
		$out['valid'] = $check['valid'];
		$out['amount'] = $check['amount'];
		return $out;
	}

	/**
	 * Incrémente les compteurs d'un code promo après une commande réussie.
	 *
	 * @param int   $promo_id    Ligne icod_promos.
	 * @param float $discount    Remise accordée.
	 * @param float $order_total Total de la commande.
	 * @return void
	 */
	public static function record_usage( $promo_id, $discount, $order_total ) {
		global $wpdb;

		$promo_id = absint( $promo_id );
		if ( ! $promo_id ) {
			return;
		}

		$table = Schema::table( 'promos' );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE {$table} SET used_count = used_count + 1, discount_total = discount_total + %f, revenue_total = revenue_total + %f WHERE id = %d",
				(float) $discount,
				(float) $order_total,
				$promo_id
			)
		);
	}

	/**
	 * Statistiques agrégées pour la page Codes promo.
	 *
	 * @return array{codes:int,active:int,uses:int,discount:float}
	 */
	public static function stats() {
		global $wpdb;

		$table = Schema::table( 'promos' );
		$row   = $wpdb->get_row( "SELECT COUNT(*) AS codes, COALESCE(SUM(active),0) AS active, COALESCE(SUM(used_count),0) AS uses, COALESCE(SUM(discount_total),0) AS discount FROM {$table}", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		if ( ! $row ) {
			return array( 'codes' => 0, 'active' => 0, 'uses' => 0, 'discount' => 0.0 );
		}
		return array(
			'codes'    => (int) $row['codes'],
			'active'   => (int) $row['active'],
			'uses'     => (int) $row['uses'],
			'discount' => (float) $row['discount'],
		);
	}

	/**
	 * Historique des commandes ayant utilisé un code donné.
	 *
	 * @param string $code Code promo.
	 * @param int    $limit Nombre max de lignes.
	 * @return array[]
	 */
	public static function history( $code, $limit = 100 ) {
		global $wpdb;

		$table  = Schema::table( 'orders' );
		$limit  = max( 1, min( 500, (int) $limit ) );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, wc_order_id, created_at, customer_name, phone, total, discount, status FROM {$table} WHERE coupon = %s ORDER BY created_at DESC LIMIT %d",
				strtoupper( trim( (string) $code ) ),
				$limit
			),
			ARRAY_A
		);
		return $rows ? $rows : array();
	}
}
