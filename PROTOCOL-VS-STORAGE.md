# What Gutenberg Can Remove

## Division of Labor

**With composite storage, Gutenberg offloads storage concerns to feature plugins.**

### ❌ Gutenberg Can Delete (Eventually)

Once `__unstable_wp_sync_storage` filter is stable and composite storage is proven:

#### 1. **Default Storage Implementation**
```php
// DELETE: lib/experimental/collaboration/class-wp-sync-post-meta-storage.php
```

**Why:** This entire file becomes reference implementation only. Production sites use feature plugins.

**Lines saved:** ~500 lines of storage logic, race handling, cache workarounds.

#### 2. **Storage Post Type**
```php
// DELETE from collaboration.php
function gutenberg_register_sync_storage_post_type() {
    register_post_type( 'wp_sync_storage', ... );
}
```

**Why:** Only needed for post meta storage. Composite storage uses:
- Presence API's `wp_presence` table
- Our `wp_collaboration` table

**No intermediate storage posts needed.**

#### 3. **Cache Invalidation Workarounds**
```php
// DELETE from class-wp-sync-post-meta-storage.php
// Use direct database operation to avoid cache invalidation performed by
// post meta functions (`wp_cache_set_posts_last_changed()` and direct
// `wp_cache_delete()` calls).
return (bool) $wpdb->insert( $wpdb->postmeta, ... );
```

**Why:** We don't touch post meta, so zero cache side effects. No workarounds needed.

#### 4. **Duplicate Storage Post Resolution**
```php
// DELETE from class-wp-sync-post-meta-storage.php
private function resolve_canonical_storage_post_id_after_insert( ... ): ?int {
    // Complex logic to handle concurrent first writers creating duplicate posts
    // with slug suffixes, race conditions, canonical vs non-canonical posts...
}
private function find_canonical_storage_post_id( ... ): ?int { ... }
private function promote_storage_post_to_canonical_slug( ... ): ?int { ... }
```

**Why:** Dedicated table with proper constraints (UNIQUE KEY on room). MySQL handles races.

**Lines saved:** ~150 lines of race condition handling.

#### 5. **Post Meta Cursor Logic**
```php
// DELETE from class-wp-sync-post-meta-storage.php
// Uses meta_id as cursor, requires ORDER BY meta_id DESC for latest,
// duplicate awareness row handling...
```

**Why:** Auto-increment PRIMARY KEY is the cursor. Monotonic, no duplicates possible.

### ✅ Gutenberg Keeps

#### 1. **Storage Interface**
```php
// KEEP: interface-wp-sync-storage.php
interface WP_Sync_Storage {
    public function get_awareness_state( string $room ): array;
    public function set_awareness_state( string $room, array $awareness ): bool;
    // ... 5 more methods
}
```

**Why:** This is the contract. Storage implementations are swappable.

#### 2. **REST Server Layer**
```php
// KEEP: class-wp-http-polling-sync-server.php
// KEEP: class-wp-sync-save-server.php
```

**Why:** 
- Protocol logic (compaction, sync steps, client filtering)
- Permissions
- Request validation
- REST endpoints

**Storage is injected via interface.**

#### 3. **Filter Hook**
```php
// KEEP: collaboration.php
$sync_storage = apply_filters( '__unstable_wp_sync_storage', new WP_Sync_Post_Meta_Storage() );
```

**Why:** This is the integration point. Default can become a stub or no-op once feature plugins are standard.

#### 4. **Client-Side CRDT Logic**
**Keep all JavaScript** - Yjs integration, operational transform, UI, polling provider.

**Why:** Storage is server-only. Client logic unchanged.

#### 5. **`_crdt_document` Post Meta**
```php
// KEEP: Persisting final snapshot to actual post
update_post_meta( $post_id, '_crdt_document', $doc );
```

**Why:** This is document persistence (separate from ephemeral sync storage).

## Impact Summary

| Component | Before | After |
|-----------|--------|-------|
| **Storage implementation** | 500+ lines in Gutenberg | Feature plugin |
| **Post type** | `wp_sync_storage` registered | Not needed |
| **Cache workarounds** | Direct `$wpdb` to avoid invalidation | Not needed (no post meta) |
| **Race handling** | Complex canonical post resolution | MySQL UNIQUE constraint |
| **Cursor logic** | meta_id + ORDER BY DESC + dupes | Auto-increment PRIMARY KEY |
| **Awareness** | Stored in post meta | Delegated to Presence API |
| **Interface** | Defined in Gutenberg | ✅ Stays |
| **REST layer** | HTTP polling server | ✅ Stays |
| **CRDT logic** | Client-side Yjs | ✅ Stays |

## Transition Path

### Phase 1 (Now)
- Gutenberg ships interface + default storage
- Feature plugins replace via filter
- Both coexist

### Phase 2 (Future)
- Composite storage proven in production
- Default storage becomes reference/fallback only
- Gutenberg simplifies to just the interface

### Phase 3 (Core Merge)
- Interface moves to WordPress core
- Default storage deleted
- Feature plugin becomes recommended (or core)

## What This Means

**Gutenberg can focus on:**
- ✅ Protocol design
- ✅ Client CRDT logic
- ✅ UI/UX for collaboration
- ✅ REST API contracts

**Not:**
- ❌ Database schema optimization
- ❌ Cache invalidation workarounds
- ❌ Storage post race conditions
- ❌ Cursor implementation details

**Storage is a solved problem, delegated to feature plugins.**
