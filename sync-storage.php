<?php
/**
 * Plugin Name: Sync Storage
 * Description: WP_Sync_Storage implementation for WordPress collaborative editing.
 * Version: 0.1.11
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Requires Plugins: presence-api
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

define( 'WP_SYNC_STORAGE_VERSION', '0.1.11' );
define( 'WP_SYNC_STORAGE_DB_VERSION', 1 );
define( 'WP_SYNC_STORAGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_SYNC_STORAGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/*
 * Above the guards below, and deliberately.
 *
 * A deactivation hook only fires if the plugin file registered it before it
 * stopped loading, and deactivating is how a site recovers once its
 * environment no longer holds -- Presence API deleted over SFTP, or core
 * rolled back. Registering the teardown behind a check that the environment
 * is intact leaves the daily cron scheduled on exactly the sites that most
 * need it gone. Neither file touches the table or any editor symbol.
 */
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/class-sync-storage-logger.php';
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/deactivate.php';

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

/*
 * Notices, and does not return.
 *
 * Presence API supplies awareness -- who else is in a room and where their
 * cursor is -- and nothing else. Requires Plugins above means activation needs
 * it, but a site can lose it afterwards, and every call into it is already
 * guarded at its call site. Returning here would take the table and its
 * cleanup down with a dependency neither of them uses.
 */
if ( ! function_exists( 'wp_get_presence' ) ) {
	add_action( 'admin_notices', 'sync_storage_presence_missing_notice' );
}

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
 *
 * Only the store is loaded unconditionally. Everything that needs Gutenberg
 * waits for sync_storage_load_collaboration_integration() below: this plugin
 * supplies storage to a collaborative editor, and a storage layer that
 * refuses to install itself unless a particular editor is present is not
 * infrastructure, whatever the layering says.
 */
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/store/schema.php';
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/store/class-sync-storage-store.php';
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/store/cleanup.php';

// Before anything reads $wpdb->collaboration.
wp_sync_storage_register_table();

require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/install.php';

// Presence API's collaboration threshold events. No Gutenberg symbols and no
// Presence calls, only listeners, so this loads whether or not either is
// installed; the actions simply never fire.
require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/rtc/server-authority.php';

Sync_Storage_Logger::event(
	'Store loaded',
	array(
		'version'    => WP_SYNC_STORAGE_VERSION,
		'db_version' => WP_SYNC_STORAGE_DB_VERSION,
	)
);

/**
 * Loads the parts of the plugin that only mean something with Gutenberg.
 *
 * Deferred to plugins_loaded rather than run at file scope: plugins load in
 * the order they appear in active_plugins, not alphabetically, so
 * WP_Sync_Storage is not guaranteed to be declared while this file runs.
 *
 * The interface, not GUTENBERG_VERSION, is the thing being checked. It is
 * what Sync_Storage_Provider implements and what Gutenberg's own
 * instanceof check rejects the provider without, and it moves to core with
 * the feature.
 */
function sync_storage_load_collaboration_integration() {
	if ( ! interface_exists( 'WP_Sync_Storage' ) ) {
		add_action( 'admin_notices', 'sync_storage_editor_missing_notice' );

		Sync_Storage_Logger::event(
			'Collaboration integration inactive',
			array( 'reason' => 'WP_Sync_Storage not declared' )
		);

		return;
	}

	require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/rtc/class-sync-storage-provider.php';
	require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/rtc/integration.php';
	require_once WP_SYNC_STORAGE_PLUGIN_DIR . 'lib/site/experiment.php';

	Sync_Storage_Logger::event(
		'Collaboration integration loaded',
		array( 'gutenberg' => defined( 'GUTENBERG_VERSION' ) ? GUTENBERG_VERSION : false )
	);
}
add_action( 'plugins_loaded', 'sync_storage_load_collaboration_integration' );

/**
 * Reports that awareness is unavailable, not that the store is broken.
 *
 * A warning rather than an error for the same reason the guard above does not
 * return: the table and its cleanup are still working, and collaborative edits
 * still sync. What is missing is seeing who else is in the room.
 */
function sync_storage_presence_missing_notice() {
	echo '<div class="notice notice-warning"><p>';
	echo esc_html__(
		'Sync Storage cannot show who else is editing a post, because the Presence API plugin is not active. Collaborative edits still sync, and the collaboration table is unaffected.',
		'sync-storage'
	);
	echo '</p></div>';
}

/**
 * Reports that the storage is installed but nothing is consuming it.
 *
 * Deliberately not an error: the table, its cleanup and its API are all
 * working. What is missing is a collaborative editor to serve.
 */
function sync_storage_editor_missing_notice() {
	echo '<div class="notice notice-warning"><p>';
	echo esc_html__(
		"Sync Storage installed its collaboration table, but nothing on this site provides WordPress's collaborative editing interface. Activate the Gutenberg plugin to use it as real-time collaboration storage.",
		'sync-storage'
	);
	echo '</p></div>';
}
