<?php
/**
 * Base commune des connecteurs : HTTP, normalisation des statuts.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Carriers;

defined( 'ABSPATH' ) || exit;

abstract class AbstractCarrier implements CarrierInterface {

	/**
	 * Configuration du transporteur (clés API, base_url…).
	 *
	 * @var array
	 */
	protected $config = array();

	/**
	 * Constructeur.
	 *
	 * @param array $config Identifiants et réglages.
	 */
	public function __construct( array $config = array() ) {
		$this->config = wp_parse_args( $config, array( 'base_url' => '' ) );
	}

	/**
	 * Url de base sans slash final.
	 *
	 * @return string
	 */
	protected function base() {
		return untrailingslashit( (string) $this->config['base_url'] );
	}

	/**
	 * Requête HTTP JSON avec timeout et messages d'erreur lisibles.
	 *
	 * @param string               $url     Url complète.
	 * @param array                $args    Arguments wp_remote_request + 'body_array'.
	 * @return array|\WP_Error Réponse décodée.
	 */
	protected function http( $url, array $args = array() ) {
		$defaults = array(
			'method'  => 'GET',
			'timeout' => 20,
			'headers' => array(),
		);
		$args = wp_parse_args( $args, $defaults );

		if ( isset( $args['body_array'] ) ) {
			$args['body'] = wp_json_encode( $args['body_array'] );
			unset( $args['body_array'] );
			$args['headers']['Content-Type'] = 'application/json';
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			if ( false !== strpos( $message, 'timed out' ) ) {
				return new \WP_Error( 'icod_timeout', __( 'Délai dépassé — le serveur du transporteur ne répond pas.', 'infinitycod' ) );
			}
			return new \WP_Error( 'icod_http', $message );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( null === $data ) {
			$data = array( 'raw' => $body );
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = '';
			if ( is_array( $data ) ) {
				foreach ( array( 'message', 'error', 'message_error', 'raw' ) as $key ) {
					if ( ! empty( $data[ $key ] ) ) {
						$message = is_string( $data[ $key ] ) ? $data[ $key ] : wp_json_encode( $data[ $key ] );
						break;
					}
				}
			}
			return new \WP_Error(
				'icod_http_' . $code,
				sprintf( 'HTTP %d%s', $code, $message ? ' — ' . mb_substr( $message, 0, 180 ) : '' )
			);
		}

		return $data;
	}

	/**
	 * Normalise un libellé de statut (français libre) vers un statut interne.
	 *
	 * Ordre important : « en cours de livraison » contient « livr » —
	 * les cas d'échec/retour/annulation sont testés avant « livré ».
	 *
	 * @param string $raw_label Libellé brut du transporteur.
	 * @return string
	 */
	public static function normalize_status( $raw_label ) {
		$s = strtolower( (string) $raw_label );
		$s = remove_accents( $s );

		if ( preg_match( '/non livre|pas livre/', $s ) ) { return 'echoue'; }
		if ( preg_match( '/retour/', $s ) ) { return 'retour'; }
		if ( preg_match( '/annul|refus|supprim/', $s ) ) { return 'annule'; }
		if ( preg_match( '/echou|echec|introuvable|ferme|injoignable|absent|reporte|replanif/', $s ) ) { return 'echoue'; }
		if ( preg_match( '/livre/', $s ) ) { return 'livre'; }
		if ( preg_match( '/ramass|prise en charge|recuper|enleve|collecte/', $s ) ) { return 'ramasse'; }
		if ( preg_match( '/en cours|expedie|transit|achemin|en route|sortie|en livraison/', $s ) ) { return 'en_cours'; }
		if ( preg_match( '/nouveau|attente|cree|demande|en preparation|prepare/', $s ) ) { return 'nouveau'; }

		return 'en_cours';
	}

	/**
	 * Statut transporteur normalisé → statut COD interne.
	 *
	 * @param string $normalized Statut normalisé.
	 * @return string|null Statut COD ou null (pas de changement).
	 */
	public static function to_cod_status( $normalized ) {
		$map = array(
			'livre'   => 'delivered',
			'retour'  => 'returned',
			'echoue'  => 'failed',
			'annule'  => 'cancelled',
			'ramasse' => 'shipped',
			'en_cours' => 'shipped',
		);
		return isset( $map[ $normalized ] ) ? $map[ $normalized ] : null;
	}
}
