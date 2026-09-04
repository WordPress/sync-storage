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
 * Fingerprints the installed Gutenberg build, for use as a cache key.
 *
 * GUTENBERG_VERSION on its own can't tell a trunk build from the release it
 * was branched from: trunk carries the last released number in its plugin
 * header until the next release bumps it, so the two report the same version.
 * A site that swapped one for the other would keep the cached answer from the
 * build it replaced -- and this repository swaps exactly that way, between the
 * release .wp-env.json pins and the trunk build the nightly runs. The
 * modification time of the file that would apply the filter moves with any
 * such swap.
 *
 * The constant is guarded because WP_Sync_Storage moves to core with the
 * feature, so the interface can be declared with no Gutenberg plugin
 * installed. Core's version stands in, and the mtime falls to 0.
 *
 * @global string $wp_version
 *
 * @return string Version and mtime of the collaboration bootstrap.
 */
function sync_storage_gutenberg_build_id() {
	global $wp_version;

	$mtime = 0;

	if ( function_exists( 'gutenberg_register_collaboration_rest_routes' ) ) {
		try {
			$file  = ( new ReflectionFunction( 'gutenberg_register_collaboration_rest_routes' ) )->getFileName();
			$mtime = $file ? (int) filemtime( $file ) : 0;
		} catch ( ReflectionException $e ) {
			$mtime = 0;
		}
	}

	$version = defined( 'GUTENBERG_VERSION' ) ? GUTENBERG_VERSION : 'core-' . $wp_version;

	return $version . ':' . $mtime;
}

/**
 * Determines whether this Gutenberg build actually calls
 * __unstable_wp_sync_storage, caching the result against the installed
 * Gutenberg build so the check reruns whenever that build changes rather
 * than on any arbitrary schedule.
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
	$cached   = get_option( 'sync_storage_filter_check' );
	$build_id = sync_storage_gutenberg_build_id();

	if ( is_array( $cached ) && ( $cached['gutenberg_build'] ?? null ) === $build_id ) {
		return $cached['supported'];
	}

	rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/' ) );

	$supported = sync_storage_filter_fired();

	update_option(
		'sync_storage_filter_check',
		array(
			'gutenberg_build' => $build_id,
			'supported'       => $supported,
		),
		false
	);

	return $supported;
}

/**
 * Warns when this Gutenberg build never calls __unstable_wp_sync_storage.
 *
 * The filter arrived in Gutenberg 23.9.0. On anything older, real-time
 * collaboration silently falls back to Gutenberg's default post meta
 * storage: no error, no fatal, just every write going to the exact
 * cache-thrashing storage this plugin exists to replace.
 */
add_action(
	'admin_notices',
	function () {
		if ( sync_storage_collaboration_filter_supported() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__(
			"Sync Storage: this Gutenberg build doesn't call the __unstable_wp_sync_storage filter, so real-time collaboration is silently using Gutenberg's default post meta storage instead of this plugin's dedicated tables. Update Gutenberg to 23.9.0 or later to use the intended storage.",
			'sync-storage'
		);
		echo '</p></div>';
	}
);

/**
 * Warns when Presence API is installed but recording nothing.
 *
 * Switching recording off (Presence API 0.3.0, Settings > General) is a
 * supported choice, and every presence surface it owns empties visibly. This
 * one does not: awareness still round trips through the sync server, which
 * echoes each client its own state back, so the editor looks like it is working
 * and every collaborator is simply invisible to every other one.
 *
 * Loaded with the rest of the integration, so it is only reachable on a site
 * that has an editor to be invisible in. A site with no Presence API at all
 * gets sync_storage_presence_missing_notice() instead, and never both.
 */
add_action(
	'admin_notices',
	function () {
		if ( ! function_exists( 'wp_presence_recording_enabled' ) || wp_presence_recording_enabled() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__(
			"Sync Storage: presence recording is switched off, so collaborators can't see each other in the editor. Collaborative edits still sync. Turn recording back on under Settings > General, or on the network settings screen if this is a multisite network.",
			'sync-storage'
		);
		echo '</p></div>';
	}
);
