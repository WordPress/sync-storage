<?php
/**
 * Plugin Name: Realtime Collaboration
 * Description: Storage layer for real-time collaborative editing in WordPress.
 * Version: 0.1.1
 * Requires at least: 7.0
 * Requires PHP: 7.4
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

// Check for Gutenberg with __unstable_wp_sync_storage filter support.
if ( ! defined( 'GUTENBERG_VERSION' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'Realtime Collaboration requires the Gutenberg plugin (trunk or later).', 'realtime-collaboration' );
			echo '</p></div>';
		}
	);
	return;
}

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

require_once WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'lib/class-rtc-logger.php';
require_once WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'lib/class-rtc-presence-storage.php';
require_once WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'lib/gutenberg-integration.php';
require_once WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'lib/server-authority.php';
require_once WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'lib/install.php';
require_once WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'lib/cleanup.php';
require_once WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'lib/migration.php';

RTC_Logger::event(
	'Plugin loaded',
	array(
		'version'    => WP_REALTIME_COLLABORATION_VERSION,
		'db_version' => WP_REALTIME_COLLABORATION_DB_VERSION,
		'presence'   => function_exists( 'wp_get_presence' ),
		'gutenberg'  => defined( 'GUTENBERG_VERSION' ) ? GUTENBERG_VERSION : false,
	)
);
