<?php
/**
 * Plugin Name: Sync Storage
 * Description: WP_Sync_Storage implementation for WordPress collaborative editing.
 * Version: 0.1.4
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Requires Plugins: presence-api, gutenberg
 * Author: WordPress Core Team
 * Author URI: https://make.wordpress.org/core/
 * Text Domain: sync-storage
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wp_version;
if ( version_compare( $wp_version, '7.0-alpha', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Sync Storage requires WordPress 7.0 or later.', 'sync-storage' );
			echo '</p></div>';
		}
	);
	return;
}

// Check for Presence API dependency.
if ( ! function_exists( 'wp_get_presence' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Sync Storage requires the Presence API plugin.', 'sync-storage' );
			echo '</p></div>';
		}
	);
	return;
}

// Check for Gutenberg with __unstable_wp_sync_storage filter support.
if ( ! defined( 'GUTENBERG_VERSION' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Sync Storage requires the Gutenberg plugin (trunk or later).', 'sync-storage' );
			echo '</p></div>';
		}
	);
	return;
}

define( 'WP_SYNC_STORAGE_VERSION', '0.1.4' );
define( 'WP_SYNC_STORAGE_DB_VERSION', 1 );
define( 'WP_SYNC_STORAGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_SYNC_STORAGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Registers the collaboration table name on $wpdb.
 */
function wp_sync_storage_register_table() {
	global $wpdb;
	$wpdb->collaboration = $wpdb->prefix . 'collaboration';
	$wpdb->tables[]      = 'collaboration';
}
wp_sync_storage_register_table();

require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/class-sync-storage-logger.php';
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/class-sync-storage-provider.php';
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/gutenberg-integration.php';
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/server-authority.php';
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/install.php';
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/cleanup.php';
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/migration.php';

Sync_Storage_Logger::event(
	'Plugin loaded',
	array(
		'version'    => WP_SYNC_STORAGE_VERSION,
		'db_version' => WP_SYNC_STORAGE_DB_VERSION,
		'presence'   => function_exists( 'wp_get_presence' ),
		'gutenberg'  => defined( 'GUTENBERG_VERSION' ) ? GUTENBERG_VERSION : false,
	)
);
