<?php
/**
 * Connecteur réseau Ecotrack (Noest Express, E-COM Delivery, DHD, instances
 * personnalisées — plusieurs dizaines de sociétés algériennes partagent
 * cette plateforme).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
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
	 * Fonctionnalités supportées par la plateforme Ecotrack.
	 *
	 * @return string[]
	 */
	public function features() {
		return array( 'update', 'delete', 'label', 'wilayas', 'rates', 'info', 'note' );
	}

	/**
	 * Paramètres d'authentification en query string.
	 *
	 * @return string
	 */
	private function auth_qs() {
		return 'api_token=' . rawurlencode( (string) $this->config['api_token'] );
	}

	/**
	 * Modifie un colis avant expédition.
	 *
	 * @param string $tracking Numéro de suivi.
	 * @param array  $s        Champs à modifier.
	 * @return array{ok: bool, message: string}
	 */
	public function update_parcel( $tracking, array $s ) {
		$payload = array(
			'api_token' => (string) $this->config['api_token'],
		);
		if ( isset( $s['customer_name'] ) ) { $payload['nom_client'] = $s['customer_name']; }
		if ( isset( $s['customer_phone'] ) ) { $payload['telephone'] = preg_replace( '/[^\d+]/', '', (string) $s['customer_phone'] ); }
		if ( isset( $s['address'] ) ) { $payload['adresse'] = $s['address']; }
		if ( isset( $s['commune'] ) ) { $payload['commune'] = $s['commune']; }
		if ( isset( $s['declared_value'] ) ) { $payload['montant'] = (string) max( 0, (float) $s['declared_value'] ); }
		if ( isset( $s['note'] ) ) { $payload['remarque'] = $s['note']; }
		if ( isset( $s['weight'] ) ) { $payload['poids'] = (string) (float) $s['weight']; }
		if ( isset( $s['delivery_type'] ) ) { $payload['type_id'] = ( 'desk' === $s['delivery_type'] ) ? '2' : '1'; }

		$data = $this->http( $this->base() . '/api/v1/edit/order/' . rawurlencode( $tracking ), array( 'method' => 'POST', 'body_array' => $payload ) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'message' => $data->get_error_message() );
		}
		return array( 'ok' => true, 'message' => __( 'Colis modifié chez le transporteur.', 'infinitycod' ) );
	}

	/**
	 * Supprime un colis avant expédition.
	 *
	 * @param string $tracking Numéro de suivi.
	 * @return array{ok: bool, message: string}
	 */
	public function delete_parcel( $tracking ) {
		$data = $this->http( $this->base() . '/api/v1/delete/order', array(
			'method'     => 'POST',
			'body_array' => array(
				'api_token' => (string) $this->config['api_token'],
				'trackings' => array( (string) $tracking ),
			),
		) );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'message' => $data->get_error_message() );
		}
		return array( 'ok' => true, 'message' => __( 'Colis supprimé chez le transporteur.', 'infinitycod' ) );
	}

	/**
	 * Bordereau PDF du colis (url directe signée par le jeton).
	 *
	 * @param string $tracking Numéro de suivi.
	 * @return array{ok: bool, url: string, pdf: string, message: string}
	 */
	public function get_label( $tracking ) {
		$url = $this->base() . '/api/v1/create/dispatch?' . $this->auth_qs() . '&trackings=' . rawurlencode( (string) $tracking );
		return array( 'ok' => true, 'url' => $url, 'pdf' => '', 'message' => '' );
	}

	/**
	 * Fiche complète du colis.
	 *
	 * @param string $tracking Numéro de suivi.
	 * @return array{ok: bool, data: array, message: string}
	 */
	public function get_parcel_info( $tracking ) {
		$data = $this->http( $this->base() . '/api/v1/get/order/' . rawurlencode( $tracking ) . '?' . $this->auth_qs() );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'data' => array(), 'message' => $data->get_error_message() );
		}
		return array( 'ok' => true, 'data' => $data, 'message' => '' );
	}

	/**
	 * Wilayas actives chez le transporteur.
	 *
	 * @return array{ok: bool, wilayas: array[], message: string}
	 */
	public function get_wilayas() {
		$data = $this->http( $this->base() . '/api/v1/get/wilayas?' . $this->auth_qs() );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'wilayas' => array(), 'message' => $data->get_error_message() );
		}
		$rows = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : ( is_array( $data ) ? $data : array() );
		$out  = array();
		foreach ( $rows as $w ) {
			$out[] = array(
				'code' => isset( $w['code'] ) ? (string) $w['code'] : ( isset( $w['id'] ) ? (string) $w['id'] : '' ),
				'name' => isset( $w['name'] ) ? (string) $w['name'] : ( isset( $w['nom'] ) ? (string) $w['nom'] : '' ),
			);
		}
		return array( 'ok' => true, 'wilayas' => $out, 'message' => '' );
	}

	/**
	 * Tarifs de livraison.
	 *
	 * @return array{ok: bool, rates: array[], message: string}
	 */
	public function get_rates() {
		$data = $this->http( $this->base() . '/api/v1/get/fees?' . $this->auth_qs() );
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'rates' => array(), 'message' => $data->get_error_message() );
		}
		$rows = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : ( is_array( $data ) ? $data : array() );
		$out  = array();
		foreach ( $rows as $r ) {
			if ( ! is_array( $r ) ) { continue; }
			$out[] = array(
				'wilaya' => isset( $r['wilaya'] ) ? (string) $r['wilaya'] : ( isset( $r['nom'] ) ? (string) $r['nom'] : '' ),
				'home'   => isset( $r['price_home'] ) ? (float) $r['price_home'] : ( isset( $r['tarif_domicile'] ) ? (float) $r['tarif_domicile'] : 0 ),
				'desk'   => isset( $r['price_desk'] ) ? (float) $r['price_desk'] : ( isset( $r['tarif_stopdesk'] ) ? (float) $r['tarif_stopdesk'] : 0 ),
			);
		}
		return array( 'ok' => true, 'rates' => $out, 'message' => '' );
	}

	/**
	 * Ajoute une remarque au colis (via l'édition Ecotrack).
	 *
	 * @param string $tracking Numéro de suivi.
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
