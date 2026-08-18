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
	 */
	public function test_add_update_no_permission() {
		wp_set_current_user( 0 );

		$update = array( 'data' => 'unauthorized' );
		$result = $this->provider->add_update( $this->room, $update );

		$this->assertFalse( $result );
	}

	/**
	 * Test getting updates after cursor.
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
	 */
	public function test_invalid_room_format() {
		$invalid_room = 'invalid-room-format';
		$result       = $this->provider->add_update( $invalid_room, array( 'data' => 'test' ) );

		$this->assertFalse( $result );
	}

	/**
	 * Test awareness state integration.
	 */
	public function test_awareness_state() {
		if ( ! function_exists( 'wp_set_presence' ) ) {
			$this->markTestSkipped( 'Presence API not available' );
		}

		$awareness = array(
			array(
				'client_id'  => 'client-123',
				'state'      => array( 'cursor' => 10 ),
				'wp_user_id' => get_current_user_id(),
			),
		);

		$result = $this->provider->set_awareness_state( $this->room, $awareness );
		$this->assertTrue( $result );

		$retrieved = $this->provider->get_awareness_state( $this->room );
		$this->assertIsArray( $retrieved );
	}
}
