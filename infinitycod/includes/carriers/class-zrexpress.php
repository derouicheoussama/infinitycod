<?php
/**
 * Connecteur ZR Express.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Carriers;

defined( 'ABSPATH' ) || exit;

class Zrexpress extends AbstractCarrier {

	/**
	 * Constructeur.
	 *
	 * @param array $config api_key.
	 */
	public function __construct( array $config = array() ) {
		parent::__construct( wp_parse_args( $config, array( 'base_url' => 'https://api.zrexpress.dz/api/dev' ) ) );
	}

	/**
	 * Url avec clé API.
	 *
	 * @param string $path Chemin.
	 * @return string
	 */
	private function url( $path ) {
		return $this->base() . $path . ( false === strpos( $path, '?' ) ? '?' : '&' ) . 'api_key=' . rawurlencode( (string) $this->config['api_key'] );
	}

	/**
	 * Teste la connexion.
	 *
	 * @return array{ok: bool, message: string}
	 */
	public function test() {
		$data = $this->http( $this->url( '/wilayas' ) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'message' => $data->get_error_message() );
		}
		return array( 'ok' => true, 'message' => is_array( $data ) ? sprintf( /* translators: %d : nombre de wilayas. */ __( 'Connexion réussie — %d wilayas', 'infinitycod' ), count( $data ) ) : __( 'Connexion réussie — clé acceptée', 'infinitycod' ) );
	}

	/**
	 * Crée un colis.
	 *
	 * @param array $s Données d'expédition.
	 * @return array{ok: bool, tracking: string, message: string}
	 */
	public function create_parcel( array $s ) {
		$payload = array(
			'Tracking'       => isset( $s['order_ref'] ) ? $s['order_ref'] : '',
			'Consignee'      => isset( $s['customer_name'] ) ? $s['customer_name'] : '',
			'ConsigneePhone1' => isset( $s['customer_phone'] ) ? $s['customer_phone'] : '',
			'Address'        => isset( $s['address'] ) ? $s['address'] : '',
			'Commune'        => isset( $s['commune'] ) ? $s['commune'] : '',
			'Wilaya'         => isset( $s['wilaya'] ) ? $s['wilaya'] : '',
			'ProductName'    => isset( $s['product_name'] ) ? $s['product_name'] : __( 'Marchandise', 'infinitycod' ),
			'Amount'         => max( 0, (float) $s['declared_value'] ),
			'TypeDelivery'   => ( isset( $s['delivery_type'] ) && 'desk' === $s['delivery_type'] ) ? 'stopdesk' : 'domicile',
			'StopDeskName'   => isset( $s['stopdesk'] ) ? $s['stopdesk'] : '',
			'Comments'       => isset( $s['note'] ) ? $s['note'] : '',
			'Weight'         => isset( $s['weight'] ) ? (string) (float) $s['weight'] : '1',
		);

		$data = $this->http( $this->url( '/create-colis' ), array( 'method' => 'POST', 'body_array' => $payload ) );

		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'tracking' => '', 'message' => $data->get_error_message() );
		}

		$tracking = '';
		foreach ( array( 'Tracking', 'tracking' ) as $key ) {
			if ( ! empty( $data[ $key ] ) ) { $tracking = (string) $data[ $key ]; break; }
		}
		if ( '' === $tracking && ! empty( $data['data']['Tracking'] ) ) {
			$tracking = (string) $data['data']['Tracking'];
		}

		if ( '' === $tracking ) {
			return array( 'ok' => false, 'tracking' => '', 'message' => __( 'ZR Express n‘a pas renvoyé de numéro de suivi : ', 'infinitycod' ) . mb_substr( wp_json_encode( $data ), 0, 200 ) );
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
		$data = $this->http( $this->url( '/track-colis' ) . '&track=' . rawurlencode( $tracking ) );

		$events = array();
		if ( ! is_wp_error( $data ) ) {
			$rows = is_array( $data ) ? $data : ( isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array( $data ) );
			foreach ( $rows as $history ) {
				$label = '';
				foreach ( array( 'Status', 'status', 'Statut' ) as $key ) {
					if ( ! empty( $history[ $key ] ) ) { $label = (string) $history[ $key ]; break; }
				}
				$events[] = array(
					'status' => self::normalize_status( $label ),
					'label'  => $label,
					'date'   => isset( $history['Date'] ) ? (string) $history['Date'] : ( isset( $history['date'] ) ? (string) $history['date'] : '' ),
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
