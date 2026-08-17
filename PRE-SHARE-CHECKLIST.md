# Pre-Share Checklist for WordPress.org Transfer

## ❌ Must Fix Before Sharing

### 1. Update GitHub URLs
Replace `josephfusco` with `WordPress` organization:

**Files to update:**
- `README.md` (clone URL, Playground badge URL)
- `blueprint.json` (line 41: plugin URL)
- `CHANGELOG.md` (release links)
- `CONTRIBUTING.md` (clone URL, issues, discussions links)
- `SECURITY.md` (security advisory URL)
- `blueprint-test.json` (if keeping)

**Search/replace:**
```bash
# Dry run
grep -r "josephfusco" --include="*.md" --include="*.json" .

# After manual review, update to:
github.com/WordPress/realtime-collaboration
```

### 2. Blueprint.json Won't Work Yet
**Issue:** Uses wordpress.org Gutenberg, which doesn't have `__unstable_wp_sync_storage` filter yet (trunk only).

**Options:**
- **A)** Remove `blueprint.json` until filter ships in Gutenberg release
- **B)** Add warning in README:
  ```markdown
  > ⚠️ **Blueprint demo pending:** Requires `__unstable_wp_sync_storage` filter from Gutenberg trunk. 
  > Will work once [PR #81697](https://github.com/WordPress/gutenberg/pull/81697) ships in a release.
  ```

**Recommendation:** Keep blueprint.json but add warning. Shows intent, ready when filter ships.

### 3. TESTING.md Has Local Paths
**Line 14:** `cd ~/Documents/GitHub/realtime-collaboration`

**Fix:** Use relative path or remove personal home directory:
```markdown
cd /path/to/realtime-collaboration
```

### 4. blueprint-test.json References
**Line 18:** `https://github.com/josephfusco/gutenberg/...`

**Options:**
- Delete `blueprint-test.json` (seems like a test artifact)
- Update URL if keeping

**Recommendation:** Delete it (not needed for public repo).

## ⚠️ Should Review

### 5. GUTENBERG-DEPENDENCIES.md
**Check:** Is this ready for public eyes?
- ✅ Good technical analysis
- ⚠️ Tone okay for WordPress.org org repo? (seems fine)

### 6. PROTOCOL-VS-STORAGE.md
**Check:** Clear division of responsibilities?
- ✅ Well structured
- ⚠️ "Lines saved: ~500" - is this accurate? (seems reasonable)

### 7. README.md Temporary Note
**Line 18:** 
```markdown
> ⚠️ **Temporary**: Requires Gutenberg trunk build until `__unstable_wp_sync_storage` filter ships
```

**Good:** Sets expectations. Keep it.

### 8. setup.sh and start.sh
**Check:** Are these needed or duplicates of npm scripts?

```bash
# Current npm scripts already cover this:
npm run env:start  # Builds Gutenberg + starts wp-env
npm run env:stop
```

**Recommendation:** Delete `setup.sh` and `start.sh` if they duplicate npm scripts.

## ✅ Already Good

- ✅ LICENSE (GPL-2.0-or-later)
- ✅ CONTRIBUTING.md (comprehensive)
- ✅ SECURITY.md (clear process)
- ✅ CHANGELOG.md (follows Keep a Changelog)
- ✅ README.md (clear, concise)
- ✅ CI workflows (.github/workflows/*)
- ✅ Code quality (phpcs, phpstan, phpunit configs)

## Quick Fixes Script

```bash
# 1. Delete test/duplicate files
rm blueprint-test.json setup.sh start.sh

# 2. Update TESTING.md
sed -i '' 's|~/Documents/GitHub/realtime-collaboration|/path/to/realtime-collaboration|g' TESTING.md

# 3. Add blueprint warning to README
# (Manual edit recommended)

# 4. Update GitHub URLs
# Find all references:
grep -r "josephfusco" --include="*.md" --include="*.json" . | grep -v node_modules
# Then manually update to WordPress organization
```

## Final Check

Before pushing to WordPress.org:

```bash
# 1. Clean build
rm -rf node_modules gutenberg-trunk
npm install

# 2. Test full setup
npm run env:start

# 3. Verify plugins active
npm run env:cli -- plugin list

# 4. Check logs
docker exec $(docker ps -q --filter "name=realtime-collaboration.*wordpress-1") tail -20 /var/www/html/wp-content/debug.log

# 5. Stop
npm run env:stop
```

## Transfer Steps

1. ✅ Fix all "Must Fix" items above
2. ✅ Review "Should Review" items
3. ✅ Run quick fixes script
4. ✅ Test locally one more time
5. ✅ Create new repo at `github.com/WordPress/realtime-collaboration`
6. ✅ Update remote:
   ```bash
   git remote set-url origin https://github.com/WordPress/realtime-collaboration.git
   ```
7. ✅ Push to WordPress.org GitHub
8. ✅ Announce alongside Presence API
