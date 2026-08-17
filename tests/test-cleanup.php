<?php
/**
 * Tests for cron cleanup (lib/cleanup.php).
 *
 * @package Realtime_Collaboration
 *
 * @group rtc
 * @group cron
 */
class WP_Test_RTC_Cleanup extends WP_UnitTestCase {

	const HOOK = 'rtc_cleanup_stale_updates';

	public function set_up() {
		parent::set_up();
		wp_clear_scheduled_hook( self::HOOK );
	}

	public function tear_down() {
		wp_clear_scheduled_hook( self::HOOK );
		parent::tear_down();
	}

	/**
	 * @covers ::rtc_cleanup_old_updates
	 */
	public function test_cleanup_hook_is_registered() {
		$this->assertTrue( has_action( self::HOOK, 'rtc_cleanup_old_updates' ) !== false );
	}

	/**
	 * @covers ::rtc_cleanup_old_updates
	 */
	public function test_cleanup_removes_old_updates() {
		global $wpdb;

		// Create test table if it doesn't exist
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$wpdb->collaboration} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				room varchar(191) NOT NULL,
				type varchar(20) DEFAULT NULL,
				data longtext NOT NULL,
				timestamp bigint(20) unsigned NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY room_type (room(191), type),
				KEY room_id (room(50), id),
				KEY room_timestamp (room(50), timestamp)
			)"
		);

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
		rtc_cleanup_old_updates();

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
