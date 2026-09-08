<?php
/**
 * Système de logs InfinityCod : fichiers mensuels dans uploads/infinitycod-logs/.
 *
 * Catégories : license, update, security, migration, api, error, diagnostic.
 * Règle : ne JAMAIS journaliser clé de licence complète, token, mot de passe
 * ni données clients (les messages sont rédigés par le code appelant).
 *
 * @package InfinityCod
 * @author Derouiche Oussama
 * @copyright © Derouiche Oussama
 * @link https://derouicheoussama.com
 */

namespace InfinityCod\Logging;

defined( 'ABSPATH' ) || exit;

class Logger {

	const CATEGORIES = array( 'license', 'update', 'security', 'migration', 'api', 'error', 'diagnostic' );
	const MAX_AGE_DAYS = 60;

	/**
	 * Hooks : cron de nettoyage.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'infinitycod_cleanup_logs', array( $this, 'cleanup' ) );

		if ( ! wp_next_scheduled( 'infinitycod_cleanup_logs' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'infinitycod_cleanup_logs' );
		}
	}

	/**
	 * Écrit une entrée de log.
	 *
	 * @param string $category Catégorie (voir CATEGORIES).
	 * @param string $message  Message court, sans données sensibles.
	 * @return bool
	 */
	public static function log( $category, $message ) {
		$category = in_array( $category, self::CATEGORIES, true ) ? $category : 'error';

		$file = self::current_file();
		if ( ! $file ) {
			return false;
		}

		$line = sprintf(
			"[%s][%s] %s\n",
			current_time( 'mysql' ),
			$category,
			str_replace( array( "\r", "\n" ), ' ', (string) $message )
		);

		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		if ( $wp_filesystem ) {
			return (bool) $wp_filesystem->put_contents( $file, (string) $wp_filesystem->get_contents( $file ) . $line, FS_CHMOD_FILE );
		}

		return false !== file_put_contents( $file, $line, FILE_APPEND ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Fichier du mois courant (crée le dossier si besoin).
	 *
	 * @return string|null
	 */
	private static function current_file() {
		$upload = wp_get_upload_dir();
		if ( empty( $upload['basedir'] ) ) {
			return null;
		}

		$dir = $upload['basedir'] . '/infinitycod-logs';

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			// Protection directe : interdire la lecture des logs via le web.
			@file_put_contents( $dir . '/.htaccess', "Deny from all\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		return $dir . '/log-' . current_time( 'Y-m' ) . '.log';
	}

	/**
	 * Supprime les fichiers de log plus vieux que MAX_AGE_DAYS (cron).
	 *
	 * @return int Fichiers supprimés.
	 */
	public function cleanup() {
		$upload = wp_get_upload_dir();
		$dir    = $upload['basedir'] . '/infinitycod-logs';

		if ( ! is_dir( $dir ) ) {
			return 0;
		}

		$removed = 0;
		$cutoff  = time() - self::MAX_AGE_DAYS * DAY_IN_SECONDS;

		foreach ( (array) glob( $dir . '/log-*.log' ) as $file ) {
			if ( filemtime( $file ) < $cutoff ) {
				@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$removed++;
			}
		}

		return $removed;
	}
}
