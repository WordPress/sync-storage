<?php
/**
 * Uninstall script for Realtime Collaboration.
 *
 * @package Realtime_Collaboration
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove collaboration table.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}collaboration" );

// Remove options.
delete_option( 'rtc_collaboration_db_version' );
delete_option( 'rtc_migrated_from_post_meta' );

// Clear scheduled cron.
wp_clear_scheduled_hook( 'rtc_cleanup_stale_updates' );

// On multisite, clean up all sites.
if ( is_multisite() ) {
	$sites = get_sites( array( 'number' => 10000 ) );
	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}collaboration" );
		delete_option( 'rtc_collaboration_db_version' );
		delete_option( 'rtc_migrated_from_post_meta' );
		wp_clear_scheduled_hook( 'rtc_cleanup_stale_updates' );

		restore_current_blog();
	}
}
