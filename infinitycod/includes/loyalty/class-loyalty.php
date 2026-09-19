<?php
/**
 * Fidélité : points gagnés sur les commandes livrées, remise automatique.
 *
 * Aucune table dédiée : les points d'un téléphone se calculent depuis les
 * commandes livrées (total / point_da) moins les remises déjà consommées
 * (colonne loyalty_used). Au formulaire, si le client a assez de points,
 * une remise plafonnée à 30 % du sous-total s'applique automatiquement.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Loyalty;

use InfinityCod\Core\Schema;
use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Loyalty {

	/**
	 * Module sans hook : logique appelée par REST et OrderStore.
	 *
	 * @return void
	 */
	public function register() {}

	/**
	 * Points disponibles pour un téléphone (points gagnés - consommés).
	 *
	 * @param string $phone Téléphone normalisé.
	 * @return int
	 */
	public static function available_points( $phone ) {
		global $wpdb;
		$phone  = preg_replace( '/[^0-9]/', '', (string) $phone );
		$per_da = max( 1, (int) Settings::get( 'loyalty_point_da', 1000 ) );

		$orders = Schema::table( 'orders' ); // Nom de table interne (constante du schéma) — jamais d'entrée utilisateur.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- agrégat temps réel du solde ; nom de table issu du schéma interne (l'interpolation est couverte par l'ignore sur la ligne SQL).
		$row    = $wpdb->get_row( $wpdb->prepare(
			"SELECT COALESCE(SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END),0) AS earned_total,
			 COALESCE(SUM(loyalty_used),0) AS redeemed
			 FROM {$orders} WHERE phone = %s", // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- agrégat temps réel du solde ; valeur paramétrée via %s ; {$orders} = nom de table issu du schéma interne.
			$phone
		), ARRAY_A );

		if ( ! is_array( $row ) ) {
			return 0;
		}

		$earned   = (int) floor( ( (float) $row['earned_total'] ) / $per_da );
		$redeemed = (int) ceil( ( (float) $row['redeemed'] ) / max( 1, (int) Settings::get( 'loyalty_point_value', 10 ) ) );
		return max( 0, $earned - $redeemed );
	}

	/**
	 * Remise fidélité applicable (valeur DA) pour un téléphone et un base.
	 *
	 * @param string $phone Téléphone.
	 * @param float  $base  Base de calcul (après autres remises).
	 * @return float 0 si non applicable.
	 */
	public static function discount_for( $phone, $base ) {
		if ( ! Settings::get( 'loyalty_enabled' ) ) {
			return 0.0;
		}
		$points    = self::available_points( $phone );
		$min       = (int) Settings::get( 'loyalty_min_points', 20 );
		if ( $points < $min ) {
			return 0.0;
		}
		$value     = max( 1, (int) Settings::get( 'loyalty_point_value', 10 ) );
		$discount  = $points * $value;
		return round( min( (float) $discount, (float) $base * 0.3 ), 2 );
	}

	/**
	 * Aperçu pour la réponse quote (points + remise).
	 *
	 * @param string $phone Téléphone (peut être vide).
	 * @param float  $base  Base de calcul.
	 * @return array{enabled:bool,points:int,discount:float}
	 */
	public static function preview( $phone, $base ) {
		if ( ! Settings::get( 'loyalty_enabled' ) || '' === trim( (string) $phone ) ) {
			return array( 'enabled' => false, 'points' => 0, 'discount' => 0.0 );
		}
		$points   = self::available_points( $phone );
		$discount = self::discount_for( $phone, $base );
		return array(
			'enabled' => true,
			'points'  => $points,
			'discount' => $discount,
		);
	}
}
