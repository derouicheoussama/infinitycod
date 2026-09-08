<?php
/**
 * Accès aux données géographiques : wilayas, communes, bureaux stopdesk.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Geo;

use InfinityCod\Core\Schema;

defined( 'ABSPATH' ) || exit;

class GeoManager {

	/**
	 * Aucun hook : service appelé par les autres modules.
	 *
	 * @return void
	 */
	public function register() {}

	/**
	 * Toutes les wilayas actives (avec cache objet par requête).
	 *
	 * @param bool $active_only Wilayas actives uniquement.
	 * @return array[]
	 */
	public function wilayas( $active_only = true ) {
		static $cache = array();

		$key = $active_only ? 'active' : 'all';
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}

		global $wpdb;
		$table  = Schema::table( 'wilayas' );
		$where  = $active_only ? 'WHERE active = 1' : '';
		$rows   = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY code ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

		$cache[ $key ] = $rows ? $rows : array();
		return $cache[ $key ];
	}

	/**
	 * Une wilaya par son code.
	 *
	 * @param string $code Code wilaya ("01".."58").
	 * @return array|null
	 */
	public function wilaya( $code ) {
		foreach ( $this->wilayas( false ) as $wilaya ) {
			if ( $code === $wilaya['code'] ) {
				return $wilaya;
			}
		}
		return null;
	}

	/**
	 * Communes d'une wilaya.
	 *
	 * @param string $wilaya_code Code wilaya.
	 * @param bool   $active_only Communes actives uniquement.
	 * @return array[]
	 */
	public function communes( $wilaya_code, $active_only = true ) {
		global $wpdb;

		$table = Schema::table( 'communes' );
		$where = $active_only ? 'AND active = 1' : '';

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE wilaya_code = %s {$where} ORDER BY name_fr ASC", $wilaya_code ), // phpcs:ignore WordPress.DB.PreparedSQL
			ARRAY_A
		);

		return $rows ? $rows : array();
	}

	/**
	 * Une commune par wilaya + nom (insensible à la casse).
	 *
	 * @param string $wilaya_code Code wilaya.
	 * @param string $name        Nom de la commune.
	 * @return array|null
	 */
	public function commune( $wilaya_code, $name ) {
		global $wpdb;

		$table = Schema::table( 'communes' );
		$row   = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE wilaya_code = %s AND LOWER(name_fr) = LOWER(%s) LIMIT 1", $wilaya_code, $name ),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Nom d'affichage d'une wilaya selon la langue du site.
	 *
	 * @param array $wilaya Ligne wilaya.
	 * @return string
	 */
	public function wilaya_label( array $wilaya ) {
		$arabic = \InfinityCod\Core\I18n::is_rtl();
		return $arabic && ! empty( $wilaya['name_ar'] ) ? $wilaya['name_ar'] : $wilaya['name_fr'];
	}

	/**
	 * Libellé d'affichage d'une commune selon la langue.
	 *
	 * @param array $commune Ligne commune.
	 * @return string
	 */
	public function commune_label( array $commune ) {
		$arabic = \InfinityCod\Core\I18n::is_rtl();
		return $arabic && ! empty( $commune['name_ar'] ) ? $commune['name_ar'] : $commune['name_fr'];
	}

	/**
	 * Bureaux stopdesk d'une wilaya (tous transporteurs ou un seul).
	 *
	 * @param string $wilaya_code Code wilaya.
	 * @param string $carrier     Slug transporteur optionnel.
	 * @return array[]
	 */
	public function stopdesks( $wilaya_code, $carrier = '' ) {
		global $wpdb;

		$table = Schema::table( 'stopdesks' );
		$sql   = "SELECT * FROM {$table} WHERE wilaya_code = %s AND active = 1";
		$args  = array( $wilaya_code );

		if ( '' !== $carrier ) {
			$sql  .= ' AND carrier = %s';
			$args[] = $carrier;
		}
		$sql .= ' ORDER BY name ASC';

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		return $rows ? $rows : array();
	}

	/**
	 * Enregistre / met à jour une liste complète de bureaux pour un transporteur.
	 *
	 * @param string $carrier Slug transporteur.
	 * @param array  $rows    Chaque ligne : wilaya_code, commune, name, address, external_id.
	 * @return int Nombre de bureaux enregistrés.
	 */
	public function replace_stopdesks( $carrier, array $rows ) {
		global $wpdb;

		$table = Schema::table( 'stopdesks' );
		$wpdb->delete( $table, array( 'carrier' => $carrier ), array( '%s' ) );

		$count = 0;
		foreach ( $rows as $row ) {
			$inserted = $wpdb->insert(
				$table,
				array(
					'carrier'     => $carrier,
					'wilaya_code' => isset( $row['wilaya_code'] ) ? $row['wilaya_code'] : '',
					'commune'     => isset( $row['commune'] ) ? $row['commune'] : '',
					'name'        => isset( $row['name'] ) ? $row['name'] : '',
					'address'     => isset( $row['address'] ) ? $row['address'] : '',
					'active'      => 1,
					'external_id' => isset( $row['external_id'] ) ? $row['external_id'] : '',
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s' )
			);
			if ( $inserted ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Marque les communes qui possèdent au moins un bureau stopdesk.
	 *
	 * @return int Nombre de communes mises à jour.
	 */
	public function sync_has_desk_flags() {
		global $wpdb;

		$communes_table = Schema::table( 'communes' );
		$desks_table    = Schema::table( 'stopdesks' );

		$affected = (int) $wpdb->query(
			"UPDATE {$communes_table} c
			 SET c.has_desk = IF(EXISTS(
				SELECT 1 FROM {$desks_table} d
				WHERE d.wilaya_code = c.wilaya_code AND d.active = 1
				  AND (LOWER(d.commune) = LOWER(c.name_fr) OR LOWER(d.commune) = LOWER(c.name_ar))
			 ), 1, 0)" // phpcs:ignore WordPress.DB.PreparedSQL
		);

		return $affected;
	}
}
