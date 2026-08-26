<?php
/**
 * Tests for deactivation teardown (lib/install.php).
 *
 * @package Sync_Storage
 *
 * @group rtc
 */
class WP_Test_Sync_Storage_Deactivation extends WP_UnitTestCase {

	/**
	 * Schedules the cleanup event from a known state.
	 *
	 * The bootstrap runs sync_storage_install(), so the event is already on
	 * the schedule when a test starts. Clearing first keeps a test from
	 * asserting against whatever that left behind.
	 */
	private function schedule_cleanup() {
		wp_clear_scheduled_hook( 'sync_storage_cleanup_stale_updates' );
		wp_schedule_event( time(), 'daily', 'sync_storage_cleanup_stale_updates' );
	}

	/**
	 * @covers ::sync_storage_deactivate
	 */
	public function test_deactivation_hook_is_registered() {
		$hook = 'deactivate_' . plugin_basename( WP_SYNC_STORAGE_PLUGIN_DIR . 'sync-storage.php' );

		$this->assertNotFalse( has_action( $hook, 'sync_storage_deactivate' ) );
	}

	/**
	 * @covers ::sync_storage_deactivate_site
	 */
	public function test_deactivation_clears_the_cleanup_cron() {
		$this->schedule_cleanup();
		$this->assertNotFalse(
			wp_next_scheduled( 'sync_storage_cleanup_stale_updates' ),
			'Precondition: the event should be scheduled before deactivating.'
		);

		sync_storage_deactivate();

		$this->assertFalse( wp_next_scheduled( 'sync_storage_cleanup_stale_updates' ) );
	}

	/**
	 * @covers ::sync_storage_deactivate_site
	 */
	public function test_deactivation_is_harmless_with_nothing_scheduled() {
		wp_clear_scheduled_hook( 'sync_storage_cleanup_stale_updates' );

		sync_storage_deactivate();

		$this->assertFalse( wp_next_scheduled( 'sync_storage_cleanup_stale_updates' ) );
	}

	/**
	 * wp_clear_scheduled_hook() would key on the empty argument list and
	 * walk past these.
	 *
	 * @covers ::sync_storage_deactivate_site
	 */
	public function test_deactivation_clears_events_scheduled_with_arguments() {
		wp_clear_scheduled_hook( 'sync_storage_cleanup_stale_updates' );
		wp_schedule_event( time(), 'daily', 'sync_storage_cleanup_stale_updates', array( 'legacy' ) );

		sync_storage_deactivate();

		$this->assertFalse( wp_next_scheduled( 'sync_storage_cleanup_stale_updates', array( 'legacy' ) ) );
	}

	/**
	 * Deactivating is not uninstalling: the log survives so a site that
	 * reactivates finds it where it left it.
	 *
	 * @covers ::sync_storage_deactivate
	 */
	public function test_deactivation_leaves_the_table_and_its_rows() {
		global $wpdb;

		Sync_Storage_Store::append( 'postType/post:1', array( 'update' => 'kept' ) );

		sync_storage_deactivate();

		$this->assertSame(
			$wpdb->collaboration,
			$wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->collaboration ) )
		);
		$this->assertSame( 1, Sync_Storage_Store::count( 'postType/post:1' ) );
	}

	/**
	 * @covers ::sync_storage_deactivate
	 * @covers ::sync_storage_for_each_site
	 *
	 * @group multisite
	 */
	public function test_network_deactivation_clears_the_cron_on_every_site() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}

		$site_ids = array(
			self::factory()->blog->create(),
			self::factory()->blog->create(),
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			$this->schedule_cleanup();
			restore_current_blog();
		}

		sync_storage_deactivate( true );

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			$scheduled = wp_next_scheduled( 'sync_storage_cleanup_stale_updates' );
			restore_current_blog();

			$this->assertFalse( $scheduled, "Site {$site_id} kept the cleanup event." );
		}
	}
}
