<?php
/**
 * Contrat commun à tous les connecteurs transporteurs.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Carriers;

defined( 'ABSPATH' ) || exit;

interface CarrierInterface {

	/**
	 * Teste la validité des identifiants.
	 *
	 * @return array{ok: bool, message: string}
	 */
	public function test();

	/**
	 * Crée un colis chez le transporteur.
	 *
	 * @param array $shipment Données d'expédition :
	 *   order_ref, customer_name, customer_phone, address, wilaya, commune,
	 *   product_name, declared_value, qty, weight, note, delivery_type ('home'|'desk'), stopdesk.
	 * @return array{ok: bool, tracking: string, message: string}
	 */
	public function create_parcel( array $shipment );

	/**
	 * Récupère le suivi d'un colis.
	 *
	 * @param string $tracking Numéro de suivi.
	 * @return array{status: string, label: string, events: array[]}
	 *   status normalisé : nouveau|ramasse|en_cours|livre|retour|echoue|annule.
	 */
	public function fetch_status( $tracking );
}
