<?php
/**
 * Composite storage: Awareness via Presence API, CRDT updates via wp_collaboration.
 *
 * @package Realtime_Collaboration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage implementation that delegates awareness to Presence API
 * and CRDT updates to wp_collaboration table.
 *
 * Implements Gutenberg_Sync_Storage interface (when available).
 */
class RTC_Presence_Storage implements Gutenberg_Sync_Storage {

	/**
	 * Validate user can access room.
	 *
	 * @param string $room Room identifier (e.g., postType/post:42).
	 * @return bool True if user can collaborate in this room.
	 */
	private function validate_access( $room ) {
		// Validate room format: postType/type:id.
		if ( ! preg_match( '/^postType\/([a-z0-9_-]+):(\d+)$/i', $room, $matches ) ) {
			return false;
		}

		$post_id = (int) $matches[2];
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Get awareness state from Presence API.
	 *
	 * Maps wp_presence entries to Gutenberg awareness format.
	 *
	 * @param string $room Room identifier.
	 * @return array Awareness state keyed by client_id.
	 */
	public function get_awareness_state( $room ) {
		if ( ! $this->validate_access( $room ) ) {
			return array();
		}

		if ( ! function_exists( 'wp_get_presence' ) ) {
			return array();
		}

		$entries = wp_get_presence( $room );

		return array_reduce(
			$entries,
			function ( $acc, $entry ) {
				// Map Presence API entry to awareness state.
				$acc[ $entry->client_id ] = array(
					'user'   => array(
						'id'     => $entry->user_id,
						'name'   => $entry->data['display_name'] ?? '',
						'avatar' => $entry->data['avatar_url'] ?? '',
					),
					'cursor' => $entry->data['cursor'] ?? null,
				);
				return $acc;
			},
			array()
		);
	}

	/**
	 * Set awareness state via Presence API.
	 *
	 * Delegates to wp_set_presence() for zero cache side effects.
	 *
	 * @param string $room Room identifier.
	 * @param string $client_id Client identifier.
	 * @param array  $state Awareness state.
	 * @return bool True on success.
	 */
	public function set_awareness_state( $room, $client_id, $state ) {
		if ( ! function_exists( 'wp_set_presence' ) ) {
			return false;
		}

		// Extract user info from awareness state.
		$user_id = $state['user']['id'] ?? get_current_user_id();

		wp_set_presence(
			$room,
			$client_id,
			array(
				'display_name' => $state['user']['name'] ?? '',
				'avatar_url'   => $state['user']['avatar'] ?? '',
				'cursor'       => $state['cursor'] ?? null,
				'rtc_active'   => true,
			),
			$user_id
		);

		return true;
	}

	/**
	 * Add CRDT update to wp_collaboration table.
	 *
	 * @param string $room Room identifier.
	 * @param array  $update Update data with client_id, type, data, timestamp.
	 * @return bool True on success.
	 */
	public function add_update( $room, $update ) {
		if ( ! $this->validate_access( $room ) ) {
			return false;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->collaboration,
			array(
				'room'      => $room,
				'client_id' => $update['client_id'],
				'type'      => $update['type'],
				'data'      => $update['data'],
				'timestamp' => $update['timestamp'],
			),
			array( '%s', '%d', '%s', '%s', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get CRDT updates from wp_collaboration table.
	 *
	 * @param string $room Room identifier.
	 * @param int    $after_cursor Timestamp cursor (only return updates after this).
	 * @return array Array of updates.
	 */
	public function get_updates( $room, $after_cursor = 0 ) {
		if ( ! $this->validate_access( $room ) ) {
			return array();
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT client_id, type, data, timestamp
				 FROM {$wpdb->collaboration}
				 WHERE room = %s AND timestamp > %d
				 ORDER BY timestamp ASC",
				$room,
				$after_cursor
			),
			ARRAY_A
		);

		return $results ? $results : array();
	}

	/**
	 * Compact updates (clear old, store compaction).
	 *
	 * @param string $room Room identifier.
	 * @param array  $compaction_update Compaction update data.
	 * @return bool True on success.
	 */
	public function compact_updates( $room, $compaction_update ) {
		if ( ! $this->validate_access( $room ) ) {
			return false;
		}

		global $wpdb;

		// Delete all updates for this room.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->collaboration,
			array( 'room' => $room ),
			array( '%s' )
		);

		// Store the compaction as single update.
		return $this->add_update( $room, $compaction_update );
	}
}
