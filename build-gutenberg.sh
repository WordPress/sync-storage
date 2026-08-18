#!/bin/bash
set -eo pipefail

echo "🔨 Building Gutenberg trunk for wp-env..."

GUTENBERG_DIR="gutenberg-trunk"

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
