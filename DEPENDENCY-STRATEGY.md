# Dependency Strategy: Presence API

## Strategy: WordPress.org Plugin Dependency System

**Sync Storage uses the `Requires Plugins:` header (WordPress 6.5+) to declare Presence API as a required dependency.**

```php
/**
 * Plugin Name: Sync Storage
 * Requires Plugins: presence-api
 */
```

### How It Works

WordPress automatically:
1. Detects the dependency when sync-storage is installed
2. Prompts user to install presence-api if not already active
3. Prevents sync-storage activation until presence-api is installed
4. Shows dependency information in the Plugins admin screen

**Zero manual bundling required.** WordPress.org handles the entire dependency chain.

### Decision Rationale

1. **Native WordPress feature** - Uses WordPress 6.5+ built-in dependency management
2. **Automatic updates** - WordPress.org updates both plugins independently  
3. **No bundling complexity** - No git subtrees, no vendor directories
4. **Clear dependency graph** - Visible in WordPress admin
5. **Standard WordPress pattern** - Same system all WordPress.org plugins use

### Why Not Bundle?

**Bundling downsides:**
- Duplicate installations if user has standalone Presence API
- Manual sync with upstream changes
- Larger plugin size
- Version conflicts
- Harder to update

**WordPress.org system wins:**
- Single source of truth (WordPress.org repository)
- Automatic version compatibility
- User can update dependencies independently
- Clear separation of concerns

## Version Compatibility

### Minimum Versions

| Plugin | Minimum Version | Reason |
|--------|----------------|---------|
| WordPress | 7.0 | RTC infrastructure, modern WP patterns |
| PHP | 7.4 | Type hints, modern PHP features |
| Presence API | 0.1.22+ | Stable room format, composite storage support |

### Header Declaration

```php
/**
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * Requires Plugins: presence-api
 */
```

WordPress validates all three requirements before allowing activation.

## Runtime Detection

Even with `Requires Plugins:` header, we validate at runtime (defense in depth):

```php
// sync-storage.php lines 34-45

if ( ! function_exists( 'wp_get_presence' ) ) {
    add_action(
        'admin_notices',
        function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__( 'Sync Storage requires the Presence API plugin.', 'sync-storage' );
            echo '</p></div>';
        }
    );
    return;
}
```

**Why both?**
- `Requires Plugins:` header = prevents activation (WordPress admin)
- Runtime check = prevents execution if dependency is deactivated after initial install

## Transition Plan: When Presence API Ships in Core

### Scenario: WordPress 7.1+ includes Presence API

**Version 1.0.0 Changes:**

```php
/**
 * Plugin Name: Sync Storage
 * Version: 1.0.0
 * Requires at least: 7.1
 * Requires PHP: 7.4
 * // Requires Plugins: presence-api  ← REMOVED (now in core)
 */
```

**Runtime check becomes:**

```php
// Presence API guaranteed in WordPress 7.1+
// Function will exist, but check anyway for backward compat
if ( ! function_exists( 'wp_get_presence' ) ) {
    // Fatal error - should never happen on WP 7.1+
    wp_die( esc_html__( 'Sync Storage requires WordPress 7.1+ with Presence API.', 'sync-storage' ) );
}
```

### Backwards Compatibility

| Sync Storage | WP Version | Presence API | Result |
|--------------|------------|--------------|---------|
| 0.x | 6.5-7.0 | Plugin (required) | ✅ Works |
| 0.x | 7.1+ | Core | ✅ Works, plugin ignored |
| 1.0+ | 7.0 | Plugin | ❌ Blocked (requires WP 7.1+) |
| 1.0+ | 7.1+ | Core | ✅ Works |

**No breaking changes for users on WP 7.0 or earlier** - they stay on Sync Storage 0.x.

## WordPress.org Release Process

### Plugin Header is All You Need

```bash
# No build step required
# No bundling required
# No vendor directory management

# Just standard WordPress.org SVN deployment:
svn co https://plugins.svn.wordpress.org/sync-storage trunk
cp -r /path/to/plugin/* trunk/
svn add trunk/*
svn ci -m "Release 0.2.0"
```

WordPress.org reads `Requires Plugins: presence-api` from the header and:
1. Links to presence-api in the plugin directory
2. Shows dependency in "Dependencies" tab
3. Auto-installs presence-api when user clicks "Install"

### No .distignore Changes Needed

Previously considered:
```
# .distignore
vendor/
```

**Now: No vendor directory exists.** Nothing to exclude.

## User Experience

### Installation Flow

**User clicks "Install Sync Storage" on WordPress.org:**

1. WordPress detects `Requires Plugins: presence-api`
2. Checks if presence-api is installed
3. If missing:
   - Shows: "Sync Storage requires Presence API"
   - Button: "Install Presence API"
   - User clicks → both plugins install
4. If present:
   - Installs sync-storage normally

**Zero manual dependency management.**

### Plugin Admin Screen

```
Sync Storage 0.1.1
├─ Requires WordPress 7.0+
├─ Requires PHP 7.4+
└─ Requires Plugins: Presence API (installed ✓)
```

Clicking "Presence API" links to its WordPress.org page.

## What If Presence API Changes?

### API Compatibility

Presence API maintains backwards compatibility:
- Function signatures stable
- Table schema versioned
- No breaking changes in minor versions

### Version Pinning

**Future consideration (not needed yet):**

WordPress doesn't support version pinning in `Requires Plugins:` header (as of 6.5). We declare dependency but not minimum version.

**If breaking changes occur:**
- Update runtime check to verify Presence API version
- Show admin notice if version too old
- Or: wait for WordPress to add version syntax (`Requires Plugins: presence-api (>= 0.2.0)`)

**Current status:** Presence API is stable, no breaking changes expected.

## Documentation Updates

### README.md

```markdown
## Requirements

- **WordPress:** 7.0 or later
- **PHP:** 7.4 or later  
- **Presence API:** Installed automatically from WordPress.org

Sync Storage uses the [Presence API](https://wordpress.org/plugins/presence-api/) 
for awareness tracking. WordPress will prompt you to install it automatically.
```

### readme.txt (WordPress.org)

```
== Installation ==

1. Install Sync Storage from the WordPress plugin directory
2. WordPress will automatically prompt you to install Presence API (required dependency)
3. Activate both plugins
4. Done! No configuration needed.

== Frequently Asked Questions ==

= Does this plugin require other plugins? =

Yes, Sync Storage requires the Presence API plugin. WordPress will install it 
automatically when you install Sync Storage.
```

## Testing

### Local Development

```bash
# Install both plugins in wp-env
npm run env:start

# Presence API is auto-installed via build-gutenberg.sh
# Downloads presence-api.zip from WordPress.org
```

### CI Testing

Already covered in `.wp-env.json`:

```json
{
  "plugins": [
    "https://downloads.wordpress.org/plugin/presence-api.zip",
    "./gutenberg-trunk",
    "."
  ]
}
```

WordPress.org URL pulls the latest stable release.

## Summary

**Decision:** Use `Requires Plugins: presence-api` header (WordPress 6.5+ feature)

**Method:** WordPress.org automatic dependency management

**User impact:** Zero manual steps (WordPress handles installation)

**Maintenance:** Zero (no bundling, no version syncing)

**Future-proof:** Graceful transition when Presence API ships in core (remove header line)

**Standard WordPress pattern:** Same system WordPress.org uses for all plugin dependencies

This is the **correct WordPress way** to declare plugin dependencies. No bundling needed.
