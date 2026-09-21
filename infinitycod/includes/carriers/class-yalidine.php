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
	 * Fonctionnalités supportées par l'API Yalidine.
	 *
	 * @return string[]
	 */
	public function features() {
		return array( 'update', 'delete', 'label', 'wilayas', 'rates', 'info' );
	}

	/**
	 * Modifie un colis avant expédition (PUT /parcels/{tracking}).
	 *
	 * @param string $tracking Numéro de suivi.
	 * @param array  $s        Champs à modifier.
	 * @return array{ok: bool, message: string}
	 */
	public function update_parcel( $tracking, array $s ) {
		$payload = array();
		if ( isset( $s['customer_name'] ) ) {
			$parts            = preg_split( '/\s+/', trim( (string) $s['customer_name'] ), -1, PREG_SPLIT_NO_EMPTY );
			$payload['firstname']  = $parts ? $parts[0] : (string) $s['customer_name'];
			$payload['familyname'] = count( $parts ) > 1 ? implode( ' ', array_slice( $parts, 1 ) ) : '-';
		}
		if ( isset( $s['customer_phone'] ) ) { $payload['contact_phone'] = preg_replace( '/[^\d+]/', '', (string) $s['customer_phone'] ); }
		if ( isset( $s['address'] ) ) { $payload['address'] = $s['address']; }
		if ( isset( $s['commune'] ) ) { $payload['to_commune_name'] = $s['commune']; }
		if ( isset( $s['wilaya'] ) ) { $payload['to_wilaya_name'] = $s['wilaya']; }
		if ( isset( $s['product_name'] ) ) { $payload['product_list'] = $s['product_name']; }
		if ( isset( $s['declared_value'] ) ) { $payload['price'] = max( 0, (float) $s['declared_value'] ); }
		if ( isset( $s['delivery_type'] ) ) { $payload['is_stopdesk'] = ( 'desk' === $s['delivery_type'] ); }

		$data = $this->http( $this->base() . '/parcels/' . rawurlencode( (string) $tracking ), array(
			'method'     => 'PUT',
			'headers'    => $this->headers(),
			'body_array' => $payload,
		) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'message' => $data->get_error_message() );
		}
		return array( 'ok' => true, 'message' => __( 'Colis modifié chez Yalidine.', 'infinitycod' ) );
	}

	/**
	 * Supprime un colis avant expédition (DELETE /parcels/{tracking}).
	 *
	 * @param string $tracking Numéro de suivi.
	 * @return array{ok: bool, message: string}
	 */
	public function delete_parcel( $tracking ) {
		$data = $this->http( $this->base() . '/parcels/' . rawurlencode( (string) $tracking ), array(
			'method'  => 'DELETE',
			'headers' => $this->headers(),
		) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'message' => $data->get_error_message() );
		}
		return array( 'ok' => true, 'message' => __( 'Colis supprimé chez Yalidine.', 'infinitycod' ) );
	}

	/**
	 * Étiquette du colis (PDF récupéré côté serveur — auth par en-têtes).
	 *
	 * @param string $tracking Numéro de suivi.
	 * @return array{ok: bool, url: string, pdf: string, message: string}
	 */
	public function get_label( $tracking ) {
		$response = wp_remote_get( $this->base() . '/labels/?trackings=' . rawurlencode( (string) $tracking ), array(
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
		// Yalidine renvoie le PDF brut quand une seule référence est demandée.
		if ( 0 === strpos( $body, '%PDF' ) ) {
			return array( 'ok' => true, 'url' => '', 'pdf' => base64_encode( $body ), 'message' => '' );
		}
		// Sinon JSON contenant un lien de téléchargement.
		$json = json_decode( $body, true );
		$link = isset( $json['data']['label'][ $tracking ]['pdf_link'] ) ? (string) $json['data']['label'][ $tracking ]['pdf_link'] : '';
		if ( '' === $link && ! empty( $json['pdf_link'] ) ) {
			$link = (string) $json['pdf_link'];
		}
		if ( '' === $link ) {
			return array( 'ok' => false, 'url' => '', 'pdf' => '', 'message' => __( 'Étiquette introuvable dans la réponse Yalidine.', 'infinitycod' ) );
		}
		return array( 'ok' => true, 'url' => $link, 'pdf' => '', 'message' => '' );
	}

	/**
	 * Fiche complète du colis (GET /parcels/{tracking}).
	 *
	 * @param string $tracking Numéro de suivi.
	 * @return array{ok: bool, data: array, message: string}
	 */
	public function get_parcel_info( $tracking ) {
		$data = $this->http( $this->base() . '/parcels/' . rawurlencode( (string) $tracking ), array( 'headers' => $this->headers() ) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'data' => array(), 'message' => $data->get_error_message() );
		}
		return array( 'ok' => true, 'data' => $data, 'message' => '' );
	}

	/**
	 * Wilayas actives chez Yalidine.
	 *
	 * @return array{ok: bool, wilayas: array[], message: string}
	 */
	public function get_wilayas() {
		$data = $this->http( $this->base() . '/wilayas/', array( 'headers' => $this->headers() ) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'wilayas' => array(), 'message' => $data->get_error_message() );
		}
		$out = array();
		foreach ( (array) $data as $code => $w ) {
			$out[] = array(
				'code' => (string) $code,
				'name' => isset( $w['name'] ) ? (string) $w['name'] : '',
			);
		}
		return array( 'ok' => true, 'wilayas' => $out, 'message' => '' );
	}

	/**
	 * Tarifs de livraison Yalidine.
	 *
	 * @return array{ok: bool, rates: array[], message: string}
	 */
	public function get_rates() {
		$data = $this->http( $this->base() . '/fees/', array( 'headers' => $this->headers() ) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'rates' => array(), 'message' => $data->get_error_message() );
		}
		$rows = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : ( is_array( $data ) ? $data : array() );
		$out  = array();
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) { continue; }
			$out[] = array(
				'wilaya' => isset( $r['wilaya_name'] ) ? (string) $r['wilaya_name'] : ( isset( $r['wilaya'] ) ? (string) $r['wilaya'] : '' ),
				'home'   => isset( $r['return_fee'] ) ? (float) $r['return_fee'] : ( isset( $r['delivery_fee'] ) ? (float) $r['delivery_fee'] : 0 ),
				'desk'   => isset( $r['desks_fee'] ) ? (float) $r['desks_fee'] : 0,
			);
		}
		return array( 'ok' => true, 'rates' => $out, 'message' => '' );
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
