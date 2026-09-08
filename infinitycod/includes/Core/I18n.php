<?php
/**
 * Internationalisation : FR par défaut, AR (RTL) et EN.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Core;

defined( 'ABSPATH' ) || exit;

class I18n {

	/**
	 * Enregistre les hooks liés à la langue.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'plugin_locale', array( $this, 'force_locale' ), 10, 2 );
	}

	/**
	 * Le français est la langue de secours du plugin quand aucune
	 * traduction n'est disponible pour la langue du site.
	 *
	 * @param string $locale  Locale calculée par WP.
	 * @param string $domain  Text domain demandé.
	 * @return string
	 */
	public function force_locale( $locale, $domain ) {
		if ( 'infinitycod' !== $domain ) {
			return $locale;
		}

		$langs = array( 'fr_FR', 'ar', 'en_US', 'ar_DZ' );
		return in_array( $locale, $langs, true ) ? $locale : 'fr_FR';
	}

	/**
	 * Indique si l'affichage courant doit être RTL (arabe).
	 *
	 * @return bool
	 */
	public static function is_rtl() {
		return is_rtl() || 'ar' === substr( get_locale(), 0, 2 );
	}
}
