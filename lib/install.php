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

	RTC_Logger::event( 'Installation started' );

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	// Create collaboration table (per-site, not global).
	// Stores CRDT updates only (awareness lives in wp_presence via Presence API).
	dbDelta(
		"CREATE TABLE {$wpdb->collaboration} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			room varchar(191) NOT NULL,
			type varchar(20) DEFAULT NULL,
			data longtext NOT NULL,
			timestamp bigint(20) unsigned NOT NULL,
			PRIMARY KEY (id),
			KEY room_id (room(50), id),
			KEY room_timestamp (room(50), timestamp)
		) $charset_collate;"
	);

	RTC_Logger::event(
		'Table created',
		array(
			'table'   => $wpdb->collaboration,
			'charset' => $charset_collate,
		)
	);

	update_option( 'rtc_collaboration_db_version', WP_REALTIME_COLLABORATION_DB_VERSION );

	// Schedule cleanup cron.
	if ( ! wp_next_scheduled( 'rtc_cleanup_stale_updates' ) ) {
		wp_schedule_event( time(), 'daily', 'rtc_cleanup_stale_updates' );
		RTC_Logger::event( 'Cleanup cron scheduled' );
	}

	// Migrate from post meta if needed.
	rtc_migrate_post_meta_storage();

	RTC_Logger::event( 'Installation complete' );
}

/**
 * Multisite: Activate on newly created sites.
 */
if ( is_multisite() ) {
	add_action( 'wpmu_new_blog', 'rtc_collaboration_install_new_site', 10, 1 );

	/**
	 * Install on newly created multisite site.
	 *
	 * @param int $blog_id Site ID.
	 */
	function rtc_collaboration_install_new_site( $blog_id ) {
		switch_to_blog( $blog_id );
		rtc_collaboration_install();
		restore_current_blog();
	}
}
