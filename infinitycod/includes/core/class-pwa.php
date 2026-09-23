<?php
/**
 * PWA marchand : manifest + service worker + balises d'installation.
 *
 * Permet au marchand d'installer le tableau de bord InfinityCod sur l'écran
 * d'accueil de son téléphone (icône, plein écran, raccourcis Commandes).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Core;

defined( 'ABSPATH' ) || exit;

class Pwa {

	/**
	 * Hooks : rewrites + balises admin.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'add_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
		add_action( 'admin_head', array( $this, 'head_tags' ) );
	}

	/**
	 * Rewrites /icod-manifest.json et /icod-sw.js.
	 *
	 * @return void
	 */
	public function add_rewrite() {
		add_rewrite_rule( '^icod-manifest\.json/?$', 'index.php?icod_pwa=manifest', 'top' );
		add_rewrite_rule( '^icod-sw\.js/?$', 'index.php?icod_pwa=sw', 'top' );
		if ( ! get_option( 'icod_pwa_flushed' ) ) {
			flush_rewrite_rules();
			update_option( 'icod_pwa_flushed', 1, true );
		}
	}

	/**
	 * Variables de requête.
	 *
	 * @param array $vars Variables existantes.
	 * @return array
	 */
	public function add_query_var( $vars ) {
		$vars[] = 'icod_pwa';
		return $vars;
	}

	/**
	 * Balises d'installation dans l'admin (manifest + SW + thème).
	 *
	 * @return void
	 */
	public function head_tags() {
		// URLs en query-string : fonctionnent quel que soit le réglage de permaliens
		// (les rewrites /icod-sw.js échouent en permaliens « simple » → SW jamais enregistré).
		$manifest = home_url( '/?icod_pwa=manifest' );
		echo '<link rel="manifest" href="' . esc_url( $manifest ) . '">' . "\n";
		echo '<meta name="theme-color" content="#0e7a4f">' . "\n";
		echo '<link rel="apple-touch-icon" href="' . esc_url( INFINITYCOD_URL . 'assets/icon-256x256.png' ) . '">' . "\n";
		echo '<script>if("serviceWorker" in navigator){navigator.serviceWorker.register("' . esc_js( home_url( '/?icod_pwa=sw' ) ) . '").catch(function(){})}</script>' . "\n";
	}

	/**
	 * Rend le manifest ou le service worker.
	 *
	 * @return void
	 */
	public function maybe_render() {
		$what = (string) get_query_var( 'icod_pwa' );
		if ( '' === $what ) {
			return;
		}

		nocache_headers();

		if ( 'manifest' === $what ) {
			header( 'Content-Type: application/manifest+json; charset=utf-8' );
			$manifest = array(
				'name'             => 'InfinityCod — ' . get_bloginfo( 'name' ),
				'short_name'       => 'InfinityCod',
				'start_url'        => admin_url( 'admin.php?page=infinitycod' ),
				'display'          => 'standalone',
				'background_color' => '#f4f7f5',
				'theme_color'      => '#0e7a4f',
				'icons'            => array(
					array( 'src' => INFINITYCOD_URL . 'assets/icon-128x128.png', 'sizes' => '128x128', 'type' => 'image/png' ),
					array( 'src' => INFINITYCOD_URL . 'assets/icon-256x256.png', 'sizes' => '256x256', 'type' => 'image/png' ),
				),
				'shortcuts'        => array(
					array( 'name' => __( 'Commandes', 'infinitycod' ), 'url' => admin_url( 'admin.php?page=infinitycod-orders' ) ),
					array( 'name' => __( 'Statistiques', 'infinitycod' ), 'url' => admin_url( 'admin.php?page=infinitycod-stats' ) ),
				),
			);
			echo wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			exit;
		}

		header( 'Content-Type: application/javascript; charset=utf-8' );
		echo '/* InfinityCod PWA */';
		echo 'self.addEventListener("install",function(e){self.skipWaiting()});';
		echo 'self.addEventListener("activate",function(e){e.waitUntil(self.clients.claim())});';
		echo 'self.addEventListener("fetch",function(){/* passthrough */});';
		// Web Push : notification « nouvelle commande » même écran verrouillé.
		echo 'self.addEventListener("push",function(e){var d={};try{d=e.data.json()}catch(err){d={title:"InfinityCod"}};';
		echo 'e.waitUntil(self.registration.showNotification(d.title||"InfinityCod",{body:d.body||"",icon:d.icon||"",badge:d.icon||"",tag:d.tag||"icod",data:{url:d.url||""},requireInteraction:true}))});';
		echo 'self.addEventListener("notificationclick",function(e){e.notification.close();var u=e.notification.data&&e.notification.data.url;e.waitUntil(self.clients.matchAll({type:"window",includeUncontrolled:true}).then(function(cs){for(var i=0;i<cs.length;i++){if("focus" in cs[i]){if(u){return cs[i].navigate(u)}return cs[i].focus()}}return u?self.clients.openWindow(u):undefined}))});';
		exit;
	}
}
