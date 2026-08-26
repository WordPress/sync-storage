<?php
/**
 * Tests for the Gutenberg integration (lib/rtc/integration.php).
 *
 * @package Sync_Storage
 */

/**
 * Test the cached filter-support check.
 *
 * Whether the filter fires is a property of the Gutenberg build under test,
 * not something these tests can set, so they assert on how the answer is
 * cached and invalidated rather than on the answer itself.
 */
class WP_Test_Sync_Storage_Integration extends WP_UnitTestCase {

	/**
	 * Skips the class when the deferred loader did not run.
	 *
	 * Without it the suite fatals on an undefined function instead of skipping.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! function_exists( 'sync_storage_gutenberg_build_id' ) ) {
			$this->markTestSkipped( 'Collaboration integration not loaded.' );
		}
	}

	/**
	 * Test that the build id distinguishes builds sharing a version number.
	 */
	public function test_build_id_includes_the_collaboration_file_mtime() {
		if ( ! function_exists( 'gutenberg_register_collaboration_rest_routes' ) ) {
			$this->markTestSkipped( 'Gutenberg collaboration bootstrap not loaded.' );
		}

		$file = ( new ReflectionFunction( 'gutenberg_register_collaboration_rest_routes' ) )->getFileName();

		$this->assertSame(
			GUTENBERG_VERSION . ':' . filemtime( $file ),
			sync_storage_gutenberg_build_id()
		);
	}

	/**
	 * Test that a cache entry from a different build is rechecked.
	 *
	 * Trunk keeps the last released version number in its plugin header, so
	 * swapping a released build for a trunk build of the same version has to
	 * invalidate this cache on something other than GUTENBERG_VERSION.
	 */
	public function test_cache_from_another_build_is_ignored() {
		update_option(
			'sync_storage_filter_check',
			array(
				'gutenberg_build' => GUTENBERG_VERSION . ':1',
				'supported'       => false,
			),
			false
		);

		$supported = sync_storage_collaboration_filter_supported();

		$this->assertSame(
			array(
				'gutenberg_build' => sync_storage_gutenberg_build_id(),
				'supported'       => $supported,
			),
			get_option( 'sync_storage_filter_check' )
		);
	}

	/**
	 * Test that a cache entry written by an earlier version is rechecked.
	 */
	public function test_cache_in_the_pre_build_id_format_is_ignored() {
		update_option(
			'sync_storage_filter_check',
			array(
				'gutenberg_version' => GUTENBERG_VERSION,
				'supported'         => false,
			),
			false
		);

		sync_storage_collaboration_filter_supported();

		$this->assertArrayHasKey( 'gutenberg_build', get_option( 'sync_storage_filter_check' ) );
	}

	/**
	 * Test that a cache entry for this build is reused.
	 */
	public function test_cache_for_this_build_is_reused() {
		// A value the live check could not produce: if it comes back, it came
		// from the cache and no REST dispatch happened.
		update_option(
			'sync_storage_filter_check',
			array(
				'gutenberg_build' => sync_storage_gutenberg_build_id(),
				'supported'       => 'cached',
			),
			false
		);

		$this->assertSame( 'cached', sync_storage_collaboration_filter_supported() );
	}
}
