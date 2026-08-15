<?php
/**
 * Installation: Create wp_collaboration table and schedule cleanup.
 *
 * @package Realtime_Collaboration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_activation_hook( WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'realtime-collaboration.php', 'rtc_collaboration_install' );

/**
 * Create wp_collaboration table on activation.
 */
function rtc_collaboration_install() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	// Create collaboration table (per-site, not global).
	dbDelta(
		"CREATE TABLE {$wpdb->collaboration} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			room varchar(191) NOT NULL,
			client_id bigint(20) unsigned NOT NULL,
			type varchar(20) NOT NULL,
			data longtext NOT NULL,
			timestamp bigint(20) unsigned NOT NULL,
			PRIMARY KEY (id),
			KEY room_timestamp (room(50), timestamp)
		) $charset_collate;"
	);

	update_option( 'rtc_collaboration_db_version', WP_REALTIME_COLLABORATION_DB_VERSION );

	// Schedule cleanup cron.
	if ( ! wp_next_scheduled( 'rtc_cleanup_stale_updates' ) ) {
		wp_schedule_event( time(), 'daily', 'rtc_cleanup_stale_updates' );
	}

	// Migrate from post meta if needed.
	rtc_migrate_post_meta_storage();
}

/**
 * Multisite network activation.
 */
if ( is_multisite() ) {
	register_activation_hook( WP_REALTIME_COLLABORATION_PLUGIN_DIR . 'realtime-collaboration.php', 'rtc_collaboration_network_activate' );

	/**
	 * Activate on all sites in network.
	 *
	 * @param bool $network_wide Whether network-wide activation.
	 */
	function rtc_collaboration_network_activate( $network_wide ) {
		if ( ! $network_wide ) {
			rtc_collaboration_install();
			return;
		}

		$sites = get_sites( array( 'number' => 10000 ) );
		foreach ( $sites as $site ) {
			switch_to_blog( $site->blog_id );
			rtc_collaboration_install();
			restore_current_blog();
		}
	}
}
