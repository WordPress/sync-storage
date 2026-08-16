# Architecture

```
┌────────────────────────────┐
│    Gutenberg Editor        │
│  • Yjs CRDT                │
│  • HTTP polling            │
│  • Editor UI               │
└────────┬───────────────────┘
         │ gutenberg_sync_storage filter
         ▼
┌────────────────────────────┐
│ Realtime Collaboration     │
│  Sync Layer                │
│    ├─ CRDT Updates         │
│    │   └─ wp_collaboration │
│    └─ Awareness            │
│        └─ Presence API     │
└────────────────────────────┘
```

## Dependencies

```
realtime-collaboration
├── presence-api (required) - awareness storage
└── gutenberg (required) - Yjs, transport, UI
```
