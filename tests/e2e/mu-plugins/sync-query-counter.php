<?php
/**
 * Plugin Name: Sync Query Counter
 * Description: Records how many MySQL queries the sync endpoint runs per request.
 *
 * The cost claims made for the polling path are per-poll query counts, so they
 * are only worth as much as a measurement of the endpoint actually running.
 * This records that number from a live request rather than inferring it from
 * the call graph.
 *
 * Test-only. Mapped into mu-plugins by .wp-env.json; never shipped.
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
if ( defined( 'SYNC_QUERY_COUNTER_OPTION' ) ) {
	return;
}

define( 'SYNC_QUERY_COUNTER_OPTION', 'sync_query_counter_log' );
define( 'SYNC_QUERY_COUNTER_ROUTE', '/wp-sync/v1/updates' );

/**
 * Mark the query count as the endpoint is entered.
 *
 * `rest_pre_dispatch` runs before route matching and before the permission
 * callback, so the window covers every query the endpoint is responsible for,
 * including the awareness read in check_permissions().
 *
 * @param mixed           $result  Dispatch result, passed through untouched.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Request being dispatched.
 * @return mixed The unmodified $result.
 */
function sync_query_counter_start( $result, $server, $request ) {
	if ( SYNC_QUERY_COUNTER_ROUTE === $request->get_route() ) {
		$GLOBALS['sync_query_counter_start'] = get_num_queries();
	}

	return $result;
}
add_filter( 'rest_pre_dispatch', 'sync_query_counter_start', 10, 3 );

/**
 * Record the delta once the endpoint has produced its response.
 *
 * The count is read before the log is written, so the write does not measure
 * itself. The option is non-autoloading to keep it off every other request.
 *
 * @param WP_REST_Response $response Response object.
 * @param WP_REST_Server   $server   Server instance.
 * @param WP_REST_Request  $request  Request being dispatched.
 * @return WP_REST_Response The unmodified $response.
 */
function sync_query_counter_stop( $response, $server, $request ) {
	if ( SYNC_QUERY_COUNTER_ROUTE !== $request->get_route() ) {
		return $response;
	}

	if ( ! isset( $GLOBALS['sync_query_counter_start'] ) ) {
		return $response;
	}

	$started = $GLOBALS['sync_query_counter_start'];
	$queries = get_num_queries() - $started;
	unset( $GLOBALS['sync_query_counter_start'] );

	$body  = $request->get_json_params();
	$rooms = isset( $body['rooms'] ) && is_array( $body['rooms'] ) ? $body['rooms'] : array();

	// With SAVEQUERIES on, keep the SQL too: the count alone says the endpoint
	// is flat in the number of clients, but not which statements make it up.
	$sql = array();
	if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
		global $wpdb;
		foreach ( array_slice( $wpdb->queries, $started ) as $query ) {
			$sql[] = preg_replace( '/\s+/', ' ', $query[0] );
		}
	}

	$log   = get_option( SYNC_QUERY_COUNTER_OPTION, array() );
	$log[] = array(
		'queries' => $queries,
		'rooms'   => count( $rooms ),
		'sql'     => $sql,
	);

	update_option( SYNC_QUERY_COUNTER_OPTION, $log, false );

	return $response;
}
add_filter( 'rest_post_dispatch', 'sync_query_counter_stop', 10, 3 );
