<?php
/**
 * Statistiques P&L : agrégations sur les commandes COD.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Stats;

use InfinityCod\Core\Schema;

defined( 'ABSPATH' ) || exit;

class Pnl {

	/**
	 * Aucun hook : service appelé par la page Stats.
	 *
	 * @return void
	 */
	public function register() {}

	/**
	 * Indicateurs clés sur une période.
	 *
	 * @param string $from Date SQL de début.
	 * @param string $to   Date SQL de fin.
	 * @return array
	 */
	public function kpis( $from, $to ) {
		global $wpdb;

		$table = Schema::table( 'orders' );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COUNT(*) AS total,
				SUM(CASE WHEN status IN ('confirmed','shipped','delivered') THEN 1 ELSE 0 END) AS confirmed,
				SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
				SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) AS returned,
				SUM(CASE WHEN status IN ('confirmed','shipped','delivered') THEN total ELSE 0 END) AS revenue_confirmed,
				SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END) AS revenue_delivered,
				SUM(CASE WHEN status = 'delivered' THEN shipping ELSE 0 END) AS shipping_collected,
				SUM(discount) AS discounts,
				SUM(quantity) AS items
			 FROM {$table} WHERE created_at BETWEEN %s AND %s", // phpcs:ignore WordPress.DB.PreparedSQL
			$from,
			$to
		), ARRAY_A );

		$total    = (int) $row['total'];
		$delivered = (int) $row['delivered'];

		return array(
			'total'               => $total,
			'confirmed'           => (int) $row['confirmed'],
			'delivered'           => $delivered,
			'returned'            => (int) $row['returned'],
			'revenue_confirmed'   => (float) $row['revenue_confirmed'],
			'revenue_delivered'   => (float) $row['revenue_delivered'],
			'shipping_collected'  => (float) $row['shipping_collected'],
			'discounts'           => (float) $row['discounts'],
			'items'               => (int) $row['items'],
			'confirmation_rate'   => $total ? round( (int) $row['confirmed'] / $total * 100, 1 ) : 0,
			'delivery_rate'       => $total ? round( $delivered / $total * 100, 1 ) : 0,
			'return_rate'         => $total ? round( (int) $row['returned'] / $total * 100, 1 ) : 0,
			'avg_order_value'     => $total ? round( ( (float) $row['revenue_confirmed'] ) / max( 1, (int) $row['confirmed'] ), 2 ) : 0,
		);
	}

	/**
	 * Série journalière (commandes + CA confirmé) pour le graphique.
	 *
	 * @param string $from Date SQL de début.
	 * @param string $to   Date SQL de fin.
	 * @return array[] [ ['date' => 'Y-m-d', 'orders' => n, 'revenue' => x], … ]
	 */
	public function daily_series( $from, $to ) {
		global $wpdb;

		$table = Schema::table( 'orders' );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(created_at) AS day,
				COUNT(*) AS orders,
				SUM(CASE WHEN status IN ('confirmed','shipped','delivered') THEN total ELSE 0 END) AS revenue
			 FROM {$table}
			 WHERE created_at BETWEEN %s AND %s
			 GROUP BY DATE(created_at)
			 ORDER BY day ASC", // phpcs:ignore WordPress.DB.PreparedSQL
			$from,
			$to
		), ARRAY_A );

		// Compléter les jours vides pour un axe continu.
		$series = array();
		$index  = array();
		foreach ( (array) $rows as $row ) {
			$index[ $row['day'] ] = $row;
		}

		$cursor = strtotime( substr( $from, 0, 10 ) );
		$end    = strtotime( substr( $to, 0, 10 ) );

		while ( $cursor <= $end ) {
			$day  = gmdate( 'Y-m-d', $cursor );
			$row  = isset( $index[ $day ] ) ? $index[ $day ] : null;
			$series[] = array(
				'date'    => $day,
				'orders'  => $row ? (int) $row['orders'] : 0,
				'revenue' => $row ? (float) $row['revenue'] : 0,
			);
			$cursor = strtotime( '+1 day', $cursor );
		}

		return $series;
	}

	/**
	 * Répartition par wilaya sur une période.
	 *
	 * @param string $from Date SQL de début.
	 * @param string $to   Date SQL de fin.
	 * @param int    $limit Nombre maximum de lignes.
	 * @return array[]
	 */
	public function by_wilaya( $from, $to, $limit = 15 ) {
		global $wpdb;

		$orders  = Schema::table( 'orders' );
		$wilayas = Schema::table( 'wilayas' );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT o.wilaya_code, COALESCE(w.name_fr, o.wilaya_code) AS name,
				COUNT(*) AS orders,
				SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
				SUM(CASE WHEN o.status = 'returned' THEN 1 ELSE 0 END) AS returned,
				SUM(CASE WHEN o.status = 'delivered' THEN o.total ELSE 0 END) AS revenue
			 FROM {$orders} o
			 LEFT JOIN {$wilayas} w ON w.code = o.wilaya_code
			 WHERE o.created_at BETWEEN %s AND %s
			 GROUP BY o.wilaya_code, w.name_fr
			 ORDER BY orders DESC
			 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL
			$from,
			$to,
			$limit
		), ARRAY_A );

		return array_map( function ( $row ) {
			$orders = (int) $row['orders'];
			$row['orders']        = $orders;
			$row['delivered']     = (int) $row['delivered'];
			$row['returned']      = (int) $row['returned'];
			$row['revenue']       = (float) $row['revenue'];
			$row['delivery_rate'] = $orders ? round( $row['delivered'] / $orders * 100, 1 ) : 0;
			return $row;
		}, (array) $rows );
	}

	/**
	 * Répartition par transporteur.
	 *
	 * @param string $from Date SQL de début.
	 * @param string $to   Date SQL de fin.
	 * @return array[]
	 */
	public function by_carrier( $from, $to ) {
		global $wpdb;

		$table = Schema::table( 'orders' );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT carrier,
				COUNT(*) AS shipped,
				SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
				SUM(CASE WHEN status = 'returned' THEN 1 ELSE 0 END) AS returned
			 FROM {$table}
			 WHERE carrier <> '' AND created_at BETWEEN %s AND %s
			 GROUP BY carrier
			 ORDER BY shipped DESC", // phpcs:ignore WordPress.DB.PreparedSQL
			$from,
			$to
		), ARRAY_A );

		return array_map( function ( $row ) {
			$shipped = (int) $row['shipped'];
			$row['shipped']       = $shipped;
			$row['delivered']     = (int) $row['delivered'];
			$row['returned']      = (int) $row['returned'];
			$row['delivery_rate'] = $shipped ? round( $row['delivered'] / $shipped * 100, 1 ) : 0;
			return $row;
		}, (array) $rows );
	}

	/**
	 * Répartition par produit.
	 *
	 * @param string $from Date SQL de début.
	 * @param string $to   Date SQL de fin.
	 * @param int    $limit Nombre maximum de lignes.
	 * @return array[]
	 */
	public function by_product( $from, $to, $limit = 10 ) {
		global $wpdb;

		$table = Schema::table( 'orders' );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT product_id,
				COUNT(*) AS orders,
				SUM(quantity) AS items,
				SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END) AS revenue
			 FROM {$table}
			 WHERE product_id > 0 AND created_at BETWEEN %s AND %s
			 GROUP BY product_id
			 ORDER BY revenue DESC
			 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL
			$from,
			$to,
			$limit
		), ARRAY_A );

		return array_map( function ( $row ) {
			$row['orders']   = (int) $row['orders'];
			$row['items']    = (int) $row['items'];
			$row['revenue']  = (float) $row['revenue'];
			$row['name']     = (int) $row['product_id'] ? get_the_title( (int) $row['product_id'] ) : '—';
			return $row;
		}, (array) $rows );
	}
}
