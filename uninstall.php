<?php
/**
 * Uninstall script for Sync Storage.
 *
 * @package Sync_Storage
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove collaboration table.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}collaboration" );

// Remove options.
delete_option( 'sync_storage_db_version' );
delete_option( 'sync_storage_migrated_from_post_meta' );

// Clear scheduled cron.
wp_clear_scheduled_hook( 'sync_storage_cleanup_old_updates' );

// On multisite, clean up all sites.
// Note: Limited to 10,000 sites. For larger networks, manually clean via WP-CLI:
// wp site list --field=url | xargs -I {} wp --url={} plugin uninstall sync-storage
if ( is_multisite() ) {
	$sites = get_sites( array( 'number' => 10000 ) );
	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}collaboration" );
		delete_option( 'sync_storage_db_version' );
		delete_option( 'sync_storage_migrated_from_post_meta' );
		wp_clear_scheduled_hook( 'sync_storage_cleanup_old_updates' );

		restore_current_blog();
	}
}
