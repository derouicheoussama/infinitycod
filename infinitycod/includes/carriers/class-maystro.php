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
