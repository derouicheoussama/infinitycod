<?php
/**
 * Zones régionales : groupes de wilayas tarifés ensemble.
 *
 * La grille wilaya par wilaya reste prioritaire ; une zone fournit un tarif
 * de repli pour les wilayas en mode « hérite » (-1). Config stockée dans les
 * réglages (zones_config), éditable depuis l'écran Géo & Tarifs.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Shipping;

use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class Zones {

	/**
	 * Aucun hook : service appelé par l'écran Géo et le calcul des tarifs.
	 *
	 * @return void
	 */
	public function register() {}

	/**
	 * Toutes les zones configurées, nettoyées.
	 *
	 * @return array<int, array{name:string,codes:array<int,string>,home:float,desk:float}>
	 */
	public static function all() {
		$zones = Settings::get( 'zones_config', array() );
		if ( ! is_array( $zones ) ) {
			return array();
		}

		$clean = array();
		foreach ( $zones as $zone ) {
			if ( ! is_array( $zone ) ) {
				continue;
			}
			$name  = sanitize_text_field( (string) ( $zone['name'] ?? '' ) );
			$codes = array();
			foreach ( (array) ( $zone['codes'] ?? array() ) as $code ) {
				$code = preg_replace( '/[^0-9]/', '', (string) $code );
				if ( '' !== $code && strlen( $code ) <= 2 ) {
					$codes[] = str_pad( $code, 2, '0', STR_PAD_LEFT );
				}
			}
			if ( '' === $name || ! $codes ) {
				continue;
			}
			$clean[] = array(
				'name'  => $name,
				'codes' => array_values( array_unique( $codes ) ),
				'home'  => max( 0, (float) ( $zone['home'] ?? 0 ) ),
				'desk'  => max( 0, (float) ( $zone['desk'] ?? 0 ) ),
			);
		}
		return $clean;
	}

	/**
	 * Zone d'une wilaya (première zone déclarée qui la contient).
	 *
	 * @param string $wilaya_code Code wilaya.
	 * @return array|null
	 */
	public static function for_wilaya( $wilaya_code ) {
		$code = str_pad( preg_replace( '/[^0-9]/', '', (string) $wilaya_code ), 2, '0', STR_PAD_LEFT );
		foreach ( self::all() as $zone ) {
			if ( in_array( $code, $zone['codes'], true ) ) {
				return $zone;
			}
		}
		return null;
	}

	/**
	 * Tarif de repli d'une wilaya via sa zone.
	 *
	 * @param string $wilaya_code Code wilaya.
	 * @param string $mode        'home' ou 'desk'.
	 * @return float|null Null si aucune zone ou tarif non défini.
	 */
	public static function price( $wilaya_code, $mode ) {
		$zone = self::for_wilaya( $wilaya_code );
		if ( ! $zone ) {
			return null;
		}
		$price = ( 'desk' === $mode ) ? $zone['desk'] : $zone['home'];
		return $price > 0 ? (float) $price : null;
	}

	/**
	 * Enregistre les zones depuis l'écran Géo (POST nettoyé).
	 *
	 * @param array $raw Tableau brut icod_zones.
	 * @return int Nombre de zones valides enregistrées.
	 */
	public static function save( array $raw ) {
		$zones = array();
		foreach ( $raw as $zone ) {
			if ( ! is_array( $zone ) ) {
				continue;
			}
			$name  = sanitize_text_field( (string) ( $zone['name'] ?? '' ) );
			$codes = array();
			foreach ( preg_split( '/[,\s;]+/', (string) ( $zone['codes'] ?? '' ) ) as $code ) {
				$code = preg_replace( '/[^0-9]/', '', $code );
				if ( '' !== $code && strlen( $code ) <= 2 ) {
					$codes[] = str_pad( $code, 2, '0', STR_PAD_LEFT );
				}
			}
			if ( '' === $name || ! $codes ) {
				continue;
			}
			$zones[] = array(
				'name'  => $name,
				'codes' => array_values( array_unique( $codes ) ),
				'home'  => max( 0, (float) ( $zone['home'] ?? 0 ) ),
				'desk'  => max( 0, (float) ( $zone['desk'] ?? 0 ) ),
			);
		}
		Settings::set( 'zones_config', $zones );
		return count( $zones );
	}

	/**
	 * Préréglage national (Centre / Est / Ouest / Sud-Ouest / Sud-Est) :
	 * codes pré-remplis, tarifs laissés à remplir par le marchand.
	 *
	 * @return array<int, array{name:string,codes:string,home:float,desk:float}>
	 */
	public static function preset() {
		return array(
			array(
				'name'  => 'Centre',
				'codes' => '16,35,44,09,06,42,26',
				'home'  => 0,
				'desk'  => 0,
			),
			array(
				'name'  => 'Est',
				'codes' => '25,05,24,23,36,43,18,41,21,04',
				'home'  => 0,
				'desk'  => 0,
			),
			array(
				'name'  => 'Ouest',
				'codes' => '31,22,13,27,29,48,14,02,03,46,38',
				'home'  => 0,
				'desk'  => 0,
			),
			array(
				'name'  => 'Sud (Hauts Plateaux & Grand Sud)',
				'codes' => '47,32,39,30,51,52,55,56,57,58,33,34,45,07,40,17,54,53,28,37,08,49,12,15,19,20,11,10,01,50',
				'home'  => 0,
				'desk'  => 0,
			),
		);
	}
}
