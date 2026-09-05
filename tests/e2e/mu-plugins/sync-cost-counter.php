<?php
/**
 * Plugin Name: Sync Cost Counter
 * Description: Records what the database actually did for each sync request: queries, rows touched, and physical IO.
 *
 * The cost claims made for the polling path are per-poll numbers, and a number
 * read off the call graph is worth less than one taken from the endpoint
 * running. This records them from a live request.
 *
 * Query count alone stops being the interesting number once it is flat. Six
 * queries that each read one row by primary key and six that each scan a
 * growing room cost the same on this counter and diverge without bound on a
 * real disk, so the row and IO counters below are what carry the claim on
 * shared hosting.
 *
 * Test-only. Mapped into mu-plugins by .wp-env.json; never shipped. The
 * field equivalent, for a site this harness cannot drive, is
 * tools/rtc-cost-probe.php.
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// This file lives in the plugin tree and is also mapped into mu-plugins, so it
// can be reached twice in one request. The guard is a runtime constant rather
// than function_exists(): PHP declares a file's top-level functions when it
// compiles the file, before running any of its statements, so the functions
// below already exist by the time this line is reached on the first load.
if ( defined( 'SYNC_COST_COUNTER_OPTION' ) ) {
	return;
}

define( 'SYNC_COST_COUNTER_OPTION', 'sync_cost_counter_log' );
define( 'SYNC_COST_COUNTER_ROUTE', '/wp-sync/v1/updates' );

/**
 * Per-connection row access, counted by the storage engine handler.
 *
 * These are the rows the server actually touched, which is the quantity a
 * query count hides. `read_next` is the depth of an index range scan and
 * `read_rnd_next` the depth of a full table scan, so either one growing with
 * the size of a room is the signal that a poll is reading the log rather than
 * seeking into it.
 *
 * Session scope, so a parallel request on another connection cannot
 * contaminate the reading.
 */
const SYNC_COST_COUNTER_SESSION_VARS = array(
	'Handler_read_key',
	'Handler_read_next',
	'Handler_read_rnd_next',
	'Handler_write',
	'Handler_update',
	'Handler_delete',
);

/**
 * Physical IO, counted by InnoDB.
 *
 * `data_reads` and `data_writes` are page-level disk operations and
 * `os_log_fsyncs` is roughly one durable write per commit: together they are
 * the IOPS a shared host meters, as opposed to the logical work above.
 * `buffer_pool_reads` is the subset of reads that missed the buffer pool and
 * went to the disk, which is what a cold cache costs.
 *
 * Global scope, because InnoDB does not break these out per connection. The
 * delta is only attributable to the request under test on an otherwise idle
 * server, which is what the e2e suite is: workers: 1, fullyParallel: false.
 */
const SYNC_COST_COUNTER_GLOBAL_VARS = array(
	'Innodb_data_reads',
	'Innodb_data_writes',
	'Innodb_data_fsyncs',
	'Innodb_os_log_fsyncs',
	'Innodb_buffer_pool_reads',
);

/**
 * Read the counters above in their current state.
 *
 * Two queries rather than one against performance_schema or
 * information_schema, because which of those exposes status variables, and
 * under which name, differs across MySQL 5.7, MySQL 8 and MariaDB. SHOW
 * STATUS is the spelling all three agree on.
 *
 * @return array<string,int> Counter name to value.
 */
