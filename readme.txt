=== Sync Storage ===
Contributors: joefusco, iamchitti, obenland
Tags: collaboration, real-time, gutenberg, presence, yjs
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.16
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Storage layer for real-time collaborative editing in WordPress.

== Description ==

Sync Storage provides storage infrastructure for Gutenberg's real-time collaborative editing feature, eliminating cache invalidation side effects caused by storing sync data in post meta.

**Features:**

* Dedicated `wp_collaboration` table for CRDT update storage
* Integration with Presence API for awareness (cursors, user metadata)
* Server authority: RTC activates automatically when 2+ editors detected
* Zero cache side effects (dedicated table, not post meta)
* Automatic cleanup of stale updates
* Multisite support

**Requirements:**

* Presence API plugin

The storage itself has no editor requirement. Gutenberg, with sync storage filter support, is what currently consumes it: install it and Sync Storage becomes its real-time collaboration backend, otherwise the table is installed and idle.

**How It Works:**

When a second editor opens the same post, the server automatically enables real-time collaboration. Both editors see each other's cursors and changes sync in real-time. When the second editor leaves, RTC automatically deactivates to eliminate overhead.

== Installation ==

1. Install and activate the Presence API plugin
2. Upload `sync-storage` to the `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

Activating Sync Storage turns on Gutenberg's "Real-Time Collaboration" experiment for you, so there is no second toggle to find on the Experiments screen.

== Frequently Asked Questions ==

= Does this work with the block editor? =

Yes, this plugin provides the storage layer for Gutenberg's real-time collaboration feature.

= Do I need the Presence API plugin? =

Yes, Sync Storage requires the Presence API plugin for awareness infrastructure.

= Do I need the Gutenberg plugin? =

Only to use the collaboration feature. Sync Storage installs and runs without it, and picks Gutenberg up whenever it is activated. It is the consumer of this storage, not a dependency of it.

= Collaborators stopped showing up in the editor. Why? =

Check Settings > General. Presence API 0.3.0 added a switch for whether presence is recorded at all, and Sync Storage stores awareness through Presence API. With recording switched off, edits still sync but nobody sees anyone else. Sync Storage says so in a notice in wp-admin.

= Does this work on multisite? =

Yes, with one caveat. Collaboration data is stored per site, so every site needs its own table. Activating Sync Storage on a site creates that site's table, and sites created later get one automatically.

Network activation is the exception: it does not backfill sites that already existed when you activated. On an established network, activate Sync Storage on each existing site individually so each one gets its table.

= How is old data cleaned up? =

Updates are cleaned up via compaction (coordinated by Gutenberg) and a daily cron job that removes updates older than 7 days.

== Changelog ==

Only the most recent releases are listed here. For the full history, see https://github.com/WordPress/sync-storage/blob/main/CHANGELOG.md

= 0.1.16 =
* Feature: rename the collaboration primary key to collaboration_id
* Dependencies: patch wp-env's PHP 7.4 image for Debian bullseye's EOL archive

= 0.1.15 =
* Fix: say when presence recording is switched off

= 0.1.14 =
* Feature: install the Gutenberg 23.9.0 release instead of building trunk

= 0.1.13 =
* Feature: make the Playground grid three collaborator counts
* Fix: land the Playground demo in the room the peers were seeded into
* Fix: make the Playground preview trigger match what it previews

= 0.1.12 =
* Feature: add Playground preview grid for pull requests
* Fix: cast wp_user_id to int in awareness state
* Fix: defer room access to the sync server's own grammar
* Fix: stamp awareness with a real timestamp so the editor keeps it
* Fix: write only the polling client's awareness entry
