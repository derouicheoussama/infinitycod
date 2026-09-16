<?php
/**
 * A/B testing du formulaire : attribution stable par session + compteurs.
 *
 * Chaque visiteur reçoit une variante (A = contrôle, B = variante testée)
 * fixée dans un cookie 30 jours : le rendu est cohérent d'une page à l'autre.
 * Les vues et les commandes sont comptées par variante pour mesurer le taux
 * de conversion dans Statistiques P&L.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Orders;

use InfinityCod\Core\Schema;
use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders, PluginCheck.Security.DirectDB, WordPress.Security.ValidatedSanitizedInput
// Table custom icod_orders (colonne ab_variant) + cookie lu/écrit par le
// plugin, valeur contrainte à A|B par in_array avant tout usage.

class AbTest {

	/**
	 * Variante de la requête courante (mémo).
	 *
	 * @var string
	 */
	private static $variant = '';

	/**
	 * Hook : attribution dès « wp » (avant tout output, cookie posable).
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp', array( $this, 'maybe_assign' ), 1 );
	}

	/**
	 * Attribue la variante si le test est actif (cookie stable 30 jours).
	 *
	 * @return void
	 */
	public function maybe_assign() {
		if ( ! Settings::get( 'abtest_enabled' ) ) {
			return;
		}
		self::assign();
	}

	/**
	 * Variante de la session courante ('' si le test est désactivé).
	 *
	 * @return string 'A' | 'B' | ''
	 */
	public static function assign() {
		if ( '' !== self::$variant ) {
			return self::$variant;
		}
		if ( ! Settings::get( 'abtest_enabled' ) ) {
			return '';
		}
		if ( isset( $_COOKIE['icod_ab'] ) ) {
			$cookie = sanitize_text_field( wp_unslash( (string) $_COOKIE['icod_ab'] ) );
			if ( in_array( $cookie, array( 'A', 'B' ), true ) ) {
				self::$variant = $cookie;
				return $cookie;
			}
		}
		$variant = ( 0 === mt_rand( 0, 1 ) ) ? 'A' : 'B';
		if ( ! headers_sent() ) {
			setcookie( 'icod_ab', $variant, time() + 30 * DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN );
		}
		$_COOKIE['icod_ab'] = $variant;
		self::$variant      = $variant;
		return $variant;
	}

	/**
	 * Compte une vue de formulaire pour la variante donnée.
	 *
	 * @param string $variant Variante.
	 * @return void
	 */
	public static function track_view( $variant ) {
		if ( ! in_array( $variant, array( 'A', 'B' ), true ) ) {
			return;
		}
		$key = 'icod_ab_views_' . strtolower( $variant );
		update_option( $key, (int) get_option( $key, 0 ) + 1, false );
	}

	/**
	 * Nombre de vues d'une variante.
	 *
	 * @param string $variant Variante.
	 * @return int
	 */
	public static function views( $variant ) {
		return (int) get_option( 'icod_ab_views_' . strtolower( $variant ), 0 );
	}

	/**
	 * Nombre de commandes d'une variante.
	 *
	 * @param string $variant Variante.
	 * @return int
	 */
	public static function orders( $variant ) {
		global $wpdb;
		$table = Schema::table( 'orders' );
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ab_variant = %s", $variant ) // phpcs:ignore WordPress.DB.PreparedSQL
		);
	}

	/**
	 * Résumé A/B pour la page Statistiques.
	 *
	 * @return array{enabled:bool,a:array{views:int,orders:int,rate:float},b:array{views:int,orders:int,rate:float}}
	 */
	public static function stats() {
		$out = array( 'enabled' => (bool) Settings::get( 'abtest_enabled' ) );
		foreach ( array( 'A', 'B' ) as $variant ) {
			$views        = self::views( $variant );
			$orders       = self::orders( $variant );
			$out[ strtolower( $variant ) ] = array(
				'views'  => $views,
				'orders' => $orders,
				'rate'   => $views > 0 ? round( $orders * 100 / $views, 2 ) : 0.0,
			);
		}
		return $out;
	}
}
