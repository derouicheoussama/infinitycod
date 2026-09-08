<?php
/**
 * Connecteur Yalidine Express (API v1).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Carriers;

defined( 'ABSPATH' ) || exit;

class Yalidine extends AbstractCarrier {

	/**
	 * Constructeur avec valeurs par défaut.
	 *
	 * @param array $config api_id, api_token.
	 */
	public function __construct( array $config = array() ) {
		parent::__construct( wp_parse_args( $config, array( 'base_url' => 'https://api.yalidine.app/v1' ) ) );
	}

	/**
	 * En-têtes d'authentification.
	 *
	 * @return array
	 */
	private function headers() {
		return array(
			'X-API-ID'    => (string) $this->config['api_id'],
			'X-API-TOKEN' => (string) $this->config['api_token'],
		);
	}

	/**
	 * Teste la connexion (liste des wilayas).
	 *
	 * @return array{ok: bool, message: string}
	 */
	public function test() {
		$data = $this->http( $this->base() . '/wilayas/', array( 'headers' => $this->headers() ) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'message' => $data->get_error_message() );
		}
		$count = is_array( $data ) ? count( $data ) : 58;
		return array( 'ok' => true, 'message' => sprintf( /* translators: %d : nombre de wilayas. */ __( 'Connexion réussie — %d wilayas récupérées', 'infinitycod' ), $count ) );
	}

	/**
	 * Crée un colis.
	 *
	 * @param array $s Données d'expédition.
	 * @return array{ok: bool, tracking: string, message: string}
	 */
	public function create_parcel( array $s ) {
		$name_parts = preg_split( '/\s+/', trim( (string) $s['customer_name'] ), -1, PREG_SPLIT_NO_EMPTY );
		$firstname  = $name_parts ? $name_parts[0] : (string) $s['customer_name'];
		$familyname = count( $name_parts ) > 1 ? implode( ' ', array_slice( $name_parts, 1 ) ) : '-';

		$payload = array(
			array(
				'order_id'       => isset( $s['order_ref'] ) ? $s['order_ref'] : '',
				'firstname'      => $firstname,
				'familyname'     => $familyname,
				'contact_phone'  => preg_replace( '/[^\d+]/', '', (string) $s['customer_phone'] ),
				'address'        => isset( $s['address'] ) ? $s['address'] : '',
				'to_commune_name' => isset( $s['commune'] ) ? $s['commune'] : '',
				'to_wilaya_name' => isset( $s['wilaya'] ) ? $s['wilaya'] : '',
				'product_list'   => isset( $s['product_name'] ) ? $s['product_name'] : __( 'Marchandise', 'infinitycod' ),
				'price'          => max( 0, (float) $s['declared_value'] ),
				'do_insurance'   => false,
				'declared_value' => max( 0, (float) $s['declared_value'] ),
				'freeshipping'   => isset( $s['freeshipping'] ) && $s['freeshipping'],
				'is_stopdesk'    => isset( $s['delivery_type'] ) && 'desk' === $s['delivery_type'],
				'stopdesk_id'    => ( isset( $s['delivery_type'] ) && 'desk' === $s['delivery_type'] ) ? -1 : null,
				'has_exchange'   => false,
			),
		);

		$data = $this->http( $this->base() . '/parcels/', array(
			'method'     => 'POST',
			'headers'    => $this->headers(),
			'body_array' => $payload,
		) );

		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'tracking' => '', 'message' => $data->get_error_message() );
		}

		$tracking = '';
		if ( isset( $data['trackings'][0] ) ) {
			$tracking = (string) $data['trackings'][0];
		} elseif ( isset( $data[0]['tracking'] ) ) {
			$tracking = (string) $data[0]['tracking'];
		} elseif ( ! empty( $data['success'] ) && ! empty( $data['tracking'] ) ) {
			$tracking = (string) $data['tracking'];
		}

		if ( '' === $tracking ) {
			return array( 'ok' => false, 'tracking' => '', 'message' => __( 'Yalidine n‘a pas renvoyé de numéro de suivi : ', 'infinitycod' ) . mb_substr( wp_json_encode( $data ), 0, 200 ) );
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
		$data = $this->http( $this->base() . '/histories/?tracking=' . rawurlencode( $tracking ), array( 'headers' => $this->headers() ) );

		$events = array();
		if ( ! is_wp_error( $data ) ) {
			$rows = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : ( is_array( $data ) ? $data : array() );
			foreach ( $rows as $history ) {
				$label    = isset( $history['status'] ) ? (string) $history['status'] : '';
				$events[] = array(
					'status' => self::normalize_status( $label ),
					'label'  => $label,
					'date'   => isset( $history['date'] ) ? (string) $history['date'] : '',
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

	/**
	 * Bureaux stopdesk de toutes les wilayas (pour l'import).
	 *
	 * @return array|\WP_Error Liste brute.
	 */
	public function fetch_offices() {
		return $this->http( $this->base() . '/offices/', array( 'headers' => $this->headers() ) );
	}
}
