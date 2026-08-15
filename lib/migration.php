<?php
/**
 * Migration: Migrate existing RTC data from post meta to wp_collaboration table.
 *
 * @package Realtime_Collaboration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrate existing RTC data from post meta to wp_collaboration table.
 */
function rtc_migrate_post_meta_storage() {
	// Skip if already migrated.
	if ( get_option( 'rtc_migrated_from_post_meta' ) ) {
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
		update_option( 'rtc_migrated_from_post_meta', true );
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
					'client_id' => $update['client_id'] ?? 0,
					'type'      => $update['type'] ?? 'update',
					'data'      => $update['data'] ?? '',
					'timestamp' => $update['timestamp'] ?? 0,
				),
				array( '%s', '%d', '%s', '%s', '%d' )
			);
			++$migrated;
		}
	}

	update_option( 'rtc_migrated_from_post_meta', true );

	error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		sprintf(
			'RTC Collaboration: Migrated %d updates from post meta to wp_collaboration table',
			$migrated
		)
	);
}
