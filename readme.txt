=== Realtime Collaboration ===
Contributors: josephfusco
Tags: collaboration, real-time, gutenberg, presence, yjs
Requires at least: 7.0
Tested up to: 7.2
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Storage layer for real-time collaborative editing in WordPress.

== Description ==

Realtime Collaboration provides storage infrastructure for Gutenberg's real-time collaborative editing feature, eliminating cache invalidation side effects caused by storing sync data in post meta.

**Features:**

* Dedicated `wp_collaboration` table for CRDT update storage
* Integration with Presence API for awareness (cursors, user metadata)
* Server authority: RTC activates automatically when 2+ editors detected
* Zero cache side effects (dedicated table, not post meta)
* Automatic cleanup of stale updates
* Multisite support

**Requirements:**

* Presence API plugin
* Gutenberg with sync storage filter support

**How It Works:**

When a second editor opens the same post, the server automatically enables real-time collaboration. Both editors see each other's cursors and changes sync in real-time. When the second editor leaves, RTC automatically deactivates to eliminate overhead.

== Installation ==

1. Install and activate the Presence API plugin
2. Upload `realtime-collaboration` to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Enable the "Real-Time Collaboration" experiment in Gutenberg

== Frequently Asked Questions ==

= Does this work with the block editor? =

Yes, this plugin provides the storage layer for Gutenberg's real-time collaboration feature.

= Do I need the Presence API plugin? =

Yes, Realtime Collaboration requires the Presence API plugin for awareness infrastructure.

= Does this work on multisite? =

Yes, the plugin creates per-site collaboration tables on multisite networks.

= How is old data cleaned up? =

Updates are cleaned up via compaction (coordinated by Gutenberg) and a daily cron job that removes updates older than 7 days.

== Changelog ==

= 0.1.0 =
* Initial release
* Composite storage (Presence API + wp_collaboration table)
* Server authority integration
* Automatic cleanup
* Multisite support
* Migration from post meta storage

== Upgrade Notice ==

= 0.1.0 =
Initial release.
