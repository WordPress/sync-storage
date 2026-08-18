# Sync Storage Storage Plugin

> **Composite storage layer for WordPress real-time collaborative editing**  
> Eliminates post meta cache invalidation by delegating awareness to [Presence API](https://wordpress.org/plugins/presence-api/) and CRDT updates to a dedicated `wp_collaboration` table.

## Architecture

```
Gutenberg (trunk with __unstable_wp_sync_storage filter)
    ↓
sync-storage plugin (WP_Sync_Storage implementation)
    ├─ Awareness → Presence API (wp_presence table)
    └─ CRDT updates → wp_collaboration table
```

**Zero cache side effects**: No `wp_cache_set_posts_last_changed()` calls, no site-wide WP_Query invalidation.

> ⚠️ **Temporary**: Requires Gutenberg trunk build until `__unstable_wp_sync_storage` filter ships in a release (merged [PR #81697](https://github.com/WordPress/gutenberg/pull/81697)). First run builds trunk (~3 min), cached after.

## Quick Start

### One-Command Setup

```bash
git clone https://github.com/WordPress/sync-storage.git
cd sync-storage
npm install
npm run env:start  # Builds Gutenberg trunk (~3 min first run, <10s after)
```

**Access**: http://localhost:8888/wp-admin  
**Credentials**: admin / password

**Watch integration logs**:
```bash
docker exec $(docker ps -q --filter "name=sync-storage.*wordpress-1") tail -f /var/www/html/wp-content/debug.log
```

Look for:
- `[Sync] Filter hooked: __unstable_wp_sync_storage` (storage replacement working)
- `[Sync] Storage initialized` (Sync_Storage_Provider active)

**Stop**: `npm run env:stop`

### WordPress Playground (Zero Install)

[![Try in WordPress Playground](https://img.shields.io/badge/Try-Playground-blue)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/WordPress/sync-storage/main/blueprint.json)

Click the badge above for a zero-install browser demo.

## What It Does

### ✅ Solves

- **Cache thrashing**: Post meta updates no longer invalidate site-wide caches ([Trac #64696](https://core.trac.wordpress.org/ticket/64696))
- **Race conditions**: Auto-increment cursor prevents timestamp collision bugs
- **Compaction safety**: Atomic DELETE prevents message loss during cleanup

### ❌ Does NOT Do

- Replace Gutenberg's HTTP polling provider (stays in Gutenberg)
- Own REST endpoints (Gutenberg keeps `/wp/v2/sync/updates`)
- Change editor UI (cursors, avatars, etc. - Gutenberg)
- Handle WebSocket transport (future separate plugin)

## How It Works

### Awareness (Ephemeral Presence)

```php
// Gutenberg calls:
$sync_storage->set_awareness_state('postType/post:42', $awareness);

// We delegate to Presence API:
wp_set_presence('postType/post:42', $client_id, $state, $user_id);
// → wp_presence table (60s TTL, zero cache impact)
```

### CRDT Updates (Persistent Sync Log)

```php
// Gutenberg calls:
$sync_storage->add_update('postType/post:42', $update);

// We store in dedicated table:
INSERT INTO wp_collaboration (room, data, timestamp) VALUES (...);
// → Zero cache invalidation
```

## Database Schema

### `wp_collaboration`

| Column      | Type           | Purpose                          |
|-------------|----------------|----------------------------------|
| id          | BIGINT UNSIGNED| Auto-increment cursor for polling|
| room        | VARCHAR(191)   | Room identifier (e.g., postType/post:42)|
| type        | VARCHAR(20)    | Reserved for future update classification; unused today (always NULL) |
| data        | LONGTEXT       | JSON-encoded opaque payload      |
| timestamp   | BIGINT UNSIGNED| Milliseconds since epoch, matches Yjs. Used by the 7-day cleanup cron |

**Indexes**:
- `PRIMARY KEY (id)` - cursor lookups
- `KEY room_id (room, id)` - polling queries
- `KEY room_timestamp (room, timestamp)` - cleanup queries

**Cleanup**: Daily cron removes rows older than 7 days.

## Requirements

- **WordPress**: 7.0+ (or 6.7+ with Presence API)
- **PHP**: 7.4+
- **Plugins**:
  - [Presence API](https://wordpress.org/plugins/presence-api/) (from wordpress.org)
  - [Gutenberg](https://github.com/WordPress/gutenberg) (trunk or 21.x+ with `__unstable_wp_sync_storage` filter)

## Development

### Run Tests

```bash
npm run test
```

### Debug Logging

All storage operations log to `wp-content/debug.log` when `WP_DEBUG_LOG` is enabled.

**Example log output**:
```
[Sync] Plugin loaded {"presence":true,"gutenberg":"21.x"}
[Sync] Filter hooked: __unstable_wp_sync_storage
[Sync] Storage initialized
[Sync] Storage::set_awareness_state() {"room":"postType/post:1","count":1}
[Sync] Presence::wp_set_presence() {"room":"postType/post:1"}
[Sync] Storage::add_update() {"room":"postType/post:1"}
```

### Manual Database Check

```bash
npm run env:cli -- wp db query "SELECT * FROM wp_collaboration LIMIT 10"
npm run env:cli -- wp db query "SELECT * FROM wp_presence WHERE room LIKE 'postType/%'"
```

## Project Status

**Current**: Proof of concept / featured plugin for WordPress 7.2  
**Target**: Core inclusion in WordPress 8.0

Part of the broader effort to make RTC production-ready:
- [Gutenberg PR #81697](https://github.com/WordPress/gutenberg/pull/81697) - Added storage filter ✅ Merged
- [Trac #64696](https://core.trac.wordpress.org/ticket/64696) - Cache invalidation fix
- This plugin - Storage implementation

## Contributing

This plugin demonstrates the architecture proposed for WordPress 7.2. Feedback welcome via issues or PRs.

### Key Files

- `lib/class-sync-storage-provider.php` - WP_Sync_Storage implementation
- `lib/gutenberg-integration.php` - Filter hook
- `lib/install.php` - Table creation
- `lib/class-sync-storage-logger.php` - Debug logging

## License

GPL-2.0-or-later

## Credits

Built to validate the architecture discussed in [Trac #64696](https://core.trac.wordpress.org/ticket/64696).

Integrates with:
- [Presence API](https://wordpress.org/plugins/presence-api/) by Joe Fusco
- [Gutenberg](https://github.com/WordPress/gutenberg) RTC experiment
