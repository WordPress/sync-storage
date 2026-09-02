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
 * Adapts the generic store to Gutenberg's real-time collaboration interface.
 *
 * Everything specific to collaborative editing lives here: what a room name
 * means, who is allowed into one, the shape Gutenberg expects awareness in,
 * and the cursor bookkeeping its polling loop relies on. CRDT updates are
 * delegated to Sync_Storage_Store and awareness to Presence API, so neither
 * touches post meta.
 *
 * Implements WP_Sync_Storage interface from Gutenberg.
 */
class Sync_Storage_Provider implements WP_Sync_Storage {

	/**
	 * Prefix marking a presence entry as Gutenberg awareness written by us.
	 *
	 * Presence API's Heartbeat handler writes into the same room string this
	 * provider uses, keyed `editor-{user_id}`, with its own state shape.
	 * Returning those rows to Gutenberg as awareness makes the editor throw on
	 * a field it has no equality check for. Prefixing our own writes, and
	 * reading nothing else back, keeps the two sets of entries apart.
	 */
	private const CLIENT_PREFIX = 'sync-';

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
		// Defer to the sync server's own room grammar and capability map rather
		// than restating them. The editor opens rooms beyond postType, such as
		// root/comment, and a narrower rule here does not deny them quietly: a
		// refused add_update() is a WP_Error, and the server abandons the whole
		// batched poll on the first one, so a room we do not recognise takes
		// the post room down with it.
		if ( ! class_exists( 'WP_Sync_Config' ) ) {
			return false;
		}

		$parsed = WP_Sync_Config::parse_room( $room );

		if ( null === $parsed ) {
			return false;
		}

		return WP_Sync_Config::can_user_sync_entity_type(
			$parsed['entity_kind'],
			$parsed['entity_name'],
			$parsed['object_id']
		);
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

		if ( ! $this->validate_access( $room ) ) {
			Sync_Storage_Logger::event( 'Access denied', array( 'room' => $room ) );
			return array();
		}

		if ( ! function_exists( 'wp_get_presence' ) ) {
			Sync_Storage_Logger::event( 'Presence API not available' );
			return array();
		}

		$entries = wp_get_presence( $room );
		Sync_Storage_Logger::presence( 'wp_get_presence', $room, $entries );

		if ( empty( $entries ) ) {
			return array();
		}

		// Transform our own presence entries to Gutenberg awareness format.
		// Gutenberg expects: [ {client_id, state, updated_at, wp_user_id}, ... ].
		// client_id and wp_user_id are ints, which the sync server compares
		// strictly against the polling client's own and get_current_user_id();
		// the database hands both back as strings. updated_at is a Unix
		// timestamp, which it subtracts from time() to expire an entry;
		// Presence stores a GMT datetime string.
		$awareness = array();

		foreach ( $entries as $entry ) {
			if ( 0 !== strpos( $entry->client_id, self::CLIENT_PREFIX ) ) {
				continue;
			}

			$awareness[] = array(
				'client_id'  => (int) substr( $entry->client_id, strlen( self::CLIENT_PREFIX ) ),
				'state'      => $entry->data,
				'updated_at' => strtotime( $entry->date_gmt . ' UTC' ),
				'wp_user_id' => (int) $entry->user_id,
			);
		}

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
	 * @return bool True when every entry was stored.
	 */
	public function set_awareness_state( string $room, array $awareness ): bool {
		Sync_Storage_Logger::storage( 'set_awareness_state', $room, $awareness );

		if ( ! $this->validate_access( $room ) ) {
			Sync_Storage_Logger::event( 'Access denied', array( 'room' => $room ) );
			return false;
		}

		if ( ! function_exists( 'wp_set_presence' ) ) {
			Sync_Storage_Logger::event( 'Presence API not available' );
			return false;
		}

		// A site can switch presence recording off (Presence API 0.3.0), and
		// wp_set_presence() answers false for every write while it is. Asked
		// once here rather than inferred from a row of failures, so the log
		// separates a site that opted out from storage that broke -- and so a
		// switched-off site does no work per poll beyond this option read.
		//
		// The function is guarded because Requires Plugins carries no version:
		// a site on 0.2.x has the switch's default behaviour and no switch.
		if ( function_exists( 'wp_presence_recording_enabled' ) && ! wp_presence_recording_enabled() ) {
			Sync_Storage_Logger::event( 'Presence recording switched off', array( 'room' => $room ) );
			return false;
		}

		// Each entry in awareness: {client_id, state, updated_at, wp_user_id}
		//
		// The sync server hands over the whole merged room on every poll: the
		// polling client's entry, freshly stamped, and every other client's
		// passed through untouched. Only the freshest are this request's to
		// write; the rest belong to clients that refresh their own rows on
		// their own polls.
		//
		// Writing them all costs an upsert per client per poll, and because
		// wp_set_presence() takes no timestamp and stamps date_gmt to now, it
		// also resets an age its owner is no longer refreshing, keeping a
		// departed collaborator in the room.
		//
		// Entries without a timestamp are written, so an unexpected shape
		// stores rather than vanishes.
		$latest = 0;

		foreach ( $awareness as $entry ) {
			if ( isset( $entry['updated_at'] ) ) {
				$latest = max( $latest, (int) $entry['updated_at'] );
			}
		}

		// Transform to presence-api format and store each client.
		//
		// Reporting what the writes actually did, rather than that the method
		// ran. Gutenberg discards this return -- awareness failing is not worth
		// failing a poll over -- so the value is the log line and the tests.
		$stored = true;

		foreach ( $awareness as $entry ) {
			if ( ! isset( $entry['client_id'], $entry['wp_user_id'] ) ) {
				continue;
			}

			if ( isset( $entry['updated_at'] ) && (int) $entry['updated_at'] < $latest ) {
				continue;
			}

			$written = wp_set_presence(
				$room,
				self::CLIENT_PREFIX . $entry['client_id'],
				$entry['state'] ?? array(),
				$entry['wp_user_id']
			);

			$stored = $stored && $written;

			Sync_Storage_Logger::presence(
				'wp_set_presence',
				$room,
				array(
					'client_id' => $entry['client_id'],
					'user_id'   => $entry['wp_user_id'],
					'stored'    => $written,
				)
			);
		}

		return $stored;
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

		$insert_id = Sync_Storage_Store::append( $room, $update );
		$success   = false !== $insert_id;

		Sync_Storage_Logger::storage(
			'add_update:result',
			$room,
			array(
				'success'   => $success,
				'insert_id' => $success ? $insert_id : null,
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

		return Sync_Storage_Store::count( $room );
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

		$entries = Sync_Storage_Store::get_after( $room, $cursor );

		if ( ! $entries ) {
			Sync_Storage_Logger::storage( 'get_updates_after_cursor:result', $room, array( 'count' => 0 ) );
			return array();
		}

		$last_id                     = end( $entries )['id'];
		$this->room_cursors[ $room ] = $last_id;

		$updates = array_values(
			array_map(
				function ( $entry ) {
					return $entry['data'];
				},
				$entries
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

		return Sync_Storage_Store::delete_before( $room, $cursor );
	}
}
