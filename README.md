# Sync Storage

[![CI](https://github.com/WordPress/sync-storage/actions/workflows/ci.yml/badge.svg)](https://github.com/WordPress/sync-storage/actions/workflows/ci.yml)
[![Open in WordPress Playground](https://img.shields.io/badge/Open%20in-WordPress%20Playground-3858E9?logo=wordpress&logoColor=white)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/WordPress/sync-storage/main/blueprint.json)

> **Status:** Experimental feature plugin

Storage layer for Gutenberg's real-time collaborative editing.

## Problem

Gutenberg's real-time collaboration needs somewhere to keep two kinds of data: ephemeral awareness (who's in the room, cursor position) and a persistent log of CRDT updates for each document. Storing either as post meta means every write invalidates post caches site-wide ([#64696](https://core.trac.wordpress.org/ticket/64696)). This plugin implements Gutenberg's `WP_Sync_Storage` interface to keep both out of post meta entirely: awareness is delegated to [Presence API](https://wordpress.org/plugins/presence-api/)'s `wp_presence` table, and CRDT updates go into a dedicated `wp_collaboration` table.

## Run locally

```bash
git clone https://github.com/WordPress/sync-storage.git
cd sync-storage
npm install
npm run env:start
```

Then open [localhost:8888/wp-admin/](http://localhost:8888/wp-admin/) (admin / password).

The first run builds Gutenberg from trunk, since the `__unstable_wp_sync_storage` filter this plugin hooks hasn't shipped in a tagged Gutenberg release yet. That takes a few minutes; subsequent runs reuse the build.

## Data flow

**Awareness**
1. Gutenberg calls `set_awareness_state( $room, $awareness )`
2. Each entry is forwarded to Presence API's `wp_set_presence()`
3. Reads go through `get_awareness_state( $room )`, which calls `wp_get_presence()` and reshapes the result into Gutenberg's expected format

**CRDT updates**
1. Gutenberg calls `add_update( $room, $update )`
2. The update is inserted into `wp_collaboration` as an opaque, JSON-encoded row
3. Gutenberg polls `get_updates_after_cursor( $room, $cursor )` to fetch anything new
4. `remove_updates_before_cursor()` deletes compacted rows once Gutenberg confirms they're no longer needed

Both paths validate that the current user can `edit_post` the room's underlying post before touching storage.

## Rooms

| Pattern                | Example            |
| ----------------------- | ------------------ |
| `postType/{type}:{id}` | `postType/post:42` |

## PHP API

This plugin implements Gutenberg's `WP_Sync_Storage` interface. These are the methods `Sync_Storage_Provider` provides; there's no separate global-function API like Presence API's.

```php
// Read awareness state for a room, reshaped from Presence API's format.
$entries = $storage->get_awareness_state( $room );

// Write each client's awareness state, delegated to wp_set_presence().
$storage->set_awareness_state( $room, $awareness );

// Append an opaque CRDT update to wp_collaboration.
$storage->add_update( $room, $update );

// Last update id returned to this request for a room (0 if none yet).
$cursor = $storage->get_cursor( $room );

// Number of stored updates for a room.
$count = $storage->get_update_count( $room );

// Updates with id > $cursor, ordered by id.
$updates = $storage->get_updates_after_cursor( $room, $cursor );

// Delete compacted updates with id < $cursor.
$storage->remove_updates_before_cursor( $room, $cursor );
```

## Database schema

### `wp_collaboration`

| Column    | Type             | Purpose                                                    |
| --------- | ---------------- | ----------------------------------------------------------- |
| id        | BIGINT UNSIGNED  | Auto-increment cursor for polling                            |
| room      | VARCHAR(191)     | Room identifier, e.g. `postType/post:42`                    |
| type      | VARCHAR(20)      | Reserved for future update classification; always NULL today |
| data      | LONGTEXT         | JSON-encoded opaque payload                                  |
| timestamp | BIGINT UNSIGNED  | Milliseconds since epoch, matching Yjs. Used by cleanup      |

**Indexes:** `PRIMARY KEY (id)`, `KEY room_id (room, id)` for polling, `KEY room_timestamp (room, timestamp)` for cleanup.

A daily cron removes rows older than 7 days.

## Hooks

### `sync_storage_room_active` / `sync_storage_room_inactive`
Fired when a room's collaborator count crosses the 1-to-2 threshold, as reported by Presence API. Used internally to flag `_sync_storage_active` post meta; also available for third-party integrations.
```php
add_action( 'sync_storage_room_active', function ( $post_id, $entries ) {
    // A second collaborator just joined $post_id.
}, 10, 2 );

add_action( 'sync_storage_room_inactive', function ( $post_id, $entries ) {
    // Back down to a single editor (or none).
}, 10, 2 );
```

### `__unstable_wp_sync_storage`
Gutenberg's own filter, hooked by this plugin to replace its default post-meta-backed storage with `Sync_Storage_Provider`. Any plugin can hook this filter to supply a different `WP_Sync_Storage` implementation (Redis, WebSocket-backed, etc.) without patching Gutenberg.

## Requirements

- WordPress 7.0+
- PHP 7.4+
- [Presence API](https://wordpress.org/plugins/presence-api/)
- [Gutenberg](https://github.com/WordPress/gutenberg) trunk (or a future release once `__unstable_wp_sync_storage` ships stable)

## Maintainers

- [@josephfusco](https://github.com/josephfusco)

Sponsored by the [Core team](https://make.wordpress.org/core/). Discussion happens in [#feature-realtime-collaboration](https://wordpress.slack.com/archives/C07NVJ51X6K) on WordPress Slack and on [Trac #64696](https://core.trac.wordpress.org/ticket/64696).

## Support

Questions and bug reports: [GitHub Issues](https://github.com/WordPress/sync-storage/issues).

## License

GPL-2.0-or-later
