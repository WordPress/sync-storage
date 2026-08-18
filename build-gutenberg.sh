#!/bin/bash
set -eo pipefail

echo "🔨 Building Gutenberg trunk for wp-env..."

# Must be named "gutenberg", matching the slug in sync-storage.php's
# "Requires Plugins" header -- wp-env mounts local plugin paths using the
# source directory's basename, and WordPress's plugin dependency checker
# matches "Requires Plugins" slugs against installed plugins' folder names.
# A mismatched folder name (e.g. "gutenberg-trunk") makes WordPress report
# the dependency as missing even while the plugin is active.
GUTENBERG_DIR="gutenberg"

# Clone if not already done
if [ ! -d "$GUTENBERG_DIR" ]; then
    echo "📦 Cloning Gutenberg trunk..."
    git clone --depth=1 https://github.com/WordPress/gutenberg.git "$GUTENBERG_DIR"
fi

# Build
echo "🏗️  Building Gutenberg (this takes ~2 minutes)..."
cd "$GUTENBERG_DIR"

echo "📦 Installing dependencies..."
npm install --legacy-peer-deps 2>&1 | grep -E "added|removed|changed|^npm" || true

echo "🔧 Running build..."
npm run build 2>&1 | tail -10

cd ..

echo "✅ Gutenberg built successfully!"
echo "   Located at: $GUTENBERG_DIR"
