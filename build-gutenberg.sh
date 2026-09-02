#!/bin/bash
set -eo pipefail

# Swaps wp-env off the pinned Gutenberg release and onto a fresh trunk build.
#
# .wp-env.json installs a release zip, which is what every ordinary run wants:
# fixed, prebuilt, and the same everywhere. This script is for the runs that
# want the opposite -- the nightly, and anyone reproducing one. The filter this
# plugin hooks is `__unstable_wp_sync_storage`, so it can change shape in trunk
# with no deprecation; the nightly is where that surfaces the morning after
# rather than at the next Gutenberg release.
#
# The swap goes through .wp-env.override.json (gitignored). Delete that file to
# go back to the release.

echo "🔨 Building Gutenberg trunk for wp-env..."

# Must be named "gutenberg", matching the slug in sync-storage.php's
# "Requires Plugins" header -- wp-env mounts local plugin paths using the
# source directory's basename, and WordPress's plugin dependency checker
# matches "Requires Plugins" slugs against installed plugins' folder names.
# A mismatched folder name (e.g. "gutenberg-trunk") makes WordPress report
# the dependency as missing even while the plugin is active.
GUTENBERG_DIR="gutenberg"

# Clone if not already done. If a cached checkout already exists (CI
# restores one to skip the ~1 minute fresh clone), fetch and reset to the
# latest trunk commit so the cache never goes stale.
if [ ! -d "$GUTENBERG_DIR" ]; then
    echo "📦 Cloning Gutenberg trunk..."
    git clone --depth=1 https://github.com/WordPress/gutenberg.git "$GUTENBERG_DIR"
else
    echo "📦 Updating cached Gutenberg checkout to latest trunk..."
    cd "$GUTENBERG_DIR"
    git fetch --depth=1 origin trunk
    git reset --hard origin/trunk
    cd ..
fi

# Build
echo "🏗️  Building Gutenberg (this takes ~2 minutes)..."
cd "$GUTENBERG_DIR"

echo "📦 Installing dependencies..."
# grep exits 1 when it matches nothing, so read npm's own status from
# PIPESTATUS rather than a trailing `|| true` that would swallow both.
set +e
npm install --legacy-peer-deps 2>&1 | grep -E "added|removed|changed|^npm"
npm_status=${PIPESTATUS[0]}
set -e
if [ "$npm_status" -ne 0 ]; then
    echo "❌ npm install failed for Gutenberg trunk (exit $npm_status)" >&2
    exit "$npm_status"
fi

echo "🔧 Running build..."
npm run build 2>&1 | tail -10

cd ..

# wp-env replaces arrays wholesale rather than merging them, so the override
# has to restate the whole plugins list. It is derived from .wp-env.json rather
# than written out here so the two can't drift: adding a plugin there is enough.
echo "🔁 Pointing wp-env at the trunk build..."
node -e '
const fs = require( "fs" );
const config = JSON.parse( fs.readFileSync( ".wp-env.json", "utf8" ) );
const plugins = config.plugins.map( ( plugin ) =>
	plugin.includes( "/gutenberg/releases/" ) ? "./gutenberg" : plugin
);

// Loud, because the failure is otherwise silent and convincing: the release
// stays installed, every log line still says trunk, and the run reports green
// on a Gutenberg it never tested.
if ( ! plugins.includes( "./gutenberg" ) ) {
	throw new Error(
		"No Gutenberg release URL in .wp-env.json to swap for the trunk build."
	);
}

fs.writeFileSync(
	".wp-env.override.json",
	JSON.stringify( { plugins }, null, "\t" ) + "\n"
);
'

echo "✅ Gutenberg built successfully!"
echo "   Located at: $GUTENBERG_DIR"
