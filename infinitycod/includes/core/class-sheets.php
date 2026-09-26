<?php
/**
 * Google Sheets — synchronisation native des commandes (API Google v4).
 *
 * Sans Apps Script ni service tiers : un compte de service Google (JSON)
 * écrit directement dans le tableur du marchand.
 *  - nouvelle commande → nouvelle ligne (structure de colonnes configurable) ;
 *  - changement de statut → mise à jour EN PLACE de la ligne (jamais de doublon) ;
 *  - file d'attente persistante avec réessais exponentiels (Google indisponible,
 *    quota) — aucune commande perdue, l'ajout de commande n'est jamais bloqué ;
 *  - création automatique de l'onglet + des en-têtes, export complet possible.
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Core;

use InfinityCod\Orders\OrderStore;

defined( 'ABSPATH' ) || exit;

class Sheets {

	const OPT_ROWS    = 'icod_sheets_rows';    // {icod_id: {row, cols}}
	const OPT_QUEUE   = 'icod_sheets_queue';   // file persistante
	const OPT_LOG     = 'icod_sheets_log';     // 30 derniers événements
	const TOKEN_TTL   = 3000;                  // jeton Google (1 h réel, marge)
	const MAX_ATTEMPT = 6;

	/**
	 * Hooks : commandes, AJAX, cron.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'infinitycod_order_created', array( $this, 'on_order_created' ), 30, 3 );
		add_action( 'infinitycod_order_status_changed', array( $this, 'on_status_changed' ), 30, 3 );

		add_action( 'wp_ajax_icod_sheets_test', array( $this, 'handle_test' ) );
		add_action( 'wp_ajax_icod_sheets_init', array( $this, 'handle_init' ) );
		add_action( 'wp_ajax_icod_sheets_backfill', array( $this, 'handle_backfill' ) );
		add_action( 'wp_ajax_icod_sheets_flush', array( $this, 'handle_flush' ) );

		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- intervalle métier 5 min.
		add_action( 'infinitycod_sheets_flush', array( $this, 'cron_flush' ) );
		add_action( 'admin_init', array( $this, 'maybe_schedule' ) );
	}

	/**
	 * Intervalle cron de 5 minutes (nom dédié, sans collision).
	 *
	 * @param array $schedules Planifications existantes.
	 * @return array
	 */
	public function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['icod_5min'] ) ) {
			$schedules['icod_5min'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display'  => __( 'Toutes les 5 minutes (InfinityCod)', 'infinitycod' ),
			);
		}
		return $schedules;
	}

	/**
	 * Planifie la vidange de la file si la synchro est active.
	 *
	 * @return void
	 */
	public function maybe_schedule() {
		if ( ! Settings::get( 'sheets_enabled', 0 ) ) {
			$ts = wp_next_scheduled( 'infinitycod_sheets_flush' );
			if ( $ts ) {
				wp_unschedule_event( $ts, 'infinitycod_sheets_flush' );
			}
			return;
		}
		if ( ! wp_next_scheduled( 'infinitycod_sheets_flush' ) ) {
			wp_schedule_event( time() + 2 * MINUTE_IN_SECONDS, 'icod_5min', 'infinitycod_sheets_flush' );
		}
	}

	/* ---------------- Catalogue de colonnes ---------------- */

	/**
	 * Colonnes proposées, dans l'ordre du tableur.
	 *
	 * @return array<string,string> clé => en-tête.
	 */
	public static function columns() {
		return array(
			'wc_id'    => __( 'Commande Woo', 'infinitycod' ),
			'date'     => __( 'Date', 'infinitycod' ),
			'status'   => __( 'Statut', 'infinitycod' ),
			'name'     => __( 'Client', 'infinitycod' ),
			'phone'    => __( 'Téléphone', 'infinitycod' ),
			'wilaya'   => __( 'Wilaya', 'infinitycod' ),
			'commune'  => __( 'Commune', 'infinitycod' ),
			'delivery' => __( 'Livraison', 'infinitycod' ),
			'product'  => __( 'Produit', 'infinitycod' ),
			'qty'      => __( 'Qté', 'infinitycod' ),
			'total'    => __( 'Total', 'infinitycod' ),
			'carrier'  => __( 'Transporteur', 'infinitycod' ),
			'tracking' => __( 'N° suivi', 'infinitycod' ),
			'fraud'    => __( 'Risque', 'infinitycod' ),
			'note'     => __( 'Note', 'infinitycod' ),
		);
	}

	/**
	 * Colonnes activées (réglage), validées contre le catalogue.
	 *
	 * @return string[]
	 */
	public static function enabled_columns() {
		$catalog = self::columns();
		$saved   = Settings::get( 'sheets_columns', '' );
		$keys    = is_string( $saved ) ? json_decode( (string) $saved, true ) : $saved;
		if ( ! is_array( $keys ) || empty( $keys ) ) {
			return array_keys( $catalog ); // Tout par défaut.
		}
		$out = array();
		foreach ( array_keys( $catalog ) as $key ) { // Ordre du catalogue, jamais celui du POST.
			if ( in_array( $key, array_map( 'strval', $keys ), true ) ) {
				$out[] = $key;
			}
		}
		return $out ? $out : array_keys( $catalog );
	}

	/**
	 * Libellé humain d'un statut COD.
	 *
	 * @param string $status Statut interne.
	 * @return string
	 */
	private static function status_label( $status ) {
		return isset( OrderStore::STATUSES[ $status ] ) ? OrderStore::STATUSES[ $status ] : (string) $status;
	}

	/**
	 * Nom de wilaya lisible (repli : code brut).
	 *
	 * @param string $code Code wilaya.
	 * @return string
	 */
	private static function wilaya_label( $code ) {
		if ( '' === (string) $code ) {
			return '';
		}
		if ( class_exists( '\\InfinityCod\\Geo\\GeoManager' ) ) {
			$geo = new \InfinityCod\Geo\GeoManager();
			$w   = $geo->wilaya( (string) $code );
			if ( is_array( $w ) && ! empty( $w['name_fr'] ) ) {
				return (string) $w['name_fr'];
			}
		}
		return (string) $code;
	}

	/**
	 * Nom du produit (variante incluse), mis en cache par requête.
	 *
	 * @param int $product_id  Produit.
	 * @param int $variation_id Variante.
	 * @return string
	 */
	private static function product_name( $product_id, $variation_id ) {
		static $cache = array();
		$key = (int) $product_id . ':' . (int) $variation_id;
		if ( isset( $cache[ $key ] ) ) {
			return $cache[ $key ];
		}
		$name = '';
		if ( $variation_id && function_exists( 'wc_get_product' ) ) {
			$variation = wc_get_product( (int) $variation_id );
			if ( $variation ) {
				$name = $variation->get_name();
			}
		}
		if ( '' === $name ) {
			$name = (string) get_the_title( (int) $product_id );
		}
		$cache[ $key ] = $name;
		return $name;
	}

	/**
	 * Valeurs d'une ligne, dans l'ordre des colonnes activées.
	 *
	 * @param array $row Ligne icod_orders (ARRAY_A).
	 * @return array<int,mixed>
	 */
	public static function build_row( array $row ) {
		$delivery = '';
		if ( 'desk' === $row['delivery_mode'] ) {
			$delivery = '' !== (string) $row['stopdesk'] ? __( 'Stopdesk', 'infinitycod' ) . ' — ' . $row['stopdesk'] : __( 'Stopdesk', 'infinitycod' );
		} elseif ( 'home' === $row['delivery_mode'] ) {
			$delivery = __( 'Domicile', 'infinitycod' );
		}
		$values = array(
			'wc_id'    => (int) $row['wc_order_id'] > 0 ? '#' . (int) $row['wc_order_id'] : '',
			'date'     => (string) $row['created_at'],
			'status'   => self::status_label( (string) $row['status'] ),
			'name'     => (string) $row['customer_name'],
			'phone'    => (string) $row['phone'],
			'wilaya'   => self::wilaya_label( (string) $row['wilaya_code'] ),
			'commune'  => (string) $row['commune'],
			'delivery' => $delivery,
			'product'  => self::product_name( (int) $row['product_id'], (int) $row['variation_id'] ),
			'qty'      => (int) $row['quantity'],
			'total'    => (float) $row['total'],
			'carrier'  => (string) $row['carrier'],
			'tracking' => (string) $row['tracking'],
			'fraud'    => (int) $row['fraud_score'],
			'note'     => (string) $row['note'],
		);
		$out = array();
		foreach ( self::enabled_columns() as $key ) {
			$out[] = isset( $values[ $key ] ) ? $values[ $key ] : '';
		}
		return $out;
	}

	/* ---------------- Commandes : événements ---------------- */

	/**
	 * Nouvelle commande → file (traitement immédiat best-effort).
	 *
	 * @param \WC_Order $order   Commande WooCommerce.
	 * @param array     $data    Données du formulaire.
	 * @param int       $icod_id Ligne interne.
	 * @return void
	 */
	public function on_order_created( $order, $data, $icod_id ) {
		$this->enqueue( (int) $icod_id, 'created' );
		$this->flush( 2 ); // 1-2 envois immédiats, sans jamais ralentir la commande.
	}

	/**
	 * Changement de statut → mise à jour en place (option).
	 *
	 * @param int    $icod_id Ligne interne.
	 * @param string $status  Nouveau statut.
	 * @param array  $row     Ligne complète.
	 * @return void
	 */
	public function on_status_changed( $icod_id, $status, $row ) {
		if ( ! Settings::get( 'sheets_on_status', 0 ) ) {
			return;
		}
		$this->enqueue( (int) $icod_id, 'status' );
		$this->flush( 2 );
	}

	/* ---------------- File d'attente persistante ---------------- */

	/**
	 * Ajoute un travail à la file (dédupliqué par commande+événement).
	 *
	 * @param int    $icod_id Ligne commande.
	 * @param string $event   created|status.
	 * @return void
	 */
	private function enqueue( $icod_id, $event ) {
		if ( ! Settings::get( 'sheets_enabled', 0 ) || $icod_id < 1 ) {
			return;
		}
		$queue = get_option( self::OPT_QUEUE, array() );
		if ( ! is_array( $queue ) ) {
			$queue = array();
		}
		foreach ( $queue as $job ) {
			if ( (int) $job['order_id'] === $icod_id && $job['event'] === $event ) {
				return; // Déjà en file.
			}
		}
		if ( count( $queue ) >= 500 ) { // Garde-fou (site hors ligne des semaines).
			array_shift( $queue );
		}
		$queue[] = array(
			'order_id' => $icod_id,
			'event'    => $event,
			'attempts' => 0,
			'next_try' => 0,
			'error'    => '',
		);
		update_option( self::OPT_QUEUE, $queue, false );
	}

	/**
	 * Traite la file : envois immédiats + réessais exponentiels.
	 *
	 * @param int $max Nombre maximum de travaux traités.
	 * @return void
	 */
	public function flush( $max = 15 ) {
		if ( ! Settings::get( 'sheets_enabled', 0 ) ) {
			return;
		}
		$queue = get_option( self::OPT_QUEUE, array() );
		if ( ! is_array( $queue ) || empty( $queue ) ) {
			return;
		}
		$now     = time();
		$keep    = array();
		$changed = false;
		foreach ( $queue as $job ) {
			if ( empty( $job['order_id'] ) || empty( $job['event'] ) ) {
				$changed = true; // Travail corrompu/périmé : retiré sans blocage de la file.
				continue;
			}
			if ( count( $keep ) >= $max && $now < (int) $job['next_try'] ) {
				$keep[] = $job; // Reporté après le quota de ce passage.
				continue;
			}
			if ( (int) $job['next_try'] > $now ) {
				$keep[] = $job;
				continue;
			}
			$changed = true;
			$error   = $this->sync( (int) $job['order_id'], (string) $job['event'] );
			if ( null === $error ) {
				continue; // Succès : retiré de la file.
			}
			$job['attempts'] = (int) $job['attempts'] + 1;
			$job['error']    = (string) $error;
			if ( $job['attempts'] >= self::MAX_ATTEMPT ) {
				$this->log( 'queue', false, __( 'Abandon après réessais', 'infinitycod' ) . ' #' . $job['order_id'] . ' : ' . $error );
				continue; // Perdu pour de bon : journalisé.
			}
			$job['next_try'] = $now + 60 * ( 2 ** min( 5, $job['attempts'] ) ); // 2,4,8,16,32 min.
			$keep[]          = $job;
		}
		if ( $changed ) {
			update_option( self::OPT_QUEUE, $keep, false );
		}
	}

	/**
	 * Cron : vidange périodique.
	 *
	 * @return void
	 */
	public function cron_flush() {
		$this->flush( 15 );
	}

	/* ---------------- Envoi : append / update ---------------- */

	/**
	 * Synchronise une commande.
	 *
	 * @param int    $icod_id Ligne commande.
	 * @param string $event   created|status.
	 * @return null|string null = succès, sinon message d'erreur.
	 */
	private function sync( $icod_id, $event ) {
		$row = Schema::get_order( $icod_id );
		if ( ! is_array( $row ) ) {
			return null; // Commande supprimée : rien à faire.
		}
		$cols = implode( ',', self::enabled_columns() );
		$map  = get_option( self::OPT_ROWS, array() );
		$mine = isset( $map[ $icod_id ] ) && is_array( $map[ $icod_id ] ) ? $map[ $icod_id ] : null;

		if ( 'status' === $event && $mine && (string) $mine['cols'] === $cols ) {
			return $this->update_status_cells( $row, (int) $mine['row'], $cols );
		}
		if ( 'status' === $event && $mine ) {
			// Structure de colonnes modifiée depuis l'ajout : nouvelle ligne propre.
			unset( $map[ $icod_id ] );
			update_option( self::OPT_ROWS, $map, false );
		}
		if ( 'created' === $event && $mine && (string) $mine['cols'] === $cols ) {
			return null; // Déjà dans le tableur.
		}
		return $this->append_order( $row, $icod_id, $cols );
	}

	/**
	 * Ajoute la ligne au tableur et mémorise son numéro.
	 *
	 * @param array  $row     Ligne icod_orders.
	 * @param int    $icod_id Ligne commande.
	 * @param string $cols    Colonnes actives (hash simple).
	 * @return null|string
	 */
	private function append_order( array $row, $icod_id, $cols ) {
		$tab = $this->tab_name();
		$err = $this->ensure_tab();
		if ( null !== $err ) {
			return $err;
		}
		$res = $this->request(
			sprintf( 'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s!A1:append', rawurlencode( $this->spreadsheet_id() ), rawurlencode( $tab ) ),
			array(
				'method' => 'POST',
				'body'   => wp_json_encode( array( 'values' => array( self::build_row( $row ) ) ) ),
				'query'  => array( 'valueInputOption' => 'USER_ENTERED', 'insertDataOption' => 'INSERT_ROWS' ),
			)
		);
		if ( true !== $res['ok'] ) {
			return $res['error'];
		}
		$range  = isset( $res['body']['updates']['updatedRange'] ) ? (string) $res['body']['updates']['updatedRange'] : '';
		$parsed = array();
		if ( ! preg_match( '/![A-Z]+([0-9]+)/', $range, $parsed ) ) {
			return __( 'réponse append sans plage', 'infinitycod' );
		}
		$map           = get_option( self::OPT_ROWS, array() );
		$map[ $icod_id ] = array(
			'row'  => (int) $parsed[1],
			'cols' => $cols,
		);
		update_option( self::OPT_ROWS, $map, false );
		$this->log( 'append', true, '#' . $icod_id . ' → ligne ' . (int) $parsed[1] );
		return null;
	}

	/**
	 * Met à jour statut + suivi + transporteur dans la ligne existante.
	 *
	 * @param array  $row      Ligne icod_orders.
	 * @param int    $row_num  Numéro de ligne dans le tableur.
	 * @param string $cols     Colonnes actives (hash simple).
	 * @return null|string
	 */
	private function update_status_cells( array $row, $row_num, $cols ) {
		$enabled = explode( ',', $cols );
		$cells   = array(
			'status'   => self::status_label( (string) $row['status'] ),
			'tracking' => (string) $row['tracking'],
			'carrier'  => (string) $row['carrier'],
		);
		$data = array();
		foreach ( $cells as $key => $value ) {
			$idx = array_search( $key, $enabled, true );
			if ( false === $idx ) {
				continue; // Colonne absente de la structure choisie.
			}
			$letter = chr( 65 + (int) $idx );
			$data[] = array(
				'range'  => $this->tab_name() . '!' . $letter . $row_num,
				'values' => array( array( $value ) ),
			);
		}
		if ( empty( $data ) ) {
			return null;
		}
		$res = $this->request(
			sprintf( 'https://sheets.googleapis.com/v4/spreadsheets/%s/values:batchUpdate', rawurlencode( $this->spreadsheet_id() ) ),
			array(
				'method' => 'POST',
				'body'   => wp_json_encode( array( 'valueInputOption' => 'USER_ENTERED', 'data' => $data ) ),
			)
		);
		if ( true !== $res['ok'] ) {
			return $res['error'];
		}
		$this->log( 'update', true, '#' . $row['id'] . ' → ' . $cells['status'] );
		return null;
	}

	/* ---------------- API Google ---------------- */

	private function spreadsheet_id() {
		return trim( (string) Settings::get( 'sheets_id', '' ) );
	}

	private function tab_name() {
		$tab = trim( (string) Settings::get( 'sheets_tab', '' ) );
		return '' !== $tab ? $tab : __( 'Commandes', 'infinitycod' );
	}

	/**
	 * Compte de service : e-mail + clé PEM (réglage, JSON ou PEM accepté).
	 *
	 * @return array{email:string,key:string}
	 */
	public static function service_account() {
		$email = trim( (string) Settings::get( 'sheets_email', '' ) );
		$key   = (string) Settings::get( 'sheets_key', '' );
		if ( '' !== $key && 0 === strpos( trim( $key ), '{' ) ) { // JSON collé dans le champ clé.
			$j = json_decode( $key, true );
			if ( is_array( $j ) && ! empty( $j['client_email'] ) && ! empty( $j['private_key'] ) ) {
				return array(
					'email' => (string) $j['client_email'],
					'key'   => (string) $j['private_key'],
				);
			}
		}
		return array(
			'email' => $email,
			'key'   => $key,
		);
	}

	/**
	 * JWT RS256 signé avec la clé du compte de service.
	 *
	 * @param string $email E-mail du compte de service.
	 * @param string $key   Clé privée PEM.
	 * @return string|null
	 */
	private static function service_jwt( $email, $key ) {
		$b64 = static function ( $bytes ) {
			return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
		};
		$now   = time();
		$head  = $b64( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claim = $b64( wp_json_encode( array(
			'iss'   => $email,
			'scope' => 'https://www.googleapis.com/auth/spreadsheets',
			'aud'   => 'https://oauth2.googleapis.com/token',
			'iat'   => $now,
			'exp'   => $now + 3600,
		) ) );
		$input = $head . '.' . $claim;
		$pkey  = openssl_pkey_get_private( $key );
		if ( ! $pkey ) {
			return null;
		}
		$sig = '';
		openssl_sign( $input, $sig, $pkey, OPENSSL_ALGO_SHA256 );
		if ( '' === $sig ) {
			return null;
		}
		return $input . '.' . $b64( $sig );
	}

	/**
	 * Jeton d'accès (mis en cache 50 min).
	 *
	 * @return string|null
	 */
	private function access_token() {
		$cached = get_transient( 'icod_sheets_token' );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}
		$sa = self::service_account();
		if ( '' === $sa['email'] || '' === $sa['key'] ) {
			return null;
		}
		$jwt = self::service_jwt( $sa['email'], $sa['key'] );
		if ( null === $jwt ) {
			$this->log( 'auth', false, __( 'Clé du compte de service illisible (JSON ou PEM attendu).', 'infinitycod' ) );
			return null;
		}
		$res = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 15,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);
		if ( is_wp_error( $res ) ) {
			$this->log( 'auth', false, $res->get_error_message() );
			return null;
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $body['access_token'] ) ) {
			$this->log( 'auth', false, isset( $body['error_description'] ) ? (string) $body['error_description'] : __( 'réponse OAuth invalide', 'infinitycod' ) );
			return null;
		}
		set_transient( 'icod_sheets_token', (string) $body['access_token'], self::TOKEN_TTL );
		return (string) $body['access_token'];
	}

	/**
	 * Appel API Sheets normalisé.
	 *
	 * @param string $url    URL complète.
	 * @param array  $args   method, body (JSON), query ({}).
	 * @return array{ok:bool,error:string,body:array}
	 */
	private function request( $url, array $args = array() ) {
		$token = $this->access_token();
		if ( null === $token ) {
			return array( 'ok' => false, 'error' => __( 'Authentification Google impossible (compte de service).', 'infinitycod' ), 'body' => array() );
		}
		if ( ! empty( $args['query'] ) ) {
			$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . build_query( $args['query'] );
		}
		$res = wp_remote_request(
			$url,
			array(
				'method'  => isset( $args['method'] ) ? $args['method'] : 'GET',
				'timeout' => 12,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => isset( $args['body'] ) ? $args['body'] : null,
			)
		);
		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'error' => $res->get_error_message(), 'body' => array() );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( ! is_array( $body ) ) {
			$body = array();
		}
		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : 'HTTP ' . $code;
			if ( 401 === $code || 403 === $code ) {
				delete_transient( 'icod_sheets_token' ); // Jeton périmé ou accès refusé : re-auth complète au prochain appel.
			}
			return array( 'ok' => false, 'error' => $msg, 'body' => $body );
		}
		return array( 'ok' => true, 'error' => '', 'body' => $body );
	}

	/**
	 * Vérifie l'accès au tableur et renvoie ses métadonnées.
	 *
	 * @return array{ok:bool,error:string,title:string,tabs:array<string>,tab_exists:bool}
	 */
	public function test_connection() {
		$id = $this->spreadsheet_id();
		if ( '' === $id ) {
			return array( 'ok' => false, 'error' => __( 'ID du tableur manquant.', 'infinitycod' ), 'title' => '', 'tabs' => array(), 'tab_exists' => false );
		}
		$sa = self::service_account();
		if ( '' === $sa['email'] || '' === $sa['key'] ) {
			return array( 'ok' => false, 'error' => __( 'Compte de service manquant (e-mail + clé).', 'infinitycod' ), 'title' => '', 'tabs' => array(), 'tab_exists' => false );
		}
		$res = $this->request( sprintf( 'https://sheets.googleapis.com/v4/spreadsheets/%s', rawurlencode( $id ) ) . '?fields=properties.title,sheets.properties.title' );
		if ( true !== $res['ok'] ) {
			return array( 'ok' => false, 'error' => $res['error'], 'title' => '', 'tabs' => array(), 'tab_exists' => false );
		}
		$title = isset( $res['body']['properties']['title'] ) ? (string) $res['body']['properties']['title'] : '';
		$tabs  = array();
		foreach ( ( $res['body']['sheets'] ?? array() ) as $sheet ) {
			if ( isset( $sheet['properties']['title'] ) ) {
				$tabs[] = (string) $sheet['properties']['title'];
			}
		}
		return array(
			'ok'         => true,
			'error'      => '',
			'title'      => $title,
			'tabs'       => $tabs,
			'tab_exists' => in_array( $this->tab_name(), $tabs, true ),
		);
	}

	/**
	 * Crée l'onglet s'il manque (idempotent).
	 *
	 * @return null|string
	 */
	public function ensure_tab() {
		$state = $this->test_connection();
		if ( true !== $state['ok'] ) {
			return $state['error'];
		}
		if ( $state['tab_exists'] ) {
			return null;
		}
		$res = $this->request(
			sprintf( 'https://sheets.googleapis.com/v4/spreadsheets/%s/:batchUpdate', rawurlencode( $this->spreadsheet_id() ) ),
			array(
				'method' => 'POST',
				'body'   => wp_json_encode( array( 'requests' => array( array( 'addSheet' => array( 'properties' => array( 'title' => $this->tab_name() ) ) ) ) ) ),
			)
		);
		if ( true !== $res['ok'] ) {
			return $res['error'];
		}
		$this->log( 'tab', true, $this->tab_name() );
		return null;
	}

	/**
	 * Écrit la ligne d'en-têtes si le tableur est vide.
	 *
	 * @return null|string
	 */
	public function ensure_headers() {
		$tab = $this->tab_name();
		$get = $this->request( sprintf( 'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s!1:1', rawurlencode( $this->spreadsheet_id() ), rawurlencode( $tab ) ) );
		if ( true !== $get['ok'] ) {
			return $get['error'];
		}
		if ( ! empty( $get['body']['values'] ) ) {
			return null; // Déjà des données : ne jamais écraser.
		}
		$headers = array();
		foreach ( self::enabled_columns() as $key ) {
			$catalog   = self::columns();
			$headers[] = isset( $catalog[ $key ] ) ? $catalog[ $key ] : $key;
		}
		$res = $this->request(
			sprintf( 'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s!A1:append', rawurlencode( $this->spreadsheet_id() ), rawurlencode( $tab ) ),
			array(
				'method' => 'POST',
				'body'   => wp_json_encode( array( 'values' => array( $headers ) ) ),
				'query'  => array( 'valueInputOption' => 'USER_ENTERED', 'insertDataOption' => 'INSERT_ROWS' ),
			)
		);
		if ( true !== $res['ok'] ) {
			return $res['error'];
		}
		$this->log( 'headers', true, implode( ' | ', $headers ) );
		return null;
	}

	/* ---------------- Export complet ---------------- */

	/**
	 * Exporte les commandes absentes du tableur (lots de 200, reprise par curseur).
	 *
	 * @param int $chunks Nombre de lots à traiter (3 = 600 commandes max par appel).
	 * @return array{sent:int,remaining:int,error:string}
	 */
	public function backfill( $chunks = 3 ) {
		global $wpdb;
		$table     = Schema::table( 'orders' );
		$map       = get_option( self::OPT_ROWS, array() );
		$cursor    = (int) get_option( 'icod_sheets_cursor', 0 );
		$cols      = implode( ',', self::enabled_columns() );
		$sent      = 0;
		$remaining = 0;

		for ( $i = 0; $i < $chunks; $i++ ) { // phpcs:ignore WordPress.WP.EnqueuedResourceParameters -- boucle métier.
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- table interne du plugin, curseur entière.
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id > %d ORDER BY id ASC LIMIT 200", $cursor ),
				ARRAY_A
			);
			if ( empty( $rows ) ) {
				update_option( 'icod_sheets_cursor', 0, false ); // Cycle terminé.
				return array( 'sent' => $sent, 'remaining' => 0, 'error' => '' );
			}
			$values = array();
			foreach ( $rows as $row ) {
				$cursor = (int) $row['id'];
				if ( isset( $map[ (int) $row['id'] ] ) ) {
					continue; // Déjà synchronisée.
				}
				$values[] = self::build_row( $row );
			}
			if ( ! empty( $values ) ) {
				$err = $this->append_rows( $values, $rows, $cols, $map );
				if ( null !== $err ) {
					update_option( 'icod_sheets_cursor', $cursor, false );
					return array( 'sent' => $sent, 'remaining' => -1, 'error' => $err );
				}
				$sent += count( $values );
				usleep( 250000 ); // Douceur : quota Google.
			} else {
				update_option( 'icod_sheets_cursor', $cursor, false );
			}
		}
		$left = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE id > {$cursor}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- compteur interne, entier absolu.
		update_option( 'icod_sheets_cursor', $cursor, false );
		$remaining = $left;
		return array( 'sent' => $sent, 'remaining' => $remaining, 'error' => '' );
	}

	/**
	 * Ajoute plusieurs lignes en un appel + enregistre les numéros.
	 *
	 * @param array  $values Lignes.
	 * @param array  $rows   Lignes commandes correspondantes (même ordre, hors déjà synchro).
	 * @param string $cols   Colonnes actives.
	 * @param array  $map    Map actuelle (référence, mise à jour).
	 * @return null|string
	 */
	private function append_rows( array $values, array $rows, $cols, array &$map ) {
		$tab = $this->tab_name();
		$res = $this->request(
			sprintf( 'https://sheets.googleapis.com/v4/spreadsheets/%s/values/%s!A1:append', rawurlencode( $this->spreadsheet_id() ), rawurlencode( $tab ) ),
			array(
				'method' => 'POST',
				'body'   => wp_json_encode( array( 'values' => $values ) ),
				'query'  => array( 'valueInputOption' => 'USER_ENTERED', 'insertDataOption' => 'INSERT_ROWS' ),
			)
		);
		if ( true !== $res['ok'] ) {
			return $res['error'];
		}
		$range = isset( $res['body']['updates']['updatedRange'] ) ? (string) $res['body']['updates']['updatedRange'] : '';
		$parsed = array();
		if ( ! preg_match( '/![A-Z]+([0-9]+)/', $range, $parsed ) ) {
			return __( 'réponse append sans plage', 'infinitycod' );
		}
		$first_row = (int) $parsed[1];
		$idx       = 0;
		foreach ( $rows as $row ) {
			if ( isset( $map[ (int) $row['id'] ] ) ) {
				continue;
			}
			$map[ (int) $row['id'] ] = array(
				'row'  => $first_row + $idx,
				'cols' => $cols,
			);
			$idx++;
		}
		update_option( self::OPT_ROWS, $map, false );
		return null;
	}

	/* ---------------- Journal ---------------- */

	/**
	 * Journal léger (30 dernières entrées).
	 *
	 * @param string $event Type.
	 * @param bool   $ok    Succès.
	 * @param string $msg   Détail.
	 * @return void
	 */
	private function log( $event, $ok, $msg ) {
		$log = get_option( self::OPT_LOG, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		array_unshift( $log, array(
			'ts'    => current_time( 'mysql' ),
			'event' => (string) $event,
			'ok'    => $ok ? 1 : 0,
			'msg'   => mb_substr( (string) $msg, 0, 160 ),
		) );
		update_option( self::OPT_LOG, array_slice( $log, 0, 30 ), false );
	}

	/**
	 * Journal lisible (admin).
	 *
	 * @return array[]
	 */
	public function recent_log() {
		$log = get_option( self::OPT_LOG, array() );
		return is_array( $log ) ? $log : array();
	}

	/* ---------------- AJAX ---------------- */

	/**
	 * Garde partagée des handlers.
	 *
	 * @return void
	 */
	private function guard() {
		check_ajax_referer( 'icod_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
		}
	}

	/**
	 * Test de connexion.
	 *
	 * @return void
	 */
	public function handle_test() {
		$this->guard();
		$state = $this->test_connection();
		if ( true !== $state['ok'] ) {
			wp_send_json_success( array( 'ok' => false, 'message' => '❌ ' . $state['error'] ) );
		}
		$msg = '✅ ' . sprintf( /* translators: %s : titre du tableur. */ __( 'Connecté au tableur « %s ».', 'infinitycod' ), $state['title'] );
		$msg .= $state['tab_exists']
			? ' ' . __( 'Onglet trouvé.', 'infinitycod' )
			: ' ' . sprintf( /* translators: %s : nom de l'onglet. */ __( 'L’onglet « %s » sera créé automatiquement.', 'infinitycod' ), $this->tab_name() );
		wp_send_json_success( array( 'ok' => true, 'message' => $msg ) );
	}

	/**
	 * Création onglet + en-têtes.
	 *
	 * @return void
	 */
	public function handle_init() {
		$this->guard();
		$err = $this->ensure_tab();
		if ( null !== $err ) {
			wp_send_json_success( array( 'ok' => false, 'message' => '❌ ' . $err ) );
		}
		$err = $this->ensure_headers();
		if ( null !== $err ) {
			wp_send_json_success( array( 'ok' => false, 'message' => '❌ ' . $err ) );
		}
		wp_send_json_success( array( 'ok' => true, 'message' => __( '✅ Onglet et en-têtes prêts (aucune donnée écrasée).', 'infinitycod' ) ) );
	}

	/**
	 * Export complet (continuer en recliquant tant que « restantes » > 0).
	 *
	 * @return void
	 */
	public function handle_backfill() {
		$this->guard();
		$this->flush( 5 ); // Vide d'abord la file en attente.
		$result = $this->backfill( 3 );
		if ( '' !== $result['error'] ) {
			wp_send_json_success( array( 'ok' => false, 'message' => '❌ ' . $result['error'] . ' — ' . __( 'cliquez à nouveau pour reprendre là où c’est resté.', 'infinitycod' ) ) );
		}
		$msg = sprintf( /* translators: %d : nombre de commandes exportées. */ __( '%d commande(s) exportée(s).', 'infinitycod' ), $result['sent'] );
		if ( $result['remaining'] > 0 ) {
			$msg .= ' ' . sprintf( /* translators: %d : commandes restantes. */ __( 'Restantes : %d — recliquez pour continuer.', 'infinitycod' ), $result['remaining'] );
		} else {
			$msg .= ' ' . __( 'Export terminé ✅', 'infinitycod' );
		}
		wp_send_json_success( array( 'ok' => true, 'message' => $msg ) );
	}

	/**
	 * Vidange manuelle de la file.
	 *
	 * @return void
	 */
	public function handle_flush() {
		$this->guard();
		$this->flush( 30 );
		$queue = get_option( self::OPT_QUEUE, array() );
		$count = is_array( $queue ) ? count( $queue ) : 0;
		$msg   = 0 === $count
			? __( '✅ File vide — tout est synchronisé.', 'infinitycod' )
			: sprintf( /* translators: %d : travaux en attente. */ __( '%d synchronisation(s) en attente (réessais automatiques en cours).', 'infinitycod' ), $count );
		wp_send_json_success( array( 'ok' => 0 === $count, 'message' => $msg, 'pending' => $count ) );
	}
}
