<?php
/**
 * WhatsApp automatique : notifications de commande et relances d'abandons.
 *
 * Passerelles : WhatsApp Cloud API (Meta), UltraMsg, ou liens wa.me
 * (dans ce dernier cas les messages sont journalisés et prêts à ouvrir).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Whatsapp;

use InfinityCod\Core\Settings;
use InfinityCod\Form\Validator;
use InfinityCod\Orders\Abandoned;

defined( 'ABSPATH' ) || exit;

class WhatsappManager {

	/**
	 * Hooks : notifications + cron de relance des paniers abandonnés.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'infinitycod_order_created', array( $this, 'on_order_created' ), 10, 1 );
		add_action( 'infinitycod_parcel_created', array( $this, 'on_parcel_created' ), 10, 3 );
		add_action( 'infinitycod_recover_abandoned', array( $this, 'recover_abandoned' ) );

		// Intervalle personnalisé « toutes les 15 minutes ».
		add_filter( 'cron_schedules', array( $this, 'add_quarter_hour_schedule' ) );

		if ( ! wp_next_scheduled( 'infinitycod_recover_abandoned' ) ) {
			wp_schedule_event( time() + 15 * MINUTE_IN_SECONDS, 'icod_quarter_hour', 'infinitycod_recover_abandoned' );
		}
	}

	/**
	 * Déclare l'intervalle de 15 minutes pour WP-Cron.
	 *
	 * @param array $schedules Intervalles existants.
	 * @return array
	 */
	public function add_quarter_hour_schedule( $schedules ) {
		$schedules['icod_quarter_hour'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Toutes les 15 minutes (InfinityCod)', 'infinitycod' ),
		);
		return $schedules;
	}

	/**
	 * Message « commande reçue ».
	 *
	 * @param \WC_Order $order Commande WooCommerce.
	 * @return void
	 */
	public function on_order_created( $order ) {
		if ( ! Settings::get( 'whatsapp_enabled' ) ) {
			return;
		}

		$phone = $order->get_meta( '_icod_phone' );
		if ( ! $phone ) {
			$phone = $order->get_billing_phone();
		}

		$to = Validator::phone_to_international( $phone );
		if ( ! $to ) {
			return;
		}

		$this->send_template( 'msg_order_received', $to, array(
			'nom'       => $order->get_billing_first_name(),
			'telephone' => $phone,
			'commande'  => $order->get_id(),
			'total'     => number_format_i18n( (float) $order->get_total(), 2 ) . ' DA',
		) );
	}

	/**
	 * Message « commande expédiée ».
	 *
	 * @param array  $icod_order Ligne commande (ancienne valeur).
	 * @param string $carrier    Code transporteur.
	 * @param string $tracking   Numéro de suivi.
	 * @return void
	 */
	public function on_parcel_created( $icod_order, $carrier, $tracking ) {
		if ( ! Settings::get( 'whatsapp_enabled' ) ) {
			return;
		}

		$to = Validator::phone_to_international( $icod_order['phone'] );
		if ( ! $to ) {
			return;
		}

		$this->send_template( 'msg_order_shipped', $to, array(
			'nom'      => $icod_order['customer_name'],
			'commande' => $icod_order['wc_order_id'],
			'suivi'    => $tracking,
		) );
	}

	/**
	 * Relance automatique des paniers abandonnés (tâche planifiée).
	 *
	 * @return int Nombre de relances envoyées.
	 */
	public function recover_abandoned() {
		if ( ! Settings::get( 'whatsapp_enabled' ) || ! Settings::get( 'abandoned_enabled' ) ) {
			return 0;
		}

		$delay = max( 5, (int) Settings::get( 'abandoned_delay', 60 ) );
		$max   = max( 1, (int) Settings::get( 'abandoned_max', 2 ) );

		$due = Abandoned::due_for_reminder( $delay, $max, 25 );
		$sent = 0;

		foreach ( $due as $cart ) {
			$to = Validator::phone_to_international( $cart['phone'] );
			if ( ! $to ) {
				Abandoned::bump_reminder( (int) $cart['id'] ); // Numéro inutilisable : on consomme la tentative.
				continue;
			}

			$result = $this->send_template( 'msg_abandoned', $to, array(
				'nom'     => $cart['customer_name'],
				'produit' => $cart['product_id'] ? get_the_title( (int) $cart['product_id'] ) : __( 'votre article', 'infinitycod' ),
				'total'   => number_format_i18n( (float) $cart['cart_total'], 2 ) . ' DA',
			) );

			if ( ! is_wp_error( $result ) ) {
				$sent++;
			}
			Abandoned::bump_reminder( (int) $cart['id'] );
		}

		return $sent;
	}

	/**
	 * Rend un gabarit avec les variables fournies.
	 *
	 * @param string $template Texte avec espaces {variable}.
	 * @param array  $vars     Variables.
	 * @return string
	 */
	public static function render_template( $template, array $vars ) {
		$replacements = array();
		foreach ( $vars as $key => $value ) {
			$replacements[ '{' . $key . '}' ] = (string) $value;
		}
		return strtr( (string) $template, $replacements );
	}

	/**
	 * Envoie un gabarit rendu et journalise le résultat.
	 *
	 * @param string $setting_key Clé du réglage contenant le gabarit.
	 * @param string $to          Destinataire (format international sans +).
	 * @param array  $vars        Variables du gabarit.
	 * @return true|\WP_Error
	 */
	private function send_template( $setting_key, $to, array $vars ) {
		$message = self::render_template( Settings::get( $setting_key, '' ), $vars );
		return $this->send( $to, $message );
	}

	/**
	 * Envoie un message via la passerelle configurée.
	 *
	 * @param string $to      Destinataire (international sans +).
	 * @param string $message Texte.
	 * @return true|\WP_Error
	 */
	public function send( $to, $message ) {
		if ( ! \InfinityCod\License\LicenseManager::is_premium() ) {
			return new \WP_Error( 'icod_license', __( 'WhatsApp automatique nécessite une licence Premium InfinityCod.', 'infinitycod' ) );
		}

		$gateway = Settings::get( 'whatsapp_gateway', 'wame' );

		if ( 'cloud' === $gateway ) {
			$result = $this->send_cloud( $to, $message );
		} elseif ( 'ultramsg' === $gateway ) {
			$result = $this->send_ultramsg( $to, $message );
		} else {
			// Mode wa.me : aucun envoi automatique possible, on journalise le lien.
			$result = true;
		}

		$this->log( $gateway, $to, $message, is_wp_error( $result ) ? $result->get_error_message() : '' );

		return $result;
	}

	/**
	 * WhatsApp Cloud API (officiel Meta).
	 *
	 * @param string $to      Destinataire.
	 * @param string $message Texte.
	 * @return true|\WP_Error
	 */
	private function send_cloud( $to, $message ) {
		$token    = Settings::get( 'whatsapp_cloud_token' );
		$phone_id = Settings::get( 'whatsapp_phone_id' );

		if ( ! $token || ! $phone_id ) {
			return new \WP_Error( 'icod_wa', __( 'WhatsApp Cloud API incomplet (token ou phone ID manquant).', 'infinitycod' ) );
		}

		$response = wp_remote_post(
			'https://graph.facebook.com/v18.0/' . rawurlencode( $phone_id ) . '/messages',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'messaging_product' => 'whatsapp',
					'to'                => $to,
					'type'              => 'text',
					'text'              => array( 'body' => $message ),
				) ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			$detail = isset( $body['error']['message'] ) ? $body['error']['message'] : '';
			return new \WP_Error( 'icod_wa_cloud', sprintf( 'Cloud API HTTP %d%s', $code, $detail ? ' — ' . $detail : '' ) );
		}

		return true;
	}

	/**
	 * UltraMsg.
	 *
	 * @param string $to      Destinataire.
	 * @param string $message Texte.
	 * @return true|\WP_Error
	 */
	/** TextMeBot : GET api.textmebot.com/send.php?recipient=&apikey=&text= */
	private function send_textmebot( $to, $message ) {
		$key = Settings::get( 'wa_textmebot_key' );
		if ( '' === $key ) { return array( 'ok' => false, 'error' => 'no_key' ); }
		$phone = preg_replace( '/\D/', '', (string) $to );
		$url = add_query_arg( array( 'recipient' => '+' . $phone, 'apikey' => $key, 'text' => $message ), 'https://api.textmebot.com/send.php' );
		$res = wp_remote_get( $url, array( 'timeout' => 20 ) );
		if ( is_wp_error( $res ) ) { return array( 'ok' => false, 'error' => 'http' ); }
		$ok = wp_remote_retrieve_response_code( $res ) === 200;
		return array( 'ok' => $ok );
	}
	private function send_ultramsg( $to, $message ) {
		$instance = Settings::get( 'whatsapp_ultramsg_instance' );
		$key      = Settings::get( 'whatsapp_ultramsg_key' );

		if ( ! $instance || ! $key ) {
			return new \WP_Error( 'icod_wa', __( 'UltraMsg incomplet (instance ou clé manquante).', 'infinitycod' ) );
		}

		$response = wp_remote_post(
			'https://api.ultramsg.com/' . rawurlencode( $instance ) . '/messages/chat',
			array(
				'timeout' => 20,
				'body'    => array(
					'token' => $key,
					'to'    => '+' . $to,
					'body'  => $message,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || ( isset( $body['error'] ) && $body['error'] ) ) {
			$detail = isset( $body['error'] ) && is_string( $body['error'] ) ? $body['error'] : '';
			return new \WP_Error( 'icod_wa_ultramsg', sprintf( 'UltraMsg HTTP %d%s', $code, $detail ? ' — ' . $detail : '' ) );
		}

		return true;
	}

	/**
	 * Lien wa.me prêt à ouvrir (mode manuel / bouton du dashboard).
	 *
	 * @param string $to      Destinataire (international sans +).
	 * @param string $message Texte pré-rempli.
	 * @return string
	 */
	public static function wame_url( $to, $message = '' ) {
		return 'https://wa.me/' . preg_replace( '/\D/', '', $to ) . ( $message ? '?text=' . rawurlencode( $message ) : '' );
	}

	/**
	 * Journalise un envoi (100 derniers).
	 *
	 * @param string $gateway Passerelle.
	 * @param string $to      Destinataire.
	 * @param string $message Message.
	 * @param string $error   Erreur éventuelle.
	 * @return void
	 */
	private function log( $gateway, $to, $message, $error = '' ) {
		$log = get_option( 'infinitycod_wa_log', array() );
		$log = is_array( $log ) ? $log : array();

		array_unshift( $log, array(
			'at'      => current_time( 'mysql' ),
			'gateway' => $gateway,
			'to'      => $to,
			'message' => mb_substr( $message, 0, 300 ),
			'error'   => $error,
		) );

		update_option( 'infinitycod_wa_log', array_slice( $log, 0, 100 ), false );
	}

	/**
	 * Envoie le message WhatsApp de confirmation au client.
	 *
	 * @param int    $icod_id Ligne COD.
	 * @param string $status  Nouveau statut.
	 * @param array  $row     Ligne icod_orders.
	 */
	public function on_status_changed( $icod_id, $status, $row = array() ) {
		if ( 'confirmed' !== $status || ! Settings::get( 'whatsapp_enabled' ) || ! Settings::get( 'wa_on_confirm' ) ) { return; }
		if ( ! Settings::get( 'whatsapp_number' ) ) { return; }
		$phone = isset( $row['phone'] ) ? (string) $row['phone'] : '';
		if ( '' === $phone ) { return; }
		$message = $this->render_template(
			(string) Settings::get( 'msg_order_received' ),
			array(
				'nom'       => isset( $row['customer_name'] ) ? $row['customer_name'] : '',
				'telephone' => $phone,
				'commande'  => (int) $icod_id,
				'total'     => isset( $row['total'] ) ? number_format_i18n( (float) $row['total'], 0 ) . ' DA' : '',
			)
		);
		$this->send( $phone, $message );

		// Résumé par email à la confirmation (option distincte).
		if ( Settings::get( 'email_on_confirm' ) && isset( $row['email'] ) && is_email( $row['email'] ) ) {
			$subject = sprintf( /* translators: %1$d : numéro, %2$s : site. */ __( 'Commande #%1$d confirmée — %2$s', 'infinitycod' ), (int) $icod_id, get_bloginfo( 'name' ) );
			$body = '<p>' . sprintf( esc_html__( 'Bonjour %1$s, votre commande #%2$d est confirmée.', 'infinitycod' ), esc_html( isset( $row['customer_name'] ) ? $row['customer_name'] : '' ), (int) $icod_id ) . '</p>'
				. '<p><strong>' . esc_html__( 'Total : ', 'infinitycod' ) . wp_strip_all_tags( wc_price( (float) $row['total'] ) ) . '</strong></p>'
				. '<p style="color:#777;font-size:12px">' . esc_html__( 'Merci pour votre confiance.', 'infinitycod' ) . '</p>';
			wp_mail( $row['email'], $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
		}
	}
}
