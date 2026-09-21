<?php
/**
 * Connecteur Maystro Delivery.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Carriers;

defined( 'ABSPATH' ) || exit;

class Maystro extends AbstractCarrier {

	/**
	 * Constructeur.
	 *
	 * @param array $config api_key.
	 */
	public function __construct( array $config = array() ) {
		parent::__construct( wp_parse_args( $config, array( 'base_url' => 'https://backend.maystro-delivery.com/api' ) ) );
	}

	/**
	 * En-têtes d'authentification.
	 *
	 * @return array
	 */
	private function headers() {
		return array( 'api-key' => (string) $this->config['api_key'] );
	}

	/**
	 * Teste la connexion.
	 *
	 * @return array{ok: bool, message: string}
	 */
	public function test() {
		$data = $this->http( $this->base() . '/wilayas', array( 'headers' => $this->headers() ) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'message' => $data->get_error_message() );
		}
		return array( 'ok' => true, 'message' => __( 'Connexion réussie — clé Maystro acceptée', 'infinitycod' ) );
	}

	/**
	 * Fonctionnalités supportées par l'API Maystro.
	 *
	 * @return string[]
	 */
	public function features() {
		return array( 'update', 'delete', 'label', 'wilayas', 'info', 'note' );
	}

	/**
	 * Modifie un colis avant expédition (PUT /orders/{id}).
	 *
	 * @param string $tracking Référence du colis.
	 * @param array  $s        Champs à modifier.
	 * @return array{ok: bool, message: string}
	 */
	public function update_parcel( $tracking, array $s ) {
		$payload = array();
		if ( isset( $s['customer_name'] ) ) { $payload['customer_name'] = $s['customer_name']; }
		if ( isset( $s['customer_phone'] ) ) { $payload['customer_phone'] = $s['customer_phone']; }
		if ( isset( $s['address'] ) ) { $payload['address'] = $s['address']; }
		if ( isset( $s['commune'] ) ) { $payload['commune'] = $s['commune']; }
		if ( isset( $s['wilaya'] ) ) { $payload['wilaya'] = $s['wilaya']; }
		if ( isset( $s['product_name'] ) ) { $payload['product'] = $s['product_name']; }
		if ( isset( $s['declared_value'] ) ) { $payload['price'] = max( 0, (float) $s['declared_value'] ); }
		if ( isset( $s['note'] ) ) { $payload['note'] = $s['note']; }
		if ( isset( $s['delivery_type'] ) ) { $payload['delivery_type'] = ( 'desk' === $s['delivery_type'] ) ? 'desk' : 'home'; }

		$data = $this->http( $this->base() . '/orders/' . rawurlencode( (string) $tracking ), array(
			'method'     => 'PUT',
			'headers'    => $this->headers(),
			'body_array' => $payload,
		) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'message' => $data->get_error_message() );
		}
		return array( 'ok' => true, 'message' => __( 'Colis modifié chez Maystro.', 'infinitycod' ) );
	}

	/**
	 * Supprime un colis avant expédition (DELETE /orders/{id}).
	 *
	 * @param string $tracking Référence du colis.
	 * @return array{ok: bool, message: string}
	 */
	public function delete_parcel( $tracking ) {
		$data = $this->http( $this->base() . '/orders/' . rawurlencode( (string) $tracking ), array(
			'method'  => 'DELETE',
			'headers' => $this->headers(),
		) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'message' => $data->get_error_message() );
		}
		return array( 'ok' => true, 'message' => __( 'Colis supprimé chez Maystro.', 'infinitycod' ) );
	}

	/**
	 * Étiquette du colis (PDF récupéré côté serveur).
	 *
	 * @param string $tracking Référence du colis.
	 * @return array{ok: bool, url: string, pdf: string, message: string}
	 */
	public function get_label( $tracking ) {
		$response = wp_remote_get( $this->base() . '/orders/' . rawurlencode( (string) $tracking ) . '/label', array(
			'timeout' => 30,
			'headers' => $this->headers(),
		) );
		if ( is_wp_error( $response ) ) {
			return array( 'ok' => false, 'url' => '', 'pdf' => '', 'message' => $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( 200 !== $code ) {
			return array( 'ok' => false, 'url' => '', 'pdf' => '', 'message' => 'HTTP ' . $code );
		}
		if ( 0 === strpos( $body, '%PDF' ) ) {
			return array( 'ok' => true, 'url' => '', 'pdf' => base64_encode( $body ), 'message' => '' );
		}
		return array( 'ok' => false, 'url' => '', 'pdf' => '', 'message' => __( 'Étiquette non renvoyée par Maystro.', 'infinitycod' ) );
	}

	/**
	 * Fiche complète du colis (GET /orders/{id}).
	 *
	 * @param string $tracking Référence du colis.
	 * @return array{ok: bool, data: array, message: string}
	 */
	public function get_parcel_info( $tracking ) {
		$data = $this->http( $this->base() . '/orders/' . rawurlencode( (string) $tracking ), array( 'headers' => $this->headers() ) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'data' => array(), 'message' => $data->get_error_message() );
		}
		return array( 'ok' => true, 'data' => $data, 'message' => '' );
	}

	/**
	 * Wilayas actives chez Maystro.
	 *
	 * @return array{ok: bool, wilayas: array[], message: string}
	 */
	public function get_wilayas() {
		$data = $this->http( $this->base() . '/wilayas', array( 'headers' => $this->headers() ) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'wilayas' => array(), 'message' => $data->get_error_message() );
		}
		$rows = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : ( is_array( $data ) ? $data : array() );
		$out  = array();
		foreach ( $rows as $w ) {
			$out[] = array(
				'code' => isset( $w['code'] ) ? (string) $w['code'] : '',
				'name' => isset( $w['name'] ) ? (string) $w['name'] : ( is_string( $w ) ? $w : '' ),
			);
		}
		return array( 'ok' => true, 'wilayas' => $out, 'message' => '' );
	}

	/**
	 * Remarque de colis (via l'édition Maystro).
	 *
	 * @param string $tracking Référence du colis.
	 * @param string $note     Texte.
	 * @return array{ok: bool, message: string}
	 */
	public function add_note( $tracking, $note ) {
		return $this->update_parcel( $tracking, array( 'note' => $note ) );
	}

	/**
	 * Crée un colis.
	 *
	 * @param array $s Données d'expédition.
	 * @return array{ok: bool, tracking: string, message: string}
	 */
	public function create_parcel( array $s ) {
		$payload = array(
			'reference'      => isset( $s['order_ref'] ) ? $s['order_ref'] : '',
			'customer_name'  => isset( $s['customer_name'] ) ? $s['customer_name'] : '',
			'customer_phone' => isset( $s['customer_phone'] ) ? $s['customer_phone'] : '',
			'address'        => isset( $s['address'] ) ? $s['address'] : '',
			'wilaya'         => isset( $s['wilaya'] ) ? $s['wilaya'] : '',
			'commune'        => isset( $s['commune'] ) ? $s['commune'] : '',
			'product'        => isset( $s['product_name'] ) ? $s['product_name'] : __( 'Marchandise', 'infinitycod' ),
			'price'          => max( 0, (float) $s['declared_value'] ),
			'delivery_type'  => ( isset( $s['delivery_type'] ) && 'desk' === $s['delivery_type'] ) ? 'desk' : 'home',
			'note'           => isset( $s['note'] ) ? $s['note'] : '',
		);

		$data = $this->http( $this->base() . '/orders', array(
			'method'     => 'POST',
			'headers'    => $this->headers(),
			'body_array' => $payload,
		) );

		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'tracking' => '', 'message' => $data->get_error_message() );
		}

		$tracking = '';
		foreach ( array( 'reference', 'tracking', 'id' ) as $key ) {
			if ( ! empty( $data[ $key ] ) ) { $tracking = (string) $data[ $key ]; break; }
		}
		if ( '' === $tracking && ! empty( $data['data']['reference'] ) ) {
			$tracking = (string) $data['data']['reference'];
		}

		if ( '' === $tracking ) {
			return array( 'ok' => false, 'tracking' => '', 'message' => __( 'Maystro n‘a pas renvoyé de référence : ', 'infinitycod' ) . mb_substr( wp_json_encode( $data ), 0, 200 ) );
		}

		return array( 'ok' => true, 'tracking' => $tracking, 'message' => '' );
	}

	/**
	 * Suivi du colis.
	 *
	 * @param string $tracking Numéro de suivi.
	 * @return array{status: string, label: string, events: array[]}
	 */
	public function fetch_status( $tracking ) {
		$data = $this->http( $this->base() . '/orders/' . rawurlencode( $tracking ), array( 'headers' => $this->headers() ) );

		$events = array();
		$label  = '';

		if ( ! is_wp_error( $data ) ) {
			$label = isset( $data['status'] ) ? (string) $data['status'] : ( isset( $data['data']['status'] ) ? (string) $data['data']['status'] : '' );
			if ( '' !== $label ) {
				$events[] = array(
					'status' => self::normalize_status( $label ),
					'label'  => $label,
					'date'   => isset( $data['updated_at'] ) ? (string) $data['updated_at'] : '',
				);
			}
		}

		$last = $events ? end( $events ) : array( 'status' => '', 'label' => '', 'date' => '' );
		return array(
			'status' => $last['status'],
			'label'  => $last['label'],
			'events' => $events,
		);
	}
}
