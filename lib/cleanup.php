<?php
/**
 * Cleanup: Delete old collaboration updates (safety net).
 *
 * @package Realtime_Collaboration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rtc_cleanup_stale_updates', 'rtc_cleanup_old_updates' );

/**
 * Cleanup old collaboration updates.
 *
 * Compaction should handle most cleanup, but this ensures
 * abandoned rooms don't grow unbounded.
 */
function rtc_cleanup_old_updates() {
	global $wpdb;

	// Delete updates older than 7 days (Yjs uses milliseconds).
	$cutoff = ( time() - 7 * DAY_IN_SECONDS ) * 1000;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$deleted = $wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->collaboration}
			 WHERE timestamp < %d
			 LIMIT 1000",
			$cutoff
		)
	);

	if ( $deleted > 0 ) {
		RTC_Logger::event(
			'Cleanup complete',
			array( 'deleted_count' => $deleted )
		);
	}
}
