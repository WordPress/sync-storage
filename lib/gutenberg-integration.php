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
 * Tracks, within the current process, whether Gutenberg has actually called
 * __unstable_wp_sync_storage.
 *
 * A plain file-scope variable isn't reliable here: WP-CLI's bootstrap loads
 * plugin files in a different scope than a normal request does, so a
 * top-level $var = ...; in this file isn't guaranteed to be the same
 * variable `global $var` reaches elsewhere. A static local variable inside
 * a dedicated function has one unambiguous scope no matter how this file
 * was loaded.
 *
 * @param bool $set Pass true to record that the filter fired.
 * @return bool Whether the filter has fired yet this process.
 */
function sync_storage_filter_fired( $set = false ) {
	static $fired = false;

	if ( $set ) {
		$fired = true;
	}

	return $fired;
}

/**
 * Replace Gutenberg's post meta storage with dedicated table storage.
 *
 * CRDT updates go in the wp_collaboration table. Awareness state is
 * delegated to the Presence API. Neither touches post meta, so writes
 * don't invalidate post caches.
 *
 * Filter added in Gutenberg PR #81697.
 */
add_filter(
	'__unstable_wp_sync_storage',
	function ( $default_storage ) {
		sync_storage_filter_fired( true );

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
 * Determines whether this Gutenberg build actually calls
 * __unstable_wp_sync_storage, caching the result against the installed
 * Gutenberg version so the check reruns whenever that version changes
 * rather than on any arbitrary schedule.
 *
 * The rest_api_init hook, where Gutenberg would apply the filter, only fires for
 * requests actually routed to /wp-json/, so an admin page that doesn't
 * happen to make a REST call during its own load can't rely on
 * sync_storage_filter_fired() having a result yet by the time admin_notices
 * runs. Dispatching a request here forces that result instead of hoping one
 * already happened elsewhere in the same request.
 *
 * @return bool Whether this Gutenberg build supports the filter.
 */
function sync_storage_collaboration_filter_supported() {
	$cached = get_option( 'sync_storage_filter_check' );

	if ( is_array( $cached ) && GUTENBERG_VERSION === ( $cached['gutenberg_version'] ?? null ) ) {
		return $cached['supported'];
	}

	rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/' ) );

	$supported = sync_storage_filter_fired();

	update_option(
		'sync_storage_filter_check',
		array(
			'gutenberg_version' => GUTENBERG_VERSION,
			'supported'         => $supported,
		),
		false
	);

	return $supported;
}

/**
 * Warns when this Gutenberg build never calls __unstable_wp_sync_storage.
 *
 * The filter isn't in any tagged Gutenberg release yet, only trunk. Without
 * it, real-time collaboration silently falls back to Gutenberg's default
 * post meta storage: no error, no fatal, just every write going to the
 * exact cache-thrashing storage this plugin exists to replace.
 */
add_action(
	'admin_notices',
	function () {
		if ( sync_storage_collaboration_filter_supported() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__(
			"Sync Storage: this Gutenberg build doesn't call the __unstable_wp_sync_storage filter, so real-time collaboration is silently using Gutenberg's default post meta storage instead of this plugin's dedicated tables. The filter isn't in any tagged Gutenberg release yet; build Gutenberg from trunk to use the intended storage.",
			'sync-storage'
		);
		echo '</p></div>';
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
