<?php
/**
 * Gutenberg integration: Hook storage filter to replace post meta storage.
 *
 * @package Sync_Storage
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
		Sync_Storage_Logger::event(
			'Filter hooked: __unstable_wp_sync_storage',
			array(
				'default' => get_class( $default_storage ),
				'custom'  => 'Sync_Storage_Provider',
			)
		);

		return new Sync_Storage_Provider();
	}
);
