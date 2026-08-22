<?php
/**
 * Site policy: opt the site into Gutenberg's real-time collaboration experiment.
 *
 * Not storage. This decides what activating the plugin implies for a site's
 * settings, which is why it sits apart from lib/store/ and lib/rtc/.
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
