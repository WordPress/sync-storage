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
 * Replace Gutenberg's post meta storage with dedicated table storage.
 *
 * Uses wp_collaboration table for both CRDT updates and awareness state,
 * eliminating cache invalidation from post meta storage.
 *
 * Filter added in Gutenberg PR #81697.
 */
add_filter(
	'__unstable_wp_sync_storage',
	function ( $default_storage ) {
		RTC_Logger::event(
			'Filter hooked: __unstable_wp_sync_storage',
			array(
				'default' => get_class( $default_storage ),
				'custom'  => 'RTC_Presence_Storage',
			)
		);

		return new RTC_Presence_Storage();
	}
);
