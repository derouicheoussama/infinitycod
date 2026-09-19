<?php
/**
 * Portail de suivi client — logique de recherche (partagée portail + API).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Track;

defined( 'ABSPATH' ) || exit;

class Finder {

	/**
	 * Retrouve une commande par numéro + téléphone (9 derniers chiffres).
	 *
	 * @param int    $order_id Numéro de commande interne.
	 * @param string $phone    Téléphone saisi.
	 * @return array|null Ligne commande ou null.
	 */
	public static function find( $order_id, $phone ) {
		global $wpdb;
		$order_id = absint( $order_id );
		$digits   = preg_replace( '/[^0-9]/', '', (string) $phone );
		if ( $order_id < 1 || strlen( $digits ) < 4 ) {
			return null;
		}

		$table = Schema::table( 'orders' ); // Nom de table interne (constante du schéma) — jamais d'entrée utilisateur.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- portail public sans cache persistant ; valeurs paramétrées via %d ; nom de table issu du schéma interne.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $order_id ), ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}

		$stored = preg_replace( '/[^0-9]/', '', (string) $row['phone'] );
		if ( substr( $stored, -9 ) !== substr( $digits, -9 ) ) {
			return null;
		}
		return $row;
	}
}
