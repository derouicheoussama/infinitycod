<?php
/**
 * Test E2E de mise à jour InfinityCod — rejoue le parcours client réel.
 *
 * Monte un WordPress jetable (SQLite, sans MySQL) dans un dossier temporaire,
 * installe une version FICTIVE ANCIENNE du plugin (0.0.1), puis déclenche la
 * mise à jour par le vrai upgrader WordPress : offre depuis le serveur de
 * releases réel, signature + SHA-256 vérifiés, installation complète.
 * Échoue si l'offre ne sort pas, si l'installation échoue, ou si le moindre
 * fatal PHP survient (capteur shutdown).
 *
 * Un seul processus PHP : aucun exec/shell — téléchargements via l'API HTTP
 * WordPress après bootstrap, extraction via ZipArchive, chemins toujours
 * résolus sous la racine temporaire (garde anti-traversée).
 *
 * Usage :
 *   php tools/test-update-e2e.php                 # WordPress simple
 *   php tools/test-update-e2e.php --multisite     # réseau multisite
 *   php tools/test-update-e2e.php --keep          # garde le dossier (debug)
 *
 * Prérequis : PHP 8.1+ (pdo_sqlite, sqlite3, zip, curl, openssl, sodium).
 * Réseau sortant requis (wordpress.org, GitHub).
 *
 * @package InfinityCod
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( "CLI uniquement.\n" );
}

$MULTISITE = in_array( '--multisite', $argv, true );
$KEEP      = in_array( '--keep', $argv, true );
$ROOT      = dirname( __DIR__ );
$t0        = microtime( true );

function e2e_fail( string $msg ): void {
	global $work;
	echo "✗ E2E : {$msg}\n  dossier conservé : {$work}\n";
	exit( 1 );
}
function e2e_log( string $step ): void {
	echo '· ' . $step . "\n";
}
/** Garde anti-traversée : tout chemin doit rester sous la racine de travail. */
function e2e_path( string $relative ): string {
	global $work;
	$resolved = realpath( dirname( $relative ) ) . '/' . basename( $relative );
	if ( 0 !== strpos( $resolved, $work ) ) {
		e2e_fail( 'chemin hors racine refusé : ' . $resolved );
	}
	return $resolved;
}

$work = sys_get_temp_dir() . '/icod-e2e-' . substr( (string) md5( (string) mt_rand() ), 0, 8 );
if ( is_dir( $work ) ) {
	e2e_fail( 'dossier de travail déjà présent : ' . $work );
}
mkdir( $work, 0777, true );
$work = realpath( $work ) . '/';
e2e_log( 'dossier de travail : ' . $work );

$wp_dir    = e2e_path( $work . 'wordpress' );
$plugin_wp = $wp_dir . '/wp-content/plugins/infinitycod';

// — 1. WordPress + extension SQLite -------------------------------------------------
e2e_log( 'téléchargement de WordPress…' );
$wp_zip = $work . 'wp.zip';
if ( ! copy( 'https://wordpress.org/latest.zip', $wp_zip ) ) {
	e2e_fail( 'téléchargement WordPress impossible' );
}
e2e_log( 'téléchargement du pilote SQLite…' );
$sqlite_zip = $work . 'sqlite.zip';
if ( ! copy( 'https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip', $sqlite_zip ) ) {
	e2e_fail( 'téléchargement SQLite drop-in impossible' );
}
$zip = new ZipArchive();
$zip->open( $wp_zip );
$zip->extractTo( $work );
$zip->close();
$zip->open( $sqlite_zip );
$zip->extractTo( $wp_dir . '/wp-content/plugins/' );
$zip->close();
copy( $wp_dir . '/wp-content/plugins/sqlite-database-integration/db.copy', $wp_dir . '/wp-content/db.php' );

// wp-config minimal (SQLite ignore les constantes DB) + mode multisite.
$config = (string) file_get_contents( $wp_dir . '/wp-config-sample.php' );
$config = str_replace(
	array( 'database_name_here', 'username_here', 'password_here' ),
	array( 'sqlite', 'sqlite', 'sqlite' ),
	$config
);
if ( $MULTISITE ) {
	$config = str_replace( "/* That's all", "define( 'MULTISITE', true );\ndefine( 'SUBDOMAIN_INSTALL', false );\ndefine( 'DOMAIN_CURRENT_SITE', 'e2e.local' );\n\$base = '/';\ndefine( 'PATH_CURRENT_SITE', '/' );\ndefine( 'SITE_ID_CURRENT_SITE', 1 );\ndefine( 'BLOG_ID_CURRENT_SITE', 1 );\n\n/* That's all", $config );
}
file_put_contents( $wp_dir . '/wp-config.php', $config );

