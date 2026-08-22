<?php
/**
 * Plugin Name: Sync Storage
 * Description: WP_Sync_Storage implementation for WordPress collaborative editing.
 * Version: 0.1.7
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

// Check for the Gutenberg plugin. Whether it actually calls
// __unstable_wp_sync_storage is checked separately, in
// sync_storage_collaboration_filter_supported().
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

define( 'WP_SYNC_STORAGE_VERSION', '0.1.7' );
define( 'WP_SYNC_STORAGE_DB_VERSION', 1 );
define( 'WP_SYNC_STORAGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_SYNC_STORAGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/class-sync-storage-logger.php';

/*
 * The plugin is three layers, and lib/ is laid out to match.
 *
 * store/  A room-scoped, append-only, expiring log over wp_collaboration.
 *         Knows nothing about Gutenberg, Presence API or Yjs, and holds
 *         every query against the table.
 * rtc/    The adapter that makes that store Gutenberg's collaborative
 *         editing backend, plus the awareness delegation to Presence API.
 * site/   What activating the plugin implies for a site's settings. No
 *         storage logic.
 *
 * Dependencies point one way: rtc/ calls store/, and store/ never calls
 * back. Anything reaching for $wpdb outside store/ is a layering mistake.
 */
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/store/schema.php';
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/store/class-sync-storage-store.php';
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/store/cleanup.php';

// Before anything reads $wpdb->collaboration.
wp_sync_storage_register_table();

require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/rtc/class-sync-storage-provider.php';
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/rtc/integration.php';
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/rtc/server-authority.php';

require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/site/experiment.php';

require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/install.php';
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
