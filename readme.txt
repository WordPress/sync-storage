=== Sync Storage ===
Contributors: joefusco, iamchitti
Tags: collaboration, real-time, gutenberg, presence, yjs
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.7
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
* Gutenberg with sync storage filter support

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

= Does this work on multisite? =

Yes, with one caveat. Collaboration data is stored per site, so every site needs its own table. Activating Sync Storage on a site creates that site's table, and sites created later get one automatically.

Network activation is the exception: it does not backfill sites that already existed when you activated. On an established network, activate Sync Storage on each existing site individually so each one gets its table.

= How is old data cleaned up? =

Updates are cleaned up via compaction (coordinated by Gutenberg) and a daily cron job that removes updates older than 7 days.

== Changelog ==

Only the most recent releases are listed here. For the full history, see https://github.com/WordPress/sync-storage/blob/main/CHANGELOG.md

= 0.1.7 =
* Fix: bound Playwright browser install with a timeout and retry

= 0.1.6 =
* Fix: make playwright.yml workflow_call-only like its CI siblings

= 0.1.5 =
* Fix: activate Gutenberg's real-time-collaboration experiment on plugin load
* Fix: give CI a .env so REST discovery hits the right wp-env port
* Fix: give collaborative Playwright tests enough time on CI
* Fix: give release-please branches a real success, not skipped, on required checks
* Fix: point Playwright readiness check at /wp-json/ to warm REST routes
* Fix: provision e2e collaborator users via WP-CLI instead of REST
* Fix: reach the editor canvas iframe in the room-creation e2e test
* Fix: remove global-setup REST warm-up that hung CI for 30 minutes
* Fix: resolve zizmor code-scanning findings
* Fix: skip apt-get in Playwright browser install on cache hit
* Fix: warn when Gutenberg build silently skips sync-storage's filter

= 0.1.4 =
* Fix: stop Playwright from starting a second wp-env instance in CI
* Fix: stop plugin-check's isolated wp-env from crashing on Requires Plugins
* Fix: sync all three version copies, not just the plugin header
* Performance: cache Gutenberg trunk checkout and Playwright browsers in CI

= 0.1.3 =
* Fix: correct timestamp units, awareness access control, and uninstall mismatches
* Fix: name the local Gutenberg trunk checkout to match its dependency slug
* Fix: resolve Plugin Check findings for WordPress.org distribution
