<?php
/**
 * Migration: Migrate existing RTC data from post meta to wp_collaboration table.
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrate existing RTC data from post meta to wp_collaboration table.
 */
function sync_storage_migrate_post_meta() {
	// Skip if already migrated.
	if ( get_option( 'sync_storage_migrated_from_post_meta' ) ) {
		return;
	}

	// Find all wp_sync_storage posts (old RTC storage).
	$old_posts = get_posts(
		array(
			'post_type'   => 'wp_sync_storage',
			'numberposts' => -1,
			'post_status' => 'any',
		)
	);

	if ( empty( $old_posts ) ) {
		update_option( 'sync_storage_migrated_from_post_meta', true );
		return;
	}

	global $wpdb;
	$migrated = 0;

	foreach ( $old_posts as $post ) {
		// Extract updates from post meta.
		$updates = get_post_meta( $post->ID, 'rtc_updates', true );

		if ( ! is_array( $updates ) ) {
			continue;
		}

		foreach ( $updates as $update ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$wpdb->collaboration,
				array(
					'room'      => $update['room'] ?? '',
					'data'      => wp_json_encode( $update ),
					'timestamp' => $update['timestamp'] ?? Sync_Storage_Provider::current_time_ms(),
				),
				array( '%s', '%s', '%d' )
			);
			++$migrated;
		}
	}

	update_option( 'sync_storage_migrated_from_post_meta', true );

	Sync_Storage_Logger::event(
		'Migration complete',
		array( 'migrated_count' => $migrated )
	);
}
