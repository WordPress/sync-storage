<?php
/**
 * Tests for Sync_Storage_Store (lib/store/class-sync-storage-store.php).
 *
 * Exercises the store on its own terms: no Gutenberg, no Presence API, no
 * capability checks, and room names that are not post rooms. If this file
 * ever needs one of those to pass, the store has grown a dependency on the
 * RTC layer that it is not supposed to have.
 *
 * @package Sync_Storage
 *
 * @group store
 */
class WP_Test_Sync_Storage_Store extends WP_UnitTestCase {

	/**
	 * Room identifier. Deliberately not a `postType/...` room.
	 *
	 * @var string
	 */
	private $room = 'widget/sidebar:main';

	/**
	 * Test appending returns the new row id.
	 */
	public function test_append_returns_row_id() {
		$id = Sync_Storage_Store::append( $this->room, array( 'a' => 1 ) );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	/**
	 * Test payloads survive the JSON round trip.
	 */
	public function test_payloads_round_trip() {
		$payload = array(
			'nested' => array( 'x' => 1, 'y' => array( 2, 3 ) ),
			'string' => 'héllo',
		);

		Sync_Storage_Store::append( $this->room, $payload );

		$entries = Sync_Storage_Store::get_after( $this->room, 0 );

		$this->assertCount( 1, $entries );
		$this->assertSame( $payload, $entries[0]['data'] );
	}

	/**
	 * Test entries come back oldest first, each with its row id.
	 */
	public function test_get_after_is_ordered_and_carries_ids() {
		$first  = Sync_Storage_Store::append( $this->room, 'first' );
		$second = Sync_Storage_Store::append( $this->room, 'second' );

		$entries = Sync_Storage_Store::get_after( $this->room, 0 );

		$this->assertSame( array( $first, $second ), wp_list_pluck( $entries, 'id' ) );
		$this->assertSame( array( 'first', 'second' ), wp_list_pluck( $entries, 'data' ) );
	}

	/**
	 * Test the cursor is exclusive.
	 */
	public function test_get_after_excludes_the_cursor_row() {
		$first = Sync_Storage_Store::append( $this->room, 'first' );
		Sync_Storage_Store::append( $this->room, 'second' );

		$entries = Sync_Storage_Store::get_after( $this->room, $first );

		$this->assertCount( 1, $entries );
		$this->assertSame( 'second', $entries[0]['data'] );
	}

	/**
	 * Test rooms don't leak into each other.
	 */
	public function test_rooms_are_isolated() {
		Sync_Storage_Store::append( $this->room, 'mine' );
		Sync_Storage_Store::append( 'widget/sidebar:other', 'theirs' );

		$this->assertSame( 1, Sync_Storage_Store::count( $this->room ) );
		$this->assertSame( 1, Sync_Storage_Store::count( 'widget/sidebar:other' ) );
	}

	/**
	 * Test counting an empty room.
	 */
	public function test_count_is_zero_for_an_unused_room() {
		$this->assertSame( 0, Sync_Storage_Store::count( 'widget/sidebar:never-used' ) );
	}

	/**
	 * Test delete_before is exclusive and leaves other rooms alone.
	 */
	public function test_delete_before_trims_one_room() {
		Sync_Storage_Store::append( $this->room, 'first' );
		$second = Sync_Storage_Store::append( $this->room, 'second' );
		Sync_Storage_Store::append( 'widget/sidebar:other', 'theirs' );

		$this->assertTrue( Sync_Storage_Store::delete_before( $this->room, $second ) );

		$this->assertSame( 1, Sync_Storage_Store::count( $this->room ) );
		$this->assertSame( 'second', Sync_Storage_Store::get_after( $this->room, 0 )[0]['data'] );
		$this->assertSame( 1, Sync_Storage_Store::count( 'widget/sidebar:other' ) );
	}

	/**
	 * Test appended entries are stamped in milliseconds.
	 *
	 * The cleanup cutoff is computed in milliseconds. Storing seconds would
	 * put every row below the cutoff and delete it on the next sweep.
	 */
	public function test_append_stamps_milliseconds() {
		Sync_Storage_Store::append( $this->room, 'now' );

		global $wpdb;
		$timestamp = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT timestamp FROM {$wpdb->collaboration} WHERE room = %s",
				$this->room
			)
		);

		// A millisecond epoch for any date past 2001 exceeds 1e12; a seconds
		// epoch will not for the foreseeable future.
		$this->assertGreaterThan( 1000000000000, $timestamp );
	}

	/**
	 * Test an explicit timestamp is preserved, which is what migration relies on.
	 */
	public function test_append_accepts_an_explicit_timestamp() {
		$backdated = ( time() - 3 * DAY_IN_SECONDS ) * 1000;

		Sync_Storage_Store::append( $this->room, 'old', $backdated );

		global $wpdb;
		$timestamp = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT timestamp FROM {$wpdb->collaboration} WHERE room = %s",
				$this->room
			)
		);

		$this->assertSame( $backdated, $timestamp );
	}

	/**
	 * Test expiry deletes by age, across rooms, and reports the count.
	 */
	public function test_delete_expired_removes_only_entries_past_the_cutoff() {
		$eight_days_ago = ( time() - 8 * DAY_IN_SECONDS ) * 1000;
		$cutoff         = ( time() - 7 * DAY_IN_SECONDS ) * 1000;

		Sync_Storage_Store::append( $this->room, 'stale', $eight_days_ago );
		Sync_Storage_Store::append( 'widget/sidebar:other', 'also stale', $eight_days_ago );
		Sync_Storage_Store::append( $this->room, 'fresh' );

		$deleted = Sync_Storage_Store::delete_expired( $cutoff );

		$this->assertSame( 2, $deleted );
		$this->assertSame( 1, Sync_Storage_Store::count( $this->room ) );
		$this->assertSame( 0, Sync_Storage_Store::count( 'widget/sidebar:other' ) );
	}
}
