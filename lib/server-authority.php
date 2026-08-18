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
 * When collaboration starts (1→2 editors), flag RTC as active.
 */
add_action(
	'wp_presence_collaboration_started',
	function ( $room, $entries ) {
		// Extract post ID from room name (postType/post:42).
		if ( ! preg_match( '/postType\/\w+:(\d+)/', $room, $matches ) ) {
			return;
		}

		$post_id = (int) $matches[1];

		// Flag that RTC is active for this post.
		update_post_meta( $post_id, '_sync_storage_active', true );

		// Optionally notify via action.
		do_action( 'rtc_room_active', $post_id, $entries );
	},
	10,
	2
);

/**
 * When collaboration ends (2→1 editors), clear RTC flag.
 */
add_action(
	'wp_presence_collaboration_ended',
	function ( $room, $entries ) {
		if ( ! preg_match( '/postType\/\w+:(\d+)/', $room, $matches ) ) {
			return;
		}

		$post_id = (int) $matches[1];

		// Clear RTC flag.
		delete_post_meta( $post_id, '_sync_storage_active' );

		do_action( 'rtc_room_inactive', $post_id, $entries );
	},
	10,
	2
);
