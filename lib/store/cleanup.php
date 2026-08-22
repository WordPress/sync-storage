<?php
/**
 * Cleanup: Delete old collaboration updates (safety net).
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'sync_storage_cleanup_stale_updates', 'sync_storage_cleanup_old_updates' );

/**
 * Cleanup old collaboration updates.
 *
 * Compaction should handle most cleanup, but this ensures abandoned rooms
 * don't grow unbounded.
 */
function sync_storage_cleanup_old_updates() {
	// Yjs timestamps are milliseconds, and so is the stored column.
	$cutoff = ( time() - 7 * DAY_IN_SECONDS ) * 1000;

	$total_deleted = Sync_Storage_Store::delete_expired( $cutoff );

	if ( $total_deleted > 0 ) {
		Sync_Storage_Logger::event(
			'Cleanup complete',
			array( 'deleted_count' => $total_deleted )
		);
	}
}
