# Contributing to Realtime Collaboration

Thank you for your interest in improving WordPress real-time collaborative editing!

## Testing the Integration

### Quick Test (1 minute)

```bash
git clone https://github.com/WordPress/realtime-collaboration.git
cd realtime-collaboration
npm install
npm run env:start
npm run env:setup
```

Then visit http://localhost:8888/wp-admin/plugins.php to verify all three plugins activated:
- ✅ Presence API
- ✅ Gutenberg  
- ✅ Realtime Collaboration

### Verify Integration (5 minutes)

1. **Watch logs**:
   ```bash
   npm run env:logs
   ```

2. **Edit a post**: http://localhost:8888/wp-admin/post-new.php

3. **Look for these log entries**:
   ```
   [RTC] Filter hooked: __unstable_wp_sync_storage
   [RTC] Storage initialized
   [RTC] Storage::set_awareness_state()
   [RTC] Presence::wp_set_presence()
   [RTC] Storage::add_update()
   ```

4. **Check the database**:
   ```bash
   # CRDT updates table
   npm run env:cli -- wp db query "SELECT * FROM wp_collaboration"
   
   # Presence data
   npm run env:cli -- wp db query "SELECT * FROM wp_presence"
   ```

**✅ Success criteria**:
- Logs show filter hook fired
- Awareness calls delegate to wp_set_presence
- Updates insert to wp_collaboration table
- No `wp_cache_set_posts_last_changed` in logs

**❌ Common issues**:

| Symptom | Cause | Fix |
|---------|-------|-----|
| "Presence API not available" in logs | Plugin not active | `npm run env:cli -- wp plugin activate presence-api` |
| No filter hook message | Gutenberg trunk not loaded | Check .wp-env.json points to trunk.zip |
| "Access denied" in logs | Capability check failing | Check you're logged in as admin |

## Development Workflow

### Making Changes

1. **Edit source**: Modify files in `lib/`
2. **Restart environment**: 
   ```bash
   npm run env:stop
   npm run env:start
   npm run env:setup
   ```
3. **Test**: Watch logs and verify behavior
4. **Run tests**:
   ```bash
   npm run test
   ```

### Adding Tests

Tests live in `tests/` and use PHPUnit. To add coverage:

```php
/**
 * @covers RTC_Presence_Storage::add_update
 */
public function test_add_update_stores_in_table() {
    $storage = new RTC_Presence_Storage();
    $room = 'postType/post:1';
    $update = array('data' => 'test');
    
    $result = $storage->add_update($room, $update);
    
    $this->assertTrue($result);
}
```

Run: `npm run test`

### Debugging

**Enable verbose logging** (add to `lib/class-rtc-logger.php`):

```php
public static function debug($message, $context = array()) {
    if (!defined('RTC_VERBOSE_LOGGING') || !RTC_VERBOSE_LOGGING) {
        return;
    }
    self::log("[DEBUG] {$message}", $context);
}
```

Then in `wp-config.php`:
```php
define('RTC_VERBOSE_LOGGING', true);
```

## Code Style

Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/):

```bash
composer install
./vendor/bin/phpcs
```

Auto-fix: `./vendor/bin/phpcbf`

## Architecture Questions

### "Why not use object cache for awareness?"

The Trac discussion (#64696 comment:158) explored this. Presence API handles cache/table fallback internally, so we delegate to it and let it decide. Simpler than managing cache logic in the storage layer.

### "Why store opaque updates instead of parsing?"

The WP_Sync_Storage interface treats updates as opaque blobs - the storage layer shouldn't know about Yjs internals. This keeps it transport-agnostic (works with WebSockets, SSE, etc.).

### "Why no UNIQUE KEY on (room, client_id)?"

CRDT updates need multiple rows per (room, client_id) - they accumulate until compaction. A UNIQUE constraint would reject legitimate writes.

## Submitting Changes

1. **Fork** the repo
2. **Branch**: `git checkout -b fix/cache-invalidation`
3. **Commit**: Follow [Conventional Commits](https://www.conventionalcommits.org/)
   ```
   fix: prevent duplicate awareness writes
   
   Adds deduplication check before calling wp_set_presence.
   Fixes #123
   ```
4. **Push**: `git push origin fix/cache-invalidation`
5. **PR**: Open against `main` with:
   - **What**: One sentence summary
   - **Why**: Link to issue/trac ticket
   - **How**: Brief implementation note
   - **Testing**: Steps to verify

### Example PR Description

```markdown
## What
Prevents duplicate awareness writes when client state hasn't changed.

## Why
Reduces wp_presence table churn ([#123](link-to-issue)).

## How
Compares incoming awareness state hash with cached value before calling
wp_set_presence().

## Testing
1. Open post editor in two tabs
2. Don't move cursor
3. Check logs - should see "Awareness unchanged, skipping write"
4. Verify wp_presence row count doesn't grow
```

## Questions?

- **Issues**: https://github.com/WordPress/realtime-collaboration/issues
- **Discussions**: https://github.com/WordPress/realtime-collaboration/discussions
- **Trac**: https://core.trac.wordpress.org/ticket/64696

Thanks for contributing! 🎉
