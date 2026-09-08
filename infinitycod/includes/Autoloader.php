<?php
/**
 * Autoloader PSR-4 pour le namespace InfinityCod\.
 *
 * @package InfinityCod
 */

namespace InfinityCod;

defined( 'ABSPATH' ) || exit;

final class Autoloader {

	/**
	 * Préfixe de namespace géré.
	 *
	 * @var string
	 */
	const PREFIX = 'InfinityCod\\';

	/**
	 * Répertoire racine des classes.
	 *
	 * @var string
	 */
	private static $root;

	/**
	 * Enregistre l'autoloader sur la pile SPL.
	 *
	 * @return void
	 */
	public static function register() {
		self::$root = dirname( __DIR__ ) . '/includes/';
		spl_autoload_register( array( __CLASS__, 'load' ) );
	}

	/**
	 * Charge une classe du namespace InfinityCod\.
	 *
	 * Les sous-namespace Admin, Rest, Core… vivent dans des sous-dossiers
	 * homonymes : InfinityCod\Carriers\Yalidine -> carriers/class-yalidine.php.
	 *
	 * @param string $class Nom complet de la classe.
	 * @return bool True si la classe a été chargée.
	 */
	public static function load( $class ) {
		if ( 0 !== strpos( $class, self::PREFIX ) ) {
			return false;
		}

		$relative = substr( $class, strlen( self::PREFIX ) );
		$parts    = explode( '\\', $relative );
		$name     = strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', end( $parts ) ) );
		array_pop( $parts );

		$path = self::$root;
		foreach ( $parts as $part ) {
			$path .= strtolower( $part ) . '/';
		}
		$path .= 'class-' . $name . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
			return true;
		}

		return false;
	}
}
