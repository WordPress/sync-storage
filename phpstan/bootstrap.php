<?php
/**
 * PHPStan bootstrap: the constants sync-storage.php defines at runtime.
 *
 * PHPStan *executes* its bootstrap files, so the plugin entry point cannot
 * serve as its own. sync-storage.php calls exit when ABSPATH is undefined,
 * which ended the analysis before a single file was read and still reported
 * success (#57).
 *
 * The values here are placeholders; only each constant's existence and type
 * matters to static analysis.
 *
 * @package Sync_Storage
 */

define( 'WP_SYNC_STORAGE_VERSION', '0.0.0' );
define( 'WP_SYNC_STORAGE_DB_VERSION', 2 );
define( 'WP_SYNC_STORAGE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WP_SYNC_STORAGE_PLUGIN_URL', 'https://example.org/wp-content/plugins/sync-storage/' );
