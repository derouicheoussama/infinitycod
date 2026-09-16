<?php
/**
 * Extras admin : export Excel des paniers abandonnés, poll des nouvelles
 * commandes (bip + notification), rapport hebdomadaire par email et avis
 * de mode maintenance.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Admin;

use InfinityCod\Core\Schema;
use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders, PluginCheck.Security.DirectDB
// Tables custom InfinityCod : noms de tables issus de Schema::table() (constantes internes,
// jamais d'entree utilisateur) et valeurs toujours liees via $wpdb->prepare(). Requetes
// directes volontaires sur nos propres tables (pas d'equivalent WP_Query), avec caches
// applicatifs la ou c'est chaud (compteurs, tarifs).


class AdminExtras {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_icod_abandoned_export_xls', array( $this, 'handle_abandoned_export_xls' ) );
		add_action( 'wp_ajax_icod_orders_poll', array( $this, 'handle_orders_poll' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_sound_poll' ), 20 );
		add_action( 'init', array( $this, 'schedule_weekly' ), 20 );
		add_action( 'infinitycod_weekly_report', array( $this, 'send_weekly_report' ) );
		add_action( 'admin_notices', array( $this, 'maintenance_notice' ) );
	}

	/**
	 * Planifie (ou retire) le rapport hebdomadaire selon le réglage.
	 *
	 * @return void
	 */
	public function schedule_weekly() {
		if ( Settings::get( 'weekly_report' ) ) {
			if ( ! wp_next_scheduled( 'infinitycod_weekly_report' ) ) {
				wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'infinitycod_weekly_report' );
			}
			return;
		}
		wp_clear_scheduled_hook( 'infinitycod_weekly_report' );
	}

	/**
	 * Avis de mode maintenance (pause des commandes).
	 *
	 * @return void
	 */
	public function maintenance_notice() {
		if ( ! Settings::get( 'maintenance_mode' ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( '⚠️ Commandes en pause :', 'infinitycod' ) . '</strong> ' . esc_html__( 'le mode maintenance est actif — le formulaire COD affiche un avis aux visiteurs.', 'infinitycod' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=infinitycod-settings' ) ) . '">' . esc_html__( 'Désactiver', 'infinitycod' ) . '</a></p></div>';
	}

	/**
	 * Script de polling (bip à chaque nouvelle commande), sur toutes les pages admin.
	 *
	 * @return void
	 */
	public function enqueue_sound_poll() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! Settings::get( 'order_sound' ) ) {
			return;
		}
		wp_enqueue_script(
			'icod-sound-poll',
			INFINITYCOD_URL . 'assets/admin/js/sound-poll.js',
			array(),
			INFINITYCOD_VERSION,
			true
		);
		wp_localize_script(
			'icod-sound-poll',
			'icodPoll',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'icod_admin' ),
			)
		);
	}

	/**
	 * Poll AJAX : dernier id de commande + nombre en attente.
	 *
	 * @return void
	 */
	public function handle_orders_poll() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
		check_ajax_referer( 'icod_admin', 'nonce' );

		global $wpdb;
		$orders  = Schema::table( 'orders' );
		$last    = (int) $wpdb->get_var( "SELECT COALESCE(MAX(id),0) FROM {$orders}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$orders} WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

		wp_send_json_success(
			array(
				'last_id' => $last,
				'pending' => $pending,
			)
		);
	}

	/**
	 * Export Excel (.xls) des paniers abandonnés.
	 *
	 * @return void
	 */
	public function handle_abandoned_export_xls() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_abandoned_export' );

		global $wpdb;
		$table = Schema::table( 'abandoned' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT 2000", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

		$labels = array(
			'open'      => __( 'En attente', 'infinitycod' ),
			'recovered' => __( 'Récupéré', 'infinitycod' ),
			'archived'  => __( 'Archivé', 'infinitycod' ),
		);
		$colors = array(
			'open'      => '#fdf3e0',
			'recovered' => '#e5f2e9',
			'archived'  => '#f0f0f1',
		);

		nocache_headers();
		header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=infinitycod-paniers-abandonnes-' . gmdate( 'Ymd' ) . '.xls' );

		echo '<html><head><meta charset="utf-8" /></head><body>';
		echo '<table border="1" cellspacing="0" cellpadding="6" style="font-family:Arial,sans-serif;font-size:13px;border-collapse:collapse">';
		echo '<tr style="background:#1d5fa8;color:#fff;font-weight:bold">'
			. '<th>' . esc_html__( 'Client', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__( 'Téléphone', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__( 'Produit', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__( 'Progression', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__( 'Panier', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__( 'Relances', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__( 'Statut', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__( 'Mise à jour', 'infinitycod' ) . '</th>'
			. '</tr>';

		foreach ( (array) $rows as $row ) {
			$status = isset( $labels[ $row['status'] ] ) ? $labels[ $row['status'] ] : $row['status'];
			$color  = isset( $colors[ $row['status'] ] ) ? $colors[ $row['status'] ] : '#f0f0f1';
			echo '<tr>'
				. '<td>' . esc_html( $row['customer_name'] ) . '</td>'
				. '<td>' . esc_html( $row['phone'] ) . '</td>'
				. '<td>' . esc_html( $row['product_id'] ? get_the_title( (int) $row['product_id'] ) : '' ) . '</td>'
				. '<td>' . (int) $row['progress'] . '%</td>'
				. '<td>' . esc_html( number_format_i18n( (float) $row['cart_total'], 0 ) ) . '</td>'
				. '<td>' . (int) $row['reminders_sent'] . '</td>'
				. '<td bgcolor="' . esc_attr( $color ) . '">' . esc_html( $status ) . '</td>'
				. '<td>' . esc_html( mysql2date( 'd/m/Y H:i', $row['updated_at'] ) ) . '</td>'
				. '</tr>';
		}
		echo '</table></body></html>';
		exit;
	}

	/**
	 * Rapport hebdomadaire par email (KPI des 7 derniers jours).
	 *
	 * @return void
	 */
	public function send_weekly_report() {
		global $wpdb;

		$orders = Schema::table( 'orders' );
		$since  = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 7 * DAY_IN_SECONDS );

		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS n,
				COALESCE(SUM(CASE WHEN status IN ('confirmed','shipped','delivered') THEN total ELSE 0 END),0) AS revenue,
				COALESCE(SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END),0) AS confirmed
			 FROM {$orders} WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL
			$since
		), ARRAY_A );

		if ( ! is_array( $row ) ) {
			$row = array( 'n' => 0, 'revenue' => 0, 'confirmed' => 0 );
		}

		$pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$orders} WHERE status = 'pending'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

		$to      = get_option( 'admin_email' );
		$subject = sprintf( /* translators: 1 : nom du site, 2 : nombre de commandes. */ __( '[%1$s] Semaine COD : %2$d commande(s)', 'infinitycod' ), get_bloginfo( 'name' ), (int) $row['n'] );

		$body  = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1d2327">';
		$body .= '<h2 style="color:#0e7a4f">' . esc_html__( 'Rapport COD des 7 derniers jours', 'infinitycod' ) . '</h2>';
		$body .= '<ul>';
		$body .= '<li>' . esc_html__( 'Commandes :', 'infinitycod' ) . ' <strong>' . (int) $row['n'] . '</strong></li>';
		$body .= '<li>' . esc_html__( 'Confirmées :', 'infinitycod' ) . ' <strong>' . (int) $row['confirmed'] . '</strong></li>';
		$body .= '<li>' . esc_html__( 'Chiffre d’affaires :', 'infinitycod' ) . ' <strong>' . esc_html( number_format_i18n( (float) $row['revenue'], 0 ) . ' ' . Settings::currency_label() ) . '</strong></li>';
		$body .= '<li>' . esc_html__( 'En attente de confirmation :', 'infinitycod' ) . ' <strong>' . (int) $pending . '</strong></li>';
		$body .= '</ul>';
		$body .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=infinitycod-orders' ) ) . '">' . esc_html__( 'Ouvrir le tableau de bord', 'infinitycod' ) . '</a></p>';
		$body .= '</div>';

		wp_mail( $to, $subject, $body, array( 'Content-Type: text/html; charset=utf-8' ) );

		// Telegram (optionnel) : même synthèse en texte court.
		$bot  = trim( (string) Settings::get( 'telegram_bot_token', '' ) );
		$chat = trim( (string) Settings::get( 'telegram_chat_id', '' ) );
		if ( '' !== $bot && '' !== $chat ) {
			$text = '📊 ' . get_bloginfo( 'name' ) . ' — 7 jours : ' . (int) $row['n'] . ' commande(s), '
				. (int) $row['confirmed'] . ' confirmée(s), ' . number_format_i18n( (float) $row['revenue'], 0 ) . ' ' . Settings::currency_label() . '.';
			wp_remote_post( 'https://api.telegram.org/bot' . rawurlencode( $bot ) . '/sendMessage', array(
				'timeout' => 8,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'chat_id' => $chat, 'text' => $text ) ),
			) );
		}
	}
}
