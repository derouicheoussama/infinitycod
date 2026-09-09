<?php
/**
 * Administration : menu, pages, assets.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
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
	 * Pages à hooks précoces (admin-post / AJAX enregistrés pour chaque requête).
	 *
	 * @var array
	 */
	private $hooked_pages = array();

	/**
	 * Hooks admin.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		// IMPORTANT : ces pages enregistrent des handlers admin_post dans
		// leur constructeur. Ils doivent donc exister dès le chargement du
		// plugin — y compris quand admin-post.php reçoit le POST de
		// sauvegarde (la page n'est PAS rendue dans ce cas). C'était la
		// cause du bug « les réglages ne s'enregistrent pas ».
		$this->hooked_pages['settings']   = new Pages\SettingsPage();
		$this->hooked_pages['about']      = new Pages\AboutPage();
		$this->hooked_pages['updates']    = new Pages\UpdatesPage();
		$this->hooked_pages['diagnostics'] = new Pages\DiagnosticsPage();
		add_action( 'admin_post_icod_save_wilayas', array( $this, 'handle_wilayas_save' ) );
		add_action( 'admin_post_icod_rates_export', array( $this, 'handle_rates_export' ) );
		add_action( 'admin_post_icod_rates_import', array( $this, 'handle_rates_import' ) );
		add_action( 'wp_ajax_icod_save_commune', array( $this, 'handle_commune_save' ) );
		add_action( 'admin_post_icod_orders_bulk', array( $this, 'handle_orders_bulk' ) );
		add_action( 'wp_ajax_icod_order_status', array( $this, 'handle_order_status' ) );
		add_action( 'wp_ajax_icod_order_blacklist', array( $this, 'handle_order_blacklist' ) );
		add_action( 'admin_post_icod_orders_export', array( $this, 'handle_orders_export' ) );
		add_action( 'admin_post_icod_carrier_save', array( $this, 'handle_carrier_save' ) );
		add_action( 'wp_ajax_icod_carrier_test', array( $this, 'handle_carrier_test' ) );
		add_action( 'wp_ajax_icod_parcel_create', array( $this, 'handle_parcel_create' ) );
		add_action( 'wp_ajax_icod_sync_tracking', array( $this, 'handle_sync_tracking' ) );
		add_action( 'wp_ajax_icod_import_offices', array( $this, 'handle_import_offices' ) );
		add_action( 'admin_menu', array( $this, 'apply_menu_badge' ), 999 );
		add_filter( 'admin_footer_text', array( $this, 'footer_credit' ) );

		// Liste des extensions : liens d'action et de meta pro.
		add_filter( 'plugin_action_links_' . INFINITYCOD_BASENAME, array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );

		// Accueil d'installation : redirection unique vers le dashboard.
		add_action( 'admin_init', array( $this, 'welcome_redirect' ), 1 );

		// Métabox offres par produit.
		( new ProductMetaBox() )->register();
	}

	/**
	 * Liens « Réglages » et « Commandes » sur la ligne du plugin.
	 *
	 * @param array $actions Liens existants.
	 * @return array
	 */
	public function action_links( $actions ) {
		$custom = array(
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=infinitycod-settings' ) ),
				esc_html__( 'Réglages', 'infinitycod' )
			),
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=infinitycod-orders' ) ),
				esc_html__( 'Commandes', 'infinitycod' )
			),
		);
		return array_merge( $custom, (array) $actions );
	}

	/**
	 * Meta de ligne : site, documentation, version.
	 *
	 * @param array  $meta Meta existantes.
	 * @param string $file Fichier du plugin en cours.
	 * @return array
	 */
	public function row_meta( $meta, $file ) {
		if ( INFINITYCOD_BASENAME !== $file ) {
			return $meta;
		}

		$meta[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( 'https://infinitycoder.app' ),
			esc_html__( 'Site web', 'infinitycod' )
		);
		$meta[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( 'https://github.com/derouicheoussama/infinitycod-releases/releases' ),
			esc_html__( 'Nouveautés', 'infinitycod' )
		);
		return $meta;
	}

	/**
	 * Après activation : redirection unique vers le dashboard InfinityCod.
	 *
	 * @return void
	 */
	public function welcome_redirect() {
		// Lien « Ne plus afficher » de l'écran Bienvenue.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- action de préférence personnelle, sans conséquence sensible.
		if ( isset( $_GET['page'], $_GET['icod_hide_welcome'] ) && 'infinitycod-welcome' === $_GET['page'] ) {
			delete_transient( 'icod_welcome' );
			wp_safe_redirect( admin_url( 'admin.php?page=infinitycod' ) );
			exit;
		}

		if ( ! get_transient( 'icod_welcome' ) ) {
			return;
		}

		delete_transient( 'icod_welcome' );

		if ( wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- activation en masse.
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-welcome' ) );
		exit;
	}

	/**
	 * Badge de commandes en attente sur l'entrée de menu InfinityCod.
	 *
	 * Attaché à admin_menu en priorité 999 : le menu est construit,
	 * on enrichit le titre avant son rendu.
	 *
	 * @return void
	 */
	public function apply_menu_badge() {
		if ( ! \InfinityCod\Core\Settings::get( 'menu_badge' ) ) {
			return;
		}

		global $wpdb;

		$orders_table = \InfinityCod\Core\Schema::table( 'orders' );
		$pending      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$orders_table} WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery

		if ( $pending < 1 ) {
			return;
		}

		foreach ( (array) $GLOBALS['menu'] as $index => $item ) {
			if ( isset( $item[2] ) && 'infinitycod' === $item[2] ) {
				$GLOBALS['menu'][ $index ][0] .= sprintf(
					' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>',
					$pending
				);
				break;
			}
		}
	}

	/**
	 * Crédit en pied de page sur les écrans du plugin.
	 *
	 * @param string $text Texte courant.
	 * @return string
	 */
	public function footer_credit( $text ) {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && false !== strpos( (string) $screen->id, 'infinitycod' ) ) {
				return sprintf(
					/* translators: 1 : version. */
					esc_html__( 'InfinityCod v%1$s — développé avec ❤️ par Infinity Coder (Oussama Derouiche)', 'infinitycod' ),
					esc_html( INFINITYCOD_VERSION )
				);
			}
		}
		return $text;
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
			'📊 ' . __( 'Tableau de bord', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'infinitycod',
			__( 'Commandes COD', 'infinitycod' ),
			'📦 ' . __( 'Commandes COD', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-orders',
			array( $this, 'render_orders' )
		);

		add_submenu_page(
			'infinitycod',
			__( 'Paniers abandonnés', 'infinitycod' ),
			'🛒 ' . __( 'Paniers abandonnés', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-abandoned',
			array( $this, 'render_abandoned' )
		);

		add_submenu_page(
			'infinitycod',
			__( 'Wilayas & Tarifs', 'infinitycod' ),
			'🗺️ ' . __( 'Wilayas & Tarifs', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-geo',
			array( $this, 'render_geo' )
		);

		add_submenu_page(
			'infinitycod',
			__( 'Transporteurs', 'infinitycod' ),
			'🚚 ' . __( 'Transporteurs', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-carriers',
			array( $this, 'render_carriers' )
		);

		add_submenu_page(
			'infinitycod',
			__( 'Statistiques P&L', 'infinitycod' ),
			'📈 ' . __( 'Statistiques P&L', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-stats',
			array( $this, 'render_stats' )
		);

		add_submenu_page(
			'infinitycod',
			__( 'Réglages InfinityCod', 'infinitycod' ),
			'⚙️ ' . __( 'Réglages', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'infinitycod',
			__( 'Mises à jour', 'infinitycod' ),
			'🔄 ' . __( 'Mises à jour', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-updates',
			array( $this, 'render_updates' )
		);

		add_submenu_page(
			'infinitycod',
			__( 'Diagnostics', 'infinitycod' ),
			'🩺 ' . __( 'Diagnostics', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-diagnostics',
			array( $this, 'render_diagnostics' )
		);

		add_submenu_page(
			'infinitycod',
			__( 'À propos d‘InfinityCod', 'infinitycod' ),
			'ℹ️ ' . __( 'À propos', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-about',
			array( $this, 'render_about' )
		);

		// Écran de bienvenue : caché du menu (parent null), affiché après
		// l'activation et accessible depuis « À propos ».
		add_submenu_page(
			null,
			__( 'Bienvenue dans InfinityCod', 'infinitycod' ),
			__( 'Bienvenue', 'infinitycod' ),
			'manage_woocommerce',
			'infinitycod-welcome',
			array( $this, 'render_welcome' )
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
	 * Rendu de la page paniers abandonnés.
	 *
	 * @return void
	 */
	public function render_abandoned() {
		( new Pages\AbandonedPage() )->render();
	}

	/**
	 * Rendu de la page wilayas & tarifs.
	 *
	 * @return void
	 */
	public function render_geo() {
		( new Pages\GeoPage() )->render();
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
		if ( ! \InfinityCod\License\LicenseManager::is_premium() ) {
			printf(
				'<div class="wrap icod-wrap"><h1>%1$s</h1><div class="icod-card"><h2>%2$s</h2><p>%3$s</p><p><a class="button button-primary" href="%4$s">%5$s</a></p></div></div>',
				esc_html__( 'Statistiques P&L', 'infinitycod' ),
				esc_html__( 'Fonctionnalité Premium', 'infinitycod' ),
				esc_html__( 'Les statistiques P&L (CA, taux de confirmation et de retour par wilaya, transporteur et produit) nécessitent une licence Premium.', 'infinitycod' ),
				esc_url( admin_url( 'admin.php?page=infinitycod-settings&tab=license' ) ),
				esc_html__( 'Activer une licence', 'infinitycod' )
			);
			return;
		}

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
		if ( ! isset( $this->hooked_pages['settings'] ) ) {
			$this->hooked_pages['settings'] = new Pages\SettingsPage();
		}
		$this->hooked_pages['settings']->render();
	}

	/**
	 * Rendu de la page mises à jour.
	 *
	 * @return void
	 */
	public function render_updates() {
		if ( ! isset( $this->hooked_pages['updates'] ) ) {
			$this->hooked_pages['updates'] = new Pages\UpdatesPage();
		}
		$this->hooked_pages['updates']->render();
	}

	/**
	 * Rendu de la page diagnostics.
	 *
	 * @return void
	 */
	public function render_diagnostics() {
		if ( ! isset( $this->hooked_pages['diagnostics'] ) ) {
			$this->hooked_pages['diagnostics'] = new Pages\DiagnosticsPage();
		}
		$this->hooked_pages['diagnostics']->render();
	}

	/**
	 * Rendu de la page à propos.
	 *
	 * @return void
	 */
	public function render_about() {
		if ( ! isset( $this->hooked_pages['about'] ) ) {
			$this->hooked_pages['about'] = new Pages\AboutPage();
		}
		$this->hooked_pages['about']->render();
	}

	/**
	 * Écran de bienvenue (page cachée — pas d'entrée de menu).
	 *
	 * @return void
	 */
	public function render_welcome() {
		if ( ! isset( $this->hooked_pages['welcome'] ) ) {
			$this->hooked_pages['welcome'] = new Pages\WelcomePage();
		}
		$this->hooked_pages['welcome']->render();
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

		// Livraison gratuite intelligente + poids.
		\InfinityCod\Core\Settings::set( array(
			'free_amount_enabled'   => empty( $_POST['icod_free_amount_enabled'] ) ? 0 : 1,
			'free_amount_threshold' => isset( $_POST['icod_free_amount_threshold'] ) ? (float) $_POST['icod_free_amount_threshold'] : 0,
			'free_amount_message'   => isset( $_POST['icod_free_amount_message'] ) ? sanitize_text_field( wp_unslash( $_POST['icod_free_amount_message'] ) ) : '',
			'weight_fee_enabled'    => empty( $_POST['icod_weight_fee_enabled'] ) ? 0 : 1,
			'weight_fee_per_kg'     => isset( $_POST['icod_weight_fee_per_kg'] ) ? (float) $_POST['icod_weight_fee_per_kg'] : 0,
			'weight_fee_free_kg'    => isset( $_POST['icod_weight_fee_free_kg'] ) ? (float) $_POST['icod_weight_fee_free_kg'] : 0,
		) );

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

	/**
	 * Actions groupées sur les commandes (admin-post).
	 *
	 * @return void
	 */
	public function handle_orders_bulk() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_orders_bulk' );

		$bulk = isset( $_POST['bulk'] ) ? sanitize_key( wp_unslash( $_POST['bulk'] ) ) : '';
		$ids  = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? array_map( 'absint', wp_unslash( $_POST['ids'] ) ) : array();

		if ( ! $bulk || ! $ids ) {
			wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-orders' ) );
			exit;
		}

		$orders = infinitycod()->module( 'orders' );
		$shield = infinitycod()->module( 'shield' );

		foreach ( $ids as $id ) {
			if ( 'blacklist' === $bulk ) {
				if ( $orders && $shield ) {
					$order = \InfinityCod\Core\Schema::get_order( $id );
					if ( $order && $order['phone'] ) {
						$shield->blacklist( 'phone', $order['phone'], __( 'Blacklist manuelle (dashboard)', 'infinitycod' ) );
					}
				}
				continue;
			}
			if ( $orders ) {
				$orders->set_status( $id, $bulk );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-orders&icod_msg=done' ) );
		exit;
	}

	/**
	 * Changement rapide de statut (AJAX).
	 *
	 * @return void
	 */
	public function handle_order_status() {
		check_ajax_referer( 'icod_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ) );
		}

		$id     = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		if ( ! $id || ! array_key_exists( $status, \InfinityCod\Orders\OrderStore::STATUSES ) ) {
			wp_send_json_error( array( 'message' => 'invalid' ) );
		}

		$orders = infinitycod()->module( 'orders' );
		$ok     = $orders ? $orders->set_status( $id, $status ) : false;

		if ( $ok ) {
			wp_send_json_success( array(
				'status' => $status,
				'label'  => \InfinityCod\Orders\OrderStore::STATUSES[ $status ],
			) );
		}
		wp_send_json_error( array( 'message' => 'db' ) );
	}

	/**
	 * Blacklist d'un téléphone (AJAX).
	 *
	 * @return void
	 */
	public function handle_order_blacklist() {
		check_ajax_referer( 'icod_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ) );
		}

		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$phone = \InfinityCod\Form\Validator::normalize_phone( $phone );

		if ( null === $phone ) {
			wp_send_json_error( array( 'message' => 'invalid' ) );
		}

		$shield = infinitycod()->module( 'shield' );
		$ok     = $shield ? $shield->blacklist( 'phone', $phone, __( 'Blacklist manuelle (dashboard)', 'infinitycod' ) ) : false;

		if ( $ok ) {
			wp_send_json_success();
		}
		wp_send_json_error( array( 'message' => 'db' ) );
	}

	/**
	 * Export CSV des commandes filtrées.
	 *
	 * @return void
	 */
	public function handle_orders_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_orders_export' );

		global $wpdb;

		$orders_table  = \InfinityCod\Core\Schema::table( 'orders' );
		$wilayas_table = \InfinityCod\Core\Schema::table( 'wilayas' );

		$where  = array( '1=1' );
		$params = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- export filtré, nonce vérifié ci-dessus.
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$wilaya = isset( $_GET['wilaya'] ) ? sanitize_text_field( wp_unslash( $_GET['wilaya'] ) ) : '';
		$q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$from   = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
		$to     = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
		// phpcs:enable

		if ( $status && array_key_exists( $status, \InfinityCod\Orders\OrderStore::STATUSES ) ) {
			$where[]  = 'o.status = %s';
			$params[] = $status;
		}
		if ( $wilaya && preg_match( '/^\d{1,2}$/', $wilaya ) ) {
			$where[]  = 'o.wilaya_code = %s';
			$params[] = str_pad( $wilaya, 2, '0', STR_PAD_LEFT );
		}
		if ( $q ) {
			$like     = '%' . $wpdb->esc_like( $q ) . '%';
			$where[]  = '(o.customer_name LIKE %s OR o.phone LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}
		if ( $from && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from ) ) {
			$where[]  = 'o.created_at >= %s';
			$params[] = $from . ' 00:00:00';
		}
		if ( $to && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to ) ) {
			$where[]  = 'o.created_at <= %s';
			$params[] = $to . ' 23:59:59';
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT o.*, w.name_fr AS wilaya_name FROM {$orders_table} o
			LEFT JOIN {$wilayas_table} w ON w.code = o.wilaya_code
			WHERE {$where_sql} ORDER BY o.created_at DESC LIMIT 5000";

		$rows = $params
			? $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) // phpcs:ignore WordPress.DB.PreparedSQL
			: $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

		$statuses = \InfinityCod\Orders\OrderStore::STATUSES;

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=infinitycod-commandes-' . gmdate( 'Ymd-Hi' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		// BOM UTF-8 pour Excel.
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'ID', 'WC #', 'Date', 'Nom', 'Telephone', 'Wilaya', 'Commune', 'Mode', 'Bureau', 'Produit', 'Qte', 'Sous-total', 'Remise', 'Livraison', 'Total', 'Statut', 'Transporteur', 'Suivi', 'Score risque', 'IP' ), ';' );

		foreach ( (array) $rows as $row ) {
			$cells = array(
				$row['id'],
				$row['wc_order_id'],
				$row['created_at'],
				$row['customer_name'],
				$row['phone'],
				$row['wilaya_name'],
				$row['commune'],
				'home' === $row['delivery_mode'] ? 'Domicile' : 'Bureau',
				$row['stopdesk'],
				$row['product_id'] ? get_the_title( (int) $row['product_id'] ) : '',
				$row['quantity'],
				$row['subtotal'],
				$row['discount'],
				$row['shipping'],
				$row['total'],
				isset( $statuses[ $row['status'] ] ) ? $statuses[ $row['status'] ] : $row['status'],
				$row['carrier'],
				$row['tracking'],
				$row['fraud_score'],
				$row['ip'],
			);

			// Anti CSV-injection : neutraliser les formules (Excel).
			$cells = array_map( function( $v ) {
				$v = (string) $v;
				if ( isset( $v[0] ) && in_array( $v[0], array( '=', '+', '-', '@' ), true ) ) {
					$v = "'" . $v;
				}
				return $v;
			}, $cells );

			fputcsv( $out, $cells, ';' );
		}

		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}


	/**
	 * Import CSV des tarifs wilayas (format : code;domicile;stopdesk;active;gratuite).
	 *
	 * @return void
	 */
	public function handle_rates_import() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}
		check_admin_referer( 'icod_save_wilayas' );

		if ( empty( $_FILES['icod_rates_csv_file']['tmp_name'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-geo&icod_msg=import-empty' ) );
			exit;
		}

		$handle = fopen( $_FILES['icod_rates_csv_file']['tmp_name'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions, WordPress.Security.ValidatedSanitizedInput
		if ( ! $handle ) {
			wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-geo&icod_msg=import-empty' ) );
			exit;
		}

		$rates = infinitycod()->module( 'rates' );
		$count = 0;
		$line  = 0;

		while ( ( $row = fgetcsv( $handle, 1000, ';' ) ) !== false ) {
			$line++;
			if ( 1 === $line && 0 === stripos( implode( '', (array) $row ), 'code' ) ) {
				continue; // ligne d'en-tête.
			}
			$code = isset( $row[0] ) ? preg_replace( '/[^0-9]/', '', (string) $row[0] ) : '';
			if ( '' === $code || ! preg_match( '/^\\d{1,2}$/', $code ) ) {
				continue;
			}
			$data = array(
				'home'   => isset( $row[2] ) && '' !== $row[2] ? (float) str_replace( ',', '.', $row[2] ) : -1,
				'desk'   => isset( $row[3] ) && '' !== $row[3] ? (float) str_replace( ',', '.', $row[3] ) : -1,
				'active' => isset( $row[4] ) ? (int) (bool) $row[4] : 1,
				'free'   => isset( $row[5] ) ? (int) (bool) $row[5] : 0,
			);
			if ( $rates && $rates->save_wilaya_prices( array( str_pad( $code, 2, '0', STR_PAD_LEFT ) => $data ) ) ) {
				$count++;
			}
		}
		fclose( $handle );

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-geo&icod_msg=imported&count=' . $count ) );
		exit;
	}

	/**
	 * Export CSV des tarifs wilayas (§77 : capability + nonce + anti-injection).
	 *
	 * @return void
	 */
	public function handle_rates_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}
		check_admin_referer( 'icod_rates_export' );

		global $wpdb;
		$table = \InfinityCod\Core\Schema::table( 'wilayas' );
		$rows  = $wpdb->get_results( "SELECT code, name_fr, price_home, price_desk, active, free_shipping FROM {$table} ORDER BY code ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=infinitycod-tarifs-' . gmdate( 'Ymd' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, array( 'code', 'wilaya', 'domicile', 'stopdesk', 'active', 'gratuite' ), ';' );

		foreach ( (array) $rows as $row ) {
			$cells = array(
				$row['code'],
				$row['name_fr'],
				(float) $row['price_home'] < 0 ? '' : $row['price_home'],
				(float) $row['price_desk'] < 0 ? '' : $row['price_desk'],
				$row['active'] ? '1' : '0',
				$row['free_shipping'] ? '1' : '0',
			);
			// Anti CSV-injection.
			$cells = array_map( function ( $v ) {
				$v = (string) $v;
				return ( isset( $v[0] ) && in_array( $v[0], array( '=', '+', '-', '@' ), true ) ) ? "'" . $v : $v;
			}, $cells );
			fputcsv( $out, $cells, ';' );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Sauvegarde des connexions transporteurs (admin-post).
	 *
	 * @return void
	 */
	public function handle_carrier_save() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_carrier_save' );

		$posted = isset( $_POST['icod_carrier'] ) && is_array( $_POST['icod_carrier'] ) ? wp_unslash( $_POST['icod_carrier'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$fields = array( 'api_id', 'api_token', 'api_key', 'user_guid', 'base_url' );

		foreach ( $posted as $code => $data ) {
			$code  = sanitize_key( $code );
			$clean = array();

			foreach ( $fields as $field ) {
				if ( isset( $data[ $field ] ) ) {
					$value = sanitize_text_field( (string) $data[ $field ] );
					if ( '' !== $value ) {
						$clean[ $field ] = $value;
					}
				}
			}

			// Conserver les anciennes valeurs secrètes si le champ est laissé vide (mot de passe).
			$previous = \InfinityCod\Carriers\CarrierManager::config( $code );
			foreach ( array( 'api_token', 'api_key' ) as $secret ) {
				if ( empty( $clean[ $secret ] ) && ! empty( $previous[ $secret ] ) ) {
					$clean[ $secret ] = $previous[ $secret ];
				}
			}

			$clean['enabled'] = empty( $data['enabled'] ) ? 0 : 1;
			\InfinityCod\Carriers\CarrierManager::save_config( $code, $clean );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=infinitycod-carriers&icod_msg=saved' ) );
		exit;
	}

	/**
	 * Test de connexion AJAX (teste les valeurs saisies sans les enregistrer).
	 *
	 * @return void
	 */
	public function handle_carrier_test() {
		check_ajax_referer( 'icod_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ) );
		}

		$code    = isset( $_POST['code'] ) ? sanitize_key( wp_unslash( $_POST['code'] ) ) : '';
		$manager = infinitycod()->module( 'carriers' );

		if ( ! $code || ! $manager || ! $manager->catalog_entry( $code ) ) {
			wp_send_json_error( array( 'message' => 'invalid' ) );
		}

		$config = array();
		foreach ( array( 'api_id', 'api_token', 'api_key', 'user_guid', 'base_url' ) as $field ) {
			if ( isset( $_POST[ $field ] ) && '' !== $_POST[ $field ] ) {
				$config[ $field ] = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
			}
		}

		$previous        = \InfinityCod\Carriers\CarrierManager::config( $code );
		$config          = array_merge( $previous, $config );
		$config['enabled'] = 1;

		$entry = $manager->catalog_entry( $code );
		$class = '\\InfinityCod\\Carriers\\' . $entry['adapter'];

		if ( ! class_exists( $class ) ) {
			wp_send_json_error( array( 'message' => 'adapter' ) );
		}

		if ( empty( $config['base_url'] ) ) {
			$config['base_url'] = $entry['default_base'];
		}

		$adapter = new $class( $config );
		$result  = $adapter->test();

		wp_send_json_success( $result );
	}

	/**
	 * Création d'un colis (AJAX).
	 *
	 * @return void
	 */
	public function handle_parcel_create() {
		check_ajax_referer( 'icod_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ) );
		}

		$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$carrier = isset( $_POST['carrier'] ) ? sanitize_key( wp_unslash( $_POST['carrier'] ) ) : '';

		$manager = infinitycod()->module( 'carriers' );
		$result  = $manager ? $manager->create_parcel_from_order( $id, $carrier ) : array( 'ok' => false, 'message' => 'no manager', 'tracking' => '' );

		if ( ! empty( $result['ok'] ) ) {
			wp_send_json_success( $result );
		}
		wp_send_json_error( $result );
	}

	/**
	 * Synchronisation manuelle des suivis (AJAX).
	 *
	 * @return void
	 */
	public function handle_sync_tracking() {
		check_ajax_referer( 'icod_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ) );
		}

		$manager = infinitycod()->module( 'carriers' );
		$result  = $manager ? $manager->sync_tracking() : array( 'checked' => 0, 'updated' => 0 );

		wp_send_json_success( $result );
	}

	/**
	 * Import des bureaux Yalidine (AJAX).
	 *
	 * @return void
	 */
	public function handle_import_offices() {
		check_ajax_referer( 'icod_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ) );
		}

		$manager = infinitycod()->module( 'carriers' );
		$result  = $manager ? $manager->import_yalidine_offices() : array( 'ok' => false, 'message' => 'no manager' );

		if ( ! empty( $result['ok'] ) ) {
			wp_send_json_success( $result );
		}
		wp_send_json_error( $result );
	}
}
