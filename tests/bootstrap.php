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
	if ( file_exists( dirname( __DIR__ ) . '/gutenberg/gutenberg.php' ) ) {
		require dirname( __DIR__ ) . '/gutenberg/gutenberg.php';
	}

	// Load Presence API if present. No stub otherwise: tests that need it
	// (e.g. test_awareness_state()) check for it and skip themselves.
	if ( file_exists( dirname( __DIR__, 2 ) . '/presence-api/presence-api.php' ) ) {
		require dirname( __DIR__, 2 ) . '/presence-api/presence-api.php';
	}

	require dirname( __DIR__ ) . '/sync-storage.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

/**
 * Create database tables after WordPress is loaded.
 */
function _create_test_tables() {
	sync_storage_install();

	// Presence provisions its table from admin_init, which never fires here.
	// Without this wp_presence_has_table() is false, so every presence read
	// and write no-ops and the awareness tests assert against nothing.
	if ( function_exists( 'wp_maybe_create_presence_table' ) ) {
		wp_maybe_create_presence_table();
	}
}
tests_add_filter( 'init', '_create_test_tables' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
