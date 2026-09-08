<?php
/**
 * Paniers abandonnés : sessions incomplètes et récupération.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Orders;

use InfinityCod\Core\Schema;

defined( 'ABSPATH' ) || exit;

class Abandoned {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {}

	/**
	 * Marque récupérés les paniers ouverts correspondant à ce numéro
	 * (appelé après création d'une commande).
	 *
	 * @param string $phone Téléphone normalisé.
	 * @return int Nombre de paniers marqués.
	 */
	public static function mark_recovered_by_phone( $phone ) {
		global $wpdb;

		$table = Schema::table( 'abandoned' );
		$now   = current_time( 'mysql' );

		$affected = (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'recovered', updated_at = %s WHERE phone = %s AND status = 'open'", // phpcs:ignore WordPress.DB.PreparedSQL
				$now,
				$phone
			)
		);

		return $affected;
	}

	/**
	 * Paniers ouverts éligibles à une relance (délai atteint, relances restantes).
	 *
	 * @param int $delay_minutes Délai minimal depuis la dernière activité.
	 * @param int $max_reminders Nombre maximal de relances par panier.
	 * @param int $limit         Nombre maximum de lignes.
	 * @return array[]
	 */
	public static function due_for_reminder( $delay_minutes = 60, $max_reminders = 2, $limit = 50 ) {
		global $wpdb;

		$table  = Schema::table( 'abandoned' );
		$cutoff = current_time( 'mysql', time() - $delay_minutes * MINUTE_IN_SECONDS );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE status = 'open'
				   AND phone <> ''
				   AND reminders_sent < %d
				   AND updated_at <= %s
				 ORDER BY updated_at ASC
				 LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL
				$max_reminders,
				$cutoff,
				$limit
			),
			ARRAY_A
		);

		return $rows ? $rows : array();
	}

	/**
	 * Incrémente le compteur de relances d'un panier.
	 *
	 * @param int $id Ligne abandonnée.
	 * @return bool
	 */
	public static function bump_reminder( $id ) {
		global $wpdb;

		$table = Schema::table( 'abandoned' );
		return false !== $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET reminders_sent = reminders_sent + 1, last_reminder = %s
				 WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL
				current_time( 'mysql' ),
				(int) $id
			)
		);
	}
}
