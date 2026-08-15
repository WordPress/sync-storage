<?php
/**
 * Gutenberg integration: Hook storage filter to replace post meta storage.
 *
 * @package Realtime_Collaboration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replace Gutenberg's post meta storage with composite Presence API storage.
 *
 * This filter doesn't exist yet - requires Gutenberg PR.
 * See: https://github.com/WordPress/gutenberg/issues/80387
 */
add_filter(
	'gutenberg_sync_storage',
	function ( $default_storage ) {
		// Only replace if Presence API is active.
		if ( ! function_exists( 'wp_get_presence' ) ) {
			return $default_storage;
		}

		// Use our composite storage.
		return new RTC_Presence_Storage();
	}
);
