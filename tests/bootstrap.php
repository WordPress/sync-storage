<?php
/**
 * PHPUnit bootstrap file for the Sync Storage plugin.
 *
 * @package Sync_Storage
 */

// Determine the WordPress test suite location.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Verify the test suite exists.
if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php. Have you run wp-env start?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI bootstrap, WordPress not loaded.
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested and its dependencies.
 */
function _manually_load_plugin() {
	// Load Gutenberg trunk (required dependency)
	if ( file_exists( dirname( __DIR__ ) . '/gutenberg-trunk/gutenberg.php' ) ) {
		require dirname( __DIR__ ) . '/gutenberg-trunk/gutenberg.php';
	}

	// Load Presence API dependency (stub if not available)
	if ( file_exists( dirname( __DIR__, 2 ) . '/presence-api/presence-api.php' ) ) {
		require dirname( __DIR__, 2 ) . '/presence-api/presence-api.php';
	}

	require dirname( __DIR__ ) . '/sync-storage.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
