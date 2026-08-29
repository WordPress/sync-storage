<?php
/**
 * Activation: create the store's table and schedule its cleanup.
 *
 * Orchestration only -- it decides *when* each layer's setup runs. The table
 * definition itself lives in lib/store/schema.php, and the teardown in
 * lib/deactivate.php.
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_activation_hook( WP_SYNC_STORAGE_PLUGIN_DIR . 'sync-storage.php', 'sync_storage_install' );

/**
 * Set up the plugin for the current site, or the whole network.
 *
 * WordPress passes $network_wide on the activation hook during a network
 * activation, so pre-existing sites get a table too, not just the one the
 * request happens to run in.
 *
 * @param bool $network_wide Whether the plugin is being network-activated.
 */
function sync_storage_install( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		sync_storage_install_network();
		return;
	}

	sync_storage_install_site();
}

/**
 * Set up the plugin for the current site.
 */
function sync_storage_install_site() {
	Sync_Storage_Logger::event( 'Installation started' );

	sync_storage_create_table();

	if ( ! wp_next_scheduled( 'sync_storage_cleanup_stale_updates' ) ) {
		wp_schedule_event( time(), 'daily', 'sync_storage_cleanup_stale_updates' );
		Sync_Storage_Logger::event( 'Cleanup cron scheduled' );
	}

	Sync_Storage_Logger::event( 'Installation complete' );
}

/**
 * Set up the plugin on every site of the network.
 */
function sync_storage_install_network() {
	sync_storage_for_each_site( 'sync_storage_install_site' );
}

/**
 * Multisite: Activate on newly created sites.
 */
if ( is_multisite() ) {
	add_action( 'wp_initialize_site', 'sync_storage_install_new_site', 10, 1 );

	/**
	 * Install on a newly created multisite site.
	 *
	 * @param WP_Site $new_site Newly created site.
	 */
	function sync_storage_install_new_site( WP_Site $new_site ) {
		switch_to_blog( $new_site->id );
		sync_storage_install();
		restore_current_blog();
	}
}
