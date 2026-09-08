<?php
/**
 * Paiement en ligne — Chargily Pay v2 (CIB / Edahabia).
 *
 * API : https://pay.chargily.net/api/v2/ (live) | https://pay.chargily.net/test/api/v2/ (test)
 * Auth : Authorization: Bearer <clé secrète>
 * Checkout : POST checkouts {amount (DZD entier), currency:'dzd', success_url, …}
 * Statut   : GET  checkouts/{id}
 * Webhook  : en-tête « Signature » = hash_hmac('sha256', corps brut, clé secrète)
 *
 * @package InfinityCod
 */

namespace InfinityCod\Payment;

use InfinityCod\Core\Schema;
use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class PaymentManager {

	/**
	 * Hooks : page de retour après paiement.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'handle_return' ) );
	}

	/**
	 * Paiement en ligne actif et configuré ?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) Settings::get( 'payment_enabled' )
			&& '' !== trim( (string) Settings::get( 'chargily_secret' ) )
			&& 'chargily' === Settings::get( 'payment_mode', 'chargily' );
	}

	/**
	 * Url de base de l'API selon le mode.
	 *
	 * @return string
	 */
	private function base_url() {
		return 'live' === Settings::get( 'chargily_mode', 'test' )
			? 'https://pay.chargily.net/api/v2/'
			: 'https://pay.chargily.net/test/api/v2/';
	}

	/**
	 * En-têtes d'authentification.
	 *
	 * @return array
	 */
	private function headers() {
		return array(
			'Authorization' => 'Bearer ' . trim( (string) Settings::get( 'chargily_secret' ) ),
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Crée un checkout Chargily pour une commande COD créée.
	 *
	 * @param array $result Résultat OrderStore::create_from_form (ok, order_id, icod_id, total).
	 * @param array $input  Données du formulaire (produit, client…).
	 * @return array{ok: bool, redirect: string, message: string}
	 */
	public function create_checkout( array $result, array $input ) {
		if ( empty( $result['ok'] ) || ! self::enabled() ) {
			return array( 'ok' => false, 'redirect' => '', 'message' => __( 'Paiement en ligne indisponible.', 'infinitycod' ) );
		}

		$return_url = add_query_arg(
			array(
				'icod_checkout' => 'CKOUT',
				'icod_order'    => (int) $result['icod_id'],
			),
			home_url( '/' )
		);

		$product_name = ! empty( $input['product_id'] ) ? get_the_title( (int) $input['product_id'] ) : __( 'Commande', 'infinitycod' );

		$payload = array(
			'amount'           => (int) round( (float) $result['total'] ),
			'currency'         => 'dzd',
			'success_url'      => $return_url,
			'failure_url'      => $return_url,
			'webhook_endpoint' => rest_url( 'infinitycod/v1/chargily/webhook' ),
			'description'      => sprintf( '#%1$s — %2$s', $result['order_id'], wp_strip_all_tags( $product_name ) ),
			'metadata'         => array(
				'icod_id'  => (int) $result['icod_id'],
				'wc_order' => (int) $result['order_id'],
			),
		);

		$response = wp_remote_post(
			$this->base_url() . 'checkouts',
			array(
				'timeout' => 25,
				'headers' => $this->headers(),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'redirect' => '', 'message' => $response->get_error_message() );
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$status = (int) wp_remote_retrieve_response_code( $response );

		if ( $status < 200 || $status >= 300 || empty( $body['checkout_url'] ) || empty( $body['id'] ) ) {
			$detail = isset( $body['message'] ) ? (string) $body['message'] : '';
			return array( 'ok' => false, 'redirect' => '', 'message' => sprintf( 'Chargily HTTP %d %s', $status, $detail ) );
		}

		// Lie le checkout à la commande COD.
		global $wpdb;
		$wpdb->update(
			Schema::table( 'orders' ),
			array( 'checkout_id' => (string) $body['id'] ),
			array( 'id' => (int) $result['icod_id'] )
		);

		if ( ! empty( $result['order_id'] ) ) {
			$wc_order = wc_get_order( (int) $result['order_id'] );
			if ( $wc_order ) {
				$wc_order->update_meta_data( '_icod_checkout_id', (string) $body['id'] );
				$wc_order->save();
			}
		}

		return array(
			'ok'      => true,
			'redirect' => (string) $body['checkout_url'],
			'message' => '',
		);
	}

	/**
	 * Vérifie le statut d'un checkout auprès de l'API.
	 *
	 * @param string $checkout_id ID du checkout.
	 * @return string statut ('paid', 'pending', 'failed', '') ou '' si erreur.
	 */
	public function get_status( $checkout_id ) {
		$response = wp_remote_get(
			$this->base_url() . 'checkouts/' . rawurlencode( (string) $checkout_id ),
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . trim( (string) Settings::get( 'chargily_secret' ) ),
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return isset( $body['status'] ) ? (string) $body['status'] : '';
	}

	/**
	 * Page de retour du client après paiement (success_url / failure_url).
	 *
	 * Vérifie le statut réel auprès de l'API avant de marquer la commande.
	 *
	 * @return void
	 */
	public function handle_return() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- retour public, vérifié contre l'API Chargily.
		$checkout_id = isset( $_GET['icod_checkout'] ) ? sanitize_text_field( wp_unslash( $_GET['icod_checkout'] ) ) : '';
		$icod_id     = isset( $_GET['icod_order'] ) ? absint( $_GET['icod_order'] ) : 0;
		// phpcs:enable

		if ( '' === $checkout_id || $icod_id < 1 || ! self::enabled() ) {
			return;
		}

		$status = $this->get_status( $checkout_id );

		if ( 'paid' === $status ) {
			self::mark_paid( $icod_id, $checkout_id );
			$message = (string) Settings::get( 'payment_return_text' );
			$success = true;
		} else {
			$message = __( 'Le paiement n‘a pas été finalisé. Vous pouvez réessayer ou nous contacter pour confirmer votre commande.', 'infinitycod' );
			$success = false;
		}

		wp_load_translations_early();

		nocache_headers();
		$title = $success ? __( 'Paiement confirmé — InfinityCod', 'infinitycod' ) : __( 'Paiement non finalisé — InfinityCod', 'infinitycod' );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title><?php echo esc_html( $title ); ?></title>
	<style>
		body{margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#f6f8f8;display:flex;min-height:100vh;align-items:center;justify-content:center;color:#16191d}
		.icod-thanks{background:#fff;border:1px solid #dfe3e8;border-radius:16px;box-shadow:0 6px 24px rgba(16,24,40,.08);max-width:480px;margin:24px;padding:40px 32px;text-align:center}
		.icod-thanks .emoji{font-size:3rem;line-height:1}
		.icod-thanks h1{font-size:1.35rem;margin:14px 0 8px}
		.icod-thanks p{margin:0 0 22px;color:#5f6670;line-height:1.6}
		.icod-thanks a{display:inline-block;padding:12px 26px;border-radius:10px;background:#0e7a4f;color:#fff;text-decoration:none;font-weight:700}
	</style>
</head>
<body>
	<div class="icod-thanks">
		<div class="emoji"><?php echo $success ? '✅' : '⚠️'; ?></div>
		<h1><?php echo esc_html( $title ); ?></h1>
		<p><?php echo esc_html( $message ); ?></p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Retour à la boutique', 'infinitycod' ); ?></a>
	</div>
</body>
</html>
		<?php
		exit;
	}

	/**
	 * Marque une commande COD comme payée (flag + statuts + WooCommerce).
	 *
	 * @param int    $icod_id    Ligne commande.
	 * @param string $checkout_id ID checkout (optionnel, re-lié si fourni).
	 * @return bool
	 */
	public static function mark_paid( $icod_id, $checkout_id = '' ) {
		global $wpdb;

		$table = Schema::table( 'orders' );
		$order = Schema::get_order( (int) $icod_id );

		if ( ! $order ) {
			return false;
		}

		$updated = $wpdb->update(
			$table,
			array(
				'payment'     => 'chargily',
				'paid'        => 1,
				'paid_at'     => current_time( 'mysql' ),
				'checkout_id' => $checkout_id ? (string) $checkout_id : $order['checkout_id'],
			),
			array( 'id' => (int) $icod_id )
		);

		if ( false === $updated ) {
			return false;
		}

		// Flux COD : payée = confirmée.
		$orders = infinitycod()->module( 'orders' );
		if ( $orders && ! in_array( $order['status'], array( 'delivered', 'cancelled' ), true ) ) {
			$orders->set_status( (int) $icod_id, 'confirmed' );
		}

		// WooCommerce : paiement complété (→ processing).
		if ( $order['wc_order_id'] ) {
			$wc_order = wc_get_order( (int) $order['wc_order_id'] );
			if ( $wc_order && ! $wc_order->is_paid() ) {
				$wc_order->update_meta_data( '_icod_checkout_id', $checkout_id ? (string) $checkout_id : $order['checkout_id'] );
				$wc_order->payment_complete( 'chargily_' . ( $checkout_id ? $checkout_id : $order['checkout_id'] ) );
				$wc_order->add_order_note( __( 'Paiement CIB/Edahabia reçu via Chargily (InfinityCod).', 'infinitycod' ) );
				$wc_order->save();
			}
		}

		/**
		 * Après confirmation d'un paiement en ligne.
		 *
		 * @param int    $icod_id Ligne commande.
		 * @param string $checkout_id ID checkout Chargily.
		 */
		do_action( 'infinitycod_payment_paid', (int) $icod_id, (string) $checkout_id );

		return true;
	}

	/**
	 * Traite un webhook Chargily (appelé par Rest\Routes).
	 *
	 * @param string $raw_body  Corps brut de la requête.
	 * @param string $signature En-tête « Signature ».
	 * @return bool
	 */
	public function handle_webhook( $raw_body, $signature ) {
		$secret = trim( (string) Settings::get( 'chargily_secret' ) );

		if ( '' === $secret || '' === (string) $signature ) {
			return false;
		}

		$computed = hash_hmac( 'sha256', (string) $raw_body, $secret );

		if ( ! hash_equals( $computed, (string) $signature ) ) {
			return false;
		}

		$event = json_decode( (string) $raw_body, true );

		if ( ! is_array( $event ) || empty( $event['type'] ) || empty( $event['data']['id'] ) ) {
			return false;
		}

		// Paiement finalisé : checkout.paid (checkout.failed = rien à faire).
		if ( 'checkout.paid' !== $event['type'] ) {
			return true;
		}

		$checkout_id = (string) $event['data']['id'];
		$icod_id     = $this->find_order_by_checkout( $checkout_id );

		if ( $icod_id ) {
			return self::mark_paid( $icod_id, $checkout_id );
		}

		return false;
	}

	/**
	 * Trouve la ligne commande liée à un checkout.
	 *
	 * @param string $checkout_id ID checkout.
	 * @return int 0 si introuvable.
	 */
	private function find_order_by_checkout( $checkout_id ) {
		global $wpdb;

		$table = Schema::table( 'orders' );
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE checkout_id = %s LIMIT 1", (string) $checkout_id ) // phpcs:ignore WordPress.DB.PreparedSQL
		);
	}
}
