# Testing RTC Integration

## Environment Setup

The wp-env loads:
- **realtime-collaboration** (local development)
- **presence-api** (from wordpress.org)
- **Gutenberg** (trunk.zip with `__unstable_wp_sync_storage` filter)

## What to Watch

### 1. Plugin Activation (wp-content/debug.log)

```
[RTC] Plugin loaded {"version":"0.1.0","db_version":1,"presence":true,"gutenberg":"21.x"}
[RTC] Installation started
[RTC] Table created {"table":"wp_collaboration","charset":"utf8mb4_unicode_ci"}
[RTC] Cleanup cron scheduled
[RTC] Installation complete
```

**Verifies**: Plugin loads, dependencies detected, table created.

### 2. Filter Hook (when Gutenberg initializes)

```
[RTC] Filter hooked: __unstable_wp_sync_storage {"default":"WP_Sync_Post_Meta_Storage","custom":"RTC_Presence_Storage"}
[RTC] Storage initialized {"class":"RTC_Presence_Storage"}
```

**Verifies**: Gutenberg called the filter, our storage replaced the default.

### 3. Awareness Operations (when you open the post editor)

```
[RTC] Storage::set_awareness_state() {"room":"postType/post:1","count":1}
[RTC] Presence::wp_set_presence() {"room":"postType/post:1","client_id":12345,"user_id":1}

[RTC] Storage::get_awareness_state() {"room":"postType/post:1"}
[RTC] Presence::wp_get_presence() {"room":"postType/post:1","entries":1}
[RTC] Storage::get_awareness_state:result {"room":"postType/post:1","count":1}
```

**Verifies**: Awareness delegated to Presence API (zero cache impact).

### 4. CRDT Update Operations (when you type in the editor)

```
[RTC] Storage::add_update() {"room":"postType/post:1"}
[RTC] Storage::add_update:result {"room":"postType/post:1","success":true,"insert_id":1}

[RTC] Storage::get_updates_after_cursor() {"room":"postType/post:1","cursor":0}
[RTC] Storage::get_updates_after_cursor:result {"room":"postType/post:1","count":1,"new_cursor":1}
```

**Verifies**: Updates stored in wp_collaboration table, cursor tracking works.

## How to Test

1. **Start environment** (already running):
   ```bash
   cd /path/to/realtime-collaboration
   npm run env:start
   ```

2. **Watch logs**:
   ```bash
   npm run env:cli -- tail -f /var/www/html/wp-content/debug.log
   ```

3. **Activate plugin**:
   - Visit http://localhost:8888/wp-admin/plugins.php
   - Activate "Realtime Collaboration"
   - Check logs for installation messages

4. **Enable Gutenberg RTC**:
   - Visit http://localhost:8888/wp-admin/options-writing.php
   - Enable "Real-time Collaboration" (if available)
   - Or enable via Gutenberg experiments

5. **Edit a post**:
   - Open any post in the block editor
   - Watch logs for:
     - Filter hook confirmation
     - Storage initialization
     - Awareness state updates
   - Type some text
   - Watch for CRDT update logs

6. **Verify table**:
   ```bash
   npm run env:cli -- wp db query "SELECT * FROM wp_collaboration"
   ```
   Should show rows with room, data, timestamp.

7. **Verify Presence API integration**:
   ```bash
   npm run env:cli -- wp db query "SELECT * FROM wp_presence WHERE room LIKE 'postType/%'"
   ```
   Should show presence entries for your editing session.

## Expected Outcomes

### ✅ Success Indicators
- Plugin loads without errors
- Filter hook fires (Gutenberg calls our storage)
- Awareness → Presence API (logs show wp_set_presence / wp_get_presence)
- Updates → wp_collaboration table (logs show insert_id)
- No cache invalidation in logs (no wp_cache_set_posts_last_changed)

### ❌ Failure Indicators
- "Presence API not available" in logs → presence-api plugin not active
- No filter hook message → Gutenberg trunk not loaded or filter missing
- "Access denied" in logs → capability check failing
- SQL errors → table creation failed

## Debugging

**If filter doesn't hook:**
```bash
# Check Gutenberg version
npm run env:cli -- wp eval "echo GUTENBERG_VERSION . PHP_EOL;"

# Check if filter exists (should be in Gutenberg 21.x+)
npm run env:cli -- wp eval "var_dump(has_filter('__unstable_wp_sync_storage'));"
```

**If Presence API missing:**
```bash
# Check if installed
npm run env:cli -- wp plugin list

# Verify function exists
npm run env:cli -- wp eval "var_dump(function_exists('wp_get_presence'));"
```

**If table doesn't exist:**
```bash
# Run installer manually
npm run env:cli -- wp eval "rtc_collaboration_install();"

# Check table
npm run env:cli -- wp db query "SHOW TABLES LIKE 'wp_collaboration'"
```

## Clean Slate

To reset and test from scratch:
```bash
npm run env:stop
npm run env:clean
npm run env:start
```
