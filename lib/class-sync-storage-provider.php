<?php
/**
 * Composite storage: Awareness via Presence API, CRDT updates via wp_collaboration.
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage implementation that delegates awareness to Presence API
 * and CRDT updates to wp_collaboration table.
 *
 * Implements WP_Sync_Storage interface from Gutenberg.
 */
class Sync_Storage_Provider implements WP_Sync_Storage {

	/**
	 * Cache of cursors by room (last returned update ID).
	 *
	 * @var array<string, int>
	 */
	private array $room_cursors = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		Sync_Storage_Logger::event( 'Storage initialized', array( 'class' => __CLASS__ ) );
	}

	/**
	 * Validate user can access room.
	 *
	 * @param string $room Room identifier (e.g., postType/post:42).
	 * @return bool True if user can collaborate in this room.
	 */
	private function validate_access( string $room ): bool {
		// Validate room format: postType/type:id.
		if ( ! preg_match( '/^postType\/([a-z0-9_-]+):(\d+)$/i', $room, $matches ) ) {
			return false;
		}

		$post_id = (int) $matches[2];
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Get awareness state for a room.
	 *
	 * Delegates to Presence API and transforms to Gutenberg format.
	 *
	 * @param string $room Room identifier.
	 * @return array<int, mixed> Awareness state array (Gutenberg sync server format).
	 */
	public function get_awareness_state( string $room ): array {
		Sync_Storage_Logger::storage( 'get_awareness_state', $room );

		if ( ! function_exists( 'wp_get_presence' ) ) {
			Sync_Storage_Logger::event( 'Presence API not available' );
			return array();
		}

		$entries = wp_get_presence( $room );
		Sync_Storage_Logger::presence( 'wp_get_presence', $room, $entries );

		if ( empty( $entries ) ) {
			return array();
		}

		// Transform presence entries to Gutenberg awareness format.
		// Gutenberg expects: [ {client_id, state, updated_at, wp_user_id}, ... ].
		$awareness = array_map(
			function ( $entry ) {
				return array(
					'client_id'  => $entry->client_id,
					'state'      => $entry->data,
					'updated_at' => $entry->last_seen,
					'wp_user_id' => $entry->user_id,
				);
			},
			$entries
		);

		Sync_Storage_Logger::storage( 'get_awareness_state:result', $room, $awareness );
		return $awareness;
	}

	/**
	 * Set awareness state for a room.
	 *
	 * Delegates to Presence API, transforming from Gutenberg format.
	 *
	 * @param string            $room      Room identifier.
	 * @param array<int, mixed> $awareness Awareness state array (Gutenberg sync server format).
	 * @return bool True on success.
	 */
	public function set_awareness_state( string $room, array $awareness ): bool {
		Sync_Storage_Logger::storage( 'set_awareness_state', $room, $awareness );

		if ( ! function_exists( 'wp_set_presence' ) ) {
			Sync_Storage_Logger::event( 'Presence API not available' );
			return false;
		}

		// Each entry in awareness: {client_id, state, updated_at, wp_user_id}
		// Transform to presence-api format and store each client.
		foreach ( $awareness as $entry ) {
			if ( ! isset( $entry['client_id'], $entry['wp_user_id'] ) ) {
				continue;
			}

			wp_set_presence(
				$room,
				$entry['client_id'],
				$entry['state'] ?? array(),
				$entry['wp_user_id']
			);

			Sync_Storage_Logger::presence(
				'wp_set_presence',
				$room,
				array(
					'client_id' => $entry['client_id'],
					'user_id'   => $entry['wp_user_id'],
				)
			);
		}

		return true;
	}

	/**
	 * Add CRDT update to wp_collaboration table.
	 *
	 * @param string $room   Room identifier.
	 * @param mixed  $update Update data (opaque, serializable).
	 * @return bool True on success.
	 */
	public function add_update( string $room, $update ): bool {
		Sync_Storage_Logger::storage( 'add_update', $room );

		if ( ! $this->validate_access( $room ) ) {
			Sync_Storage_Logger::event( 'Access denied', array( 'room' => $room ) );
			return false;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->collaboration,
			array(
				'room'      => $room,
				'data'      => wp_json_encode( $update ),
				'timestamp' => time(),
			),
			array( '%s', '%s', '%d' )
		);

		$success = false !== $result;
		Sync_Storage_Logger::storage(
			'add_update:result',
			$room,
			array(
				'success'   => $success,
				'insert_id' => $success ? $wpdb->insert_id : null,
			)
		);

		return $success;
	}

	/**
	 * Get current cursor for a room.
	 *
	 * Returns the last update ID returned for this room during current request.
	 *
	 * @param string $room Room identifier.
	 * @return int Current cursor.
	 */
	public function get_cursor( string $room ): int {
		return $this->room_cursors[ $room ] ?? 0;
	}

	/**
	 * Get total number of updates for a room.
	 *
	 * @param string $room Room identifier.
	 * @return int Update count.
	 */
	public function get_update_count( string $room ): int {
		if ( ! $this->validate_access( $room ) ) {
			return 0;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->collaboration}
				 WHERE room = %s AND type IS NULL",
				$room
			)
		);

		return (int) $count;
	}

	/**
	 * Get updates after a given cursor.
	 *
	 * @param string $room   Room identifier.
	 * @param int    $cursor Return updates after this cursor (update ID).
	 * @return array<int, mixed> Updates array.
	 */
	public function get_updates_after_cursor( string $room, int $cursor ): array {
		Sync_Storage_Logger::storage( 'get_updates_after_cursor', $room, array( 'cursor' => $cursor ) );

		if ( ! $this->validate_access( $room ) ) {
			Sync_Storage_Logger::event( 'Access denied', array( 'room' => $room ) );
			return array();
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, data FROM {$wpdb->collaboration}
				 WHERE room = %s AND type IS NULL AND id > %d
				 ORDER BY id ASC",
				$room,
				$cursor
			),
			ARRAY_A
		);

		if ( ! $results ) {
			Sync_Storage_Logger::storage( 'get_updates_after_cursor:result', $room, array( 'count' => 0 ) );
			return array();
		}

		// Track the last cursor for this room.
		$last_id                     = end( $results )['id'];
		$this->room_cursors[ $room ] = (int) $last_id;

		// Decode and return updates.
		$updates = array_values(
			array_map(
				function ( $row ) {
					return json_decode( $row['data'], true );
				},
				$results
			)
		);

		Sync_Storage_Logger::storage(
			'get_updates_after_cursor:result',
			$room,
			array(
				'count'      => count( $updates ),
				'new_cursor' => $last_id,
			)
		);

		return $updates;
	}

	/**
	 * Remove updates before a given cursor.
	 *
	 * @param string $room   Room identifier.
	 * @param int    $cursor Remove updates with ID < this cursor.
	 * @return bool True on success.
	 */
	public function remove_updates_before_cursor( string $room, int $cursor ): bool {
		if ( ! $this->validate_access( $room ) ) {
			return false;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->collaboration}
				 WHERE room = %s AND type IS NULL AND id < %d",
				$room,
				$cursor
			)
		);

		return false !== $result;
	}
}
