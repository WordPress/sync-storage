<?php
/**
 * Tests for cron cleanup (lib/store/cleanup.php).
 *
 * @package Sync_Storage
 *
 * @group rtc
 * @group cron
 */
class WP_Test_Sync_Storage_Cleanup extends WP_UnitTestCase {

	const HOOK = 'sync_storage_cleanup_stale_updates';

	public function set_up() {
		parent::set_up();
		wp_clear_scheduled_hook( self::HOOK );

		// Ensure $wpdb->collaboration is registered
		global $wpdb;
		if ( ! isset( $wpdb->collaboration ) ) {
			$wpdb->collaboration = $wpdb->prefix . 'collaboration';
		}
	}

	public function tear_down() {
		wp_clear_scheduled_hook( self::HOOK );
		parent::tear_down();
	}

	/**
	 * Asserts on registration, so it executes nothing in lib/.
	 *
	 * @coversNothing
	 */
	public function test_cleanup_hook_is_registered() {
		$this->assertTrue( has_action( self::HOOK, 'sync_storage_cleanup_old_updates' ) !== false );
	}

	/**
	 * @covers ::sync_storage_cleanup_old_updates
	 */
	public function test_cleanup_removes_old_updates() {
		global $wpdb;

		// The plugin's own definition, not a copy: a test carrying its own
		// CREATE TABLE keeps passing after the schema moves.
		sync_storage_create_table();

		// Insert old update (8 days ago)
		$old_timestamp = ( time() - 8 * DAY_IN_SECONDS ) * 1000;
		$wpdb->insert(
			$wpdb->collaboration,
			array(
				'room'      => 'postType/post:1',
				'data'      => 'test',
				'timestamp' => $old_timestamp,
			)
		);

		// Insert recent update (1 day ago)
		$recent_timestamp = ( time() - 1 * DAY_IN_SECONDS ) * 1000;
		$wpdb->insert(
			$wpdb->collaboration,
			array(
				'room'      => 'postType/post:1',
				'data'      => 'test',
				'timestamp' => $recent_timestamp,
			)
		);

		// Run cleanup
		sync_storage_cleanup_old_updates();

		// Verify old update was deleted
		$old_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->collaboration} WHERE timestamp < %d",
				( time() - 7 * DAY_IN_SECONDS ) * 1000
			)
		);
		$this->assertSame( 0, (int) $old_count );

		// Verify recent update still exists
		$recent_count = $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->collaboration}"
		);
		$this->assertSame( 1, (int) $recent_count );
	}
}
