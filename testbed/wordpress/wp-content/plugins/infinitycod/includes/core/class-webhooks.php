<?php
/**
 * Webhooks sortants : notifie une URL externe (Google Sheets via Apps Script,
 * Zapier, Make, CRM…) à chaque commande et, en option, à chaque changement
 * de statut. Signature HMAC dans l'en-tête X-InfinityCod-Signature.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Core;

defined( 'ABSPATH' ) || exit;

class Webhooks {

	/**
	 * Hooks commande : création (+ statuts en option).
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'infinitycod_order_created', array( $this, 'on_order_created' ), 30, 3 );
		add_action( 'infinitycod_order_status_changed', array( $this, 'on_status_changed' ), 30, 3 );
	}

	/**
	 * Nouvelle commande → webhook.
	 *
	 * @param \WC_Order $order   Commande WooCommerce.
	 * @param array     $data    Données du formulaire.
	 * @param int       $icod_id Ligne interne.
	 * @return void
	 */
	public function on_order_created( $order, $data, $icod_id ) {
		$this->dispatch( 'order.created', $icod_id, array(
			'wc_order_id' => $order ? $order->get_id() : 0,
			'status'      => 'pending',
		) );
	}

	/**
	 * Changement de statut → webhook (si activé).
	 *
	 * @param int    $icod_id Ligne interne.
	 * @param string $status  Nouveau statut.
	 * @param array  $row     Ligne complète.
	 * @return void
	 */
	public function on_status_changed( $icod_id, $status, $row ) {
		if ( ! Settings::get( 'webhook_on_status' ) ) {
			return;
		}
		$this->dispatch( 'order.status_changed', (int) $icod_id, array(
			'status' => (string) $status,
		) );
	}

	/**
	 * Construit et envoie la charge utile.
	 *
	 * @param string $event  Type d'événement.
	 * @param int    $icod_id Ligne interne.
	 * @param array  $extra   Champs additionnels.
	 * @return void
	 */
	private function dispatch( $event, $icod_id, array $extra ) {
		$url = trim( (string) Settings::get( 'webhook_url', '' ) );
		if ( '' === $url || $icod_id < 1 ) {
			return;
		}

		global $wpdb;
		$table = Schema::table( 'orders' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $icod_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders, PluginCheck.Security.DirectDB
		if ( ! is_array( $row ) ) {
			return;
		}

		$body = array(
			'event'       => $event,
			'icod_id'     => $icod_id,
			'wc_order_id' => isset( $extra['wc_order_id'] ) ? (int) $extra['wc_order_id'] : (int) $row['wc_order_id'],
			'status'      => isset( $extra['status'] ) ? $extra['status'] : (string) $row['status'],
			'customer'    => array(
				'name'   => (string) $row['customer_name'],
				'phone'  => (string) $row['phone'],
				'wilaya' => (string) $row['wilaya_code'],
				'commune' => (string) $row['commune'],
			),
			'product_id'  => (int) $row['product_id'],
			'quantity'    => (int) $row['quantity'],
			'shipping'    => (float) $row['shipping'],
			'total'       => (float) $row['total'],
			'currency'    => Settings::currency(),
			'created_at'  => (string) $row['created_at'],
		);

		$json  = (string) wp_json_encode( $body );
		$secret = trim( (string) Settings::get( 'webhook_secret', '' ) );
		$args   = array(
			'timeout' => 8,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => $json,
		);
		if ( '' !== $secret ) {
			$args['headers']['X-InfinityCod-Signature'] = 'sha256=' . hash_hmac( 'sha256', $json, $secret );
		}

		wp_remote_post( $url, $args );
	}
}
