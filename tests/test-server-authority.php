<?php
/**
 * Tests for server authority (lib/rtc/server-authority.php).
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
 *
 * The listeners are anonymous functions on add_action(), so there is no
 * symbol to name here rather than coverage this class could attribute.
 *
 * @coversNothing
 */
class WP_Test_Sync_Storage_Server_Authority extends WP_UnitTestCase {

	/**
	 * Test post ID.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Arguments each recorded action was called with, keyed by action name.
	 *
	 * @var array
	 */
	private $calls = array();

	/**
	 * Set up test.
	 */
	public function set_up() {
		parent::set_up();
		$this->post_id = $this->factory->post->create();
		$this->calls   = array();
	}

	/**
	 * Records every call to an action, so tests can assert on both how many
	 * times it fired and the arguments the plugin forwarded.
	 *
	 * @param string $action Action name to record.
	 */
	private function record_action( $action ) {
		$this->calls[ $action ] = array();

		add_action(
			$action,
			function ( $post_id, $entries ) use ( $action ) {
				$this->calls[ $action ][] = array( $post_id, $entries );
			},
			10,
			2
		);
	}

	/**
	 * Test collaboration starting announces the room as active.
	 */
	public function test_collaboration_started_fires_room_active() {
		$this->record_action( 'sync_storage_room_active' );
		$entries = array( array( 'user_id' => 1 ), array( 'user_id' => 2 ) );

		do_action( 'wp_presence_collaboration_started', "postType/post:{$this->post_id}", $entries );

		$this->assertSame(
			array( array( $this->post_id, $entries ) ),
			$this->calls['sync_storage_room_active']
		);
	}

	/**
	 * Test collaboration ending announces the room as inactive.
	 */
	public function test_collaboration_ended_fires_room_inactive() {
		$this->record_action( 'sync_storage_room_inactive' );

		do_action( 'wp_presence_collaboration_ended', "postType/post:{$this->post_id}", array() );

		$this->assertSame(
			array( array( $this->post_id, array() ) ),
			$this->calls['sync_storage_room_inactive']
		);
	}

	/**
	 * Test rooms that don't match the postType/<type>:<id> format are ignored.
	 */
	public function test_invalid_room_format_is_ignored() {
		$this->record_action( 'sync_storage_room_active' );
		$this->record_action( 'sync_storage_room_inactive' );

		do_action( 'wp_presence_collaboration_started', 'not-a-valid-room', array() );
		do_action( 'wp_presence_collaboration_ended', 'not-a-valid-room', array() );

		$this->assertSame( array(), $this->calls['sync_storage_room_active'] );
		$this->assertSame( array(), $this->calls['sync_storage_room_inactive'] );
	}

	/**
	 * Test the listeners no longer write the post meta flag they used to (#56).
	 */
	public function test_collaboration_writes_no_post_meta() {
		// Asserted while the room is active, not after it closes: the flag this
		// replaces was deleted on the way back down, so a start-then-end test
		// would pass against the very code it is meant to catch.
		do_action( 'wp_presence_collaboration_started', "postType/post:{$this->post_id}", array() );

		$this->assertSame( array(), get_post_meta( $this->post_id, '_sync_storage_active' ) );
	}
}
