<?php
/**
 * Validation des saisies client — normes algériennes.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Form;

defined( 'ABSPATH' ) || exit;

class Validator {

	/**
	 * Normalise un numéro de téléphone algérien vers le format national 0XXXXXXXXX.
	 *
	 * Accepte : +213 5/6/7XXXXXXXX, 00213…, 0 5/6/7…, avec espaces/tirets/points.
	 *
	 * @param string $raw Numéro saisi.
	 * @return string|null Numéro normalisé (10 chiffres) ou null si invalide.
	 */
	public static function normalize_phone( $raw ) {
		$digits = preg_replace( '/[\s\-.()]/', '', (string) $raw );

		if ( 0 === strpos( $digits, '00213' ) ) {
			$digits = '0' . substr( $digits, 5 );
		} elseif ( 0 === strpos( $digits, '+213' ) ) {
			$digits = '0' . substr( $digits, 4 );
		} elseif ( 0 === strpos( $digits, '213' ) && 12 === strlen( $digits ) ) {
			$digits = '0' . substr( $digits, 3 );
		}

		if ( ! preg_match( '/^0[5-7][0-9]{8}$/', $digits ) ) {
			return null;
		}

		return $digits;
	}

	/**
	 * Numéro valide au sens strict (opérateurs DZ uniquement) ?
	 *
	 * @param string $raw Numéro saisi.
	 * @return bool
	 */
	public static function is_valid_phone( $raw ) {
		return null !== self::normalize_phone( $raw );
	}

	/**
	 * Numéro au format international sans "+" (utilisé par WhatsApp).
	 *
	 * @param string $raw Numéro saisi ou normalisé.
	 * @return string|null Ex. "213661234567".
	 */
	public static function phone_to_international( $raw ) {
		$normalized = self::normalize_phone( $raw );
		if ( null === $normalized ) {
			return null;
		}
		return '213' . substr( $normalized, 1 );
	}

	/**
	 * Nom client plausible : lettres (toutes écritures), espaces, apostrophes, tirets.
	 *
	 * @param string $raw Nom saisi.
	 * @return bool
	 */
	public static function is_valid_name( $raw ) {
		$name = trim( (string) $raw );
		$len  = function_exists( 'mb_strlen' ) ? mb_strlen( $name ) : strlen( $name );
		return $len >= 2 && $len <= 80 && preg_match( '/^[\p{L}\s\'-]+$/u', $name );
	}

	/**
	 * Nettoie un nom pour stockage.
	 *
	 * @param string $raw Nom saisi.
	 * @return string
	 */
	public static function clean_name( $raw ) {
		return sanitize_text_field( trim( (string) $raw ) );
	}
}
