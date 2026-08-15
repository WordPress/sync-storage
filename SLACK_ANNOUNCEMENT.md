# RTC Storage Layer Plugin + Gutenberg PR

Built a storage layer plugin for RTC that eliminates the cache invalidation issue from #64696.

## What shipped

**Plugin:** https://github.com/josephfusco/realtime-collaboration

- `wp_collaboration` table for CRDT update storage
- Integrates Presence API for awareness (cursors, user metadata)
- Implements `Gutenberg_Sync_Storage` interface
- Server authority: RTC activates automatically when 2+ editors detected
- Zero cache side effects (dedicated table, not post meta)

**Architecture:**
```
Gutenberg → gutenberg_sync_storage filter → RTC_Presence_Storage
  ├── Awareness → wp_set_presence() → wp_presence table
  └── Updates → INSERT → wp_collaboration table
```

## Blocker

**Gutenberg PR #81697:** https://github.com/WordPress/gutenberg/pull/81697

One-line change adds `gutenberg_sync_storage` filter to make storage pluggable:

```php
$sync_storage = apply_filters( 'gutenberg_sync_storage', new WP_Sync_Post_Meta_Storage() );
```

Without this filter, the plugin can't hook in.

## Test it now

Blueprint installs Gutenberg from PR branch + presence-api + realtime-collaboration:

https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/josephfusco/realtime-collaboration/main/blueprint-test.json

Open a post in 2 tabs to verify RTC activates only when collaboration starts.

## Path forward

1. Get Gutenberg PR merged (needs `[Type]` label from maintainer)
2. Plugin works once filter is available
3. WordPress 8.0: wp_collaboration table merges to core, becomes default storage

This offloads the hardest RTC infrastructure problems to WordPress primitives (Presence API for awareness, dedicated table for CRDT updates).

cc: relevant folks for review
