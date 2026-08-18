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

/**
 * Treats activating Sync Storage (with Presence API and Gutenberg both
 * present, already checked before this file loads) as opting in to
 * Gutenberg's real-time collaboration experiment, rather than requiring a
 * second, easy-to-miss toggle on the Experiments settings screen. Merges
 * onto whatever's already stored instead of overwriting it, so other
 * experiments a site has enabled are left untouched.
 *
 * Hooked on both option_* and default_option_*: WordPress only fires
 * option_$name when a row already exists for it. A site that has never
 * opened the Experiments screen has no such row, so get_option() takes the
 * "does not exist" branch and fires default_option_$name instead.
 */
$sync_storage_enable_rtc_experiment = function ( $value ) {
	if ( ! is_array( $value ) ) {
		$value = array();
	}

	$value['gutenberg-real-time-collaboration'] = true;

	return $value;
};
add_filter( 'option_gutenberg-experiments', $sync_storage_enable_rtc_experiment );
add_filter( 'default_option_gutenberg-experiments', $sync_storage_enable_rtc_experiment );
