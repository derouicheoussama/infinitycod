<?php
/**
 * Notifications marchand à chaque nouvelle commande : Discord + Telegram.
 *
 * Ces canaux sont OPTIONNELS : aucun envoi tant que l'URL webhook / le bot
 * ne sont pas configurés dans Réglages → Avancé. Un échec réseau est
 * silencieux côté client et journalisé (sans jamais écrire les secrets).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Orders;

use InfinityCod\Core\Settings;

defined( 'ABSPATH' ) || exit;

class AdminNotifications {

	/**
	 * Hook : nouvelle commande COD.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'infinitycod_order_created', array( $this, 'notify' ), 20, 3 );
	}

	/**
	 * Prépare et expédie les notifications configurées.
	 *
	 * @param \WC_Order $order   Commande WooCommerce.
	 * @param array     $data    Données validées du formulaire.
	 * @param int       $icod_id Ligne icod_orders.
	 * @return void
	 */
	public function notify( $order, $data, $icod_id ) {
		$discord = trim( (string) Settings::get( 'discord_webhook_url', '' ) );
		$bot     = trim( (string) Settings::get( 'telegram_bot_token', '' ) );
		$chat    = trim( (string) Settings::get( 'telegram_chat_id', '' ) );

		if ( '' === $discord && ( '' === $bot || '' === $chat ) ) {
			return;
		}

		$message = $this->message( $order, $data, $icod_id );

		if ( '' !== $discord ) {
			$this->post( $discord, wp_json_encode( array( 'content' => $message ) ), 'Discord' );
		}

		if ( '' !== $bot && '' !== $chat ) {
			$this->post(
				'https://api.telegram.org/bot' . rawurlencode( $bot ) . '/sendMessage',
				wp_json_encode(
					array(
						'chat_id'                  => $chat,
						'text'                     => $message,
						'disable_web_page_preview' => true,
					)
				),
				'Telegram'
			);
		}
	}

	/**
	 * Message résumé de la commande (sans donnée sensible au-delà de ce que
	 * le marchand possède déjà dans son dashboard).
	 *
	 * @param \WC_Order $order   Commande.
	 * @param array     $data    Données formulaire.
	 * @param int       $icod_id Ligne interne.
	 * @return string
	 */
	private function message( $order, $data, $icod_id ) {
		$product = isset( $data['product_id'] ) ? get_the_title( (int) $data['product_id'] ) : '';
		$lines   = array(
			sprintf( /* translators: %d : numéro de commande. */ __( '🛒 Nouvelle commande #%d', 'infinitycod' ), (int) $icod_id ),
			'👤 ' . ( isset( $data['name'] ) ? $data['name'] : '' ),
			'📞 ' . ( isset( $data['phone'] ) ? $data['phone'] : '' ),
			'📦 ' . $product . ( isset( $data['quantity'] ) ? ' ×' . (int) $data['quantity'] : '' ),
			'📍 ' . trim( ( isset( $data['wilaya_code'] ) ? $data['wilaya_code'] : '' ) . ' ' . ( isset( $data['commune'] ) ? $data['commune'] : '' ) ),
			'💰 ' . ( $order instanceof \WC_Order ? Settings::format_price( (float) $order->get_total() ) : '' ),
		);
		return implode( "\n", array_filter( $lines ) );
	}

	/**
	 * POST silencieux : échec journalisé, jamais bloquant pour la commande.
	 *
	 * @param string $url     Destination.
	 * @param string $body    JSON.
	 * @param string $channel Nom du canal (logs).
	 * @return void
	 */
	private function post( $url, $body, $channel ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 8,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			\InfinityCod\Logging\Logger::log( 'api', 'Notification ' . $channel . ' échec : ' . $response->get_error_message() );
			return;
		}
		\InfinityCod\Logging\Logger::log( 'api', 'Notification ' . $channel . ' envoyée : HTTP ' . (int) wp_remote_retrieve_response_code( $response ) );
	}
}
