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
 * Re-stamp seeded awareness entries on every sync poll.
 *
 * The sync server drops entries older than 30 seconds (WP_HTTP_Polling_Sync_Server::AWARENESS_TIMEOUT).
 * Seeded collaborators are faked peers with no polling client refreshing their
 * own state, so without this they vanish after half a minute.
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

	if ( ! function_exists( 'wp_set_presence' ) ) {
		preg_match( '/postType\/post:(\d+)/', $room, $matches );

		if ( ! empty( $matches[1] ) ) {
			$post_id = (int) $matches[1];

			foreach ( $entries as &$entry ) {
				$entry['updated_at'] = time();
			}

			update_post_meta(
				$post_id,
				'wp_sync_awareness_state',
				wp_json_encode( $entries )
			);
		}
	}

	return $result;
}
add_filter( 'rest_pre_dispatch', 'sync_storage_demo_refresh_awareness', 10, 3 );

/**
 * Raise the max clients per room so the seeded peers do not block the viewer.
 *
 * The default ceiling is 3 (DEFAULT_CLIENT_LIMIT_PER_ROOM). With 5 seeded
 * collaborators, a real viewer cannot connect unless the limit is raised.
 */
function sync_storage_demo_raise_client_limit() {
	$script = "wp.hooks.addFilter( 'sync.pollingProvider.maxClientsPerRoom', 'sync-storage-demo', () => 20 );";

	wp_enqueue_script( 'sync-storage-demo-client-limit', '', array( 'wp-hooks' ), false, true );
	wp_add_inline_script( 'sync-storage-demo-client-limit', $script );
}
add_action( 'enqueue_block_editor_assets', 'sync_storage_demo_raise_client_limit' );
