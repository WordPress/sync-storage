<?php
/**
 * Room-scoped append-only store over the wp_collaboration table.
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every query against wp_collaboration lives here.
 *
 * This layer is deliberately ignorant of Gutenberg, Presence API and Yjs: a
 * room is just a string, a payload is just something JSON-serializable, and
 * ordering is by autoincrement id. The real-time collaboration adapter in
 * lib/rtc/ is the only caller today, and keeping that vocabulary out of here
 * is what would let a second caller reuse the table -- ephemeral, high-churn,
 * post-scoped state is a recurring problem in WordPress and post meta is the
 * usual, cache-invalidating answer to it.
 *
 * Access control is not this layer's job. Callers decide who may touch a
 * room; see Sync_Storage_Provider::validate_access().
 *
 * Every query scopes itself to `type IS NULL` rather than assuming it owns
 * the whole table. The `type` column partitions wp_collaboration between
 * kinds of payload: the 0.1.0 schema paired it with a UNIQUE KEY on
 * (room, type), which gives a one-row-per-room snapshot slot alongside this
 * append-only log. Nothing writes a non-NULL type today, so the log is the
 * only partition in use.
 */
class Sync_Storage_Store {

	/**
	 * Current time in the unit the `timestamp` column stores.
	 *
	 * Milliseconds, matching the Yjs client that produces the payloads. The
	 * cleanup cutoff is computed in the same unit; storing seconds here would
	 * make every row look older than the cutoff and get swept on the next run.
	 *
	 * @return int Current time in milliseconds since epoch.
	 */
	public static function current_time_ms(): int {
		return (int) round( microtime( true ) * 1000 );
	}

	/**
	 * Appends an entry to a room's log.
	 *
	 * @param string   $room         Room identifier.
	 * @param mixed    $data         Payload. Opaque to this layer, stored JSON-encoded.
	 * @param int|null $timestamp_ms Timestamp in milliseconds. Defaults to now; pass one
	 *                               only when backfilling entries that already have a time.
	 * @return int|false Inserted row id, or false on failure.
	 */
	public static function append( string $room, $data, ?int $timestamp_ms = null ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->collaboration,
			array(
				'room'      => $room,
				'data'      => wp_json_encode( $data ),
				'timestamp' => $timestamp_ms ?? self::current_time_ms(),
			),
			array( '%s', '%s', '%d' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Reads a room's entries newer than a cursor, oldest first.
	 *
	 * @param string $room   Room identifier.
	 * @param int    $cursor Return entries with an id greater than this.
	 * @return array<int, array{id: int, data: mixed}> Entries, each with its row id.
	 */
	public static function get_after( string $room, int $cursor ): array {
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
			return array();
		}

		return array_map(
			function ( $row ) {
				return array(
					'id'   => (int) $row['id'],
					'data' => json_decode( $row['data'], true ),
				);
			},
			$results
		);
	}

	/**
	 * Counts a room's entries.
	 *
	 * @param string $room Room identifier.
	 * @return int Number of entries.
	 */
	public static function count( string $room ): int {
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
	 * Drops a room's entries older than a cursor.
	 *
	 * @param string $room   Room identifier.
	 * @param int    $cursor Delete entries with an id below this.
	 * @return bool True on success.
	 */
	public static function delete_before( string $room, int $cursor ): bool {
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

	/**
	 * Drops entries older than a cutoff, across every room.
	 *
	 * Deletes in batches of 1000 so one call can't lock the table for too
	 * long, repeating until a batch comes up short.
	 *
	 * Unlike the per-room methods this is not scoped to the log partition:
	 * it is the table's safety net and expiring by age applies to anything
	 * stored here.
	 *
	 * @param int $cutoff_ms Delete entries with a timestamp below this, in milliseconds.
	 * @return int Number of entries deleted.
	 */
	public static function delete_expired( int $cutoff_ms ): int {
		global $wpdb;

		$total_deleted = 0;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->collaboration}
					 WHERE timestamp < %d
					 LIMIT 1000",
					$cutoff_ms
				)
			);

			$total_deleted += max( $deleted, 0 );
		} while ( 1000 === $deleted );

		return $total_deleted;
	}
}
