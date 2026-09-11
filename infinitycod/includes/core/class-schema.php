<?php
/**
 * Définition centralisée des tables custom.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Toutes les tables vivent sous le préfixe {prefix}icod_ et sont créées
 * via dbDelta() dans Activator.
 */
class Schema {

	/**
	 * Nom complet d'une table du plugin.
	 *
	 * @param string $name Nom court, ex. 'orders'.
	 * @return string
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'icod_' . $name;
	}

	/**
	 * Lit une ligne de commande COD.
	 *
	 * @param int $id Ligne icod_orders.
	 * @return array|null
	 */
	public static function get_order( $id ) {
		global $wpdb;
		$table = self::table( 'orders' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		return $row ? $row : null;
	}

	/**
	 * Retourne les requêtes CREATE TABLE de toutes les tables.
	 *
	 * @return array<string, string> nom court => SQL.
	 */
	public static function create_tables() {
		global $wpdb;

		$collate = $wpdb->get_charset_collate();
		$tables  = array();

		$tables['promos'] = 'CREATE TABLE ' . self::table( 'promos' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(50) NOT NULL,
			discount_type varchar(10) NOT NULL DEFAULT 'percent',
			discount_value decimal(10,2) NOT NULL DEFAULT 0,
			starts_at datetime NULL,
			ends_at datetime NULL,
			product_ids text NULL,
			min_total decimal(10,2) NOT NULL DEFAULT 0,
			usage_limit int unsigned NOT NULL DEFAULT 0,
			used_count int unsigned NOT NULL DEFAULT 0,
			revenue_total decimal(12,2) NOT NULL DEFAULT 0,
			discount_total decimal(12,2) NOT NULL DEFAULT 0,
			active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code)
		) $collate;";

		$tables['wilayas'] = 'CREATE TABLE ' . self::table( 'wilayas' ) . " (
			code varchar(8) NOT NULL,
			country_code varchar(2) NOT NULL DEFAULT 'DZ',
			name_fr varchar(120) NOT NULL,
			name_ar varchar(120) NOT NULL DEFAULT '',
			active tinyint(1) NOT NULL DEFAULT 1,
			price_home decimal(10,2) NOT NULL DEFAULT -1,
			price_desk decimal(10,2) NOT NULL DEFAULT -1,
			free_shipping tinyint(1) NOT NULL DEFAULT 0,
			delivery_days varchar(50) NOT NULL DEFAULT '',
			min_order decimal(10,2) NOT NULL DEFAULT 0,
			PRIMARY KEY (code),
			KEY country_code (country_code)
		) $collate;";

		$tables['communes'] = 'CREATE TABLE ' . self::table( 'communes' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wilaya_code varchar(8) NOT NULL,
			country_code varchar(2) NOT NULL DEFAULT 'DZ',
			name_fr varchar(160) NOT NULL,
			name_ar varchar(160) NOT NULL DEFAULT '',
			active tinyint(1) NOT NULL DEFAULT 1,
			has_desk tinyint(1) NOT NULL DEFAULT 0,
			price_home decimal(10,2) NOT NULL DEFAULT -1,
			price_desk decimal(10,2) NOT NULL DEFAULT -1,
			PRIMARY KEY (id),
			KEY wilaya_code (wilaya_code),
			KEY name_fr (name_fr(60))
		) $collate;";

		$tables['stopdesks'] = 'CREATE TABLE ' . self::table( 'stopdesks' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			carrier varchar(40) NOT NULL,
			wilaya_code varchar(3) NOT NULL,
			commune varchar(160) NOT NULL DEFAULT '',
			name varchar(200) NOT NULL,
			address text,
			active tinyint(1) NOT NULL DEFAULT 1,
			external_id varchar(80) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY carrier (carrier),
			KEY wilaya_code (wilaya_code)
		) $collate;";

		$tables['orders'] = 'CREATE TABLE ' . self::table( 'orders' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wc_order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
			quantity smallint(6) NOT NULL DEFAULT 1,
			customer_name varchar(160) NOT NULL DEFAULT '',
			phone varchar(20) NOT NULL DEFAULT '',
			email varchar(120) NOT NULL DEFAULT '',
			wilaya_code varchar(3) NOT NULL DEFAULT '',
			commune varchar(160) NOT NULL DEFAULT '',
			delivery_mode varchar(10) NOT NULL DEFAULT 'home',
			stopdesk varchar(200) NOT NULL DEFAULT '',
			subtotal decimal(10,2) NOT NULL DEFAULT 0,
			discount decimal(10,2) NOT NULL DEFAULT 0,
			coupon varchar(50) NOT NULL DEFAULT '',
			shipping decimal(10,2) NOT NULL DEFAULT 0,
			total decimal(10,2) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'pending',
			payment varchar(20) NOT NULL DEFAULT 'cod',
			checkout_id varchar(64) NOT NULL DEFAULT '',
			paid tinyint(1) NOT NULL DEFAULT 0,
			paid_at datetime NULL DEFAULT NULL,
			carrier varchar(40) NOT NULL DEFAULT '',
			tracking varchar(80) NOT NULL DEFAULT '',
			carrier_status varchar(40) NOT NULL DEFAULT '',
			fraud_score tinyint(3) unsigned NOT NULL DEFAULT 0,
			fraud_flags text,
			ip varchar(45) NOT NULL DEFAULT '',
			fingerprint varchar(64) NOT NULL DEFAULT '',
			note text,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			confirmed_at datetime NULL DEFAULT NULL,
			shipped_at datetime NULL DEFAULT NULL,
			delivered_at datetime NULL DEFAULT NULL,
			PRIMARY KEY (id),
			KEY wc_order_id (wc_order_id),
			KEY status (status),
			KEY phone (phone),
			KEY wilaya_code (wilaya_code),
			KEY created_at (created_at)
		) $collate;";

		$tables['abandoned'] = 'CREATE TABLE ' . self::table( 'abandoned' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_key varchar(64) NOT NULL,
			product_id bigint(20) unsigned NOT NULL DEFAULT 0,
			customer_name varchar(160) NOT NULL DEFAULT '',
			phone varchar(20) NOT NULL DEFAULT '',
			wilaya_code varchar(3) NOT NULL DEFAULT '',
			commune varchar(160) NOT NULL DEFAULT '',
			cart_total decimal(10,2) NOT NULL DEFAULT 0,
			progress tinyint(3) unsigned NOT NULL DEFAULT 0,
			reminders_sent tinyint(3) unsigned NOT NULL DEFAULT 0,
			last_reminder datetime NULL DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			recovered_order bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY session_key (session_key),
			KEY status (status),
			KEY phone (phone)
		) $collate;";

		$tables['blacklist'] = 'CREATE TABLE ' . self::table( 'blacklist' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			kind varchar(10) NOT NULL DEFAULT 'phone',
			value varchar(190) NOT NULL,
			reason varchar(255) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY (id),
			KEY kind (kind),
			KEY value (value)
		) $collate;";

		$tables['fraud_logs'] = 'CREATE TABLE ' . self::table( 'fraud_logs' ) . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			ip varchar(45) NOT NULL DEFAULT '',
			phone varchar(20) NOT NULL DEFAULT '',
			fingerprint varchar(64) NOT NULL DEFAULT '',
			flags varchar(255) NOT NULL DEFAULT '',
			action varchar(20) NOT NULL DEFAULT '',
			PRIMARY KEY (id),
			KEY created_at (created_at),
			KEY ip (ip),
			KEY phone (phone)
		) $collate;";

		return $tables;
	}
}
