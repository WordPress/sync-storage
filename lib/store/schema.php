<?php
/**
 * Store schema: the wp_collaboration table.
 *
 * Owns the table definition and nothing else. Deciding *when* to create it
 * (activation, a new network site) is lib/install.php's job.
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the collaboration table name on $wpdb.
 *
 * Runs on every request, before anything reads $wpdb->collaboration. Adding
 * it to $wpdb->tables is what makes switch_to_blog() reprefix it, so the
 * store keeps addressing the current site's table on multisite.
 */
function wp_sync_storage_register_table() {
	global $wpdb;
	$wpdb->collaboration = $wpdb->prefix . 'collaboration';
	$wpdb->tables[]      = 'collaboration';
}

/**
 * Creates or updates the collaboration table for the current site.
 *
 * Per-site, not global: rows describe that site's posts, so each site in a
 * network needs its own table.
 *
 * `timestamp` is milliseconds since epoch rather than seconds -- see
 * Sync_Storage_Store::current_time_ms().
 */
function sync_storage_create_table() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

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
}
