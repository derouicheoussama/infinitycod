<?php
/**
 * Activation / désactivation : tables, seed géographique, réglages.
 *
 * @package InfinityCod
 */

namespace InfinityCod\Core;

defined( 'ABSPATH' ) || exit;

class Activator {

	/**
	 * Hook d'activation : crée les tables et charge les données de base.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		self::create_tables();
		self::seed_geo();
		self::seed_settings();

		set_transient( 'icod_welcome', 1, 7 * DAY_IN_SECONDS );

		update_option( 'infinitycod_db_version', INFINITYCOD_DB_VERSION );
		update_option( 'infinitycod_installed_at', current_time( 'mysql' ) );

		// Mise à jour DB silencieuse lors des montées de version.
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
	}

	/**
	 * Crée / met à jour les tables via dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( Schema::create_tables() as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Insère wilayas et communes si les tables sont vides.
	 *
	 * @return void
	 */
	public static function seed_geo() {
		global $wpdb;

		$wilayas_table  = Schema::table( 'wilayas' );
		$communes_table = Schema::table( 'communes' );

		// Wilayas.
		if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wilayas_table}" ) ) {
			$wilayas = self::read_json( 'wilayas.json' );
			if ( $wilayas ) {
				$rows = array();
				foreach ( $wilayas as $w ) {
					$code    = isset( $w['code'] ) ? esc_sql( $w['code'] ) : '';
					$name_fr = isset( $w['name_fr'] ) ? esc_sql( $w['name_fr'] ) : '';
					$name_ar = isset( $w['name_ar'] ) ? esc_sql( $w['name_ar'] ) : '';
					if ( '' === $code ) {
						continue;
					}
					$rows[] = "('{$code}','{$name_fr}','{$name_ar}',1,-1,-1,0)";
				}
				if ( $rows ) {
					$wpdb->query(
						"INSERT INTO {$wilayas_table} (code, name_fr, name_ar, active, price_home, price_desk, free_shipping) VALUES "
						. implode( ',', $rows )
					);
				}
			}
		}

		// Communes : par lots de 200 pour respecter max_allowed_packet.
		if ( 0 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$communes_table}" ) ) {
			$communes = self::read_json( 'communes.json' );
			if ( $communes ) {
				$batch = array();
				$total = 0;
				foreach ( $communes as $c ) {
					$wilaya = isset( $c['wilaya_code'] ) ? esc_sql( $c['wilaya_code'] ) : '';
					$fr     = isset( $c['name_fr'] ) ? esc_sql( $c['name_fr'] ) : '';
					$ar     = isset( $c['name_ar'] ) ? esc_sql( $c['name_ar'] ) : '';
					if ( '' === $wilaya || '' === $fr ) {
						continue;
					}
					$batch[] = "('{$wilaya}','{$fr}','{$ar}',1,0,-1,-1)";
					if ( count( $batch ) >= 200 ) {
						$wpdb->query( "INSERT INTO {$communes_table} (wilaya_code, name_fr, name_ar, active, has_desk, price_home, price_desk) VALUES " . implode( ',', $batch ) );
						$total += count( $batch );
						$batch  = array();
					}
				}
				if ( $batch ) {
					$wpdb->query( "INSERT INTO {$communes_table} (wilaya_code, name_fr, name_ar, active, has_desk, price_home, price_desk) VALUES " . implode( ',', $batch ) );
					$total += count( $batch );
				}
			}
		}
	}

	/**
	 * Écrit les réglages par défaut à la première activation.
	 *
	 * @return void
	 */
	public static function seed_settings() {
		if ( false === get_option( Settings::OPTION, false ) ) {
			add_option( Settings::OPTION, Settings::defaults(), '', true );
		}
	}

	/**
	 * Lit un fichier JSON du dossier data/.
	 *
	 * @param string $file Nom du fichier (ex. 'wilayas.json').
	 * @return array|null
	 */
	private static function read_json( $file ) {
		$path = INFINITYCOD_PATH . 'data/' . $file;
		if ( ! is_readable( $path ) ) {
			return null;
		}
		$decoded = json_decode( (string) file_get_contents( $path ), true );
		return is_array( $decoded ) ? $decoded : null;
	}

	/**
	 * Met à jour les tables quand INFINITYCOD_DB_VERSION change.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'infinitycod_db_version' ) !== INFINITYCOD_DB_VERSION ) {
			self::create_tables();
			self::seed_geo();
			update_option( 'infinitycod_db_version', INFINITYCOD_DB_VERSION, true );
		}
	}

	/**
	 * Hook de désactivation : nettoie les événements planifiés.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'infinitycod_sync_tracking' );
		wp_clear_scheduled_hook( 'infinitycod_recover_abandoned' );
		wp_clear_scheduled_hook( 'infinitycod_update_check' );
	}
}
