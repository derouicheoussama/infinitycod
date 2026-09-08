<?php
/**
 * Administration : menu, pages, assets.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Admin;

use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class AdminManager {

	/**
	 * Pages enregistrées : slug => instance.
	 *
	 * @var array
	 */
	private $pages = array();

	/**
	 * Hooks admin.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_icod_save_wilayas', array( $this, 'handle_wilayas_save' ) );
		add_action( 'wp_ajax_icod_save_commune', array( $this, 'handle_commune_save' ) );
	}

	/**
	 * Enregistre une page admin.
	 *
	 * @param string $slug   Slug de la page.
	 * @param object $page   Instance (méthode render()).
	 * @return void
	 */
	public function add_page( $slug, $page ) {
		$this->pages[ $slug ] = $page;
	}

	/**
	 * Construit le menu du plugin.
	 *
	 * @return void
	 */
	public function menu() {
		add_menu_page(
			'InfinityCod',
			'InfinityCod',
			'manage_woocommerce',
			'infinitycod',
			array( $this, 'render_dashboard' ),
			'dashicons-cart',
			56
		);

		add_submenu_page(
			'infinitycod',
			__( 'Tableau de bord', 'infinitycod' ),
			__( 'Tableau de bord', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'infinitycod',
			__( 'Commandes COD', 'infinitycod' ),
			__( 'Commandes COD', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-orders',
			array( $this, 'render_orders' )
		);

		add_submenu_page(
			'infinitycod',
			__( 'Wilayas & Tarifs', 'infinitycod' ),
			__( 'Wilayas & Tarifs', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-geo',
			array( $this, 'render_geo' )
		);

		add_submenu_page(
			'infinitycod',
			__( 'Transporteurs', 'infinitycod' ),
			__( 'Transporteurs', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-carriers',
			array( $this, 'render_carriers' )
		);

		add_submenu_page(
			'infinitycod',
			__( 'Statistiques P&L', 'infinitycod' ),
			__( 'Statistiques P&L', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-stats',
			array( $this, 'render_stats' )
		);

		add_submenu_page(
			'infinitycod',
			__( 'Réglages InfinityCod', 'infinitycod' ),
			__( 'Réglages', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Charge les assets admin sur les écrans du plugin uniquement.
	 *
	 * @param string $hook_suffix Suffixe d'écran admin.
	 * @return void
	 */
	public function assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'infinitycod' ) ) {
			return;
		}

		wp_enqueue_style(
			'icod-admin',
			INFINITYCOD_URL . 'assets/admin/css/admin.css',
			array(),
			INFINITYCOD_VERSION
		);

		wp_enqueue_script(
			'icod-admin',
			INFINITYCOD_URL . 'assets/admin/js/admin.js',
			array(),
			INFINITYCOD_VERSION,
			true
		);

		wp_localize_script( 'icod-admin', 'icodAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'icod_admin' ),
			'i18n'    => array(
				'loading'   => __( 'Chargement…', 'infinitycod' ),
				'error'     => __( 'Une erreur est survenue.', 'infinitycod' ),
				'saved'     => __( 'Enregistré ✓', 'infinitycod' ),
				'confirm'   => __( 'Confirmer ?', 'infinitycod' ),
				'inherit'   => __( 'Hérite', 'infinitycod' ),
			),
		) );
	}

	/**
	 * Rendu de la page tableau de bord.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		if ( class_exists( __NAMESPACE__ . '\\Pages\\DashboardPage' ) ) {
			( new Pages\DashboardPage() )->render();
			return;
		}
		printf(
			'<div class="wrap icod-wrap"><h1>%s</h1><p>%s</p></div>',
			esc_html__( 'InfinityCod — Tableau de bord', 'infinitycod' ),
			esc_html__( 'Le tableau de bord arrive avec la phase Commandes.', 'infinitycod' )
		);
	}

	/**
	 * Rendu de la page commandes.
	 *
	 * @return void
	 */
	public function render_orders() {
		if ( class_exists( __NAMESPACE__ . '\\Pages\\OrdersPage' ) ) {
			( new Pages\OrdersPage() )->render();
			return;
		}
		printf(
			'<div class="wrap icod-wrap"><h1>%s</h1><p>%s</p></div>',
			esc_html__( 'Commandes COD', 'infinitycod' ),
			esc_html__( 'Le gestionnaire de commandes arrive avec la phase Commandes.', 'infinitycod' )
		);
	}

	/**
	 * Rendu de la page wilayas & tarifs.
	 *
	 * @return void
	 */
	public function render_geo() {
		( new Pages\GeoPage( $this ) )->render();
	}

	/**
	 * Rendu de la page transporteurs.
	 *
	 * @return void
	 */
	public function render_carriers() {
		if ( class_exists( __NAMESPACE__ . '\\Pages\\CarriersPage' ) ) {
			( new Pages\CarriersPage() )->render();
			return;
		}
		printf(
			'<div class="wrap icod-wrap"><h1>%s</h1><p>%s</p></div>',
			esc_html__( 'Transporteurs', 'infinitycod' ),
			esc_html__( 'Les intégrations transporteurs arrivent avec la phase Livraison.', 'infinitycod' )
		);
	}

	/**
	 * Rendu de la page statistiques.
	 *
	 * @return void
	 */
	public function render_stats() {
		if ( class_exists( __NAMESPACE__ . '\\Pages\\StatsPage' ) ) {
			( new Pages\StatsPage() )->render();
			return;
		}
		printf(
			'<div class="wrap icod-wrap"><h1>%s</h1><p>%s</p></div>',
			esc_html__( 'Statistiques P&L', 'infinitycod' ),
			esc_html__( 'Les statistiques arrivent avec la phase Stats.', 'infinitycod' )
		);
	}

	/**
	 * Rendu de la page réglages.
	 *
	 * @return void
	 */
	public function render_settings() {
		( new Pages\SettingsPage() )->render();
	}

	/**
	 * Sauvegarde AJAX d'un override de commune (prix / activation).
	 *
	 * @return void
	 */
	public function handle_commune_save() {
		check_ajax_referer( 'icod_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ) );
		}

		$commune_id = isset( $_POST['commune_id'] ) ? absint( $_POST['commune_id'] ) : 0;
		$field      = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$value      = isset( $_POST['value'] ) ? wp_unslash( $_POST['value'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validé selon $field.

		if ( ! $commune_id || ! in_array( $field, array( 'active', 'price_home', 'price_desk' ), true ) ) {
			wp_send_json_error( array( 'message' => 'invalid' ) );
		}

		$data = array();
		if ( 'active' === $field ) {
			$data['active'] = ( '1' === $value ) ? 1 : 0;
		} else {
			$data[ $field ] = ( '' === $value ) ? -1 : (float) str_replace( ',', '.', $value );
			if ( $data[ $field ] < 0 ) {
				$data[ $field ] = -1;
			}
		}

		$rates = infinitycod()->module( 'rates' );
		$ok    = $rates ? $rates->save_commune( $commune_id, $data ) : false;

		if ( $ok ) {
			wp_send_json_success();
		}
		wp_send_json_error( array( 'message' => 'db' ) );
	}

	/**
	 * Traite la sauvegarde en masse des tarifs wilayas (POST).
	 *
	 * @return void
	 */
	public function handle_wilayas_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_save_wilayas' );

		if ( ! isset( $_POST['icod_wilaya'] ) || ! is_array( $_POST['icod_wilaya'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-geo&icod_msg=empty' ) );
			exit;
		}

		$rates = infinitycod()->module( 'rates' );

		$data   = array();
		$prices = wp_unslash( $_POST['icod_wilaya'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- validé champ par champ ci-dessous.

		foreach ( $prices as $code => $row ) {
			$code = sanitize_text_field( (string) $code );
			if ( ! preg_match( '/^\d{1,2}$/', $code ) ) {
				continue;
			}
			$data[ $code ] = array(
				'home'   => isset( $row['home'] ) && '' !== $row['home'] ? (float) $row['home'] : -1,
				'desk'   => isset( $row['desk'] ) && '' !== $row['desk'] ? (float) $row['desk'] : -1,
				'active' => empty( $row['active'] ) ? 0 : 1,
				'free'   => empty( $row['free'] ) ? 0 : 1,
			);
		}

		// Défauts globaux éditables sur le même écran.
		Settings::set( array(
			'default_price_home' => isset( $_POST['icod_default_home'] ) && '' !== $_POST['icod_default_home'] ? (float) $_POST['icod_default_home'] : Settings::get( 'default_price_home' ),
			'default_price_desk' => isset( $_POST['icod_default_desk'] ) && '' !== $_POST['icod_default_desk'] ? (float) $_POST['icod_default_desk'] : Settings::get( 'default_price_desk' ),
			'free_shipping_qty'  => isset( $_POST['icod_free_qty'] ) ? (int) $_POST['icod_free_qty'] : 0,
		) );

		$saved = $rates ? $rates->save_wilaya_prices( $data ) : 0;

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-geo&icod_msg=saved&count=' . $saved ) );
		exit;
	}
}
