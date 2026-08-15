<?php
/**
 * Plugin Name: Realtime Collaboration
 * Description: Storage layer for real-time collaborative editing in WordPress.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Requires Plugins: presence-api, gutenberg
 * Author: WordPress Core Team
 * Author URI: https://make.wordpress.org/core/
 * Text Domain: realtime-collaboration
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Realtime_Collaboration
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
			echo esc_html__( 'Realtime Collaboration requires WordPress 7.0 or later.', 'realtime-collaboration' );
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
			echo esc_html__( 'Realtime Collaboration requires the Presence API plugin.', 'realtime-collaboration' );
			echo '</p></div>';
		}
	);
	return;
}

// Check for Gutenberg RTC experiment.
// Note: gutenberg_sync_storage filter doesn't exist yet - needs Gutenberg PR.
if ( ! defined( 'GUTENBERG_VERSION' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Realtime Collaboration requires the Gutenberg plugin.', 'realtime-collaboration' );
			echo '</p></div>';
		}
	);
	return;
}

// Warn if Gutenberg doesn't have storage filter support yet.
// This check will pass once Gutenberg adds apply_filters( 'gutenberg_sync_storage', ... ).
add_action(
	'admin_notices',
	function () {
		// Only show this notice if we haven't hooked the filter yet.
		if ( ! has_filter( 'gutenberg_sync_storage' ) ) {
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'Realtime Collaboration is installed but inactive. Gutenberg needs gutenberg_sync_storage filter support (coming in a future release).', 'realtime-collaboration' );
			echo '</p></div>';
		}
	}
);

define( 'WP_REALTIME_COLLABORATION_VERSION', '0.1.0' );
define( 'WP_REALTIME_COLLABORATION_DB_VERSION', 1 );
define( 'WP_REALTIME_COLLABORATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_REALTIME_COLLABORATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Registers the collaboration table name on $wpdb.
 */
function wp_realtime_collaboration_register_table() {
	global $wpdb;
	$wpdb->collaboration = $wpdb->prefix . 'collaboration';
	$wpdb->tables[]      = 'collaboration';
}
wp_realtime_collaboration_register_table();

require_once WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'lib/class-rtc-presence-storage.php';
require_once WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'lib/gutenberg-integration.php';
require_once WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'lib/server-authority.php';
require_once WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'lib/install.php';
require_once WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'lib/cleanup.php';
require_once WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'lib/migration.php';
