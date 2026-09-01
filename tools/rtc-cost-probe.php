<?php
/**
 * Plugin Name: RTC Cost Probe
 * Description: Logs the per-request server cost of Gutenberg's real-time collaboration polling endpoint.
 * License: GPL-2.0-or-later
 *
 * Drop this file into wp-content/mu-plugins/ on a site running Gutenberg's RTC
 * feature. It writes one line per sync poll to the PHP error log and touches
 * nothing else: no database writes, no options, no tables, no admin surface.
 * That matters here, because a probe that records to the database changes the
 * IO profile it is supposed to be measuring.
 *
 * Each line looks like:
 *
 *   [RTC-COST] queries=6 ms=18.42 mem_peak_kb=8192 rooms=1 clients=3 user=5
 *
 * queries      MySQL queries the request ran, from first REST dispatch to response.
 * ms           Wall time over the same window.
 * mem_peak_kb  Peak memory for the whole request.
 * rooms        Rooms in the request body. The client batches up to 50.
 * clients      Awareness entries in the busiest room, i.e. how many editors it saw.
 * user         Current user ID, for spotting one person across several connections.
 *
 * Sampling: define RTC_COST_PROBE_SAMPLE as a divisor to log 1 in N requests,
 * e.g. define( 'RTC_COST_PROBE_SAMPLE', 100 ). Defaults to logging every one.
 *
 * @package RTC_Cost_Probe
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The guard is a runtime constant rather than function_exists(): PHP declares a
// file's top-level functions when it compiles the file, before running any of
// its statements, so the functions below already exist by the time this line is
// reached on the very first load.
if ( defined( 'RTC_COST_PROBE_ROUTE' ) ) {
	return;
}

define( 'RTC_COST_PROBE_ROUTE', '/wp-sync/v1/updates' );

/**
 * Whether this request should be recorded.
 *
 * Sampling is decided once and reused by both hooks, so a sampled-out request
 * does not pay for the snapshot either.
 *
 * @return bool True if this request is being measured.
 */
function rtc_cost_probe_is_sampled() {
	static $sampled = null;

	if ( null !== $sampled ) {
		return $sampled;
	}

	$divisor = defined( 'RTC_COST_PROBE_SAMPLE' ) ? (int) RTC_COST_PROBE_SAMPLE : 1;
	$sampled = $divisor < 2 ? true : ( 0 === wp_rand( 0, $divisor - 1 ) );

	return $sampled;
}

/**
 * Snapshot counters as the endpoint is entered.
 *
 * rest_pre_dispatch runs before route matching and before the permission
 * callback, so the window covers every query the endpoint is responsible for,
 * including any read the permission check performs.
 *
 * @param mixed           $result  Dispatch result, passed through untouched.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Request being dispatched.
 * @return mixed The unmodified $result.
 */
function rtc_cost_probe_start( $result, $server, $request ) {
	if ( RTC_COST_PROBE_ROUTE === $request->get_route() && rtc_cost_probe_is_sampled() ) {
		$GLOBALS['rtc_cost_probe_start'] = array(
			'queries' => get_num_queries(),
			'time'    => microtime( true ),
		);
	}

	return $result;
}
add_filter( 'rest_pre_dispatch', 'rtc_cost_probe_start', 10, 3 );

/**
 * Emit the measurement once the endpoint has produced its response.
 *
 * @param WP_REST_Response $response Response object.
 * @param WP_REST_Server   $server   Server instance.
 * @param WP_REST_Request  $request  Request being dispatched.
 * @return WP_REST_Response The unmodified $response.
 */
function rtc_cost_probe_stop( $response, $server, $request ) {
	if ( RTC_COST_PROBE_ROUTE !== $request->get_route() ) {
		return $response;
	}

	if ( ! isset( $GLOBALS['rtc_cost_probe_start'] ) ) {
		return $response;
	}

	$started = $GLOBALS['rtc_cost_probe_start'];
	unset( $GLOBALS['rtc_cost_probe_start'] );

	$body  = $request->get_json_params();
	$rooms = ( is_array( $body ) && isset( $body['rooms'] ) && is_array( $body['rooms'] ) ) ? $body['rooms'] : array();

	// Occupancy comes from the response rather than the request: the request
	// says who is polling, the response says who the room currently holds.
	$clients = 0;
	$data    = $response instanceof WP_REST_Response ? $response->get_data() : null;

	if ( is_array( $data ) && isset( $data['rooms'] ) && is_array( $data['rooms'] ) ) {
		foreach ( $data['rooms'] as $room ) {
			if ( isset( $room['awareness'] ) && is_array( $room['awareness'] ) ) {
				$clients = max( $clients, count( $room['awareness'] ) );
			}
		}
	}

	error_log(
		sprintf(
			'[RTC-COST] queries=%d ms=%.2f mem_peak_kb=%d rooms=%d clients=%d user=%d',
			get_num_queries() - $started['queries'],
			( microtime( true ) - $started['time'] ) * 1000,
			(int) round( memory_get_peak_usage( true ) / 1024 ),
			count( $rooms ),
			$clients,
			get_current_user_id()
		)
	);

	return $response;
}
add_filter( 'rest_post_dispatch', 'rtc_cost_probe_stop', 10, 3 );
