<?php
/**
 * Installation: Create wp_collaboration table and schedule cleanup.
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_activation_hook( WP_SYNC_STORAGE_PLUGIN_DIR . 'sync-storage.php', 'sync_storage_install' );

/**
 * Create wp_collaboration table on activation.
 */
function sync_storage_install() {
	global $wpdb;

	Sync_Storage_Logger::event( 'Installation started' );

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	// Create collaboration table (per-site, not global).
	// Stores CRDT updates only (awareness lives in wp_presence via Presence API).
	// `timestamp` is milliseconds since epoch (matches Yjs) -- see Sync_Storage_Provider::current_time_ms().
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

	Sync_Storage_Logger::event(
		'Table created',
		array(
			'table'   => $wpdb->collaboration,
			'charset' => $charset_collate,
		)
	);

	update_option( 'sync_storage_db_version', WP_SYNC_STORAGE_DB_VERSION );

	// Schedule cleanup cron.
	if ( ! wp_next_scheduled( 'sync_storage_cleanup_stale_updates' ) ) {
		wp_schedule_event( time(), 'daily', 'sync_storage_cleanup_stale_updates' );
		Sync_Storage_Logger::event( 'Cleanup cron scheduled' );
	}

	// Migrate from post meta if needed.
	sync_storage_migrate_post_meta();

	Sync_Storage_Logger::event( 'Installation complete' );
}

/**
 * Multisite: Activate on newly created sites.
 */
if ( is_multisite() ) {
	add_action( 'wpmu_new_blog', 'sync_storage_install_new_site', 10, 1 );

	/**
	 * Install on newly created multisite site.
	 *
	 * @param int $blog_id Site ID.
	 */
	function sync_storage_install_new_site( $blog_id ) {
		switch_to_blog( $blog_id );
		sync_storage_install();
		restore_current_blog();
	}
}
