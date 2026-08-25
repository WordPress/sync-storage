<?php
/**
 * Activation and deactivation: create the store's table, schedule cleanup,
 * run migrations, and unschedule cleanup again on the way out.
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

	sync_storage_migrate_post_meta();

	Sync_Storage_Logger::event( 'Installation complete' );
}

/**
 * Set up the plugin on every site of the network.
 */
function sync_storage_install_network() {
	sync_storage_for_each_site( 'sync_storage_install_site' );
}

register_deactivation_hook( WP_SYNC_STORAGE_PLUGIN_DIR . 'sync-storage.php', 'sync_storage_deactivate' );

/**
 * Undo what activation scheduled, for the current site or the whole network.
 *
 * The table and its rows stay put. Deactivating is not uninstalling, and a
 * site that reactivates should find its log where it left it; uninstall.php
 * is what drops the data.
 *
 * @param bool $network_wide Whether the plugin is being network-deactivated.
 */
function sync_storage_deactivate( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		sync_storage_for_each_site( 'sync_storage_deactivate_site' );
		return;
	}

	sync_storage_deactivate_site();
}

/**
 * Unschedule the cleanup sweep for the current site.
 *
 * The event is per-site, since wp_schedule_event() writes to the site's own
 * cron option, so a network deactivation has to clear each one. Left behind,
 * it stays in that option indefinitely and fires daily against a callback the
 * deactivated plugin no longer registers.
 */
function sync_storage_deactivate_site() {
	if ( ! wp_next_scheduled( 'sync_storage_cleanup_stale_updates' ) ) {
		return;
	}

	wp_clear_scheduled_hook( 'sync_storage_cleanup_stale_updates' );

	Sync_Storage_Logger::event( 'Cleanup cron cleared' );
}

/**
 * Run a callback once per site in the network, inside switch_to_blog().
 *
 * Paginates site IDs rather than loading every site at once, so a large
 * network doesn't run dbDelta -- or anything else a caller passes -- for
 * thousands of sites off one query.
 *
 * @param callable $callback Runs with each site as the current one.
 */
function sync_storage_for_each_site( callable $callback ) {
	$batch_size = 100;
	$offset     = 0;

	do {
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => $batch_size,
				'offset' => $offset,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			$callback();
			restore_current_blog();
		}

		$found_count = count( $site_ids );
		$offset     += $batch_size;
	} while ( $found_count === $batch_size );
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
