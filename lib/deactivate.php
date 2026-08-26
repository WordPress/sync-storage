<?php
/**
 * Deactivation teardown, and the network iterator activation shares with it.
 *
 * Separate from lib/install.php because sync-storage.php loads this one above
 * its version and dependency guards. Deactivating is how a site recovers from
 * a broken environment, so the teardown cannot sit behind a check that the
 * environment is intact. Nothing here touches the table or any layer's setup.
 *
 * @package Sync_Storage
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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
 * it fires daily against a callback the deactivated plugin no longer registers.
 *
 * wp_unschedule_hook() rather than wp_clear_scheduled_hook(): the latter keys
 * on md5( serialize( $args ) ), so it walks past entries scheduled with
 * arguments.
 */
function sync_storage_deactivate_site() {
	$cleared = wp_unschedule_hook( 'sync_storage_cleanup_stale_updates' );

	if ( $cleared ) {
		Sync_Storage_Logger::event( 'Cleanup cron cleared', array( 'events' => $cleared ) );
	}
}

/**
 * Run a callback once per site in the network, inside switch_to_blog().
 *
 * Lives here rather than in lib/install.php, the other caller, because this
 * file is the one guaranteed to be loaded.
 *
 * Paginates site IDs rather than loading every site at once, so a large
 * network doesn't run dbDelta -- or anything else a caller passes -- for
 * thousands of sites off one query.
 *
 * The sweep runs inline, so a timeout part way through leaves the remaining
 * sites untouched. Activation retries; deactivation does not.
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