function sync_cost_counter_read() {
	global $wpdb;

	$snapshot = array();

	foreach ( array( 'SESSION' => SYNC_COST_COUNTER_SESSION_VARS, 'GLOBAL' => SYNC_COST_COUNTER_GLOBAL_VARS ) as $scope => $names ) {
		$placeholders = implode( ', ', array_fill( 0, count( $names ), '%s' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SHOW {$scope} STATUS WHERE Variable_name IN ( {$placeholders} )",
				$names
			)
		);

		foreach ( (array) $rows as $row ) {
			$snapshot[ $row->Variable_name ] = (int) $row->Value; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
	}

	return $snapshot;
}

/**
 * Subtract one counter snapshot from another.
 *
 * @param array<string,int> $before Earlier snapshot.
 * @param array<string,int> $after  Later snapshot.
 * @return array<string,int> Per-counter difference, keyed lowercase.
 */
function sync_cost_counter_delta( array $before, array $after ) {
	$delta = array();

	foreach ( $after as $name => $value ) {
		$delta[ strtolower( $name ) ] = $value - ( isset( $before[ $name ] ) ? $before[ $name ] : 0 );
	}

	return $delta;
}

/**
 * Snapshot the counters as the endpoint is entered.
 *
 * `rest_pre_dispatch` runs before route matching and before the permission
 * callback, so the window covers every query the endpoint is responsible for,
 * including the awareness read in check_permissions().
 *
 * The probe pays for itself twice here, deliberately. SHOW STATUS is a query
 * like any other and on some server versions builds an internal temporary
 * table, so it moves both the query count and the handler counters it is
 * reporting. Taking the reading twice measures what one reading costs, and
 * the second is the baseline the request is then measured against. Both
 * corrections are applied on the way out, so the numbers this records are the
 * endpoint's and not the probe's.
 *
 * @param mixed           $result  Dispatch result, passed through untouched.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Request being dispatched.
 * @return mixed The unmodified $result.
 */
function sync_cost_counter_start( $result, $server, $request ) {
	if ( SYNC_COST_COUNTER_ROUTE !== $request->get_route() ) {
		return $result;
	}

	$queries_before = get_num_queries();

	$calibration = sync_cost_counter_read();
	$baseline    = sync_cost_counter_read();

	$GLOBALS['sync_cost_counter_start'] = array(
		'queries'        => $queries_before,
		'probe_queries'  => get_num_queries() - $queries_before,
		'counters'       => $baseline,
		'probe_counters' => sync_cost_counter_delta( $calibration, $baseline ),
	);

	return $result;
}
add_filter( 'rest_pre_dispatch', 'sync_cost_counter_start', 10, 3 );

/**
 * Record the deltas once the endpoint has produced its response.
 *
 * Everything is read before the log is written, so the write does not measure
 * itself. The option is non-autoloading to keep it off every other request.
 *
 * @param WP_REST_Response $response Response object.
 * @param WP_REST_Server   $server   Server instance.
 * @param WP_REST_Request  $request  Request being dispatched.
 * @return WP_REST_Response The unmodified $response.
 */
function sync_cost_counter_stop( $response, $server, $request ) {
	if ( SYNC_COST_COUNTER_ROUTE !== $request->get_route() ) {
		return $response;
	}

	if ( ! isset( $GLOBALS['sync_cost_counter_start'] ) ) {
		return $response;
	}

	$started = $GLOBALS['sync_cost_counter_start'];
	unset( $GLOBALS['sync_cost_counter_start'] );

	// The endpoint's own window starts after the probe's queries at dispatch.
	$endpoint_first = $started['queries'] + $started['probe_queries'];
	$queries        = get_num_queries() - $endpoint_first;

	// With SAVEQUERIES on, keep the SQL too: the counts say the endpoint is
	// flat in the number of clients, but not which statements make it up.
	// Sliced before the closing snapshot runs, so the probe's own SHOW STATUS
	// does not appear in the listing.
	$sql = array();
	if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
		global $wpdb;
		foreach ( array_slice( $wpdb->queries, $endpoint_first, $queries ) as $query ) {
			$sql[] = preg_replace( '/\s+/', ' ', $query[0] );
		}
	}

	$counters = sync_cost_counter_delta( $started['counters'], sync_cost_counter_read() );

	// Charge the closing snapshot back out. A counter the probe does not move
	// is unaffected; one it does, such as the temporary table behind SHOW
	// STATUS, comes back to what the endpoint alone was responsible for.
	foreach ( $started['probe_counters'] as $name => $cost ) {
		if ( isset( $counters[ $name ] ) ) {
			$counters[ $name ] = max( 0, $counters[ $name ] - $cost );
		}
	}

	$body  = $request->get_json_params();
	$rooms = isset( $body['rooms'] ) && is_array( $body['rooms'] ) ? $body['rooms'] : array();

	$log   = get_option( SYNC_COST_COUNTER_OPTION, array() );
	$log[] = array_merge(
		array(
			'queries' => $queries,
			'rooms'   => count( $rooms ),
			'sql'     => $sql,
		),
		$counters
	);

	update_option( SYNC_COST_COUNTER_OPTION, $log, false );

	return $response;
}
add_filter( 'rest_post_dispatch', 'sync_cost_counter_stop', 10, 3 );
