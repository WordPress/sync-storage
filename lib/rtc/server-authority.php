<?php
/**
 * Server authority: Server decides when RTC should be active based on Presence API.
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * When collaboration starts (1→2 editors), announce the room as active.
 *
 * Announcing is all this does. Nothing here writes post meta: a post cache
 * invalidation on every threshold crossing is the cost this plugin exists to
 * avoid.
 */
add_action(
	'wp_presence_collaboration_started',
	function ( $room, $entries ) {
		// Extract post ID from room name (postType/post:42).
		if ( ! preg_match( '/postType\/\w+:(\d+)/', $room, $matches ) ) {
			return;
		}

		do_action( 'sync_storage_room_active', (int) $matches[1], $entries );
	},
	10,
	2
);

/**
 * When collaboration ends (2→1 editors), announce the room as inactive.
 */
add_action(
	'wp_presence_collaboration_ended',
	function ( $room, $entries ) {
		if ( ! preg_match( '/postType\/\w+:(\d+)/', $room, $matches ) ) {
			return;
		}

		do_action( 'sync_storage_room_inactive', (int) $matches[1], $entries );
	},
	10,
	2
);
