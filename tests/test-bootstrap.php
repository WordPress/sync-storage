<?php
/**
 * Tests for what the plugin loads, and when (sync-storage.php).
 *
 * @package Sync_Storage
 */

/**
 * Test that the storage layer stays independent of the editor consuming it.
 *
 * The plugin used to refuse to load at all without the Gutenberg plugin,
 * which made a storage layer a dependent of one of its own consumers. These
 * tests hold the inversion undone.
 */
class WP_Test_Sync_Storage_Bootstrap extends WP_UnitTestCase {

	/**
	 * Reads the files sync-storage.php requires at file scope.
	 *
	 * Read out of the plugin file rather than listed here, so that moving a
	 * require across the boundary is what the test notices. A hardcoded list
	 * would keep passing while the loader changed underneath it.
	 *
	 * @return string[] Paths relative to the plugin root.
	 */
	private function unconditional_requires() {
		$plugin = file_get_contents( dirname( __DIR__ ) . '/sync-storage.php' );
		$scope  = strstr( $plugin, 'function sync_storage_load_collaboration_integration', true );

		$this->assertNotFalse( $scope, 'The deferred loader is gone; this test needs rewriting.' );

		preg_match_all( "/require_once WP_SYNC_STORAGE_PLUGIN_DIR \. '([^']+)'/", $scope, $matches );

		return $matches[1];
	}

	/**
	 * Test that nothing loaded unconditionally names an editor symbol.
	 *
	 * A symbol from the editor in any of these files is the layering mistake
	 * this plugin's structure exists to prevent: it would make the table, its
	 * cleanup and its migrations unloadable without Gutenberg again.
	 */
	public function test_unconditionally_loaded_files_name_no_editor_symbols() {
		$dir   = dirname( __DIR__ ) . '/';
		$files = $this->unconditional_requires();

		$this->assertNotEmpty( $files, 'No file scope requires found; the parsing above has drifted.' );

		foreach ( $files as $file ) {
			$contents = file_get_contents( $dir . $file );

			$this->assertNotFalse( $contents, "Could not read {$file}." );
			$this->assertDoesNotMatchRegularExpression(
				'/WP_Sync_Storage|GUTENBERG_VERSION|gutenberg_|__unstable_wp_sync_storage/',
				$contents,
				"{$file} is loaded before any editor is known to exist, so it cannot name one."
			);
		}
	}

	/**
	 * Test that the editor is not declared a hard dependency.
	 *
	 * Requires Plugins blocks activation until every plugin listed is active,
	 * so listing the editor here would reinstate the old behaviour no matter
	 * what the loader does.
	 */
	public function test_requires_plugins_does_not_list_the_editor() {
		$header = get_file_data(
			dirname( __DIR__ ) . '/sync-storage.php',
			array( 'requires_plugins' => 'Requires Plugins' )
		);

		$required = array_filter( array_map( 'trim', explode( ',', $header['requires_plugins'] ) ) );

		$this->assertSame( array( 'presence-api' ), $required );
	}

	/**
	 * Test that deferring the integration did not unhook it.
	 *
	 * The pieces that need the editor now load on plugins_loaded instead of at
	 * file scope, which is only correct if they still end up loaded when the
	 * editor is there.
	 */
	public function test_integration_is_wired_up_when_the_interface_exists() {
		if ( ! interface_exists( 'WP_Sync_Storage' ) ) {
			$this->markTestSkipped( 'No collaborative editing interface in this environment.' );
		}

		$this->assertTrue( class_exists( 'Sync_Storage_Provider' ) );
		$this->assertNotFalse( has_filter( '__unstable_wp_sync_storage' ) );
		$this->assertNotFalse( has_filter( 'option_gutenberg-experiments' ) );
	}

	/**
	 * Test that the store is usable with no editor involved at all.
	 */
	public function test_store_works_without_the_integration() {
		Sync_Storage_Store::append( 'widget/inbox:1', array( 'payload' => 'opaque' ) );

		$this->assertSame( 1, Sync_Storage_Store::count( 'widget/inbox:1' ) );
	}
}
