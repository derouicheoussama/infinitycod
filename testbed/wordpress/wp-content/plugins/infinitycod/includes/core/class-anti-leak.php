<?php
/**
 * Anti-leak : traçabilité des exports, alertes de fuite, intégrité des fichiers.
 *
 * Les téléphones clients sont le premier actif d'une boutique COD. Ce module
 * filigrane chaque export (code de traçabilité), tient un journal des exports,
 * alerte en cas d'extraction massive et surveille l'intégrité des fichiers
 * du plugin (copie piratée / fichier modifié).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Core;

defined( 'ABSPATH' ) || exit;

class AntiLeak {

	/**
	 * Hooks : vérification d'intégrité périodique en admin.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'maybe_integrity_check' ) );
		add_action( 'admin_notices', array( $this, 'integrity_notice' ) );
	}

	/**
	 * Masque un téléphone (setting anti-leak) : 0770••••56.
	 *
	 * @param string $phone Téléphone brut.
	 * @return string
	 */
	public static function mask_phone( $phone ) {
		if ( ! Settings::get( 'mask_phones' ) ) {
			return (string) $phone;
		}
		$digits = preg_replace( '/[^0-9]/', '', (string) $phone );
		if ( strlen( $digits ) < 6 ) {
			return '••••••';
		}
		return substr( $digits, 0, 4 ) . '••••' . substr( $digits, -2 );
	}

		/**
	 * Code de traçabilité court d'un export (journal + colonne du fichier).
	 *
	 * @return string Ex. « EX-4f8a2c ».
	 */
	public static function trace_code() {
		$user = wp_get_current_user();
		return 'EX-' . substr( hash( 'crc32b', get_current_user_id() . '|' . ( $user->user_login ?? '' ) . '|' . microtime() ), 0, 6 );
	}

	/**
	 * Journalise un export de données (dernières 60 entrées conservées).
	 *
	 * @param string $type  Type d'export (orders_csv, orders_xls, abandoned_xls, bordereaux…).
	 * @param int    $count Nombre de lignes exportées.
	 * @param string $trace Code de traçabilité (identique à celui du fichier).
	 * @return void
	 */
	public static function log_export( $type, $count, $trace ) {
		$log   = get_option( 'icod_export_log', array() );
		$log   = is_array( $log ) ? $log : array();
		$user  = wp_get_current_user();

		array_unshift( $log, array(
			'trace' => (string) $trace,
			'user'  => (string) ( $user->user_login ?? '?' ),
			'date'  => current_time( 'mysql' ),
			'type'  => sanitize_key( (string) $type ),
			'count' => (int) $count,
			'ip'    => \InfinityCod\AntiFraud\Shield::client_ip(),
		) );
		update_option( 'icod_export_log', array_slice( $log, 0, 60 ), false );

		// Alerte export massif : extraction anormale de la base clients.
		$threshold = (int) Settings::get( 'export_alert_min', 200 );
		if ( $threshold > 0 && (int) $count >= $threshold ) {
			$subject = sprintf(
				/* translators: 1 : nom du site, 2 : nombre de lignes, 3 : identifiant. */
				__( '[%1$s] Export massif : %2$d lignes par %3$s', 'infinitycod' ),
				get_bloginfo( 'name' ),
				(int) $count,
				(string) ( $user->user_login ?? '?' )
			);
			$body = sprintf(
				/* translators: 1 : type d'export, 2 : trace, 3 : IP. */
				__( 'Type : %1$s — Trace : %2$s — IP : %3$s — Date : %4$s', 'infinitycod' ),
				sanitize_key( (string) $type ),
				(string) $trace,
				\InfinityCod\AntiFraud\Shield::client_ip(),
				current_time( 'mysql' )
			);
			wp_mail( get_option( 'admin_email' ), $subject, $body );
		}
	}

	/**
	 * Journal des exports (le plus récent en premier).
	 *
	 * @return array
	 */
	public static function get_log() {
		$log = get_option( 'icod_export_log', array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Avis admin : fichiers du plugin modifiés (copie piratée / altérée).
	 *
	 * @return void
	 */
	public function integrity_notice() {
		$alert = get_option( 'icod_integrity_alert' );
		if ( ! is_array( $alert ) || empty( $alert['file'] ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>⚠️ InfinityCod — ' . esc_html__( 'fichier modifié détecté :', 'infinitycod' ) . '</strong> <code>' . esc_html( (string) $alert['file'] ) . '</code>. '
			. esc_html__( 'Cette copie du plugin est potentiellement piratée ou altérée : réinstallez le zip officiel et contactez le support.', 'infinitycod' ) . '</p></div>';
	}

		/**
	 * Calcule la base d'intégrité des fichiers cœur (à chaque nouvelle version).
	 *
	 * @return void
	 */
	public static function refresh_baseline() {
		$hashes = array();
		foreach ( self::watched_files() as $rel ) {
			$file = INFINITYCOD_PATH . $rel;
			if ( file_exists( $file ) ) {
				$hashes[ $rel ] = hash( 'sha256', (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- lecture locale pour hachage d'intégrité.
			}
		}
		update_option( 'icod_integrity', array( 'version' => INFINITYCOD_VERSION, 'hashes' => $hashes ), false );
	}

	/**
	 * Vérifie l'intégrité (12 h de cache, rebase silencieux si nouvelle version).
	 *
	 * @return void
	 */
	public function maybe_integrity_check() {
		if ( ! Settings::get( 'integrity_check', 1 ) ) {
			return;
		}
		if ( get_transient( 'icod_integrity_checked' ) ) {
			return;
		}
		set_transient( 'icod_integrity_checked', 1, 12 * HOUR_IN_SECONDS );

		$base = get_option( 'icod_integrity', array() );
		$base = is_array( $base ) ? $base : array();

		// Nouvelle version installée : la base est recalculée sans alerte.
		if ( ( $base['version'] ?? '' ) !== INFINITYCOD_VERSION ) {
			self::refresh_baseline();
			delete_option( 'icod_integrity_alert' );
			return;
		}

		foreach ( (array) ( $base['hashes'] ?? array() ) as $rel => $expected ) {
			$file = INFINITYCOD_PATH . $rel;
			if ( ! file_exists( $file ) ) {
				continue; // Fichier absent : cas plugin partiel, non signalé ici.
			}
			$actual = hash( 'sha256', (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- lecture locale pour hachage d'intégrité.
			if ( ! hash_equals( (string) $expected, $actual ) ) {
				update_option( 'icod_integrity_alert', array( 'file' => $rel, 'date' => current_time( 'mysql' ) ), false );
				\InfinityCod\Logging\Logger::log( 'warning', 'Intégrité : fichier modifié — ' . $rel );
				break;
			}
		}
	}

	/**
	 * Fichiers cœur surveillés (échantillon représentatif du code sensible).
	 *
	 * @return array<int, string>
	 */
	private static function watched_files() {
		return array(
			'infinitycod.php',
			'includes/core/class-plugin.php',
			'includes/core/class-settings.php',
			'includes/orders/class-order-store.php',
			'includes/anti-fraud/class-shield.php',
			'includes/license/class-license-manager.php',
			'includes/license/class-updater.php',
		);
	}
}
