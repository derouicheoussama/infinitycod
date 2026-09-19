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
		add_action( 'init', array( $this, 'schedule_daily_extras' ), 20 );
		add_action( 'infinitycod_daily_wa_report', array( $this, 'send_daily_wa_report' ) );
		add_action( 'infinitycod_stock_alert_check', array( $this, 'run_stock_alert_check' ) );
		add_action( 'infinitycod_weekly_report', array( $this, 'send_weekly_report' ) );
		add_action( 'infinitycod_telemetry_ping', array( $this, 'send_telemetry_ping' ) );
		add_action( 'infinitycod_wa_review_request', array( $this, 'send_review_requests' ) );
		add_action( 'admin_post_icod_accounting_export', array( $this, 'handle_accounting_export' ) );
		add_action( 'admin_notices', array( $this, 'maintenance_notice' ) );
	}

	/**
	 * Planifie (ou retire) le rapport hebdomadaire selon le réglage.
	 *
	 * @return void
	 */
	/**
	 * Crons journaliers : rapport WhatsApp marchand + vérification des stocks.
	 *
	 * @return void
	 */
	public function schedule_daily_extras() {
		if ( Settings::get( 'wa_daily_report' ) && ! wp_next_scheduled( 'infinitycod_daily_wa_report' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'infinitycod_daily_wa_report' );
		}
		if ( (int) Settings::get( 'stock_alert_threshold', 0 ) > 0 && ! wp_next_scheduled( 'infinitycod_stock_alert_check' ) ) {
			wp_schedule_event( time() + 2 * HOUR_IN_SECONDS, 'daily', 'infinitycod_stock_alert_check' );
		}
		if ( Settings::get( 'telemetry_optin' ) && ! wp_next_scheduled( 'infinitycod_telemetry_ping' ) ) {
			wp_schedule_event( time() + 6 * HOUR_IN_SECONDS, 'daily', 'infinitycod_telemetry_ping' );
		}
		if ( Settings::get( 'wa_review_enable' ) && ! wp_next_scheduled( 'infinitycod_wa_review_request' ) ) {
			wp_schedule_event( time() + 3 * HOUR_IN_SECONDS, 'daily', 'infinitycod_wa_review_request' );
		}
	}

	/**
	 * Télémétrie de santé — 100 % opt-in (réglage telemetry_optin, désactivé
	 * par défaut), un ping mensuel maximum, strictement anonyme : versions du
	 * plugin / PHP / WordPress et résultat de la dernière mise à jour. Aucune
	 * donnée client, commande, URL ou personnelle n'est transmise. Le but :
	 * détecter une régression de mise à jour chez un hébergeur donné avant
	 * que les marchands ne la signalent.
	 *
	 * @return void
	 */
	public function send_telemetry_ping() {
		if ( ! Settings::get( 'telemetry_optin' ) ) {
			return;
		}
		$last = get_option( 'icod_telemetry_sent', 0 );
		if ( time() - (int) $last < 30 * DAY_IN_SECONDS ) {
			return; // Un ping par mois, pas plus.
		}
		$history = get_option( 'infinitycod_update_history', array() );
		$history = is_array( $history ) ? $history : array();
		$last_up = ! empty( $history ) ? (string) end( $history )['result'] : 'none';

		$payload = array(
			'version'    => INFINITYCOD_VERSION,
			'php'        => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
			'wp'         => get_bloginfo( 'version' ),
			'woocommerce'=> defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'channel'    => \InfinityCod\License\Updater::channel(),
			'last_update'=> $last_up,
			'site_hash'  => substr( wp_hash( home_url( '/' ) ), 0, 12 ), // Sel site : impossible à inverser.
		);
		$response = wp_remote_post(
			defined( 'INFINITYCOD_TELEMETRY_URL' ) ? INFINITYCOD_TELEMETRY_URL : 'https://infinitycoder.app/telemetry',
			array(
				'timeout' => 10,
				'body'    => wp_json_encode( $payload ),
				'headers' => array( 'Content-Type' => 'application/json', 'User-Agent' => 'InfinityCod-Telemetry/' . INFINITYCOD_VERSION ),
			)
		);
		if ( ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) < 500 ) {
			update_option( 'icod_telemetry_sent', time(), false );
		}
	}

	/**
	 * Relance d'avis post-livraison : WhatsApp automatique X jours après la
	 * livraison (réglage wa_review_days, 0 = désactivé), avec le lien d'avis
	 * marchand configuré. Chaque commande ne reçoit qu'une seule relance
	 * (liste des id déjà relancés dans l'option icod_review_sent).
	 *
	 * @return void
	 */
	public function send_review_requests() {
		if ( ! Settings::get( 'wa_review_enable' ) ) {
			return;
		}
		$days = max( 1, (int) Settings::get( 'wa_review_days', 2 ) );
		$link = esc_url( (string) Settings::get( 'wa_review_link', '' ) );
		$wa   = infinitycod() ? infinitycod()->module( 'whatsapp' ) : null;
		if ( '' === $link || ! $wa ) {
			return;
		}

		global $wpdb;
		$orders = Schema::table( 'orders' );
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $days * DAY_IN_SECONDS );
		$sent   = get_option( 'icod_review_sent', array() );
		$sent   = is_array( $sent ) ? $sent : array();
		$sent   = array_slice( $sent, -2000, false, true ); // Plafonne la mémoire.

		$due = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, customer_name, phone FROM {$orders} WHERE status = 'delivered' AND delivered_at IS NOT NULL AND delivered_at <= %s ORDER BY id ASC LIMIT 50", // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$cutoff
		), ARRAY_A );

		foreach ( (array) $due as $order ) {
			$order_id = (string) $order['id'];
			if ( isset( $sent[ $order_id ] ) || '' === (string) $order['phone'] ) {
				continue;
			}
			$text = sprintf(
				/* translators: 1 : nom du client, 2 : nom de la boutique, 3 : lien d'avis. */
				__( 'Bonjour %1$s 👋, merci pour votre commande chez %2$s ! Votre avis compte énormément pour nous : %3$s', 'infinitycod' ),
				(string) $order['customer_name'],
				get_bloginfo( 'name' ),
				$link
			);
			$wa->send( (string) $order['phone'], $text );
			$sent[ $order_id ] = time();
			update_option( 'icod_review_sent', $sent, false );
		}
	}

	/**
	 * Ligne CSV échappée (séparateur ; — compatible Excel FR).
	 *
	 * @param array $cells Cellules de la ligne.
	 * @return string Ligne terminée par un saut de ligne.
	 */
	private function csv_line( array $cells ) {
		$escaped = array_map(
			static function ( $cell ) {
				$cell = (string) $cell;
				if ( false !== strpos( $cell, ';' ) || false !== strpos( $cell, '"' ) || false !== strpos( $cell, "\n" ) ) {
					$cell = '"' . str_replace( '"', '""', $cell ) . '"';
				}
				return $cell;
			},
			$cells
		);
		return implode( ';', $escaped ) . "\n";
	}

	/**
	 * Export comptable mensuel (CSV compatible Excel). Paramètre GET month
	 * (YYYY-MM), défaut : le mois écoulé. Contient chaque commande du mois,
	 * le CA enregistré, le CA livré et les frais de livraison.
	 *
	 * @return void
	 */
	public function handle_accounting_export() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__('Accès refusé.', 'infinitycod' ) );
		}
		check_admin_referer( 'icod_accounting_export' );

		$trace = \InfinityCod\Core\AntiLeak::trace_code();
		\InfinityCod\Core\AntiLeak::log_export( 'accounting_csv', 0, $trace );

		$month = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : gmdate( 'Y-m', strtotime( '-1 month' ) );
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			$month = gmdate( 'Y-m', strtotime( '-1 month' ) );
		}
		$start = $month . '-01 00:00:00';
		$end   = gmdate( 'Y-m-t 23:59:59', strtotime( $start ) );

		global $wpdb;
		$table = Schema::table( 'orders' );
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT created_at, wc_order_id, customer_name, phone, wilaya_code, commune, carrier, status, total, shipping FROM {$table} WHERE created_at >= %s AND created_at <= %s ORDER BY created_at ASC", // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
			$start,
			$end
		), ARRAY_A );

		$status_labels = array(
			'pending'   => esc_html__('En attente', 'infinitycod' ),
			'confirmed' => esc_html__('Confirmée', 'infinitycod' ),
			'shipped'   => esc_html__('Expédiée', 'infinitycod' ),
			'delivered' => esc_html__('Livrée', 'infinitycod' ),
			'returned'  => esc_html__('Retour', 'infinitycod' ),
			'cancelled' => esc_html__('Annulée', 'infinitycod' ),
			'no_answer' => esc_html__('Sans réponse', 'infinitycod' ),
		);

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=infinitycod-comptable-' . $month . '.csv' );
		// BOM UTF-8 : les accents s'affichent correctement dans Excel.
		echo "\xEF\xBB\xBF";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sortie CSV téléchargée (pas du HTML) ; libellés i18n échappés via esc_html__().
		echo $this->csv_line( array(
			esc_html__('Date', 'infinitycod' ),
			esc_html__('Commande', 'infinitycod' ),
			esc_html__('Client', 'infinitycod' ),
			esc_html__('Téléphone', 'infinitycod' ),
			esc_html__('Wilaya', 'infinitycod' ),
			esc_html__('Commune', 'infinitycod' ),
			esc_html__('Transporteur', 'infinitycod' ),
			esc_html__('Statut', 'infinitycod' ),
			esc_html__('Total', 'infinitycod' ),
			esc_html__('Livraison', 'infinitycod' ),
		) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sortie CSV téléchargée ; libellés i18n échappés.

		$totals          = array( 'total' => 0.0, 'shipping' => 0.0, 'delivered' => 0.0 );
		$currency_suffix = ' ' . Settings::currency_label();
		foreach ( (array) $rows as $row ) {
			$status = isset( $status_labels[ $row['status'] ] ) ? $status_labels[ $row['status'] ] : (string) $row['status'];
			$total  = (float) $row['total'];
			$ship   = (float) $row['shipping'];
			$cells  = array( $row['created_at'], $row['wc_order_id'], $row['customer_name'], $row['phone'], $row['wilaya_code'], $row['commune'], $row['carrier'], $status, number_format( $total, 2, ',', '' ) . $currency_suffix, number_format( $ship, 2, ',', '' ) . $currency_suffix );
			echo $this->csv_line( $cells ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sortie CSV téléchargée (pas du HTML) : valeurs construites depuis la base et number_format().
			$totals['total']    += $total;
			$totals['shipping'] += $ship;
			if ( 'delivered' === $row['status'] ) {
				$totals['delivered'] += $total;
			}
		}
		echo $this->csv_line( array() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ligne vide de séparation.
		$summary = array(
			esc_html__('TOTAL CA enregistré', 'infinitycod' ) . ' : ' . number_format( $totals['total'], 2, ',', '' ) . $currency_suffix,
			esc_html__('Frais de livraison cumulés', 'infinitycod' ) . ' : ' . number_format( $totals['shipping'], 2, ',', '' ) . $currency_suffix,
			esc_html__('TOTAL CA livré', 'infinitycod' ) . ' : ' . number_format( $totals['delivered'], 2, ',', '' ) . $currency_suffix,
		);
		echo $this->csv_line( $summary ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sortie CSV téléchargée ; libellés i18n + number_format().
		exit;
	}

	/**
	 * Rapport quotidien du jour envoyé au marchand sur son WhatsApp.
	 *
	 * @return void
	 */
	public function send_daily_wa_report() {
		global $wpdb;
		$wa_owner = trim( (string) Settings::get( 'wa_owner_phone', '' ) );
		if ( '' === $wa_owner ) {
			return;
		}
		$orders = Schema::table( 'orders' );
		$today  = current_time( 'Y-m-d' ) . ' 00:00:00';
		$row    = $wpdb->get_row( $wpdb->prepare(
			"SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN status IN ('confirmed','shipped','delivered') THEN total ELSE 0 END),0) AS revenue FROM {$orders} WHERE created_at >= %s", // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB
			$today
		), ARRAY_A );
		$wa = infinitycod()->module( 'whatsapp' );
		if ( $wa && is_array( $row ) ) {
			$text = '📊 ' . get_bloginfo( 'name' ) . ' — aujourd\'hui : ' . (int) $row['n'] . ' commande(s), ' . number_format_i18n( (float) $row['revenue'], 0 ) . ' ' . Settings::currency_label() . '.';
			$wa->send( $wa_owner, $text );
		}
	}

	/**
	 * Alerte stock : produits sous le seuil → e-mail admin.
	 *
	 * @return void
	 */
	public function run_stock_alert_check() {
		$threshold = (int) Settings::get( 'stock_alert_threshold', 0 );
		if ( $threshold < 1 || ! function_exists( 'wc_get_products' ) ) {
			return;
		}
		$low = array();
		$products = wc_get_products( array( 'limit' => 500, 'status' => 'publish', 'type' => array( 'simple' ) ) );
		foreach ( (array) $products as $product ) {
			if ( ! $product->managing_stock() ) {
				continue;
			}
			$qty = (int) $product->get_stock_quantity();
			if ( $qty >= 0 && $qty <= $threshold ) {
				$low[] = '• ' . $product->get_name() . ' — ' . $qty;
			}
		}
		if ( $low ) {
			wp_mail( get_option( 'admin_email' ),
				sprintf( /* translators: 1 : site, 2 : nombre. */ esc_html__('[%1$s] Stock faible : %2$d produit(s)', 'infinitycod' ), get_bloginfo( 'name' ), count( $low ) ),
				implode( "\n", $low )
			);
		}
	}

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
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__('⚠️ Commandes en pause :', 'infinitycod' ) . '</strong> ' . esc_html__('le mode maintenance est actif — le formulaire COD affiche un avis aux visiteurs.', 'infinitycod' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=infinitycod-settings' ) ) . '">' . esc_html__('Désactiver', 'infinitycod' ) . '</a></p></div>';
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
				'icon'    => infinitycod()->asset_url( 'assets/icon-192.png' ),
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
			wp_die( esc_html__('Accès refusé.', 'infinitycod' ) );
		}

		check_admin_referer( 'icod_abandoned_export' );

		$trace = \InfinityCod\Core\AntiLeak::trace_code();
		\InfinityCod\Core\AntiLeak::log_export( 'abandoned_xls', 0, $trace );

		global $wpdb;
		$table = Schema::table( 'abandoned' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT 2000", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL

		$labels = array(
			'open'      => esc_html__('En attente', 'infinitycod' ),
			'recovered' => esc_html__('Récupéré', 'infinitycod' ),
			'archived'  => esc_html__('Archivé', 'infinitycod' ),
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
			. '<th>' . esc_html__('Client', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__('Téléphone', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__('Produit', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__('Progression', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__('Panier', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__('Relances', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__('Statut', 'infinitycod' ) . '</th>'
			. '<th>' . esc_html__('Mise à jour', 'infinitycod' ) . '</th>'
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
		$subject = sprintf( /* translators: 1 : nom du site, 2 : nombre de commandes. */ esc_html__('[%1$s] Semaine COD : %2$d commande(s)', 'infinitycod' ), get_bloginfo( 'name' ), (int) $row['n'] );

		$body  = '<div style="font-family:Arial,sans-serif;font-size:14px;color:#1d2327">';
		$body .= '<h2 style="color:#0e7a4f">' . esc_html__('Rapport COD des 7 derniers jours', 'infinitycod' ) . '</h2>';
		$body .= '<ul>';
		$body .= '<li>' . esc_html__('Commandes :', 'infinitycod' ) . ' <strong>' . (int) $row['n'] . '</strong></li>';
		$body .= '<li>' . esc_html__('Confirmées :', 'infinitycod' ) . ' <strong>' . (int) $row['confirmed'] . '</strong></li>';
		$body .= '<li>' . esc_html__('Chiffre d’affaires :', 'infinitycod' ) . ' <strong>' . esc_html( number_format_i18n( (float) $row['revenue'], 0 ) . ' ' . Settings::currency_label() ) . '</strong></li>';
		$body .= '<li>' . esc_html__('En attente de confirmation :', 'infinitycod' ) . ' <strong>' . (int) $pending . '</strong></li>';
		$body .= '</ul>';
		$body .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=infinitycod-orders' ) ) . '">' . esc_html__('Ouvrir le tableau de bord', 'infinitycod' ) . '</a></p>';
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
