<?php
/**
 * Connecteur réseau Ecotrack (Noest Express, E-COM Delivery, DHD, instances
 * personnalisées — plusieurs dizaines de sociétés algériennes partagent
 * cette plateforme).
 *
 * @package InfinityCod
 */

namespace InfinityCod\Carriers;

defined( 'ABSPATH' ) || exit;

class Ecotrack extends AbstractCarrier {

	/**
	 * Constructeur.
	 *
	 * @param array $config api_token, user_guid (optionnel), base_url requis.
	 */
	public function __construct( array $config = array() ) {
		parent::__construct( $config );
	}

	/**
	 * Teste la connexion (tarifs = jeton validé).
	 *
	 * @return array{ok: bool, message: string}
	 */
	public function test() {
		$data = $this->http( $this->base() . '/api/v1/get/fees?api_token=' . rawurlencode( (string) $this->config['api_token'] ) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'message' => $data->get_error_message() );
		}
		return array( 'ok' => true, 'message' => __( 'Connexion réussie — jeton Ecotrack accepté', 'infinitycod' ) );
	}

	/**
	 * Crée un colis.
	 *
	 * @param array $s Données d'expédition.
	 * @return array{ok: bool, tracking: string, message: string}
	 */
	public function create_parcel( array $s ) {
		$payload = array(
			'api_token'      => (string) $this->config['api_token'],
			'user_guid'      => (string) ( isset( $this->config['user_guid'] ) ? $this->config['user_guid'] : '' ),
			'reference'      => isset( $s['order_ref'] ) ? $s['order_ref'] : '',
			'nom_client'     => isset( $s['customer_name'] ) ? $s['customer_name'] : '',
			'telephone'      => preg_replace( '/[^\d+]/', '', (string) $s['customer_phone'] ),
			'adresse'        => isset( $s['address'] ) ? $s['address'] : '',
			'code_wilaya'    => isset( $s['wilaya_code'] ) ? $s['wilaya_code'] : '',
			'commune'        => isset( $s['commune'] ) ? $s['commune'] : '',
			'montant'        => (string) max( 0, (float) $s['declared_value'] ),
			'remarque'       => isset( $s['note'] ) ? $s['note'] : '',
			'produit'        => isset( $s['product_name'] ) ? $s['product_name'] : __( 'Marchandise', 'infinitycod' ),
			'type_id'        => ( isset( $s['delivery_type'] ) && 'desk' === $s['delivery_type'] ) ? '2' : '1',
			'poids'          => (string) ( isset( $s['weight'] ) ? (float) $s['weight'] : 1 ),
			'nombre_articles' => (string) ( isset( $s['qty'] ) ? (int) $s['qty'] : 1 ),
		);

		$data = $this->http( $this->base() . '/api/v1/create/order', array( 'method' => 'POST', 'body_array' => $payload ) );

		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'tracking' => '', 'message' => $data->get_error_message() );
		}

		$tracking = '';
		foreach ( array( 'tracking', 'Tracking' ) as $key ) {
			if ( ! empty( $data[ $key ] ) ) { $tracking = (string) $data[ $key ]; break; }
		}
		if ( '' === $tracking && ! empty( $data['data']['tracking'] ) ) {
			$tracking = (string) $data['data']['tracking'];
		}

		if ( '' === $tracking ) {
			return array( 'ok' => false, 'tracking' => '', 'message' => __( 'Le transporteur n‘a pas renvoyé de numéro de suivi : ', 'infinitycod' ) . mb_substr( wp_json_encode( $data ), 0, 200 ) );
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
		$data = $this->http( $this->base() . '/api/v1/get/tracking/' . rawurlencode( $tracking ) . '?api_token=' . rawurlencode( (string) $this->config['api_token'] ) );

		$events = array();
		if ( ! is_wp_error( $data ) ) {
			$rows = array();
			if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
				$rows = array_values( $data['data'] );
			} elseif ( is_array( $data ) ) {
				$rows = array_values( $data );
			}

			foreach ( $rows as $history ) {
				$label = isset( $history['status'] ) ? (string) $history['status'] : ( isset( $history['statut'] ) ? (string) $history['statut'] : '' );
				$events[] = array(
					'status' => self::normalize_status( $label ),
					'label'  => $label,
					'date'   => isset( $history['date'] ) ? (string) $history['date'] : ( isset( $history['created_at'] ) ? (string) $history['created_at'] : '' ),
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
