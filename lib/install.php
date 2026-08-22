<?php
/**
 * Activation: create the store's table, schedule cleanup, run migrations.
 *
 * Orchestration only -- it decides *when* each layer's setup runs. The table
 * definition itself lives in lib/store/schema.php.
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_activation_hook( WP_SYNC_STORAGE_PLUGIN_DIR . 'sync-storage.php', 'sync_storage_install' );

/**
 * Set up the plugin for the current site.
 */
function sync_storage_install() {
	Sync_Storage_Logger::event( 'Installation started' );

	sync_storage_create_table();

	// Schedule cleanup cron.
	if ( ! wp_next_scheduled( 'sync_storage_cleanup_stale_updates' ) ) {
		wp_schedule_event( time(), 'daily', 'sync_storage_cleanup_stale_updates' );
		Sync_Storage_Logger::event( 'Cleanup cron scheduled' );
	}

	// Migrate from post meta if needed.
	sync_storage_migrate_post_meta();

	Sync_Storage_Logger::event( 'Installation complete' );
}

/**
 * Multisite: Activate on newly created sites.
 */
if ( is_multisite() ) {
	add_action( 'wpmu_new_blog', 'sync_storage_install_new_site', 10, 1 );

	/**
	 * Install on newly created multisite site.
	 *
	 * @param int $blog_id Site ID.
	 */
	function sync_storage_install_new_site( $blog_id ) {
		switch_to_blog( $blog_id );
		sync_storage_install();
		restore_current_blog();
	}
}
