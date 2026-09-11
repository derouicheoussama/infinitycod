<?php
/**
 * Purge des caches de pages : appelé après chaque sauvegarde de réglages
 * ET après chaque mise à jour du plugin — sans quoi les visiteurs voient
 * l'ancien HTML/CSS (l'aperçu admin, lui, rend toujours à la fraîche).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Core;

defined( 'ABSPATH' ) || exit;

class CachePurge {

	/**
	 * Purge best-effort des caches de pages les plus répandus.
	 *
	 * @return void
	 */
	public static function purge_all() {
		if ( defined( 'LSCWP_V' ) ) {
			do_action( 'litespeed_purge_all' );
		}
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
			sg_cachepress_purge_cache();
		}
		if ( function_exists( 'wpfc_clear_all_cache' ) ) {
			wpfc_clear_all_cache();
		}
		if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			\autoptimizeCache::clearall();
		}
		if ( function_exists( 'breeze_clear_cache' ) ) {
			breeze_clear_cache();
		}
		if ( class_exists( '\WPaaS\Cache' ) && method_exists( '\WPaaS\Cache', 'purge' ) ) {
			\WPaaS\Cache::purge();
		}

		/**
		 * Purge personnalisée (CDN, hébergeur managé, plugin de cache exotique).
		 *
		 * @since 5.25.4
		 */
		do_action( 'infinitycod_purge_page_caches' );
	}
}
