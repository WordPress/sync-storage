# Realtime Collaboration

> **Status:** Experimental feature plugin

Storage layer for real-time collaborative editing in WordPress.

## Try it

[![Open in WordPress Playground](https://img.shields.io/badge/Open%20in-WordPress%20Playground-3858E9?logo=wordpress&logoColor=white)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/josephfusco/realtime-collaboration/main/blueprint.json)

## Problem

Gutenberg's RTC (Real-Time Collaboration) feature currently stores sync data in `post_meta`, causing site-wide cache invalidation on every edit. This plugin provides a dedicated `wp_collaboration` table and integrates with the Presence API for awareness, eliminating cache side effects.

## Requirements

- WordPress 7.0+
- PHP 8.0+
- [Presence API](https://github.com/WordPress/presence-api) plugin
- Gutenberg with `gutenberg_sync_storage` filter (coming in future release)

## What This Plugin Does

- Provides `wp_collaboration` table for CRDT update storage
- Integrates Presence API for awareness (cursors, user metadata)
- Implements `Gutenberg_Sync_Storage` interface
- Server authority: RTC activates automatically when 2+ editors detected
- Zero cache side effects (dedicated table, not post meta)

## What It Doesn't Do

- ❌ Replace Gutenberg's HTTP polling provider (stays in Gutenberg)
- ❌ Own REST endpoints (Gutenberg keeps `/wp/v2/sync/updates`)
- ❌ Change editor UI (cursors, avatars handled by Gutenberg)
- ❌ Modify Presence API (it's a primitive, plugin is a consumer)

## Architecture

```
Gutenberg Editor
    ↓
HTTP Polling Provider (Gutenberg)
    ↓
gutenberg_sync_storage filter
    ↓
RTC_Presence_Storage (this plugin)
    ├── Awareness → wp_set_presence() → wp_presence table
    └── Updates → INSERT → wp_collaboration table
```

## Installation

### From Source

```bash
git clone https://github.com/josephfusco/realtime-collaboration.git
cd realtime-collaboration
# Install to WordPress plugins directory
```

### Requirements Check

The plugin will display admin notices if:
- Presence API is not installed/active
- WordPress version < 7.0
- Gutenberg doesn't support `gutenberg_sync_storage` filter

## Database Schema

### `wp_collaboration` Table

```sql
CREATE TABLE wp_collaboration (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    room varchar(191) NOT NULL,
    client_id bigint(20) unsigned NOT NULL,
    type varchar(20) NOT NULL,
    data longtext NOT NULL,
    timestamp bigint(20) unsigned NOT NULL,
    PRIMARY KEY (id),
    KEY room_timestamp (room(50), timestamp)
);
```

- **room**: Entity identifier (e.g., `postType/post:42`)
- **client_id**: Yjs client ID
- **type**: Update type (`update`, `sync_step1`, `sync_step2`, `compaction`)
- **data**: Base64-encoded Yjs update (opaque to server)
- **timestamp**: Milliseconds since epoch (Yjs format)

## How It Works

### Single Editor
1. User opens post in Gutenberg
2. Presence API tracks: 1 editor active
3. RTC plugin returns empty awareness (no overhead)
4. Gutenberg runs in single-user mode

### Second Editor Joins
1. Second user opens same post
2. Presence API detects: 1→2 editors
3. Fires: `wp_presence_collaboration_started` action
4. RTC plugin flags post: `_rtc_collaboration_active = true`
5. Both editors' Heartbeat gets: `X-WP-Collaboration-Active: true`
6. Gutenberg initializes Yjs, cursors/selections sync

### Second Editor Leaves
1. Presence entry expires (60s TTL)
2. Presence API detects: 2→1 editors
3. Fires: `wp_presence_collaboration_ended`
4. RTC plugin clears flag
5. Remaining editor returns to single-user mode

## Cleanup

- **Compaction:** Gutenberg handles (client-nominated compaction)
- **Safety net:** Daily cron deletes updates >7 days old
- **Migration:** Automatic migration from `wp_sync_storage` post meta on activation

## Actions

### `rtc_collaboration_room_active`
Fires when collaboration starts (2+ editors).

```php
add_action( 'rtc_collaboration_room_active', function( $post_id, $entries ) {
    // Custom logic when RTC activates
}, 10, 2 );
```

### `rtc_collaboration_room_inactive`
Fires when collaboration ends (back to 1 editor).

```php
add_action( 'rtc_collaboration_room_inactive', function( $post_id ) {
    // Custom logic when RTC deactivates
}, 10, 1 );
```

## Multisite

Supported. Tables are created per-site, not globally.

On network activation, creates `wp_collaboration` table on all sites.

## Security

- Capability checks: `current_user_can( 'edit_post', $post_id )`
- Room format validation (SQL injection prevention)
- Prepared statements for all queries
- Defensive checks for Presence API availability

## Development

### Local Setup

```bash
npm install
npx wp-env start
```

### Testing

```bash
composer install
vendor/bin/phpunit
```

## Maintainers

- [@josephfusco](https://github.com/josephfusco)

Sponsored by the [Core team](https://make.wordpress.org/core/). Updates posted on [make.wordpress.org/core](https://make.wordpress.org/core/) with the tag `#realtime-collaboration`.

## Support

Questions and bug reports: [GitHub Issues](https://github.com/josephfusco/realtime-collaboration/issues).

Discussion: [#realtime-collaboration](https://wordpress.slack.com/archives/realtime-collaboration) on WordPress Slack

## Related

- [Presence API](https://github.com/WordPress/presence-api) - Awareness infrastructure
- [Gutenberg Issue #80387](https://github.com/WordPress/gutenberg/issues/80387) - RTC provider gating
- [WordPress Trac #64696](https://core.trac.wordpress.org/ticket/64696) - RTC cache invalidation
