<?php
/**
 * Gestionnaire des transporteurs : catalogue, configuration, colis, tracking.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Carriers;

use InfinityCod\Core\Schema;

defined( 'ABSPATH' ) || exit;

class CarrierManager {

	const CONFIG_OPTION = 'infinitycod_carriers';

	/**
	 * Catalogue intégré : plateformes officielles + réseau Ecotrack.
	 *
	 * @return array[]
	 */
	public static function catalog() {
		return array(
			array( 'code' => 'yalidine', 'name' => 'Yalidine Express', 'adapter' => 'Yalidine', 'fields' => array( 'api_id', 'api_token' ), 'default_base' => 'https://api.yalidine.app/v1', 'offices' => true ),
			array( 'code' => 'zrexpress', 'name' => 'ZR Express', 'adapter' => 'Zrexpress', 'fields' => array( 'api_key' ), 'default_base' => 'https://api.zrexpress.dz/api/dev', 'offices' => false ),
			array( 'code' => 'maystro', 'name' => 'Maystro Delivery', 'adapter' => 'Maystro', 'fields' => array( 'api_key' ), 'default_base' => 'https://backend.maystro-delivery.com/api', 'offices' => false ),
			array( 'code' => 'noest', 'name' => 'Noest Express', 'adapter' => 'Ecotrack', 'fields' => array( 'api_token', 'user_guid' ), 'default_base' => 'https://noest-dz.com', 'offices' => false ),
			array( 'code' => 'ecom', 'name' => 'E-COM Delivery', 'adapter' => 'Ecotrack', 'fields' => array( 'api_token', 'user_guid' ), 'default_base' => 'https://ecom.ecotrack.dz', 'offices' => false ),
			array( 'code' => 'dhd', 'name' => 'DHD Livraison', 'adapter' => 'Ecotrack', 'fields' => array( 'api_token', 'user_guid' ), 'default_base' => 'https://dhd.ecotrack.dz', 'offices' => false ),
			array( 'code' => 'ecotrack-custom', 'name' => __( 'Autre société (réseau Ecotrack)', 'infinitycod' ), 'adapter' => 'Ecotrack', 'fields' => array( 'api_token', 'user_guid', 'base_url' ), 'default_base' => '', 'offices' => false ),
		);
	}

	/**
	 * Hooks : cron de synchronisation du suivi.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'infinitycod_sync_tracking', array( $this, 'sync_tracking' ) );

		if ( ! wp_next_scheduled( 'infinitycod_sync_tracking' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'infinitycod_sync_tracking' );
		}
	}

	/**
	 * Configuration complète (option unique).
	 *
	 * @return array
	 */
	public static function configs() {
		$saved = get_option( self::CONFIG_OPTION, array() );
		return is_array( $saved ) ? $saved : array();
	}

	/**
	 * Configuration d'un transporteur.
	 *
	 * @param string $code Code transporteur.
	 * @return array
	 */
	public static function config( $code ) {
		$configs = self::configs();
		return isset( $configs[ $code ] ) && is_array( $configs[ $code ] ) ? $configs[ $code ] : array();
	}

	/**
	 * Sauvegarde la configuration d'un transporteur.
	 *
	 * @param string $code   Code transporteur.
	 * @param array  $config Valeurs.
	 * @return bool
	 */
	public static function save_config( $code, array $config ) {
		$configs        = self::configs();
		$configs[ $code ] = $config;
		return update_option( self::CONFIG_OPTION, $configs, true );
	}

	/**
	 * Un transporteur est-il configuré (identifiants présents) ?
	 *
	 * @param string $code Code transporteur.
	 * @return bool
	 */
	public function is_configured( $code ) {
		$entry  = $this->catalog_entry( $code );
		$config = self::config( $code );

		if ( ! $entry ) {
			return false;
		}

		if ( empty( $config['enabled'] ) ) {
			return false;
		}

		foreach ( $entry['fields'] as $field ) {
			if ( 'base_url' === $field && ! empty( $entry['default_base'] ) ) {
				continue; // Base par défaut fournie.
			}
			if ( 'user_guid' === $field ) {
				continue; // Optionnel.
			}
			if ( empty( $config[ $field ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Entrée de catalogue par code.
	 *
	 * @param string $code Code transporteur.
	 * @return array|null
	 */
	public function catalog_entry( $code ) {
		foreach ( self::catalog() as $entry ) {
			if ( $code === $entry['code'] ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Instancie l'adaptateur d'un transporteur.
	 *
	 * @param string $code Code transporteur.
	 * @return AbstractCarrier|null
	 */
	public function factory( $code ) {
		if ( ! \InfinityCod\License\LicenseManager::is_premium() ) {
			return null; // Transporteurs intégrés : fonctionnalité Premium.
		}

		if ( ! $this->is_configured( $code ) ) {
			return null;
		}

		$entry  = $this->catalog_entry( $code );
		$config = self::config( $code );

		$config['base_url'] = ! empty( $config['base_url'] ) ? $config['base_url'] : $entry['default_base'];

		$class = __NAMESPACE__ . '\\' . $entry['adapter'];
		if ( ! class_exists( $class ) ) {
			return null;
		}

		return new $class( $config );
	}

	/**
	 * Crée un colis chez un transporteur depuis une commande COD.
	 *
	 * @param int    $icod_id Ligne commande.
	 * @param string $carrier Code transporteur.
	 * @return array{ok: bool, message: string, tracking: string}
	 */
	public function create_parcel_from_order( $icod_id, $carrier ) {
		global $wpdb;

		$order = Schema::get_order( $icod_id );
		if ( ! $order ) {
			return array( 'ok' => false, 'message' => __( 'Commande introuvable.', 'infinitycod' ), 'tracking' => '' );
		}

		if ( ! empty( $order['tracking'] ) ) {
			return array( 'ok' => false, 'message' => sprintf( /* translators: %s : numéro de suivi existant. */ __( 'Colis déjà créé (%s).', 'infinitycod' ), $order['tracking'] ), 'tracking' => $order['tracking'] );
		}

		$adapter = $this->factory( $carrier );
		if ( ! $adapter ) {
			return array( 'ok' => false, 'message' => __( 'Transporteur non configuré (vérifiez les clés API).', 'infinitycod' ), 'tracking' => '' );
		}

		// Nom de wilaya lisible.
		$wilayas_table = Schema::table( 'wilayas' );
		$wilaya_name   = $wpdb->get_var( $wpdb->prepare( "SELECT name_fr FROM {$wilayas_table} WHERE code = %s", $order['wilaya_code'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		$shipment = array(
			'order_ref'      => 'IC-' . $order['id'] . '-WC' . $order['wc_order_id'],
			'customer_name'  => $order['customer_name'],
			'customer_phone' => $order['phone'],
			'address'        => $order['commune'] . ', ' . ( $wilaya_name ? $wilaya_name : $order['wilaya_code'] ),
			'wilaya'         => $wilaya_name ? $wilaya_name : $order['wilaya_code'],
			'wilaya_code'    => $order['wilaya_code'],
			'commune'        => $order['commune'],
			'product_name'   => $order['product_id'] ? get_the_title( (int) $order['product_id'] ) : __( 'Marchandise', 'infinitycod' ),
			'declared_value' => (float) $order['total'],
			'qty'            => (int) $order['quantity'],
			'note'           => $order['stopdesk'] ? sprintf( /* translators: %s : bureau. */ __( 'Bureau : %s', 'infinitycod' ), $order['stopdesk'] ) : '',
			'delivery_type'  => $order['delivery_mode'],
			'stopdesk'       => $order['stopdesk'],
			'freeshipping'   => ( (float) $order['shipping'] ) <= 0,
		);

		$result = $adapter->create_parcel( $shipment );

		if ( ! empty( $result['ok'] ) ) {
			$wpdb->update(
				Schema::table( 'orders' ),
				array(
					'carrier'    => $carrier,
					'tracking'   => $result['tracking'],
					'status'     => 'shipped',
					'shipped_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $icod_id )
			);

			// Synchronise le statut WooCommerce.
			$orders = infinitycod()->module( 'orders' );
			if ( $orders && $order['wc_order_id'] ) {
				$wc_order = wc_get_order( (int) $order['wc_order_id'] );
				if ( $wc_order ) {
					$wc_order->update_status( 'processing', sprintf( /* translators: %1$s : transporteur, %2$s : suivi. */ __( 'Colis créé chez %1$s — suivi %2$s', 'infinitycod' ), $carrier, $result['tracking'] ) );
				}
			}

			/**
			 * Après création d'un colis transporteur.
			 *
			 * @param array $order    Ligne commande.
			 * @param string $carrier Code transporteur.
			 * @param string $tracking Numéro de suivi.
			 */
			do_action( 'infinitycod_parcel_created', $order, $carrier, $result['tracking'] );
		}

		return $result;
	}

	/**
	 * Synchronise le suivi de tous les colis actifs (tâche horaire).
	 *
	 * @return array{checked: int, updated: int}
	 */
	public function sync_tracking() {
		global $wpdb;

		// Synchronisation automatique : ACTIVÉE PAR DÉFAUT, désactivable
		// (Wilayas & Tarifs → « Synchronisation automatique »).
		if ( '0' === (string) Settings::get( 'carrier_autosync', '1' ) ) {
			return array( 'checked' => 0, 'updated' => 0 );
		}

		$table = Schema::table( 'orders' );

		$rows = $wpdb->get_results(
			"SELECT * FROM {$table}
			 WHERE tracking <> '' AND carrier <> ''
			   AND status IN ('confirmed','shipped')
			 LIMIT 200", // phpcs:ignore WordPress.DB.PreparedSQL
			ARRAY_A
		);

		$checked = 0;
		$updated = 0;
		$orders  = infinitycod()->module( 'orders' );

		foreach ( (array) $rows as $row ) {
			$checked++;
			$adapter = $this->factory( $row['carrier'] );
			if ( ! $adapter ) {
				continue;
			}

			$result = $adapter->fetch_status( $row['tracking'] );

			if ( empty( $result['status'] ) || $result['status'] === $row['carrier_status'] ) {
				continue;
			}

			$wpdb->update(
				$table,
				array( 'carrier_status' => $result['label'] ),
				array( 'id' => (int) $row['id'] )
			);
			$updated++;

			// Statut final détecté → mise à jour du flux COD.
			$cod_status = AbstractCarrier::to_cod_status( $result['status'] );
			if ( $cod_status && $orders ) {
				$orders->set_status( (int) $row['id'], $cod_status );
			}
		}

		return array( 'checked' => $checked, 'updated' => $updated );
	}

	/**
	 * Importe les bureaux stopdesk Yalidine et met à jour les communes.
	 *
	 * @return array{ok: bool, message: string}
	 */
	public function import_yalidine_offices() {
		$config = self::config( 'yalidine' );
		$yalidine = new Yalidine( $config );

		$data = $yalidine->fetch_offices();
		if ( is_wp_error( $data ) ) {
			return array( 'ok' => false, 'message' => $data->get_error_message() );
		}

		$rows = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : ( is_array( $data ) ? $data : array() );
		if ( ! $rows ) {
			return array( 'ok' => false, 'message' => __( 'Yalidine n‘a renvoyé aucun bureau.', 'infinitycod' ) );
		}

		$offices = array();
		foreach ( $rows as $office ) {
			$wilaya_raw = isset( $office['wilaya_id'] ) ? $office['wilaya_id'] : ( isset( $office['code_wilaya'] ) ? $office['code_wilaya'] : '' );
			$wilaya_code = str_pad( (string) $wilaya_raw, 2, '0', STR_PAD_LEFT );
			if ( ! preg_match( '/^\d{2}$/', $wilaya_code ) ) {
				continue;
			}

			$offices[] = array(
				'wilaya_code' => $wilaya_code,
				'commune'     => isset( $office['commune'] ) ? (string) $office['commune'] : '',
				'name'        => isset( $office['desk_name'] ) ? (string) $office['desk_name'] : ( isset( $office['name'] ) ? (string) $office['name'] : '' ),
				'address'     => isset( $office['desk_address'] ) ? (string) $office['desk_address'] : '',
				'external_id' => isset( $office['desk_id'] ) ? (string) $office['desk_id'] : '',
			);
		}

		if ( ! $offices ) {
			return array( 'ok' => false, 'message' => __( 'Format de réponse bureaux non reconnu.', 'infinitycod' ) );
		}

		$geo     = infinitycod()->module( 'geo' );
		$saved   = $geo ? $geo->replace_stopdesks( 'yalidine', $offices ) : 0;
		$flagged = $geo ? $geo->sync_has_desk_flags() : 0;

		return array(
			'ok' => true,
			/* translators: 1 : bureaux importés, 2 : communes mises à jour. */
			'message' => sprintf( __( '%1$d bureaux importés, %2$d communes mises à jour.', 'infinitycod' ), $saved, $flagged ),
		);
	}
}
