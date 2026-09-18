<?php
/**
 * LicenseManager — édition complète pour la distribution CodeCanyon (Envato).
 *
 * Sur Envato, un article ne doit pas verrouiller des fonctionnalités derrière
 * un achat supplémentaire hors marketplace : l'achat CodeCanyon (licence
 * Regular/Extended) donne accès à TOUTES les fonctionnalités. La version
 * commerciale valide les licences auprès du serveur InfinityCod ; ici ce
 * client est neutralisé (aucun appel distant) et `is_premium()` renvoie
 * toujours vrai. La classe garde exactement la même API publique (pages
 * admin, Checkout Builder et verrou de formulaire l'appellent).
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
	 * N'accroche aucun hook (aucune vérification distante sur CodeCanyon).
	 *
	 * @return void
	 */
	public function register() {}

	/**
	 * Statut de licence : achat Envato = édition complète.
	 *
	 * @return string
	 */
	public static function status_label() {
		return __( 'Full edition (CodeCanyon license)', 'infinitycod' );
	}

	/**
	 * L'achat CodeCanyon débloque toutes les fonctionnalités.
	 *
	 * @return bool
	 */
	public static function is_premium() {
		return true;
	}

	/**
	 * Aucune clé de licence : la preuve d'achat est gérée par Envato.
	 *
	 * @return string
	 */
	public static function license_key() {
		return '';
	}
}
