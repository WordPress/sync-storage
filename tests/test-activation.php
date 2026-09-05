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

	/**
	 * Deactivating this plugin does not drop its table, so activating a
	 * newer version against a site that ran an older one is an upgrade,
	 * not a fresh install. sync_storage_install_site() used to call
	 * sync_storage_create_table() directly, a fresh-install path where
	 * dbDelta cannot rename an existing `id` column, and the site would be
	 * recorded as migrated over a table that was never actually touched.
	 *
	 * @covers ::sync_storage_install_site
	 */
	public function test_activation_upgrades_an_existing_older_table_instead_of_skipping_it() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->collaboration}" );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query(
			"CREATE TABLE {$wpdb->collaboration} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				room varchar(191) NOT NULL,
				type varchar(20) DEFAULT NULL,
				data longtext NOT NULL,
				timestamp bigint(20) unsigned NOT NULL,
				PRIMARY KEY (id),
				KEY room_id (room(50), id),
				KEY room_timestamp (room(50), timestamp)
			) " . $wpdb->get_charset_collate()
		);
		update_option( 'sync_storage_db_version', 1 );

		sync_storage_install_site();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->collaboration}" );

		$this->assertContains( 'collaboration_id', $columns, 'Activation left the old schema in place.' );
		$this->assertSame(
			WP_SYNC_STORAGE_DB_VERSION,
			(int) get_option( 'sync_storage_db_version' ),
			'The recorded version does not match what the table actually is.'
		);

		// Restore the table the rest of the suite expects.
		sync_storage_create_table();
	}
}
