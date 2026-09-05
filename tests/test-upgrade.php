<?php
/**
 * Tests for the schema migration path (lib/store/schema.php, lib/install.php).
 *
 * A migration carries a table an older release created, and the suite cannot
 * run an older release. SCHEMA_V1 stands in for one, so it stays frozen at
 * what version 1 shipped while lib/store/schema.php moves on.
 *
 * @package Sync_Storage
 *
 * @group store
 */
class WP_Test_Sync_Storage_Upgrade extends WP_UnitTestCase {

	/**
	 * The wp_collaboration table as version 1 defined it. Do not update it to
	 * match the current schema; its value is that it does not.
	 *
	 * @var string
	 */
	const SCHEMA_V1 = 'CREATE TABLE %s (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		room varchar(191) NOT NULL,
		type varchar(20) DEFAULT NULL,
		data longtext NOT NULL,
		timestamp bigint(20) unsigned NOT NULL,
		PRIMARY KEY (id),
		KEY room_id (room(50), id),
		KEY room_timestamp (room(50), timestamp)
	)';

	public function tear_down() {
		// Restore the table and version the rest of the suite expects.
		$this->drop_table();
		sync_storage_create_table();

		parent::tear_down();
	}

	/**
	 * Asserts on registration, so it executes nothing in lib/.
	 *
	 * A migration hanging off activation alone never reaches a site that was
	 * updated rather than newly activated.
	 *
	 * @coversNothing
	 */
	public function test_upgrade_runs_on_every_request_not_just_activation() {
		$this->assertNotFalse( has_action( 'plugins_loaded', 'sync_storage_upgrade_table' ) );
	}

	/**
	 * @covers ::sync_storage_upgrade_table
	 * @covers ::sync_storage_upgrade_to_2
	 */
	public function test_upgrade_renames_the_primary_key() {
		$this->install_v1();

		sync_storage_upgrade_table();

		$this->assertSame(
			array( 'collaboration_id', 'room', 'type', 'data', 'timestamp' ),
			$this->columns()
		);
	}

	/**
	 * dbDelta alone adds the new column and leaves the old one behind, since it
	 * matches by name. The rename has to move the column, not duplicate it.
	 *
	 * @covers ::sync_storage_upgrade_to_2
	 */
	public function test_upgrade_does_not_leave_the_old_column_behind() {
		$this->install_v1();

		sync_storage_upgrade_table();

		$this->assertNotContains( 'id', $this->columns() );
	}

	/**
	 * @covers ::sync_storage_upgrade_to_2
	 */
	public function test_upgrade_preserves_rows_and_their_ids() {
		global $wpdb;

		$this->install_v1();

		// Through $wpdb, not Sync_Storage_Store::append(), which speaks the
		// current schema.
		foreach ( array( 'first', 'second' ) as $data ) {
			$wpdb->insert(
				$wpdb->collaboration,
				array(
					'room'      => 'widget/sidebar:main',
					'data'      => wp_json_encode( $data ),
					'timestamp' => Sync_Storage_Store::current_time_ms(),
				),
				array( '%s', '%s', '%d' )
			);
		}

		$ids_before = $wpdb->get_col( "SELECT id FROM {$wpdb->collaboration} ORDER BY id ASC" );

		sync_storage_upgrade_table();

		$entries = Sync_Storage_Store::get_after( 'widget/sidebar:main', 0 );

		$this->assertSame( array( 'first', 'second' ), wp_list_pluck( $entries, 'data' ) );
		$this->assertSame(
			array_map( 'intval', $ids_before ),
			wp_list_pluck( $entries, 'id' ),
			'Connected clients hold cursors into these ids; renumbering them replays or skips updates.'
		);
	}

	/**
	 * ALTER TABLE ... CHANGE keeps AUTO_INCREMENT where it was. Recreating the
	 * column resets it to 1, and a client polling with a higher cursor sees no
	 * new updates until the counter catches up.
	 *
	 * The table is left empty before migrating, on purpose: with any row still
	 * present, MAX(id) + 1 and a genuinely preserved counter allocate the same
	 * next value, so the assertion would pass even against a migration that
	 * silently reset it.
	 *
	 * @covers ::sync_storage_upgrade_to_2
	 */
	public function test_upgrade_preserves_the_autoincrement_counter() {
		global $wpdb;

		$this->install_v1();

		for ( $i = 0; $i < 5; $i++ ) {
			$wpdb->insert(
				$wpdb->collaboration,
				array(
					'room'      => 'widget/sidebar:throwaway',
					'data'      => wp_json_encode( 'discarded' ),
					'timestamp' => Sync_Storage_Store::current_time_ms(),
				),
				array( '%s', '%s', '%d' )
			);
		}

		$high_water_mark = (int) $wpdb->insert_id;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->collaboration} WHERE room = 'widget/sidebar:throwaway'" );

		sync_storage_upgrade_table();

		$this->assertGreaterThan(
			$high_water_mark,
			Sync_Storage_Store::append( 'widget/sidebar:main', 'first' ),
			'The table is empty at this point, so a reset counter would restart at 1.'
		);
	}

	/**
	 * @covers ::sync_storage_upgrade_table
	 */
	public function test_upgrade_records_the_new_version() {
		$this->install_v1();

		sync_storage_upgrade_table();

		$this->assertSame(
			WP_SYNC_STORAGE_DB_VERSION,
			(int) get_option( 'sync_storage_db_version' )
		);
	}

	/**
	 * A second request arriving before the version is written finds the rename
	 * already applied, and must not error on a column that is gone.
	 *
	 * @covers ::sync_storage_upgrade_to_2
	 */
	public function test_upgrade_is_idempotent() {
		$this->install_v1();

		sync_storage_upgrade_table();
		update_option( 'sync_storage_db_version', 1 );
		sync_storage_upgrade_table();

		$this->assertSame(
			array( 'collaboration_id', 'room', 'type', 'data', 'timestamp' ),
			$this->columns()
		);
	}

	/**
	 * A site with no table reaches the migration at version 0, where the option
	 * defaults. The step has nothing to rename and must not log a MySQL error
	 * for it.
	 *
	 * @covers ::sync_storage_upgrade_to_2
	 */
	public function test_upgrade_creates_the_table_when_there_is_none() {
		global $wpdb;

		$this->drop_table();
		delete_option( 'sync_storage_db_version' );

		$suppressed = $wpdb->suppress_errors( false );
		$wpdb->last_error = '';

		sync_storage_upgrade_table();

		$this->assertSame( '', $wpdb->last_error, 'The migration reported a database error.' );
		$wpdb->suppress_errors( $suppressed );

		$this->assertSame(
			array( 'collaboration_id', 'room', 'type', 'data', 'timestamp' ),
			$this->columns()
		);
	}

	/**
	 * A site recorded at a version newer than this code has nothing here to
	 * run. Falling through to sync_storage_create_table() anyway would
	 * reconcile the newer schema toward this older CREATE TABLE and record
	 * the site back at this version, even though the database was never
	 * touched.
	 *
	 * @covers ::sync_storage_upgrade_table
	 */
	public function test_upgrade_does_not_run_against_a_newer_recorded_version() {
		global $wpdb;

		update_option( 'sync_storage_db_version', WP_SYNC_STORAGE_DB_VERSION + 1 );

		$queries = $wpdb->num_queries;

		sync_storage_upgrade_table();

		$this->assertSame(
			$queries,
			$wpdb->num_queries,
			'A site ahead of this code has nothing here to run.'
		);
		$this->assertSame(
			WP_SYNC_STORAGE_DB_VERSION + 1,
			(int) get_option( 'sync_storage_db_version' ),
			'The recorded version must not be rewritten backward.'
		);
	}

	/**
	 * @covers ::sync_storage_upgrade_table
	 */
	public function test_upgrade_touches_nothing_when_the_version_matches() {
		global $wpdb;

		// Set rather than inherited: CREATE TABLE commits implicitly, so the
		// per-test transaction does not reliably restore this option.
		update_option( 'sync_storage_db_version', WP_SYNC_STORAGE_DB_VERSION );

		$queries = $wpdb->num_queries;

		sync_storage_upgrade_table();

		$this->assertSame(
			$queries,
			$wpdb->num_queries,
			'Called on every request, so an up-to-date site must not pay a query.'
		);
	}

	/**
	 * A table shape sync_storage_upgrade_to_2() was not written to migrate:
	 * present, but with neither the old nor the new primary key column.
	 *
	 * @covers ::sync_storage_upgrade_to_2
	 */
	public function test_upgrade_to_2_does_not_recognize_a_table_with_neither_column() {
		$this->install_unrecognized_shape();

		$this->assertFalse( sync_storage_upgrade_to_2() );
	}

	/**
	 * A step reporting failure must stop the chain before the version is
	 * recorded, or the next request would find nothing left to do.
	 *
	 * @covers ::sync_storage_upgrade_table
	 */
	public function test_upgrade_table_does_not_record_a_version_when_the_step_fails() {
		$this->install_unrecognized_shape();
		delete_option( 'sync_storage_db_version' );

		sync_storage_upgrade_table();

		$this->assertFalse( get_option( 'sync_storage_db_version' ) );
	}

	/**
	 * dbDelta reports statements run, not statements that succeeded, so the
	 * version must not be recorded on its say-so alone. An identifier over
	 * MySQL's 64-character limit is rejected before dbDelta creates anything,
	 * standing in for a dbDelta run that failed outright.
	 *
	 * @covers ::sync_storage_create_table
	 */
	public function test_create_table_does_not_record_a_version_when_dbdelta_fails() {
		global $wpdb;

		$this->drop_table();
		delete_option( 'sync_storage_db_version' );

		$real_table           = $wpdb->collaboration;
		$wpdb->collaboration = str_repeat( 'x', 65 );

		$suppressed = $wpdb->suppress_errors( true );
		sync_storage_create_table();
		$wpdb->suppress_errors( $suppressed );

		$wpdb->collaboration = $real_table;

		$this->assertFalse( get_option( 'sync_storage_db_version' ) );
	}

	/**
	 * Replace the table with the version 1 definition and say so in the option.
	 */
	private function install_v1() {
		global $wpdb;

		$this->drop_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$wpdb->query( sprintf( self::SCHEMA_V1, $wpdb->collaboration ) . ' ' . $wpdb->get_charset_collate() );

		update_option( 'sync_storage_db_version', 1 );
	}

	/**
	 * Replace the table with a shape that has neither `id` nor
	 * `collaboration_id`, and say so in the option.
	 */
	private function install_unrecognized_shape() {
		global $wpdb;

		$this->drop_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query(
			"CREATE TABLE {$wpdb->collaboration} (
				room varchar(191) NOT NULL,
				timestamp bigint(20) unsigned NOT NULL
			) " . $wpdb->get_charset_collate()
		);
	}

	/**
	 * Drop the collaboration table if it is there.
	 */
	private function drop_table() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->collaboration}" );
	}

	/**
	 * @return string[] The collaboration table's column names, in order.
	 */
	private function columns() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->collaboration}" );
	}
}
