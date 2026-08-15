# Realtime Collaboration

Storage layer for real-time collaborative editing in WordPress.

[![WordPress Plugin Required Version](https://img.shields.io/badge/WordPress-7.0%2B-blue.svg)](https://wordpress.org/)
[![PHP Required Version](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](LICENSE)

[![Open in WordPress Playground](https://img.shields.io/badge/Open%20in-WordPress%20Playground-3858E9?logo=wordpress&logoColor=white)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/josephfusco/realtime-collaboration/main/blueprint.json)

## Problem

Gutenberg's RTC feature stores sync data in `post_meta`, causing site-wide cache invalidation on every edit. This plugin provides a dedicated `wp_collaboration` table and integrates with Presence API for awareness, eliminating cache side effects.

## Requirements

| Requirement | Version | Status |
|------------|---------|--------|
| WordPress | 7.0+ | ✅ |
| PHP | 8.0+ | ✅ |
| [Presence API](https://github.com/WordPress/presence-api) | Latest | ✅ |
| [Gutenberg](https://wordpress.org/plugins/gutenberg/) | with `gutenberg_sync_storage` filter | ⚠️ Pending |

> [!WARNING]
> **Blocker:** The `gutenberg_sync_storage` filter doesn't exist yet in Gutenberg. A PR is needed to add `apply_filters( 'gutenberg_sync_storage', ... )`.

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

**Provides:**
- `wp_collaboration` table for CRDT update storage
- `Gutenberg_Sync_Storage` interface implementation
- Server authority (RTC activates when 2+ editors detected)
- Zero cache side effects

**Does not provide:**
- HTTP polling provider (stays in Gutenberg)
- REST endpoints (stays in Gutenberg)
- Editor UI (cursors, avatars - stays in Gutenberg)

## Development

```bash
npm install && composer install
npx wp-env start
composer test
```

## Related

- [Presence API](https://github.com/WordPress/presence-api) - Required dependency
- [Gutenberg #80387](https://github.com/WordPress/gutenberg/issues/80387) - RTC provider gating
- [Trac #64696](https://core.trac.wordpress.org/ticket/64696) - Cache invalidation issue
- [wordpress-develop #11609](https://github.com/WordPress/wordpress-develop/pull/11609) - Core integration exploration