// Capteur de fatals : tout fatal pendant le test → fichier → échec.
$mu_dir = $wp_dir . '/wp-content/mu-plugins';
mkdir( $mu_dir, 0777, true );
$fatal_log = $work . 'fatals.log';
file_put_contents(
	$mu_dir . '/e2e-fatals.php',
	'<?php
register_shutdown_function( function () {
	$e = error_get_last();
	if ( $e && in_array( (int) $e["type"], array( E_ERROR, E_PARSE, E_COMPILE_ERROR ), true ) ) {
		file_put_contents( ' . var_export( $fatal_log, true ) . ', json_encode( $e ) . PHP_EOL, FILE_APPEND );
	}
} );'
);

// — 2. Bootstrap WordPress (le drop-in SQLite crée la base) -------------------------
e2e_log( 'installation du site WordPress' . ( $MULTISITE ? ' (multisite)' : '' ) . '…' );
$_SERVER['HTTP_HOST']      = 'e2e.local';
$_SERVER['REQUEST_URI']    = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
define( 'WP_INSTALLING', true );
ini_set( 'display_errors', '1' );
error_reporting( E_ALL );
register_shutdown_function( function () {
	$e = error_get_last();
	if ( $e && in_array( (int) $e['type'], array( E_ERROR, E_PARSE, E_COMPILE_ERROR ), true ) ) {
		echo "\n[DERNIERE ERREUR] " . $e['message'] . ' dans ' . $e['file'] . ':' . $e['line'] . "\n";
	}
} );
require_once $wp_dir . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
wp_install( 'E2E', 'admin', 'admin@example.com', true, 'admin12345' );

// — 3. WooCommerce requis par le plugin (installation via le vrai upgrader) ---------
e2e_log( 'installation de WooCommerce…' );
$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
$wc_result = $upgrader->install( 'https://downloads.wordpress.org/plugin/woocommerce.latest-stable.zip' );
if ( true !== $wc_result ) {
	e2e_fail( 'installation de WooCommerce impossible : ' . implode( ' | ', $upgrader->skin->get_upgrade_messages() ) );
}
activate_plugin( 'woocommerce/woocommerce.php' );

// — 4. Plugin fictif ancien (0.0.1) — même code, version réécrite -------------------
e2e_log( 'construction du plugin fictif v0.0.1…' );
$plugin_src = $ROOT . '/infinitycod';
$plugin_tmp = $work . 'infinitycod-src';
e2e_recursive_copy( $plugin_src, $plugin_tmp );
$main_file = $plugin_tmp . '/infinitycod.php';
$main_src  = (string) file_get_contents( $main_file );
$marker    = "define( 'INFINITYCOD_VERSION', '";
$pos       = strpos( $main_src, $marker );
if ( false === $pos ) {
	e2e_fail( 'constante de version introuvable' );
}
$end          = strpos( $main_src, "'", $pos + strlen( $marker ) );
$main_src     = substr( $main_src, 0, $pos + strlen( $marker ) ) . '0.0.1' . substr( $main_src, $end );
$v_marker     = '* Version:';
$v_pos        = strpos( $main_src, $v_marker );
$v_end        = strpos( $main_src, "\n", $v_pos );
$main_src     = substr( $main_src, 0, $v_pos + strlen( $v_marker ) ) . ' 0.0.1' . substr( $main_src, $v_end );
file_put_contents( $main_file, $main_src );

$dummy_zip = $work . 'infinitycod-dummy.zip';
e2e_zip_dir( $plugin_tmp, $dummy_zip, 'infinitycod/' );
$dummy_result = $upgrader->install( $dummy_zip );
if ( true !== $dummy_result ) {
	e2e_fail( 'installation du plugin fictif impossible' );
}
activate_plugin( 'infinitycod/infinitycod.php' );
if ( $MULTISITE ) {
	activate_plugin( 'infinitycod/infinitycod.php', '', true );
}

