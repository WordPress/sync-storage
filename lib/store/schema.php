<?php
/**
 * Store schema: the wp_collaboration table.
 *
 * Owns the table definition and the migrations between its versions, and
 * nothing else. Deciding *when* either runs (activation, a new network site,
 * the first request after an update) is lib/install.php's job.
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the collaboration table name on $wpdb.
 *
 * Runs on every request, before anything reads $wpdb->collaboration. Adding
 * it to $wpdb->tables is what makes switch_to_blog() reprefix it, so the
 * store keeps addressing the current site's table on multisite.
 */
function wp_sync_storage_register_table() {
	global $wpdb;
	$wpdb->collaboration = $wpdb->prefix . 'collaboration';
	$wpdb->tables[]      = 'collaboration';
}

/**
 * Creates or updates the collaboration table for the current site.
 *
 * Per-site, not global: rows describe that site's posts, so each site in a
 * network needs its own table.
 *
 * `timestamp` is milliseconds since epoch rather than seconds -- see
 * Sync_Storage_Store::current_time_ms().
 *
 * The primary key is `collaboration_id`, matching the column-prefix
 * convention core applies to its own tables and the naming in
 * WordPress/wordpress-develop#11256. That PR's schema also has `client_id`,
 * `user_id` and a non-null `type`, which this table does not, so the match is
 * naming and key convention only -- it does not make rows interchangeable.
 */
function sync_storage_create_table() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	dbDelta(
		"CREATE TABLE {$wpdb->collaboration} (
			collaboration_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			room varchar(191) NOT NULL,
			type varchar(20) DEFAULT NULL,
			data longtext NOT NULL,
			timestamp bigint(20) unsigned NOT NULL,
			PRIMARY KEY (collaboration_id),
			KEY room_id (room(50), collaboration_id),
			KEY room_timestamp (room(50), timestamp)
		) $charset_collate;"
	);

	// dbDelta has no useful return value for "did this actually work" -- it
	// reports statements run, not statements that succeeded. Checking for the
	// column dbDelta was responsible for creating is what stands in for that,
	// and is what keeps a failed run from being recorded as a success below.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$wpdb->collaboration} LIKE 'collaboration_id'" ) ) {
		Sync_Storage_Logger::event(
			'Table creation failed',
			array(
				'table' => $wpdb->collaboration,
				'error' => $wpdb->last_error,
			)
		);
		return;
	}

	Sync_Storage_Logger::event(
		'Table created',
		array(
			'table'   => $wpdb->collaboration,
			'charset' => $charset_collate,
		)
	);

	update_option( 'sync_storage_db_version', WP_SYNC_STORAGE_DB_VERSION );
}

/**
 * Brings the current site's table up to WP_SYNC_STORAGE_DB_VERSION.
 *
 * Called on every request, so the no-op path reads one autoloaded option and
 * stops without touching the database.
 *
 * Steps are cumulative and each is guarded by the version that introduced it,
 * so a site updating from 1 to 4 runs 2, 3 and 4 in order. Never edit a step
 * sites have already run. Nothing serialises callers, so a step must also
 * tolerate finding its work done by a concurrent request.
 *
 * A step that reports failure stops the chain before the version is recorded.
 * The next request finds the same unmet version and tries again, instead of
 * being told there is nothing left to do.
 */
function sync_storage_upgrade_table() {
	$installed = (int) get_option( 'sync_storage_db_version', 0 );

	// >=, not ===: a site downgraded below its recorded version has nothing
	// here to run either, and re-running an old step against a schema built by
	// a newer one is not something any step is written to expect.
	if ( $installed >= WP_SYNC_STORAGE_DB_VERSION ) {
		return;
	}

	if ( $installed < 2 && ! sync_storage_upgrade_to_2() ) {
		Sync_Storage_Logger::event(
			'Table upgrade aborted',
			array(
				'from' => $installed,
				'to'   => WP_SYNC_STORAGE_DB_VERSION,
			)
		);
		return;
	}

	// dbDelta reconciles added columns, widened types and new indexes without
	// per-version code, and records the version. Steps above cover only what it
	// cannot express: renames and data rewrites.
	sync_storage_create_table();

	Sync_Storage_Logger::event(
		'Table upgraded',
		array(
			'from' => $installed,
			'to'   => WP_SYNC_STORAGE_DB_VERSION,
		)
	);
}

/**
 * Version 1 -> 2: rename the primary key from `id` to `collaboration_id`.
 *
 * The ALTER is explicit because dbDelta matches columns by name: it reads a
 * rename as an unfamiliar column to add and leaves `id` in place, which is two
 * AUTO_INCREMENT columns and rejected by InnoDB.
 *
 * CHANGE moves the column, so the primary key, the room_id index and the
 * AUTO_INCREMENT counter follow it. Copying into a new column would reset the
 * counter to 1, and clients polling with a higher cursor would see nothing
 * until it caught up.
 *
 * @return bool True if the site is left with `collaboration_id` in place (by
 *              this call or a concurrent one) or has no table yet for
 *              sync_storage_create_table() to create fresh. False only when
 *              the table exists in a shape this step does not recognise.
 */
function sync_storage_upgrade_to_2() {
	global $wpdb;

	// The version option defaults to 0, so a site with no table runs every step
	// before sync_storage_create_table() reaches it. Asked rather than left to
	// fail, to keep an ordinary state out of the error log. esc_like() matters
	// here: an unescaped prefix reads its own underscores as SQL wildcards.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->collaboration ) ) ) ) {
		return true;
	}

	// Checked before touching anything: this call may be racing another
	// request's copy of the same migration, and finding the rename already
	// done is success, not a step left to run.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( $wpdb->get_var( "SHOW COLUMNS FROM {$wpdb->collaboration} LIKE 'collaboration_id'" ) ) {
		return true;
	}

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( ! $wpdb->get_var( "SHOW COLUMNS FROM {$wpdb->collaboration} LIKE 'id'" ) ) {
		// Neither column exists: a table shape this step was not written for.
		// Recording success here would tell sync_storage_create_table() to
		// stamp the site as migrated over an unrecognised table.
		return false;
	}

	// SchemaChange: the plugin owns this table, and dbDelta, the sniff's usual
	// answer, cannot express a rename.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query(
		"ALTER TABLE {$wpdb->collaboration}
		 CHANGE `id` `collaboration_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT"
	);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

	// Re-checked instead of trusting the query() result: a concurrent request
	// renaming the column out from under this ALTER makes MySQL error it, but
	// the column ends up renamed either way, which is the outcome that matters.
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$renamed = (bool) $wpdb->get_var( "SHOW COLUMNS FROM {$wpdb->collaboration} LIKE 'collaboration_id'" );

	if ( $renamed ) {
		Sync_Storage_Logger::event(
			'Primary key renamed',
			array(
				'table' => $wpdb->collaboration,
				'from'  => 'id',
				'to'    => 'collaboration_id',
			)
		);
	} else {
		Sync_Storage_Logger::event(
			'Primary key rename failed',
			array(
				'table' => $wpdb->collaboration,
				'error' => $wpdb->last_error,
			)
		);
	}

	return $renamed;
}
