<?php
// Test bed local InfinityCod — SQLite via wp-content/db.php (drop-in performance team).
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

define( 'AUTH_KEY',         'tb+ic0d/auth/2026/x7Kq2LmV' );
define( 'SECURE_AUTH_KEY',  'tb+ic0d/sauth/2026/p9Rt4NwZ' );
define( 'LOGGED_IN_KEY',    'tb+ic0d/logged/2026/h3Ys8BcA' );
define( 'NONCE_KEY',        'tb+ic0d/nonce/2026/j5Ue1FdG' );
define( 'AUTH_SALT',        'tb+ic0d/asalt/2026/k7Wg0HqT' );
define( 'SECURE_AUTH_SALT', 'tb+ic0d/ssalt/2026/m2Zr6JvB' );
define( 'LOGGED_IN_SALT',   'tb+ic0d/lsalt/2026/n8Xc3KdF' );
define( 'NONCE_SALT',       'tb+ic0d/nsalt/2026/q1Tv9LmS' );

$table_prefix = 'wp_';

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'DISABLE_WP_CRON', true );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
