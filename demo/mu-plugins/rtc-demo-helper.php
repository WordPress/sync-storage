<?php
/**
 * Demo helper for Playground blueprints.
 *
 * Ships only with the Playground demo blueprints and is not part of the plugin.
 *
 * @package Sync_Storage_Demo
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Send the demo's landing page to the post the peers were seeded into.
 *
 * The blueprint cannot name that post. Which one gets seeded is decided
 * while the seeder runs, long after the blueprint was written, so the
 * landing page asks for the demo by name and this resolves it. Landing on
 * post-new.php instead opens a fresh auto-draft, which is a different room
 * from the seeded one: an empty editor with the peers nowhere in sight.
 */
function sync_storage_demo_open_seeded_post() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only redirect, no state change.
	if ( ! isset( $_GET['sync-storage-demo'] ) ) {
		return;
	}

	$demo = get_option( 'sync_storage_demo_entries' );

	if ( ! $demo || empty( $demo['post_id'] ) ) {
		return;
	}

	$edit_link = get_edit_post_link( (int) $demo['post_id'], 'url' );

	if ( ! $edit_link ) {
		return;
	}

	wp_safe_redirect( $edit_link );
	exit;
}
add_action( 'admin_init', 'sync_storage_demo_open_seeded_post' );

/**
 * Re-stamp seeded awareness entries before the sync server expires them.
 *
 * The server drops entries older than AWARENESS_TIMEOUT. Seeded collaborators
 * are faked peers with no polling client refreshing their own state, so
 * without this they vanish once that elapses.
 *
 * Throttled, because the poll runs about once a second and the 40-collaborator
 * cell would otherwise do 40 presence writes per request. That is load no real
 * room of that size generates -- each of those peers would be refreshing only
 * its own entry -- and it would show up as the demo being slower than the
 * architecture it is demonstrating. One batch per interval is enough to keep
 * every entry inside the window.
 *
 * @param mixed           $result  Response to return (unmodified).
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Request object.
 * @return mixed Unmodified result.
 */
function sync_storage_demo_refresh_awareness( $result, $server, $request ) {
	if ( false === strpos( $request->get_route(), '/wp-sync/' ) ) {
		return $result;
	}

	$demo = get_option( 'sync_storage_demo_entries' );

	if ( ! $demo || ! isset( $demo['room'], $demo['entries'] ) ) {
		return $result;
	}

	// Read from the server rather than repeating its number, so a change there
	// cannot leave the demo re-stamping too late to matter.
	$timeout = defined( 'WP_HTTP_Polling_Sync_Server::AWARENESS_TIMEOUT' )
		? (int) WP_HTTP_Polling_Sync_Server::AWARENESS_TIMEOUT
		: 30;

	// A third of the window in hand covers a slow request landing between two
	// re-stamps.
	$interval = max( 1, (int) floor( $timeout * 2 / 3 ) );
	$last     = (int) get_option( 'sync_storage_demo_last_refresh', 0 );

	if ( time() - $last < $interval ) {
		return $result;
	}

	update_option( 'sync_storage_demo_last_refresh', time(), false );

	$room    = $demo['room'];
	$entries = $demo['entries'];

	foreach ( $entries as $entry ) {
		if ( ! isset( $entry['client_id'], $entry['state'], $entry['wp_user_id'] ) ) {
			continue;
		}

		if ( function_exists( 'wp_set_presence' ) ) {
			wp_set_presence(
				$room,
				'sync-' . $entry['client_id'],
				$entry['state'],
				$entry['wp_user_id']
			);
		}
	}

	return $result;
}
add_filter( 'rest_pre_dispatch', 'sync_storage_demo_refresh_awareness', 10, 3 );

/**
 * Raise the max clients per room so the seeded peers do not block the viewer.
 *
 * The default ceiling is 3 (DEFAULT_CLIENT_LIMIT_PER_ROOM). The seeded peers
 * count against it, so a real viewer cannot connect unless it is raised above
 * the largest blueprint's seed count.
 */
function sync_storage_demo_raise_client_limit() {
	$script = "wp.hooks.addFilter( 'sync.pollingProvider.maxClientsPerRoom', 'sync-storage-demo', () => 50 );";

	wp_enqueue_script( 'sync-storage-demo-client-limit', '', array( 'wp-hooks' ), false, true );
	wp_add_inline_script( 'sync-storage-demo-client-limit', $script );
}
add_action( 'enqueue_block_editor_assets', 'sync_storage_demo_raise_client_limit' );
