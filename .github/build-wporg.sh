#!/usr/bin/env bash
#
# build-wporg.sh — produce the WordPress.org-shaped variant of this plugin.
#
# NOTE (June 2026): this plugin is GitHub-distributed only and will NOT be
# submitted to WordPress.org — its core feature (in-admin templates that may
# contain PHP, executed via a cached file in uploads) is disallowed by the
# .org directory. See the "Why isn't this plugin on WordPress.org?" readme
# FAQ. This script is no longer wired into the release workflow; it is kept
# solely as the staging harness for local Plugin Check runs (stage the
# output as wp-content/plugins/coywolf-custom-blocks in a test install).
#
# The plugin ships from GitHub with a bundled self-updater that pulls its own
# updates from the project's GitHub Releases. Plugin Check reports that as an
# error, so this script takes an already-staged build tree and strips the
# self-updater out before a check run.
#
# What it removes:
#   * includes/class-github-updater.php          — the updater class file
#   * any  wporg-strip:start .. wporg-strip:end  region in the main PHP file
#     (the require + bootstrap of the updater)
#   * any  wporg-strip:start .. wporg-strip:end  region in readme.txt
#     (prose that advertises GitHub-based updating / outbound calls)
#   * readme.md                                  — .org consumes readme.txt only
#
# Usage:
#   .github/build-wporg.sh <src_dir> <out_dir>
#     <src_dir>  a tree already containing the runtime plugin files. In CI this
#                is the GitHub release stage, so the .git / .github / dev-tool
#                exclusions have already been applied — the .org variant is
#                exactly the GitHub build minus the updater.
#     <out_dir>  emptied and recreated; the variant is written to
#                <out_dir>/<slug>/  (slug = the stage dir's name).
#
set -euo pipefail

SRC="${1:?usage: build-wporg.sh <src_dir> <out_dir>}"
OUT="${2:?usage: build-wporg.sh <src_dir> <out_dir>}"

# The main file is the root *.php carrying the "Plugin Name:" header.
MAIN="$(grep -lE '^[[:space:]]*\*[[:space:]]*Plugin Name:' "$SRC"/*.php 2>/dev/null | head -n1 || true)"
[ -n "$MAIN" ] || { echo "build-wporg: no main plugin file (Plugin Name header) found in $SRC" >&2; exit 1; }
MAIN_BASE="$(basename "$MAIN")"
# The .org slug / folder name is the stage dir's own name. The release
# workflow stages into a folder named after its authoritative SLUG (which can
# differ from the repo name — e.g. repo "custom-blocks" ships slug
# "coywolf-custom-blocks"), so deriving it from $SRC keeps the two in lockstep.
SLUG="$(basename "$SRC")"

STAGE="$OUT/$SLUG"
rm -rf "$OUT"
mkdir -p "$STAGE"
cp -a "$SRC"/. "$STAGE"/

# Delete a wporg-strip marked region from a file, portably (macOS + Linux).
# The Perl flip-flop matches the start..end lines inclusive.
strip_marked() {
	local f="$1"
	[ -f "$f" ] || return 0
	perl -ni -e 'print unless /wporg-strip:start/ .. /wporg-strip:end/' "$f"
}

rm -f "$STAGE/includes/class-github-updater.php"
strip_marked "$STAGE/$MAIN_BASE"
strip_marked "$STAGE/readme.txt"
rm -f "$STAGE/readme.md"

# Strip the "Update URI" plugin header. WordPress.org forbids it (it signals a
# non-.org update source, and Plugin Check reports it as a plugin updater). The
# GitHub build keeps it so .org can't hijack updates for the same slug.
perl -ni -e 'print unless /^\s*\*\s*Update URI\s*:/i' "$STAGE/$MAIN_BASE"

# The stripped main file must still parse.
php -l "$STAGE/$MAIN_BASE"

# Guard: no trace of the self-updater code may survive in the variant. (Scoped
# to .php so historical mentions in the readme changelog don't trip it.)
if grep -rIlE 'GitHub_Updater|class-github-updater' "$STAGE" --include='*.php' >/dev/null 2>&1; then
	echo "build-wporg: ERROR — self-updater code leaked into the .org variant:" >&2
	grep -rIlE 'GitHub_Updater|class-github-updater' "$STAGE" --include='*.php' >&2
	exit 1
fi

echo "build-wporg: wrote $STAGE (slug: $SLUG)"
