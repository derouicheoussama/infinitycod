<?php
/**
 * Updater — no-op pour la distribution WordPress.org.
 *
 * La version commerciale utilise GitHub Releases + miroirs signés pour les
 * mises à jour automatiques ; WordPress.org l'interdit dans son dépôt
 * (plugin_updater_detected). Le build `--wporg` remplace ce fichier par ce
 * stub : la classe garde exactement la même API publique (LicenseManager,
 * page Mises à jour, page Diagnostics et page À propos l'appellent), mais
 * n'accroche aucun hook de mise à jour — WordPress.org gère lui-même les
 * mises à jour de ses plugins.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\License;

defined( 'ABSPATH' ) || exit;

class Updater {

	/**
	 * Canal de mise à jour : WordPress.org ne connaît que stable.
	 *
	 * @return string
	 */
	public static function channel() {
		return 'stable';
	}

	/**
	 * Aucun cache distant à vider en distribution wp.org.
	 *
	 * @return void
	 */
	public static function clear_cache() {}

	/**
	 * Aucune source distante : liste vide.
	 *
	 * @return array
	 */
	public static function probe_sources() {
		return array();
	}

	/**
	 * N'accroche aucun hook de mise à jour (WordPress.org s'en charge).
	 *
	 * @return void
	 */
	public function register() {}

	/**
	 * Aucune release distante : WordPress.org alimente l'écran Extensions.
	 *
	 * @return null
	 */
	public function latest() {
		return null;
	}

	/**
	 * Toutes les versions du dépôt officiel sont compatibles par construction.
	 *
	 * @param mixed $remote Informations distantes.
	 * @return bool
	 */
	public function check_compatibility( $remote ) {
		return true;
	}

	/**
	 * Pas de sauvegarde gérée par le plugin en distribution wp.org.
	 *
	 * @return false
	 */
	public function backup_current() {
		return false;
	}

	/**
	 * Rollback indisponible : refus propre.
	 *
	 * @param string $version Version cible.
	 * @return \WP_Error
	 */
	public function restore_backup( $version ) {
		return new \WP_Error( 'icod_wporg_no_rollback', __( 'Rollback indisponible en distribution WordPress.org.', 'infinitycod' ) );
	}

	/**
	 * Pas d'historique de mise à jour interne.
	 *
	 * @return void
	 */
	public function record_history() {}
}
