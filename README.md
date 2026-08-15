# Realtime Collaboration

[![CI](https://github.com/josephfusco/realtime-collaboration/actions/workflows/ci.yml/badge.svg)](https://github.com/josephfusco/realtime-collaboration/actions/workflows/ci.yml)
[![Open in WordPress Playground](https://img.shields.io/badge/Open%20in-WordPress%20Playground-3858E9?logo=wordpress&logoColor=white)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/josephfusco/realtime-collaboration/main/blueprint.json)

> **Status:** Experimental feature plugin

Storage layer for real-time collaborative editing in WordPress.

## What this provides

Dedicated storage backend for Gutenberg's RTC feature using:
- `wp_collaboration` table for CRDT updates
- Presence API integration for awareness (cursors, user metadata)
- Server authority model (RTC activates when 2+ editors detected)
- Zero cache side effects

> [!WARNING]
> **Blocker:** The `gutenberg_sync_storage` filter doesn't exist yet in Gutenberg. Draft PR [#81697](https://github.com/WordPress/gutenberg/pull/81697) adds this filter.

## Test it now

[![Open Test Blueprint](https://img.shields.io/badge/Test%20Demo-WordPress%20Playground-3858E9?logo=wordpress&logoColor=white)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/josephfusco/realtime-collaboration/main/blueprint-test.json)

Loads with Gutenberg (from draft PR) + presence-api + realtime-collaboration. Opens Hello World post with seeded collaborators showing what the collaboration toolbar looks like. To test live editing, duplicate the Playground URL in multiple browser tabs.

## Run locally

```bash
npm install
npx wp-env start
```

Then open [localhost:8888/wp-admin/](http://localhost:8888/wp-admin/) (admin / password).

## Data flow

1. Gutenberg editor sends awareness state via `Gutenberg_Sync_Storage::set_awareness_state()`
2. Plugin delegates to `wp_set_presence()` (zero cache impact)
3. Gutenberg sends CRDT updates via `Gutenberg_Sync_Storage::add_update()`
4. Plugin writes to `wp_collaboration` table (zero cache impact)
5. Server detects 2+ editors via Presence API lifecycle hooks
6. Fires `wp_presence_collaboration_started` action
7. Plugin flags post as `_rtc_collaboration_active`
8. Heartbeat response includes collaboration signal
9. Gutenberg initializes Yjs sync

## Architecture

See [ARCHITECTURE.md](ARCHITECTURE.md) for detailed diagrams and data flow.

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

## What this plugin provides

- `wp_collaboration` table for CRDT update storage
- `Gutenberg_Sync_Storage` interface implementation
- Server authority model (RTC activates when 2+ editors detected)
- Zero cache side effects (dedicated table, not post meta)

## What stays in Gutenberg

- HTTP polling provider
- REST endpoints (`/wp/v2/sync/updates`)
- Editor UI (cursors, avatars, selection indicators)
- Yjs CRDT library

## Actions

### `rtc_room_active`

Fires when collaboration starts (2+ editors detected).

```php
add_action( 'rtc_room_active', function( $post_id, $entries ) {
    // Custom logic when RTC activates
}, 10, 2 );
```

### `rtc_room_inactive`

Fires when collaboration ends (back to single editor).

```php
add_action( 'rtc_room_inactive', function( $post_id, $entries ) {
    // Custom logic when RTC deactivates
}, 10, 2 );
```

## Maintainers

Maintained by [@josephfusco](https://github.com/josephfusco). Discussion: [#feat-realtime-collaboration](https://wordpress.slack.com/archives/C07NVJ51X6K)

## Related

- [Presence API](https://github.com/WordPress/presence-api) - Required dependency
- [Gutenberg #81697](https://github.com/WordPress/gutenberg/pull/81697) - Storage filter PR (blocker)
- [Gutenberg #80387](https://github.com/WordPress/gutenberg/issues/80387) - RTC provider gating
- [Trac #64696](https://core.trac.wordpress.org/ticket/64696) - Cache invalidation issue
- [wordpress-develop #11609](https://github.com/WordPress/wordpress-develop/pull/11609) - Core integration exploration
