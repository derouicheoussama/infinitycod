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
	 * Fonctionnalités supportées par ce transporteur.
	 *
	 * @return string[] Sous-ensemble de : update, delete, label, wilayas,
	 *                  rates, info, return, ship, note.
	 */
	public function features();

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
	 * Modifie un colis avant expédition.
	 *
	 * @param string $tracking Numéro de suivi.
	 * @param array  $s        Champs à modifier (mêmes clés que create_parcel).
	 * @return array{ok: bool, message: string}
	 */
	public function update_parcel( $tracking, array $s );

	/**
	 * Supprime un colis avant expédition.
	 *
	 * @param string $tracking Numéro de suivi.
	 * @return array{ok: bool, message: string}
	 */
	public function delete_parcel( $tracking );

	/**
	 * Étiquette (bordereau) du colis.
	 *
	 * @param string $tracking Numéro de suivi.
	 * @return array{ok: bool, url: string, pdf: string, message: string}
	 *   url : lien ouvrable · pdf : octets bruts (si récupérés côté serveur).
	 */
	public function get_label( $tracking );

	/**
	 * Fiche complète du colis chez le transporteur.
	 *
	 * @param string $tracking Numéro de suivi.
	 * @return array{ok: bool, data: array, message: string}
	 */
	public function get_parcel_info( $tracking );

	/**
	 * Suivi du colis.
	 *
	 * @param string $tracking Numéro de suivi.
	 * @return array{status: string, label: string, events: array[]}
	 *   status normalisé : nouveau|ramasse|en_cours|livre|retour|echoue|annule.
	 */
	public function fetch_status( $tracking );

	/**
	 * Wilayas actives chez le transporteur.
	 *
	 * @return array{ok: bool, wilayas: array[], message: string}
	 */
	public function get_wilayas();

	/**
	 * Tarifs de livraison du transporteur.
	 *
	 * @return array{ok: bool, rates: array[], message: string}
	 */
	public function get_rates();

	/**
	 * Ajoute/modifie la remarque d'un colis.
	 *
	 * @param string $tracking Numéro de suivi.
	 * @param string $note     Texte de la remarque.
	 * @return array{ok: bool, message: string}
	 */
	public function add_note( $tracking, $note );

	/**
	 * Demande le retour d'un colis.
	 *
	 * @param string $tracking Numéro de suivi.
	 * @return array{ok: bool, message: string}
	 */
	public function request_return( $tracking );

	/**
	 * Marque le colis comme prêt / expédié (si l'API le permet).
	 *
	 * @param string $tracking Numéro de suivi.
	 * @return array{ok: bool, message: string}
	 */
	public function ship_parcel( $tracking );
}
