<?php
/**
 * Tests for Sync_Storage_Provider class.
 *
 * @package Sync_Storage
 */

/**
 * Test Sync_Storage_Provider implementation.
 */
class WP_Test_Sync_Storage_Provider extends WP_UnitTestCase {

	/**
	 * Storage provider instance.
	 *
	 * @var Sync_Storage_Provider
	 */
	private $provider;

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Test room identifier.
	 *
	 * @var string
	 */
	private $room;

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();

		$this->provider = new Sync_Storage_Provider();
		$this->post_id  = $this->factory->post->create();
		$this->room     = "postType/post:{$this->post_id}";

		wp_set_current_user( $this->factory->user->create( array( 'role' => 'editor' ) ) );
	}

	/**
	 * Test adding update to collaboration table.
	 *
	 * @covers Sync_Storage_Provider::add_update
	 */
	public function test_add_update() {
		$update = array( 'data' => 'test update' );
		$result = $this->provider->add_update( $this->room, $update );

		$this->assertTrue( $result );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->collaboration} WHERE room = %s",
				$this->room
			)
		);

		$this->assertEquals( 1, (int) $count );
	}

	/**
	 * Test adding update without permission.
	 *
	 * @covers Sync_Storage_Provider::add_update
	 */
	public function test_add_update_no_permission() {
		wp_set_current_user( 0 );

		$update = array( 'data' => 'unauthorized' );
		$result = $this->provider->add_update( $this->room, $update );

		$this->assertFalse( $result );
	}

	/**
	 * Test getting updates after cursor.
	 *
	 * @covers Sync_Storage_Provider::get_updates_after_cursor
	 */
	public function test_get_updates_after_cursor() {
		$update1 = array( 'data' => 'first' );
		$update2 = array( 'data' => 'second' );

		$this->provider->add_update( $this->room, $update1 );
		$this->provider->add_update( $this->room, $update2 );

		$updates = $this->provider->get_updates_after_cursor( $this->room, 0 );

		$this->assertCount( 2, $updates );
		$this->assertEquals( 'first', $updates[0]['data'] );
		$this->assertEquals( 'second', $updates[1]['data'] );
	}

	/**
	 * Test cursor advances.
	 *
	 * @covers Sync_Storage_Provider::get_cursor
	 */
	public function test_cursor_advances() {
		$this->provider->add_update( $this->room, array( 'data' => 'first' ) );

		$updates = $this->provider->get_updates_after_cursor( $this->room, 0 );
		$this->assertCount( 1, $updates );

		$cursor = $this->provider->get_cursor( $this->room );
		$this->assertGreaterThan( 0, $cursor );

		$this->provider->add_update( $this->room, array( 'data' => 'second' ) );
		$new_updates = $this->provider->get_updates_after_cursor( $this->room, $cursor );

		$this->assertCount( 1, $new_updates );
		$this->assertEquals( 'second', $new_updates[0]['data'] );
	}

	/**
	 * Test update count.
	 *
	 * @covers Sync_Storage_Provider::get_update_count
	 */
	public function test_get_update_count() {
		$this->assertEquals( 0, $this->provider->get_update_count( $this->room ) );

		$this->provider->add_update( $this->room, array( 'data' => 'first' ) );
		$this->assertEquals( 1, $this->provider->get_update_count( $this->room ) );

		$this->provider->add_update( $this->room, array( 'data' => 'second' ) );
		$this->assertEquals( 2, $this->provider->get_update_count( $this->room ) );
	}

	/**
	 * Test remove updates before cursor.
	 *
	 * @covers Sync_Storage_Provider::remove_updates_before_cursor
	 */
	public function test_remove_updates_before_cursor() {
		global $wpdb;

		$this->provider->add_update( $this->room, array( 'data' => 'first' ) );
		$this->provider->add_update( $this->room, array( 'data' => 'second' ) );
		$this->provider->add_update( $this->room, array( 'data' => 'third' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$first_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->collaboration} WHERE room = %s ORDER BY id ASC LIMIT 1",
				$this->room
			)
		);

		$this->provider->remove_updates_before_cursor( $this->room, (int) $first_id + 2 );

		$remaining = $this->provider->get_update_count( $this->room );
		$this->assertEquals( 1, $remaining );
	}

	/**
	 * Test invalid room format.
	 *
	 * @covers Sync_Storage_Provider::add_update
	 */
	public function test_invalid_room_format() {
		$invalid_room = 'invalid-room-format';
		$result       = $this->provider->add_update( $invalid_room, array( 'data' => 'test' ) );

		$this->assertFalse( $result );
	}

	/**
	 * Test awareness state round trips with every field Gutenberg reads.
	 *
	 * Asserting only that an array came back is what let #88 through: the
	 * entry was present but carried a null updated_at, and the sync server
	 * drops entries on that field alone.
	 *
	 * @covers Sync_Storage_Provider::set_awareness_state
	 * @covers Sync_Storage_Provider::get_awareness_state
	 */
	public function test_awareness_state() {
		if ( ! function_exists( 'wp_set_presence' ) ) {
			$this->markTestSkipped( 'Presence API not available' );
		}

		$awareness = array(
			array(
				'client_id'  => 4033094322,
				'state'      => array( 'cursor' => 10 ),
				'wp_user_id' => get_current_user_id(),
			),
		);

		$result = $this->provider->set_awareness_state( $this->room, $awareness );
		$this->assertTrue( $result );

		$retrieved = $this->provider->get_awareness_state( $this->room );

		$this->assertCount( 1, $retrieved );
		$this->assertSame( 4033094322, $retrieved[0]['client_id'] );
		$this->assertSame( array( 'cursor' => 10 ), $retrieved[0]['state'] );
		$this->assertSame( get_current_user_id(), (int) $retrieved[0]['wp_user_id'] );
		$this->assertIsInt( $retrieved[0]['updated_at'] );
	}

	/**
	 * Test presence entries written by anything else stay out of awareness.
	 *
	 * Presence API's Heartbeat handler writes into the same room string this
	 * provider uses, keyed `editor-{user_id}`, carrying its own state shape
	 * (`action`, `screen`, `locked`). Handing those to Gutenberg makes
	 * PostEditorAwareness throw on a field it has no equality check for, and
	 * the sync client backs off on every poll.
	 *
	 * @covers Sync_Storage_Provider::get_awareness_state
	 */
	public function test_awareness_excludes_entries_this_provider_did_not_write() {
		if ( ! function_exists( 'wp_set_presence' ) ) {
			$this->markTestSkipped( 'Presence API not available' );
		}

		$user_id = get_current_user_id();

		wp_set_presence(
			$this->room,
			'editor-' . $user_id,
			array(
				'action' => 'editing',
				'screen' => 'post',
				'locked' => false,
			),
			$user_id
		);

		$this->provider->set_awareness_state(
			$this->room,
			array(
				array(
					'client_id'  => 4033094322,
					'state'      => array( 'cursor' => 10 ),
					'wp_user_id' => $user_id,
				),
			)
		);

		$retrieved = $this->provider->get_awareness_state( $this->room );

		$this->assertCount( 1, $retrieved );
		$this->assertSame( 4033094322, $retrieved[0]['client_id'] );
	}

	/**
	 * Test a just-written entry survives the sync server's expiry check.
	 *
	 * Gutenberg expires awareness on `time() - $entry['updated_at'] >= 30`
	 * (WP_HTTP_Polling_Sync_Server::AWARENESS_TIMEOUT), and applies it to
	 * whatever storage returns. A non-timestamp there makes that subtraction
	 * an epoch-sized number, so every collaborator is pruned on every poll
	 * and awareness never reaches the editor at all (#88).
	 *
	 * The threshold is duplicated rather than read off the class, which is
	 * only loaded when the editor is: the point is that our value is usable
	 * arithmetic, not that we agree on the constant.
	 *
	 * @covers Sync_Storage_Provider::get_awareness_state
	 */
	public function test_awareness_updated_at_is_a_timestamp_the_sync_server_can_age() {
		if ( ! function_exists( 'wp_set_presence' ) ) {
			$this->markTestSkipped( 'Presence API not available' );
		}

		$this->provider->set_awareness_state(
			$this->room,
			array(
				array(
					'client_id'  => 4033094322,
					'state'      => array( 'cursor' => 10 ),
					'wp_user_id' => get_current_user_id(),
				),
			)
		);

		$entry = $this->provider->get_awareness_state( $this->room )[0];
		$age   = time() - $entry['updated_at'];

		$this->assertGreaterThanOrEqual( 0, $age, 'Awareness is stamped in the future.' );
		$this->assertLessThan( 30, $age, 'A just-written entry reads as expired to the sync server.' );
	}

	/**
	 * Test wp_user_id is an int, as the sync server compares it strictly.
	 *
	 * WP_HTTP_Polling_Sync_Server::check_permissions() rejects a poll whose
	 * client_id is already held under a different wp_user_id, comparing with
	 * !== against get_current_user_id(). The database hands user_id back as a
	 * string, so an uncast value makes every client fail that check against its
	 * own entry: the first poll into an empty room succeeds and every poll
	 * after it is a 403.
	 *
	 * @covers Sync_Storage_Provider::get_awareness_state
	 */
	public function test_awareness_wp_user_id_is_an_int() {
		if ( ! function_exists( 'wp_set_presence' ) ) {
			$this->markTestSkipped( 'Presence API not available' );
		}

		$user_id = get_current_user_id();

		$this->provider->set_awareness_state(
			$this->room,
			array(
				array(
					'client_id'  => 4033094322,
					'state'      => array( 'cursor' => 10 ),
					'wp_user_id' => $user_id,
				),
			)
		);

		$entry = $this->provider->get_awareness_state( $this->room )[0];

		$this->assertSame( $user_id, $entry['wp_user_id'], 'wp_user_id does not round trip as an int.' );
	}

	/**
	 * Test only the polling client's entry is written, not the whole room.
	 *
	 * WP_HTTP_Polling_Sync_Server::process_awareness_update() hands storage the
	 * merged room every poll: the caller's entry freshly stamped, everyone
	 * else's passed through untouched. Writing all of them turns one poll into
	 * N upserts, which at the one-second collaborator interval is the
	 * difference between thousands and tens of thousands of writes an hour.
	 *
	 * @covers Sync_Storage_Provider::set_awareness_state
	 */
	public function test_awareness_writes_only_the_freshest_entries() {
		if ( ! function_exists( 'wp_set_presence' ) ) {
			$this->markTestSkipped( 'Presence API not available' );
		}

		$user_id = get_current_user_id();
		$now     = time();

		$this->provider->set_awareness_state(
			$this->room,
			array(
				array(
					'client_id'  => 1111,
					'state'      => array( 'cursor' => 1 ),
					'updated_at' => $now,
					'wp_user_id' => $user_id,
				),
				array(
					'client_id'  => 2222,
					'state'      => array( 'cursor' => 2 ),
					'updated_at' => $now - 20,
					'wp_user_id' => $user_id,
				),
			)
		);

		$retrieved  = $this->provider->get_awareness_state( $this->room );
		$client_ids = wp_list_pluck( $retrieved, 'client_id' );

		$this->assertSame( array( 1111 ), $client_ids, 'A passed-through entry was written as if it were ours.' );
	}

	/**
	 * Test relaying another client's entry does not refresh its row.
	 *
	 * wp_set_presence() takes no timestamp and stamps date_gmt to now, so a
	 * passed-through entry written back reads as freshly active. Every client
	 * still polling would hold a departed one in the room, and nothing would
	 * reach the sync server's AWARENESS_TIMEOUT.
	 *
	 * @covers Sync_Storage_Provider::set_awareness_state
	 */
	public function test_relayed_entries_keep_aging() {
		if ( ! function_exists( 'wp_set_presence' ) ) {
			$this->markTestSkipped( 'Presence API not available' );
		}

		global $wpdb;

		$user_id = get_current_user_id();

		// The departing client's own last poll.
		$this->provider->set_awareness_state(
			$this->room,
			array(
				array(
					'client_id'  => 2222,
					'state'      => array( 'cursor' => 2 ),
					'updated_at' => time(),
					'wp_user_id' => $user_id,
				),
			)
		);

		// Age it 40s: past the sync server's 30s timeout, inside Presence's TTL
		// so the row is still readable.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->presence} SET date_gmt = %s WHERE room = %s AND client_id = %s",
				gmdate( 'Y-m-d H:i:s', time() - 40 ),
				$this->room,
				'sync-2222'
			)
		);

		$aged = $this->provider->get_awareness_state( $this->room )[0]['updated_at'];

		// Another client polls, relaying the aged entry back as the server does.
		$this->provider->set_awareness_state(
			$this->room,
			array(
				array(
					'client_id'  => 1111,
					'state'      => array( 'cursor' => 1 ),
					'updated_at' => time(),
					'wp_user_id' => $user_id,
				),
				array(
					'client_id'  => 2222,
					'state'      => array( 'cursor' => 2 ),
					'updated_at' => $aged,
					'wp_user_id' => $user_id,
				),
			)
		);

		$entries = wp_list_pluck( $this->provider->get_awareness_state( $this->room ), 'updated_at', 'client_id' );

		$this->assertSame( $aged, $entries[2222], 'Another client\'s poll reset a departed collaborator\'s age.' );
		$this->assertGreaterThanOrEqual( 30, time() - $entries[2222], 'The relayed entry no longer reads as expired.' );
	}

	/**
	 * Test awareness state is gated by the same permission check as updates.
	 *
	 * @covers Sync_Storage_Provider::set_awareness_state
	 * @covers Sync_Storage_Provider::get_awareness_state
	 */
	public function test_awareness_state_requires_permission() {
		wp_set_current_user( 0 );

		$awareness = array(
			array(
				'client_id'  => 'client-123',
				'state'      => array( 'cursor' => 10 ),
				'wp_user_id' => 0,
			),
		);

		$this->assertFalse( $this->provider->set_awareness_state( $this->room, $awareness ) );
		$this->assertSame( array(), $this->provider->get_awareness_state( $this->room ) );
	}

	/**
	 * Test rooms outside postType are admitted.
	 *
	 * The editor opens a room per entity it syncs, not only the post being
	 * edited. Refusing one is not a quiet denial: add_update() returning false
	 * becomes a WP_Error, and the sync server abandons the whole batched poll on
	 * the first one, so an unrecognised room takes the post room down with it.
	 *
	 * @covers Sync_Storage_Provider::add_update
	 * @covers Sync_Storage_Provider::validate_access
	 */
	public function test_add_update_admits_a_non_post_type_room() {
		$this->assertTrue(
			$this->provider->add_update( 'root/comment', array( 'data' => 'test update' ) ),
			'A room the sync server permits was refused.'
		);
	}

	/**
	 * Test room access follows the sync server rather than a local rule.
	 *
	 * Access is delegated to WP_Sync_Config so the two cannot drift. These are
	 * its answers, asserted here so a reintroduced local rule fails loudly
	 * rather than by 500ing a poll.
	 *
	 * @covers Sync_Storage_Provider::add_update
	 * @covers Sync_Storage_Provider::validate_access
	 */
	public function test_add_update_refuses_a_room_the_sync_server_refuses() {
		$this->assertFalse(
			$this->provider->add_update( 'not-a-room', array( 'data' => 'test update' ) ),
			'A room with no entity kind was admitted.'
		);
		$this->assertFalse(
			$this->provider->add_update( 'postType/post:0', array( 'data' => 'test update' ) ),
			'A room with a zero object ID was admitted.'
		);
		$this->assertFalse(
			$this->provider->add_update( "taxonomy/category:{$this->post_id}", array( 'data' => 'test update' ) ),
			'A term room naming a post ID was admitted.'
		);
	}

	/**
	 * Test that stored timestamps are milliseconds, not seconds.
	 *
	 * Cleanup's cutoff is computed in milliseconds (matching Yjs). Storing
	 * seconds here would make every row look older than the cutoff and get
	 * deleted on the very next cleanup run, regardless of actual age.
	 *
	 * @covers Sync_Storage_Provider::add_update
	 */
	public function test_add_update_stores_millisecond_timestamp() {
		$this->provider->add_update( $this->room, array( 'data' => 'test' ) );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$timestamp = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT timestamp FROM {$wpdb->collaboration} WHERE room = %s ORDER BY id DESC LIMIT 1",
				$this->room
			)
		);

		// A millisecond epoch timestamp for any date past 2001 exceeds 1e12;
		// a seconds timestamp for the foreseeable future does not.
		$this->assertGreaterThan( 1000000000000, $timestamp );
	}

	/**
	 * Test that a fresh update survives the cleanup cron immediately after being written.
	 *
	 * Regression test for a unit mismatch: add_update() previously stored
	 * seconds while the cleanup cutoff is computed in milliseconds, so every
	 * row looked older than 7 days and was deleted on the next cron run.
	 *
	 * @covers ::sync_storage_cleanup_old_updates
	 */
	public function test_fresh_update_survives_cleanup() {
		$this->provider->add_update( $this->room, array( 'data' => 'fresh' ) );

		sync_storage_cleanup_old_updates();

		$this->assertSame( 1, $this->provider->get_update_count( $this->room ) );
	}
}
