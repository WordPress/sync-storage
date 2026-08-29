<?php
/**
 * Tests for single-site activation (lib/install.php).
 *
 * @package Sync_Storage
 *
 * @group rtc
 */
class WP_Test_Sync_Storage_Activation extends WP_UnitTestCase {

	/**
	 * Test that activation does not read the editor's post meta storage.
	 *
	 * Activation used to scan every wp_sync_storage post with numberposts =>
	 * -1 to migrate the editor's default storage across, which on a site that
	 * had been collaborating meant one post per room and their whole meta
	 * cache primed inside the activation request. It never migrated anything:
	 * it read a meta key the editor does not write, and a room is only
	 * recoverable from that storage as md5( $room ) in a post slug (#74).
	 *
	 * @covers ::sync_storage_install_site
	 */
	public function test_activation_does_not_query_the_editors_storage_post_type() {
		self::factory()->post->create( array( 'post_type' => 'wp_sync_storage' ) );

		// The removed migration ran once and flagged itself done, and the test
		// bootstrap activates the plugin before any test runs. Without clearing
		// the flag the scan would be skipped here for that reason rather than
		// because it is gone, and this would pass against the bug it covers.
		delete_option( 'sync_storage_migrated_from_post_meta' );

		$queried = false;
		$spy     = static function ( $query ) use ( &$queried ) {
			if ( 'wp_sync_storage' === $query->get( 'post_type' ) ) {
				$queried = true;
			}
		};

		add_action( 'pre_get_posts', $spy );
		sync_storage_install_site();
		remove_action( 'pre_get_posts', $spy );

		$this->assertFalse( $queried, 'Activation queried wp_sync_storage posts.' );
	}
}
