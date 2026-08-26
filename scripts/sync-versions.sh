#!/usr/bin/env bash
#
# Reads the canonical version from .release-please-manifest.json and syncs it
# to the plugin header's Version field, the WP_SYNC_STORAGE_VERSION constant,
# and readme.txt's Stable tag. Also regenerates readme.txt's Changelog section
# from CHANGELOG.md, which release-please has already rewritten by this point.
#
# Called from .github/workflows/release-please.yml after release-please opens
# (or updates) its release PR. Also runnable locally:
#
#   bash scripts/sync-versions.sh

set -euo pipefail

cd "$(dirname "$0")/.."

# WordPress.org truncates long readmes and readers only ever care about recent
# releases, so readme.txt carries a window of the newest entries and links out
# for the rest. CHANGELOG.md remains the complete history.
README_CHANGELOG_RELEASES=5
CHANGELOG_URL="https://github.com/WordPress/sync-storage/blob/main/CHANGELOG.md"

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

grep -q "^define( 'WP_SYNC_STORAGE_VERSION', " sync-storage.php \
	|| { echo "WP_SYNC_STORAGE_VERSION define() not found in sync-storage.php" >&2; exit 1; }

sed -i.bak "s|^define( 'WP_SYNC_STORAGE_VERSION', '.*' );$|define( 'WP_SYNC_STORAGE_VERSION', '${VERSION}' );|" sync-storage.php

grep -qFx "define( 'WP_SYNC_STORAGE_VERSION', '${VERSION}' );" sync-storage.php \
	|| { echo "Failed to update WP_SYNC_STORAGE_VERSION in sync-storage.php" >&2; exit 1; }

rm -f sync-storage.php.bak

echo "Synced WP_SYNC_STORAGE_VERSION constant to ${VERSION}"

grep -q '^Stable tag: ' readme.txt \
	|| { echo "'Stable tag:' line not found in readme.txt" >&2; exit 1; }

sed -i.bak "s|^Stable tag: .*$|Stable tag: ${VERSION}|" readme.txt

grep -qFx "Stable tag: ${VERSION}" readme.txt \
	|| { echo "Failed to update Stable tag in readme.txt" >&2; exit 1; }

rm -f readme.txt.bak

echo "Synced readme.txt Stable tag to ${VERSION}"

grep -q '^== Changelog ==$' readme.txt \
	|| { echo "'== Changelog ==' section not found in readme.txt" >&2; exit 1; }

grep -q '^## ' CHANGELOG.md \
	|| { echo "No release headings found in CHANGELOG.md" >&2; exit 1; }

GENERATED=$(mktemp)
# shellcheck disable=SC2064 # Expand GENERATED now; the trap must not depend on later state.
trap "rm -f '${GENERATED}'" EXIT

# Rewrites release-please's Markdown into the readme.txt dialect: `## [x.y.z]`
# headings become `= x.y.z =`, and `### Bug Fixes` collapses into a per-bullet
# `Fix:` prefix because readme.txt has no heading level below the version.
#
# Two entries per change is normal here: release-please credits both the squashed
# commit and the merge commit, which share a subject and differ only in hash. The
# hash is stripped for readability, which makes those pairs identical, so they're
# deduplicated by text.
#
# Both the trailing links and any `closes [#12](url)` clause have to go for that
# to hold. Stripping only a line-final commit link left entries that close an
# issue looking different from their twin, so the pair survived deduplication and
# reached readme.txt with raw Markdown in it, which WordPress.org renders literally.
#
# A squash-merged commit ends in `(#12)`, which release-please renders as a PR
# link alongside the commit link, so the link text is not always a hash.
awk -v max="${README_CHANGELOG_RELEASES}" '
	function label(section) {
		if (section == "Bug Fixes")                return "Fix"
		if (section == "Features")                 return "Feature"
		if (section == "Performance Improvements") return "Performance"
		if (section == "Dependencies")             return "Dependencies"
		if (section == "Reverts")                  return "Revert"
		return section
	}

	# Separates releases with a leading blank line rather than a trailing one so
	# the block never ends in whitespace, which would otherwise accumulate a
	# blank line per run.
	function emit(   i) {
		if (version == "" || count == 0) {
			return
		}
		if (released > 0) {
			printf "\n"
		}
		printf "= %s =\n", version
		for (i = 1; i <= count; i++) {
			print entries[i]
		}
		released++
	}

	/^## / {
		emit()
		if (released >= max) {
			stop = 1
			exit
		}
		version = ""
		section = ""
		count = 0
		split("", seen)
		# Matches both release-please headings (`## [0.1.7](url) (date)`) and the
		# hand-written 0.1.0 heading (`## [0.1.0] - 2026-08-15`).
		if (match($0, /\[[^]]+\]/)) {
			version = substr($0, RSTART + 1, RLENGTH - 2)
		}
		next
	}

	/^### / {
		section = substr($0, 5)
		sub(/[ \t\r]+$/, "", section)
		next
	}

	/^[*-] / {
		if (version == "") {
			next
		}
		text = substr($0, 3)
		# Anchored on the issue link rather than the word alone, so a subject
		# that happens to contain "closes" keeps its tail.
		sub(/,? *closes \[#[0-9]+\]\(.*$/, "", text)
		gsub(/ *\(\[[^]]+\]\([^)]*\)\)/, "", text)
		sub(/[ \t\r]+$/, "", text)
		if (text == "") {
			next
		}
		key = tolower(text)
		if (key in seen) {
			next
		}
		seen[key] = 1
		prefix = label(section)
		entries[++count] = (prefix == "" ? "* " text : "* " prefix ": " text)
	}

	END { if (!stop) emit() }
' CHANGELOG.md > "${GENERATED}"

[[ -s "${GENERATED}" ]] \
	|| { echo "Generated an empty changelog from CHANGELOG.md" >&2; exit 1; }

grep -qFx "= ${VERSION} =" "${GENERATED}" \
	|| { echo "CHANGELOG.md has no entry for ${VERSION}; run release-please first" >&2; exit 1; }

# readme.txt has no Markdown, so a surviving link means release-please's entry
# format moved and the stripping above no longer matches it. Fail here rather
# than commit link syntax into the WordPress.org listing.
if grep -n '](' "${GENERATED}"; then
	echo "Generated changelog contains Markdown links; update the stripping rules in scripts/sync-versions.sh" >&2
	exit 1
fi

# Replaces everything between `== Changelog ==` and the next `== Section ==`.
awk -v generated="${GENERATED}" -v url="${CHANGELOG_URL}" '
	/^== Changelog ==$/ {
		print
		print ""
		print "Only the most recent releases are listed here. For the full history, see " url
		print ""
		while ((getline line < generated) > 0) {
			print line
		}
		close(generated)
		skipping = 1
		next
	}
	skipping && /^== / { skipping = 0; print "" }
	!skipping { print }
' readme.txt > readme.txt.new

mv readme.txt.new readme.txt

grep -qFx "= ${VERSION} =" readme.txt \
	|| { echo "Failed to update the Changelog section in readme.txt" >&2; exit 1; }

echo "Synced readme.txt Changelog through ${VERSION} (${README_CHANGELOG_RELEASES} most recent releases)"
