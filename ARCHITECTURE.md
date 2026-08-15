# Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      Gutenberg Editor                        │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ • Yjs CRDT library                                     │ │
│  │ • HTTP polling provider                                │ │
│  │ • REST endpoints (/wp/v2/sync/updates)                 │ │
│  │ • Editor UI (cursors, avatars, selection indicators)   │ │
│  └────────────────────────────────────────────────────────┘ │
└────────────────────────────┬────────────────────────────────┘
                             │
                             │ gutenberg_sync_storage filter
                             │
                             ▼
┌─────────────────────────────────────────────────────────────┐
│              Realtime Collaboration Plugin                   │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ Sync Layer (RTC_Presence_Storage)                      │ │
│  │ • Implements Gutenberg_Sync_Storage interface          │ │
│  │ • Delegates to wp_collaboration table and Presence API │ │
│  │ • Server authority (activates when 2+ editors)         │ │
│  └────────┬─────────────────────────────────┬─────────────┘ │
│           │                                 │                │
│           │ CRDT Updates                    │ Awareness      │
│           ▼                                 ▼                │
│  ┌─────────────────┐              ┌──────────────────────┐  │
│  │wp_collaboration │              │   Presence API       │  │
│  │     table       │              │                      │  │
│  │ • Append-only   │              │ • wp_presence table  │  │
│  │ • Auto-increment│              │ • 60s TTL            │  │
│  │ • 7-day cleanup │              │ • User metadata      │  │
│  └─────────────────┘              │ • Cursor positions   │  │
│                                   └──────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

## Dependencies

```
realtime-collaboration
├── presence-api (required)
│   └── Provides: wp_presence table, awareness storage
└── gutenberg (required)
    └── Provides: Yjs, HTTP polling, REST endpoints, UI
```

## Data Flow

### Awareness (Ephemeral)
```
Gutenberg → HTTP Polling Provider → gutenberg_sync_storage filter
    → RTC_Presence_Storage::set_awareness_state()
    → wp_set_presence()
    → wp_presence table
```

### CRDT Updates (Persistent)
```
Gutenberg → HTTP Polling Provider → gutenberg_sync_storage filter
    → RTC_Presence_Storage::add_update()
    → INSERT INTO wp_collaboration
```

### Server Authority
```
User opens post → Heartbeat → wp_set_presence()
    → wp_presence_check_collaboration_threshold()
    → Detects 2+ editors
    → Fires wp_presence_collaboration_started
    → Plugin sets _rtc_collaboration_active meta
    → Heartbeat response: X-WP-Collaboration-Active: true
    → Gutenberg initializes Yjs
```

## What Lives Where

| Component | Location | Purpose |
|-----------|----------|---------|
| Yjs CRDT | Gutenberg | CRDT algorithm and merging |
| HTTP Polling | Gutenberg | Transport layer |
| REST Endpoints | Gutenberg | `/wp/v2/sync/updates` |
| Editor UI | Gutenberg | Cursors, avatars, selections |
| `wp_collaboration` table | realtime-collaboration | CRDT update storage |
| `wp_presence` table | presence-api | Awareness storage |
| Storage interface | realtime-collaboration | `Gutenberg_Sync_Storage` implementation |
| Server authority | realtime-collaboration | Hooks Presence API lifecycle |

## Evolution Path

### WordPress 7.2
- Presence API: Featured plugin or core
- realtime-collaboration: Featured plugin
- Gutenberg: Ships with `gutenberg_sync_storage` filter

### WordPress 8.0
- `wp_collaboration` table merges to core
- `wp_presence` table already in core (from Presence API)
- Gutenberg defaults to core storage instead of post meta
- realtime-collaboration plugin deprecated
