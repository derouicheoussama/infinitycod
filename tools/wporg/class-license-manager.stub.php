<?php
/**
 * LicenseManager — no-op pour la distribution WordPress.org.
 *
 * La version commerciale valide les licences auprès du serveur InfinityCod ;
 * sur WordPress.org cette communication distante est interdite et inutile :
 * tout ce qui est gratuit dans la version commerciale est disponible ici
 * sans clé. La classe garde exactement la même API publique (pages admin,
 * Checkout Builder et verrou de formulaire l'appellent) mais ne contacte
 * jamais de serveur distant.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\License;

defined( 'ABSPATH' ) || exit;

class LicenseManager {

	/**
	 * N'accroche aucun hook (pas de vérification distante sur wp.org).
	 *
	 * @return void
	 */
	public function register() {}

	/**
	 * Statut de licence : distribution wp.org = tout open source.
	 *
	 * @return string
	 */
	public static function status_label() {
		return __( 'Open source (WordPress.org)', 'infinitycod' );
	}

	/**
	 * Toutes les fonctionnalités gratuites de base sont actives.
	 *
	 * @return bool
	 */
	public static function is_premium() {
		return false;
	}

	/**
	 * Aucune clé de licence en distribution wp.org.
	 *
	 * @return string
	 */
	public static function license_key() {
		return '';
	}
}
