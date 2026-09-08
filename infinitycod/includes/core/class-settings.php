<?php
/**
 * Réglages du plugin avec valeurs par défaut centralisées.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Accès aux réglages : Settings::get('cle') lit la valeur fusionnée
 * avec les défauts ; Settings::set('cle', valeur) sauvegarde.
 *
 * Tout est stocké dans l'option unique 'infinitycod_settings' (autoload).
 */
class Settings {

	const OPTION = 'infinitycod_settings';

	/**
	 * Valeurs par défaut de tous les réglages.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Défauts : structure figée, documentée, jamais de logique ici.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// Tarifs de livraison.
			'default_price_home'    => 600,
			'default_price_desk'    => 350,
			'free_shipping_qty'     => 0,    // 0 = désactivé.

			// Formulaire.
			'form_title'            => __( 'Commandez maintenant — paiement à la livraison', 'infinitycod' ),
			'form_subtitle'         => __( 'Remplissez le formulaire, nous vous appelons pour confirmer.', 'infinitycod' ),
			'button_text'           => __( 'Confirmer la commande', 'infinitycod' ),
			'show_qty_selector'     => 1,
			'qty_min'               => 1,
			'qty_max'               => 20,
			'form_theme'            => 'light',        // light | dark | auto.
			'form_preset'           => 'modern',       // modern | elegant | sunset | ocean | minimal.
			'accent_color'          => '#0e7a4f',
			'sticky_bar'            => 1,

			// Libellés personnalisables des champs.
			'label_name'            => __( 'Nom complet', 'infinitycod' ),
			'label_phone'           => __( 'Téléphone', 'infinitycod' ),
			'label_wilaya'          => __( 'Wilaya', 'infinitycod' ),
			'label_commune'         => __( 'Commune', 'infinitycod' ),
			'label_note'            => __( 'Note (facultatif)', 'infinitycod' ),

			// Affichage des champs / blocs du formulaire.
			'show_note'             => 0,
			'show_stopdesk'         => 1,
			'show_offers'           => 1,
			'show_reassurance'      => 1,

			// Anti-fraude.
			'shield_enabled'        => 1,
			'min_submit_seconds'    => 3,
			'max_per_ip_hour'       => 5,
			'min_fraud_score_block' => 60,

			// Téléphone.
			'phone_strict'          => 1,    // Uniquement opérateurs DZ valides.
			'block_duplicate_phone' => 1,    // Refuse si commande en attente même numéro.

			// WhatsApp.
			'whatsapp_enabled'      => 0,
			'whatsapp_gateway'      => 'wame',  // wame | cloud | ultramsg.
			'whatsapp_number'       => '',      // Numéro marchand au format international.
			'whatsapp_cloud_token'  => '',
			'whatsapp_phone_id'     => '',
			'whatsapp_ultramsg_key' => '',
			'whatsapp_ultramsg_instance' => '',
			'msg_order_received'    => __( 'Bonjour {nom} 👋 Merci pour votre commande #{commande} ! Nous vous rappellerons très vite au {telephone} pour la confirmer. Montant à la livraison : {total}.', 'infinitycod' ),
			'msg_order_shipped'     => __( 'Bonjour {nom}, votre commande #{commande} a été expédiée 📦. Suivi : {suivi}. Merci pour votre confiance !', 'infinitycod' ),
			'msg_abandoned'         => __( 'Bonjour {nom} 👋 Votre commande de {produit} est restée inachevée 🛒. Répondez OUI et nous vous rappelons tout de suite pour la finaliser !', 'infinitycod' ),
			'abandoned_enabled'     => 0,
			'abandoned_delay'       => 60,   // minutes avant 1ère relance.
			'abandoned_max'         => 2,    // nombre de relances.

			// Statuts couleur dashboard.
			'order_status_labels'   => array(),

			// Divers.
			'delete_on_uninstall'   => 0,
			'wizard_done'           => 0,

			// Mises à jour via GitHub.
			'github_repo'           => 'derouicheoussama/infinitycod', // proprietaire/depot.
			'github_token'          => '',   // Requis pour un dépôt privé (lecture seule).
		);
	}

	/**
	 * Tous les réglages (défauts fusionnés avec la sauvegarde).
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$saved          = get_option( self::OPTION, array() );
			$saved          = is_array( $saved ) ? $saved : array();
			self::$cache = wp_parse_args( $saved, self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Lit un réglage.
	 *
	 * @param string $key     Clé (notation point acceptée pour un sous-niveau).
	 * @param mixed  $fallback Valeur si absente.
	 * @return mixed
	 */
	public static function get( $key, $fallback = null ) {
		$all = self::all();

		if ( strpos( $key, '.' ) !== false ) {
			$segments = explode( '.', $key );
			$value    = $all;
			foreach ( $segments as $segment ) {
				if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
					return $fallback;
				}
				$value = $value[ $segment ];
			}
			return $value;
		}

		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Écrit un ou plusieurs réglages.
	 *
	 * @param string|array $key   Clé ou tableau clé => valeur.
	 * @param mixed        $value Valeur (ignoré si $key est un tableau).
	 * @return bool
	 */
	public static function set( $key, $value = null ) {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();

		if ( is_array( $key ) ) {
			$saved = array_merge( $saved, $key );
		} else {
			$saved[ $key ] = $value;
		}

		self::$cache = null;
		return update_option( self::OPTION, $saved, true );
	}
}
