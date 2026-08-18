<?php
/**
 * Tests for server authority (lib/server-authority.php).
 *
 * @package Sync_Storage
 */

/**
 * Test the wp_presence_collaboration_started/ended hook listeners.
 *
 * presence-api's own collaboration-threshold detection lives in its
 * heartbeat handler and isn't something this plugin can drive directly;
 * these tests fire the actions it documents instead, verifying this
 * plugin's own reaction to them.
 */
class WP_Test_Sync_Storage_Server_Authority extends WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();
		$this->post_id = $this->factory->post->create();
	}

	/**
	 * Test collaboration starting flags the post as active and fires the room-active hook.
	 */
	public function test_collaboration_started_activates_rtc() {
		$room = "postType/post:{$this->post_id}";

		$fired = false;
		add_action(
			'sync_storage_room_active',
			function ( $post_id ) use ( &$fired ) {
				$fired = $this->post_id === $post_id;
			}
		);

		do_action( 'wp_presence_collaboration_started', $room, array() );

		$this->assertTrue( (bool) get_post_meta( $this->post_id, '_sync_storage_active', true ) );
		$this->assertTrue( $fired );
	}

	/**
	 * Test collaboration ending clears the flag and fires the room-inactive hook.
	 */
	public function test_collaboration_ended_deactivates_rtc() {
		$room = "postType/post:{$this->post_id}";
		update_post_meta( $this->post_id, '_sync_storage_active', true );

		$fired = false;
		add_action(
			'sync_storage_room_inactive',
			function ( $post_id ) use ( &$fired ) {
				$fired = $this->post_id === $post_id;
			}
		);

		do_action( 'wp_presence_collaboration_ended', $room, array() );

		$this->assertSame( '', get_post_meta( $this->post_id, '_sync_storage_active', true ) );
		$this->assertTrue( $fired );
	}

	/**
	 * Test rooms that don't match the postType/<type>:<id> format are ignored.
	 */
	public function test_invalid_room_format_is_ignored() {
		do_action( 'wp_presence_collaboration_started', 'not-a-valid-room', array() );

		$this->assertSame( '', get_post_meta( $this->post_id, '_sync_storage_active', true ) );
	}
}