// Sortie du mode installation : sans cela WordPress ne charge ni les plugins
// ni wp_update_plugins(). Bootstrap manuel des deux plugins (plugins_loaded
// est déjà consommé — on le re-déclenche après chaque inclusion, l'ordre
// WooCommerce puis InfinityCod respecte la dépendance « Requires Plugins »).
wp_installing( false );
require_once WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
do_action( 'plugins_loaded' );
require_once WP_PLUGIN_DIR . '/infinitycod/infinitycod.php';
do_action( 'plugins_loaded' );
if ( ! class_exists( 'InfinityCod\License\Updater' ) ) {
	e2e_fail( "le module de mise à jour n'est pas chargé après le bootstrap manuel" );
}

// — 5. Offre de mise à jour depuis le vrai serveur -----------------------------------
e2e_log( 'vérification de l\'offre de mise à jour…' );
delete_site_transient( 'update_plugins' );
wp_update_plugins();
$transient = get_site_transient( 'update_plugins' );
if ( empty( $transient->response['infinitycod/infinitycod.php'] ) ) {
	e2e_fail( 'aucune offre de mise à jour proposée' );
}
$latest = (string) $transient->response['infinitycod/infinitycod.php']->new_version;
e2e_log( 'offre détectée : v' . $latest . ' — mise à jour par le vrai upgrader…' );

$skin   = new WP_Ajax_Upgrader_Skin();
$upd    = new Plugin_Upgrader( $skin );
$result = $upd->upgrade( 'infinitycod/infinitycod.php' );
if ( $skin->get_errors()->has_errors() ) {
	e2e_fail( 'erreurs du skin : ' . implode( ' | ', $skin->get_error_messages() ) );
}
if ( ! $result ) {
	e2e_fail( "l'upgrader n'a pas rapporté un succès" );
}

// wp-admin réactive le plugin après la mise à jour (deactivate_plugin_before_
// upgrade l'a désactivé silencieusement) — reproduire ce comportement.
if ( ! is_plugin_active( 'infinitycod/infinitycod.php' ) ) {
	activate_plugin( 'infinitycod/infinitycod.php', '', $MULTISITE );
}

// — 6. Assertions ---------------------------------------------------------------------
$plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/infinitycod/infinitycod.php' );
if ( ( $plugin_data['Version'] ?? '' ) !== $latest ) {
	e2e_fail( 'version installée ' . ( $plugin_data['Version'] ?? '?' ) . ' != ' . $latest );
}
if ( ! is_plugin_active( 'infinitycod/infinitycod.php' ) ) {
	e2e_fail( 'le plugin n\'est plus actif après la mise à jour' );
}
if ( is_file( $fatal_log ) && 0 !== filesize( $fatal_log ) ) {
	e2e_fail( 'fatal PHP capturé : ' . substr( (string) file_get_contents( $fatal_log ), 0, 400 ) );
}

$seconds = (int) round( microtime( true ) - $t0 );
echo "✓ E2E : mise à jour 0.0.1 → v{$latest} installée, plugin actif, zéro fatal ({$seconds} s)\n";
if ( ! $KEEP ) {
	e2e_recursive_delete( $work );
} else {
	echo '  dossier conservé : ' . $work . "\n";
}
exit( 0 );

// — Outils ----------------------------------------------------------------------------

/** Copie récursive simple. */
function e2e_recursive_copy( string $src, string $dst ): void {
	mkdir( $dst, 0777, true );
	foreach ( scandir( $src ) as $entry ) {
		if ( '.' === $entry || '..' === $entry ) {
			continue;
		}
		$s = $src . '/' . $entry;
		$d = $dst . '/' . $entry;
		if ( is_dir( $s ) ) {
			e2e_recursive_copy( $s, $d );
		} else {
			copy( $s, $d );
		}
	}
}

/** Suppression récursive simple. */
function e2e_recursive_delete( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	foreach ( scandir( $dir ) as $entry ) {
		if ( '.' === $entry || '..' === $entry ) {
			continue;
		}
		$path = $dir . '/' . $entry;
		if ( is_dir( $path ) ) {
			e2e_recursive_delete( $path );
		} else {
			unlink( $path );
		}
	}
	rmdir( $dir );
}

/** Zip un dossier sous un préfixe (structure zip identique au build officiel). */
function e2e_zip_dir( string $src, string $zip_path, string $prefix ): void {
	$zip = new ZipArchive();
	$zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );
	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src ) );
	foreach ( $iterator as $file ) {
		if ( $file->isDir() ) {
			continue;
		}
		$zip->addFile( (string) $file, $prefix . substr( (string) $file, strlen( $src ) + 1 ) );
	}
	$zip->close();
}
