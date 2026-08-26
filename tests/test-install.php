<?php
/**
 * Tests for multisite installation (lib/install.php).
 *
 * @package Sync_Storage
 *
 * @group rtc
 * @group multisite
 */
class WP_Test_Sync_Storage_Install extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires multisite.' );
		}
	}

	/**
	 * Asserts on registration, so it executes nothing in lib/.
	 *
	 * @coversNothing
	 */
	public function test_uses_wp_initialize_site_not_deprecated_hook() {
		$this->assertNotFalse( has_action( 'wp_initialize_site', 'sync_storage_install_new_site' ) );
		$this->assertFalse( has_action( 'wpmu_new_blog', 'sync_storage_install_new_site' ) );
	}

	/**
	 * @covers ::sync_storage_install_new_site
	 */
	public function test_new_site_gets_table() {
		$site_id = self::factory()->blog->create();

		$this->assertTrue( $this->site_has_collaboration_table( $site_id ) );
	}

	/**
	 * @covers ::sync_storage_install_network
	 */
	public function test_network_activation_creates_table_for_pre_existing_sites() {
		// Simulate sites that predate the plugin: created with no table.
		remove_action( 'wp_initialize_site', 'sync_storage_install_new_site' );
		$site_ids = array(
			self::factory()->blog->create(),
			self::factory()->blog->create(),
		);
		add_action( 'wp_initialize_site', 'sync_storage_install_new_site', 10, 1 );

		foreach ( $site_ids as $site_id ) {
			$this->assertFalse( $this->site_has_collaboration_table( $site_id ), 'Site should not have a table yet.' );
		}

		sync_storage_install( true );

		foreach ( $site_ids as $site_id ) {
			$this->assertTrue( $this->site_has_collaboration_table( $site_id ) );
		}
	}

	/**
	 * @param int $site_id Site ID.
	 * @return bool Whether the site's wp_collaboration table exists.
	 */
	private function site_has_collaboration_table( $site_id ) {
		global $wpdb;

		switch_to_blog( $site_id );
		$table_name = $wpdb->prefix . 'collaboration';
		$exists     = (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		restore_current_blog();

		return $exists;
	}
}
