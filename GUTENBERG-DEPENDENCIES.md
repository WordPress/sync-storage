# What Gutenberg Must Shed for Composite Storage

## ✅ Clean - No Changes Needed

Gutenberg **strictly uses the WP_Sync_Storage interface** with zero bypasses:

```php
// collaboration.php - storage is injected via filter
$sync_storage = apply_filters( '__unstable_wp_sync_storage', new WP_Sync_Post_Meta_Storage() );
$sync_server = new WP_HTTP_Polling_Sync_Server( $sync_storage );
```

All storage access goes through interface methods:
- `$this->storage->get_awareness_state()`
- `$this->storage->set_awareness_state()`
- `$this->storage->add_update()`
- `$this->storage->get_updates_after_cursor()`
- `$this->storage->remove_updates_before_cursor()`
- `$this->storage->get_update_count()`
- `$this->storage->get_cursor()`

**No direct database queries in the REST layer.**

## ⚠️ Things to Watch

### 1. `wp_sync_storage` Custom Post Type
**Status:** Not a blocker, but will be unused.

Gutenberg registers this for the default post meta storage:

```php
register_post_type( 'wp_sync_storage', ... );
```

With our composite storage:
- ❌ We don't create `wp_sync_storage` posts
- ❌ We don't use post meta for RTC data
- ✅ The post type still gets registered (harmless)
- ✅ Old data from default storage won't interfere

**Action:** None needed. The post type exists but our storage never touches it.

### 2. `_crdt_document` Post Meta
**Status:** Separate concern, not RTC storage.

```php
// class-wp-sync-save-server.php
update_post_meta( $post_id, '_crdt_document', $doc );
```

This is **NOT part of WP_Sync_Storage**. It's for:
- Persisting final CRDT snapshot to the actual post
- Autosave comparison
- Post meta on the REAL post (not wp_sync_storage posts)

**Action:** None needed. This is orthogonal to sync storage.

### 3. Awareness Timeout Assumptions
**Status:** May need adjustment.

Gutenberg assumes 30s timeout:

```php
const AWARENESS_TIMEOUT = 30;

// Filters out expired entries
if ( $current_time - $entry['updated_at'] >= self::AWARENESS_TIMEOUT )
```

Our Presence API has **60s TTL** by default.

**Impact:**
- Presence API auto-expires entries after 60s
- Gutenberg manually filters after 30s
- ✅ More aggressive timeout wins (30s)
- ✅ No data corruption, just different UX

**Action:** Consider aligning Presence API TTL to 30s for consistency.

### 4. Cleanup Expectations
**Status:** Depends on future features.

If Gutenberg adds admin tools to:
- "Clear all RTC data for this post"
- Migrate old storage to new format
- Export/import RTC state

Those would need to use the storage interface, not direct queries.

**Current:** No such features exist. All cleanup is compaction-based (via interface).

## ❌ Blockers (None Found)

**Zero blockers.** Gutenberg is clean.

## Summary

| Concern | Status | Action |
|---------|--------|--------|
| Direct database access | ✅ None found | None |
| Storage interface usage | ✅ Strict | None |
| Co-located queries | ✅ None | None |
| Post meta bypass | ✅ None (except orthogonal `_crdt_document`) | None |
| Unused post type | ⚠️ Harmless | None (or hide from UI if desired) |
| Awareness TTL mismatch | ⚠️ Works but inconsistent | Optional: align to 30s |

**Gutenberg is ready for composite storage.** The `__unstable_wp_sync_storage` filter is the only integration point, and it's used consistently throughout.
