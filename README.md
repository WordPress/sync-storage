# Realtime Collaboration

[![WordPress Plugin Required Version](https://img.shields.io/badge/WordPress-7.0%2B-blue.svg)](https://wordpress.org/)
[![PHP Required Version](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](LICENSE)

> [!NOTE]
> **Experimental feature plugin** - Storage layer for real-time collaborative editing in WordPress.

## Try it

[![Open in WordPress Playground](https://img.shields.io/badge/Open%20in-WordPress%20Playground-3858E9?logo=wordpress&logoColor=white)](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/josephfusco/realtime-collaboration/main/blueprint.json)

---

## Problem

Gutenberg's RTC (Real-Time Collaboration) feature currently stores sync data in `post_meta`, causing site-wide cache invalidation on every edit. This plugin provides a dedicated `wp_collaboration` table and integrates with the Presence API for awareness, eliminating cache side effects.

## Requirements

| Requirement | Version | Status |
|------------|---------|--------|
| WordPress | 7.0+ | ✅ |
| PHP | 8.0+ | ✅ |
| [Presence API](https://github.com/WordPress/presence-api) | Latest | ✅ |
| [Gutenberg](https://wordpress.org/plugins/gutenberg/) | with `gutenberg_sync_storage` filter | ⚠️ Pending |

> [!WARNING]
> **Blocker:** The `gutenberg_sync_storage` filter doesn't exist yet in Gutenberg. A PR is needed to add `apply_filters( 'gutenberg_sync_storage', ... )` to make this plugin functional.

## What This Plugin Does

**Provides:**
- `wp_collaboration` table for CRDT update storage
- Integration with Presence API for awareness (cursors, user metadata)
- Implementation of `Gutenberg_Sync_Storage` interface
- Server authority model (RTC activates when 2+ editors detected)
- Zero cache side effects (dedicated table, not post meta)

## What It Doesn't Do

**Out of Scope:**
- Gutenberg's HTTP polling provider (remains in Gutenberg)
- REST endpoints (Gutenberg keeps `/wp/v2/sync/updates`)
- Editor UI features (cursors, avatars handled by Gutenberg)
- Presence API modifications (plugin is a consumer, not a modifier)

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

## Installation

### From Source

```bash
git clone https://github.com/josephfusco/realtime-collaboration.git
cd realtime-collaboration
# Install to WordPress plugins directory
```

### Requirements Check

The plugin will display admin notices if:
- Presence API is not installed/active
- WordPress version < 7.0
- Gutenberg doesn't support `gutenberg_sync_storage` filter

## Database Schema

<details>
<summary><strong>wp_collaboration</strong> table structure</summary>

```sql
CREATE TABLE wp_collaboration (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    room varchar(191) NOT NULL,
    client_id bigint(20) unsigned NOT NULL,
    type varchar(20) NOT NULL,
    data longtext NOT NULL,
    timestamp bigint(20) unsigned NOT NULL,
    PRIMARY KEY (id),
    KEY room_timestamp (room(50), timestamp)
);
```

| Column | Type | Description |
|--------|------|-------------|
| `id` | `bigint(20) unsigned` | Auto-increment primary key |
| `room` | `varchar(191)` | Entity identifier (e.g., `postType/post:42`) |
| `client_id` | `bigint(20) unsigned` | Yjs client ID |
| `type` | `varchar(20)` | Update type (`update`, `sync_step1`, `sync_step2`, `compaction`) |
| `data` | `longtext` | Base64-encoded Yjs update (opaque to server) |
| `timestamp` | `bigint(20) unsigned` | Milliseconds since epoch (Yjs format) |

</details>

## How It Works

<table>
<tr>
<th>Single Editor</th>
<th>Second Editor Joins</th>
<th>Second Editor Leaves</th>
</tr>
<tr>
<td valign="top">

1. User opens post in Gutenberg
2. Presence API tracks: 1 editor active
3. RTC plugin returns empty awareness
4. Gutenberg runs in single-user mode

</td>
<td valign="top">

1. Second user opens same post
2. Presence API detects: 1→2 editors
3. Fires `wp_presence_collaboration_started`
4. Plugin flags post as active
5. Heartbeat sends collaboration signal
6. Gutenberg initializes Yjs sync

</td>
<td valign="top">

1. Presence entry expires (60s TTL)
2. Presence API detects: 2→1 editors
3. Fires `wp_presence_collaboration_ended`
4. Plugin clears collaboration flag
5. Remaining editor returns to single-user

</td>
</tr>
</table>

## Cleanup

- **Compaction:** Gutenberg handles (client-nominated compaction)
- **Safety net:** Daily cron deletes updates >7 days old
- **Migration:** Automatic migration from `wp_sync_storage` post meta on activation

## Developer Hooks

### Actions

<details>
<summary><code>rtc_collaboration_room_active</code></summary>

Fires when collaboration starts (2+ editors detected).

**Parameters:**
- `$post_id` (int) - The post ID where collaboration started
- `$entries` (array) - Presence entries for all active editors

**Example:**
```php
add_action( 'rtc_collaboration_room_active', function( $post_id, $entries ) {
    // Log when collaborative editing starts
    error_log( "RTC started on post {$post_id} with " . count( $entries ) . " editors" );
}, 10, 2 );
```
</details>

<details>
<summary><code>rtc_collaboration_room_inactive</code></summary>

Fires when collaboration ends (back to single editor).

**Parameters:**
- `$post_id` (int) - The post ID where collaboration ended
- `$entries` (array) - Remaining presence entries

**Example:**
```php
add_action( 'rtc_collaboration_room_inactive', function( $post_id, $entries ) {
    // Clean up when collaboration ends
    delete_post_meta( $post_id, '_custom_rtc_flag' );
}, 10, 2 );
```
</details>

## Multisite Support

✅ **Fully supported** - Tables are created per-site, not globally.

On network activation, automatically creates `wp_collaboration` table on all sites.

## Security

**Built-in protections:**
- ✅ Capability checks via `current_user_can( 'edit_post', $post_id )`
- ✅ Room format validation (SQL injection prevention via regex)
- ✅ Prepared statements for all database queries
- ✅ Defensive checks for Presence API availability
- ✅ No data exposed without proper authentication

---

## Development

<details>
<summary><strong>Local Setup</strong></summary>

```bash
# Install dependencies
npm install
composer install

# Start wp-env (WordPress + Gutenberg)
npx wp-env start

# The site will be available at:
# http://localhost:8888 (username: admin, password: password)
```

</details>

<details>
<summary><strong>Testing</strong></summary>

```bash
# Run PHPUnit tests
composer test

# Run PHPCS linting
composer lint

# Run PHPStan static analysis
composer analyze
```

</details>

---

## Maintainers

Maintained by [@josephfusco](https://github.com/josephfusco)

Sponsored by the [WordPress Core team](https://make.wordpress.org/core/). Updates posted on [make.wordpress.org/core](https://make.wordpress.org/core/) with tag `#realtime-collaboration`.

## Support

- **Bug reports & features:** [GitHub Issues](https://github.com/josephfusco/realtime-collaboration/issues)
- **Discussion:** [#realtime-collaboration](https://wordpress.slack.com/archives/realtime-collaboration) on WordPress Slack

## Related Projects

| Project | Description |
|---------|-------------|
| [Presence API](https://github.com/WordPress/presence-api) | Awareness infrastructure (required dependency) |
| [Gutenberg #80387](https://github.com/WordPress/gutenberg/issues/80387) | RTC provider gating discussion |
| [Trac #64696](https://core.trac.wordpress.org/ticket/64696) | RTC cache invalidation issue |
| [wordpress-develop #11609](https://github.com/WordPress/wordpress-develop/pull/11609) | Prior exploration of core integration |

---

<p align="center">
Made with ❤️ for WordPress
</p>
