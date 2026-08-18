#!/usr/bin/env bash
#
# Reads the canonical version from .release-please-manifest.json and syncs it
# to the plugin header Version field.
#
# Called from .github/workflows/release-please.yml after release-please opens
# (or updates) its release PR. Also runnable locally:
#
#   bash scripts/sync-versions.sh

set -euo pipefail

cd "$(dirname "$0")/.."

command -v jq >/dev/null 2>&1 || { echo "jq is required to run scripts/sync-versions.sh" >&2; exit 1; }
VERSION=$(jq -r '."."' .release-please-manifest.json)

if [[ -z "$VERSION" || "$VERSION" == "null" ]]; then
	echo "Could not read version from .release-please-manifest.json" >&2
	exit 1
fi

grep -q '^ \* Version: ' sync-storage.php \
	|| { echo "Plugin header 'Version:' line not found in sync-storage.php" >&2; exit 1; }

sed -i.bak "s|^ \* Version: .*$| * Version: ${VERSION}|" sync-storage.php

grep -qFx " * Version: ${VERSION}" sync-storage.php \
	|| { echo "Failed to update plugin header version in sync-storage.php" >&2; exit 1; }

rm -f sync-storage.php.bak

echo "Synced plugin header version to ${VERSION}"

grep -q '^Stable tag: ' readme.txt \
	|| { echo "'Stable tag:' line not found in readme.txt" >&2; exit 1; }

sed -i.bak "s|^Stable tag: .*$|Stable tag: ${VERSION}|" readme.txt

grep -qFx "Stable tag: ${VERSION}" readme.txt \
	|| { echo "Failed to update Stable tag in readme.txt" >&2; exit 1; }

rm -f readme.txt.bak

echo "Synced readme.txt Stable tag to ${VERSION}"
