<?php
/**
 * Création et gestion des commandes COD.
 *
 * Toute la tarification est recalculée côté serveur : le client ne peut
 * jamais influer sur prix produit, remise ni frais de livraison.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Orders;

use InfinityCod\Core\Schema;
use InfinityCod\Core\Settings;
use InfinityCod\Form\OffersEngine;
use InfinityCod\Form\Validator;
use InfinityCod\Shipping\RatesManager;

defined( 'ABSPATH' ) || exit;

class OrderStore {

	/**
	 * Statuts du flux COD et leurs libellés.
	 *
	 * @var array<string, string>
	 */
	const STATUSES = array(
		'pending'    => 'En attente de confirmation',
		'confirmed'  => 'Confirmée',
		'no_answer'  => 'Sans réponse',
		'shipped'    => 'Expédiée',
		'delivered'  => 'Livrée',
		'returned'   => 'Retournée',
		'failed'     => 'Échouée',
		'cancelled'  => 'Annulée',
	);

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {}

	/**
	 * Crée la commande WooCommerce + la ligne COD depuis une soumission validée.
	 *
	 * @param array $data Données vérifiées : product_id, variation_id, quantity,
	 *                    name, phone (normalisé), wilaya_code, commune, mode,
	 *                    stopdesk, fraud_score, fraud_flags, ip, fingerprint.
	 * @return array{ok: bool, error: string, order_id: int, icod_id: int, total: float}
	 */
	public function create_from_form( array $data ) {
		$product_id  = isset( $data['product_id'] ) ? absint( $data['product_id'] ) : 0;
		$variation_id = isset( $data['variation_id'] ) ? absint( $data['variation_id'] ) : 0;
		$quantity    = max( 1, isset( $data['quantity'] ) ? absint( $data['quantity'] ) : 1 );
		$quantity    = min( $quantity, 99 );

		// 1. Produit achetable.
		$product = $variation_id ? wc_get_product( $variation_id ) : wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return array( 'ok' => false, 'error' => 'product_unavailable', 'order_id' => 0, 'icod_id' => 0, 'total' => 0 );
		}
		if ( ! $product->has_enough_stock( $quantity ) ) {
			return array( 'ok' => false, 'error' => 'insufficient_stock', 'order_id' => 0, 'icod_id' => 0, 'total' => 0 );
		}

		// 2. Tarifs serveur.
		$unit_price = (float) $product->get_price();
		$discount   = OffersEngine::discount( $product->get_id(), $unit_price, $quantity );
		$rates      = infinitycod()->module( 'rates' );
		$geo        = infinitycod()->module( 'geo' );

		$wilaya_code = isset( $data['wilaya_code'] ) ? sanitize_text_field( $data['wilaya_code'] ) : '';
		$commune     = isset( $data['commune'] ) ? sanitize_text_field( $data['commune'] ) : '';
		$mode        = ( isset( $data['mode'] ) && RatesManager::MODE_DESK === $data['mode'] ) ? RatesManager::MODE_DESK : RatesManager::MODE_HOME;

		$wilaya = $geo ? $geo->wilaya( $wilaya_code ) : null;
		if ( ! $wilaya ) {
			return array( 'ok' => false, 'error' => 'invalid_wilaya', 'order_id' => 0, 'icod_id' => 0, 'total' => 0 );
		}

		$shipping_price = $rates ? $rates->price( $wilaya_code, $commune, $mode ) : -1;
		if ( $shipping_price < 0 ) {
			return array( 'ok' => false, 'error' => 'no_shipping_rate', 'order_id' => 0, 'icod_id' => 0, 'total' => 0 );
		}

		$subtotal = round( $unit_price * $quantity, 2 );

		// Livraison gratuite (quantité OU montant) + supplément poids.
		if ( $rates && $rates->is_free( $wilaya_code, $quantity, $subtotal ) ) {
			$shipping_price = 0;
		} else {
			$weight         = \InfinityCod\Shipping\RatesManager::order_weight( $product->get_id(), $quantity );
			$shipping_price = $shipping_price + \InfinityCod\Shipping\RatesManager::weight_fee( $weight );
		}

		$discount_amount = (float) $discount['amount'];

		// Code promo : revalidé côté serveur (jamais confiance au client).
		$coupon_code   = isset( $data['coupon'] ) ? sanitize_text_field( (string) $data['coupon'] ) : '';
		$coupon        = $coupon_code ? Coupon::evaluate( $coupon_code, round( $subtotal - $discount_amount, 2 ), $quantity ) : array( 'valid' => false, 'amount' => 0.0, 'code' => '', 'label' => '' );
		$coupon_amount = $coupon['valid'] ? (float) $coupon['amount'] : 0.0;

		$total = max( 0, $subtotal - $discount_amount - $coupon_amount + $shipping_price );

		// 3. Commande WooCommerce.
		$order = wc_create_order( array( 'created_by' => 'infinitycod' ) );
		if ( is_wp_error( $order ) || ! $order ) {
			return array( 'ok' => false, 'error' => 'wc_order_failed', 'order_id' => 0, 'icod_id' => 0, 'total' => 0 );
		}

		$name = isset( $data['name'] ) ? Validator::clean_name( $data['name'] ) : '';

		$item = new \WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( $quantity );
		$item->set_subtotal( $subtotal );
		$item->set_total( $subtotal );
		$order->add_item( $item );

		// Remise quantité en frais négatif (support natif des totaux WC).
		if ( $discount_amount > 0 ) {
			$fee = new \WC_Order_Item_Fee();
			/* translators: %s : pourcentage de remise. */
			$fee->set_name( sprintf( __( 'Offre quantité (-%s%%)', 'infinitycod' ), $discount['pct'] ) );
			$fee->set_amount( -$discount_amount );
			$fee->set_total( -$discount_amount );
			$order->add_item( $fee );
		}

		// Code promo : frais négatif + meta traçable.
		if ( $coupon_amount > 0 ) {
			$fee = new \WC_Order_Item_Fee();
			/* translators: %s : code promo saisi. */
			$fee->set_name( sprintf( __( 'Code promo %s', 'infinitycod' ), strtoupper( $coupon['code'] ) ) );
			$fee->set_amount( -$coupon_amount );
			$fee->set_total( -$coupon_amount );
			$order->add_item( $fee );
			$order->update_meta_data( '_icod_coupon', $coupon['code'] );
		}

		// Livraison.
		$ship = new \WC_Order_Item_Shipping();
		$ship->set_method_title(
			RatesManager::MODE_DESK === $mode
				? sprintf( /* translators: %s : nom de la wilaya. */ __( 'Retrait au bureau — %s', 'infinitycod' ), $wilaya['name_fr'] )
				: sprintf( /* translators: %s : nom de la wilaya. */ __( 'Livraison à domicile — %s', 'infinitycod' ), $wilaya['name_fr'] )
		);
		$ship->set_method_id( 'infinitycod' );
		$ship->set_total( $shipping_price );
		$order->add_item( $ship );

		// Adresse et contact.
		$address = array(
			'first_name' => $name,
			'phone'      => isset( $data['phone'] ) ? $data['phone'] : '',
			'email'      => isset( $data['email'] ) ? $data['email'] : '',
			'city'       => $commune,
			'state'      => $wilaya['name_fr'],
			'country'    => 'DZ',
		);
		$order->set_address( $address, 'billing' );
		$order->set_address( $address, 'shipping' );

		$order->set_payment_method( 'cod' );
		$order->set_payment_method_title( __( 'Paiement à la livraison', 'infinitycod' ) );
		$order->set_customer_note( isset( $data['note'] ) ? sanitize_textarea_field( $data['note'] ) : '' );

		// Meta InfinityCod (retrouvées par le dashboard et les transporteurs).
		$order->update_meta_data( '_icod_wilaya_code', $wilaya_code );
		$order->update_meta_data( '_icod_commune', $commune );
		$order->update_meta_data( '_icod_mode', $mode );
		$order->update_meta_data( '_icod_stopdesk', isset( $data['stopdesk'] ) ? sanitize_text_field( $data['stopdesk'] ) : '' );
		$order->update_meta_data( '_icod_phone', isset( $data['phone'] ) ? $data['phone'] : '' );
		$order->update_meta_data( '_icod_fraud_score', isset( $data['fraud_score'] ) ? (int) $data['fraud_score'] : 0 );

		$order->set_currency( Settings::currency() );
		$order->calculate_totals();
		$order->update_meta_data( '_icod_total', (float) $order->get_total() );
		$order->save();

		// Statut WC : en attente (paiement à la livraison).
		$order->update_status( 'pending', __( 'Commande COD reçue via InfinityCod.', 'infinitycod' ), true );

		// 4. Ligne COD interne.
		global $wpdb;
		$now = current_time( 'mysql' );

		// Champs personnalisés cf_* → note de la commande.
		$cf_text = '';
		foreach ( $data as $dk => $dv ) {
			if ( strpos( (string) $dk, 'cf_' ) === 0 && $dv !== '' && $dv !== null ) {
				$cf_text .= ucfirst( substr( (string) $dk, 3 ) ) . ' : ' . sanitize_text_field( (string) $dv ) . "\n";
			}
		}
		$note_full = trim( ( isset( $data['note'] ) ? sanitize_textarea_field( (string) $data['note'] ) : '' ) . ( $cf_text !== '' ? "\n" . $cf_text : '' ) );

		$inserted = $wpdb->insert(
			Schema::table( 'orders' ),
			array(
				'wc_order_id'   => $order->get_id(),
				'product_id'    => $product->get_id(),
				'variation_id'  => $variation_id,
				'quantity'      => $quantity,
				'customer_name' => $name,
				'phone'         => isset( $data['phone'] ) ? $data['phone'] : '',
				'email'         => isset( $data['email'] ) ? $data['email'] : '',
				'wilaya_code'   => $wilaya_code,
				'commune'       => $commune,
				'delivery_mode' => $mode,
				'stopdesk'      => isset( $data['stopdesk'] ) ? sanitize_text_field( $data['stopdesk'] ) : '',
				'payment'       => ( isset( $data['payment'] ) && 'online' === $data['payment'] ) ? 'online' : 'cod',
				'subtotal'      => $subtotal,
				'discount'      => $discount_amount,
				'coupon'        => $coupon['valid'] ? $coupon['code'] : '',
				'shipping'      => $shipping_price,
				'total'         => (float) $order->get_total(),
				'status'        => 'pending',
				'fraud_score'   => isset( $data['fraud_score'] ) ? (int) $data['fraud_score'] : 0,
				'fraud_flags'   => isset( $data['fraud_flags'] ) ? implode( ',', (array) $data['fraud_flags'] ) : '',
				'ip'            => isset( $data['ip'] ) ? $data['ip'] : '',
				'fingerprint'   => isset( $data['fingerprint'] ) ? substr( (string) $data['fingerprint'], 0, 64 ) : '',
				'note'          => $note_full,
				'created_at'    => $now,
			)
		);

		$icod_id = $inserted ? (int) $wpdb->insert_id : 0;

		// Stock : WooCommerce décrémente automatiquement lors des passages de statut.
		/**
		 * Après création d'une commande COD.
		 *
		 * @param \WC_Order    $order    Commande WooCommerce.
		 * @param array        $data     Données validées.
		 * @param int          $icod_id  Ligne dans icod_orders.
		 */
		do_action( 'infinitycod_order_created', $order, $data, $icod_id );

		return array(
			'ok'       => true,
			'error'    => '',
			'order_id' => $order->get_id(),
			'icod_id'  => $icod_id,
			'total'    => (float) $order->get_total(),
		);
	}

	/**
	 * Met à jour le statut COD interne + synchronise la commande WC.
	 *
	 * @param int    $icod_id Ligne icod_orders.
	 * @param string $status  Nouveau statut (clé de STATUSES).
	 * @return bool
	 */
	public function set_status( $icod_id, $status ) {
		if ( ! array_key_exists( $status, self::STATUSES ) ) {
			return false;
		}

		global $wpdb;
		$table = Schema::table( 'orders' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $icod_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL

		if ( ! $row ) {
			return false;
		}

		$fields = array( 'status' => $status );
		$now    = current_time( 'mysql' );
		switch ( $status ) {
			case 'confirmed':
				$fields['confirmed_at'] = $now;
				break;
			case 'shipped':
				$fields['shipped_at'] = $now;
				break;
			case 'delivered':
				$fields['delivered_at'] = $now;
				break;
		}

		$ok = false !== $wpdb->update( $table, $fields, array( 'id' => (int) $icod_id ) );

		// Synchronisation du statut WooCommerce.
		if ( $ok && $row['wc_order_id'] ) {
			$wc_status = $this->map_to_wc_status( $status );
			if ( $wc_status ) {
				$order = wc_get_order( (int) $row['wc_order_id'] );
				if ( $order && $wc_status !== $order->get_status() ) {
					$order->update_status( $wc_status, sprintf( /* translators: %s : statut COD InfinityCod. */ __( 'InfinityCod : %s', 'infinitycod' ), self::STATUSES[ $status ] ) );
				}
			}
		}

		/**
		 * Après changement de statut COD.
		 *
		 * @param int    $icod_id Ligne concernée.
		 * @param string $status  Nouveau statut.
		 * @param array  $row     Ligne avant modification.
		 */
		do_action( 'infinitycod_order_status_changed', $icod_id, $status, $row );

		return (bool) $ok;
	}

	/**
	 * Correspondance statut COD → statut WooCommerce.
	 *
	 * @param string $status Statut COD.
	 * @return string|null Statut WC ou null (pas de changement).
	 */
	private function map_to_wc_status( $status ) {
		$map = array(
			'pending'   => 'pending',
			'confirmed' => 'processing',
			'shipped'   => 'processing',
			'delivered' => 'completed',
			'returned'  => 'cancelled',
			'failed'    => 'cancelled',
			'cancelled' => 'cancelled',
			'no_answer' => 'on-hold',
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : null;
	}
}
