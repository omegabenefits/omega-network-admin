#!/usr/bin/env bash
#
# Build a clean distribution zip of the plugin for the self-hosted update server.
#
# Uses `git archive` on the installable plugin root, so the zip contains only
# committed, tracked WordPress plugin files from that folder.
# Because it archives a git ref, commit your release before building.
#
# Output: release/dist/<slug>-<version>.zip, with a top-level <slug>/ folder
# so WordPress installs it to the right directory.
#
# After building, the zip is uploaded to the wp-update-server over SFTP using the
# connection details in release/deploy.env (gitignored; copy release/deploy.env.example).
# If that file is absent, the build still succeeds and upload is skipped.
#
# Usage:
#   Double-click release/build-zip.command in Finder
#   release/build-zip.command            # build from HEAD (current committed state) + upload
#   release/build-zip.command v1.0.3     # build from a tag or any git ref + upload

set -euo pipefail

# --- Per-plugin config -------------------------------------------------------
# When reusing this workflow in another plugin repo, update only these values:
# - PLUGIN_ROOT: folder containing the installable WordPress plugin files.
# - PLUGIN_FILE: main plugin file, relative to PLUGIN_ROOT, with the `Version:` header.
# - SLUG: plugin directory/update slug; must match the PUC slug and update-server package slug.
# - DEFAULT_METADATA_URL: optional version-check URL. Leave blank if this repo does not use one.
# Keep SLUG and DEFAULT_METADATA_URL aligned with the plugin's updater class.
#
# These can also be overridden for one-off runs:
#   PLUGIN_ROOT=plugin PLUGIN_FILE=omega-network-admin.php SLUG=omega-network-admin release/build-zip.command
PLUGIN_ROOT="${PLUGIN_ROOT:-plugin}"
PLUGIN_ROOT="${PLUGIN_ROOT%/}"
PLUGIN_FILE="${PLUGIN_FILE:-omega-network-admin.php}"
SLUG="${SLUG:-omega-network-admin}"
DEFAULT_METADATA_URL="${DEFAULT_METADATA_URL:-https://omegabenefits.net/wp-update-server/?action=get_metadata&slug=omega-network-admin}"

REF="${1:-HEAD}"

# Repo root (this script lives in release/).
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# Read the plugin version straight from the main file header so the zip name
# always matches what the update server will advertise.
PLUGIN_PATH="${PLUGIN_ROOT%/}/${PLUGIN_FILE}"
if [ ! -f "$PLUGIN_PATH" ]; then
	echo "error: plugin file not found: $PLUGIN_PATH" >&2
	exit 1
fi

VERSION="$(sed -n 's/^[[:space:]]*\*\{0,1\}[[:space:]]*Version:[[:space:]]*//p' "$PLUGIN_PATH" | head -n1 | tr -d '[:space:]')"
if [ -z "$VERSION" ]; then
	echo "error: could not read Version from $PLUGIN_PATH" >&2
	exit 1
fi

# git archive uses the committed ref, so flag uncommitted work as a heads-up.
if ! git diff --quiet HEAD -- 2>/dev/null; then
	echo "warning: working tree has uncommitted changes — building from '$REF' (committed state only)." >&2
fi

mkdir -p release/dist
OUT="release/dist/${SLUG}-${VERSION}.zip"
rm -f "$OUT"

if ! git cat-file -e "${REF}:${PLUGIN_ROOT}" 2>/dev/null; then
	echo "error: $REF does not contain plugin root: $PLUGIN_ROOT" >&2
	exit 1
fi

git archive --format=zip --prefix="${SLUG}/" -o "$OUT" "${REF}:${PLUGIN_ROOT}"

echo "Built $OUT ($(du -h "$OUT" | cut -f1))"
echo "Top-level entries:"
unzip -Z1 "$OUT" | sed 's#/.*##' | sort -u | sed 's/^/  /'

# --- Upload to the update server over SFTP -----------------------------------
# Connection details come from release/deploy.env (gitignored) so no credentials or
# host info live in the tracked script.
ENV_FILE="$ROOT/release/deploy.env"
if [ ! -f "$ENV_FILE" ]; then
	echo
	echo "note: release/deploy.env not found — skipping upload."
	echo "      Copy release/deploy.env.example to release/deploy.env and fill it in to enable SFTP upload."
	exit 0
fi

# shellcheck disable=SC1090
set -a; . "$ENV_FILE"; set +a

: "${DEPLOY_HOST:?set DEPLOY_HOST in release/deploy.env}"
: "${DEPLOY_USER:?set DEPLOY_USER in release/deploy.env}"
: "${DEPLOY_REMOTE_DIR:?set DEPLOY_REMOTE_DIR in release/deploy.env}"
PORT="${DEPLOY_PORT:-22}"
# wp-update-server serves <slug>.zip from its packages dir, so default to the
# unversioned slug filename (it reads the version from inside the zip).
REMOTE_FILENAME="${DEPLOY_REMOTE_FILENAME:-${SLUG}.zip}"
REMOTE_PATH="${DEPLOY_REMOTE_DIR%/}/${REMOTE_FILENAME}"
TARGET="${DEPLOY_USER}@${DEPLOY_HOST}:${REMOTE_PATH}"

# Version sanity check: compare the version we're about to publish with what the
# update server currently advertises. Never blocks a same-version re-upload (the
# legitimate "fix a bad package before anyone installs it" workflow) — it only
# tells you which case you're in, and stops to confirm an accidental downgrade.
METADATA_URL="${DEPLOY_METADATA_URL:-$DEFAULT_METADATA_URL}"
SERVER_VERSION=""
if [ -n "$METADATA_URL" ]; then
	SERVER_VERSION="$(curl -fsS --max-time 10 "$METADATA_URL" 2>/dev/null \
		| sed -n 's/.*"version"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -n1 || true)"
fi
if [ -n "$SERVER_VERSION" ]; then
	if [ "$SERVER_VERSION" = "$VERSION" ]; then
		echo
		echo "note: the server already advertises v${VERSION} — this is a SAME-version re-upload."
		echo "      It repairs the package for NEW installs only; sites already on v${VERSION} will"
		echo "      NOT see an update. Bump the version if you need existing installs to update."
	elif [ "$(printf '%s\n%s\n' "$VERSION" "$SERVER_VERSION" | sort -V | tail -n1)" = "$SERVER_VERSION" ]; then
		echo
		echo "warning: local v${VERSION} is LOWER than the server's v${SERVER_VERSION} — this is a downgrade." >&2
		printf "Upload anyway? [y/N] "
		read -r reply </dev/tty 2>/dev/null || reply=""
		case "$reply" in
			[yY]*) ;;
			*) echo "Aborted — server keeps v${SERVER_VERSION}."; exit 1 ;;
		esac
	else
		echo
		echo "Publishing v${VERSION} (server currently advertises v${SERVER_VERSION})."
	fi
elif [ -z "$METADATA_URL" ]; then
	echo
	echo "note: no metadata URL configured — skipping remote version check."
fi

echo
echo "Uploading to ${TARGET} (SFTP, port ${PORT})…"

SCP_OPTS=(-P "$PORT" -o StrictHostKeyChecking=accept-new)

if [ -n "${DEPLOY_SSH_KEY:-}" ]; then
	# Explicit on-disk private key.
	scp "${SCP_OPTS[@]}" -i "$DEPLOY_SSH_KEY" "$OUT" "$TARGET"
elif [ -n "${DEPLOY_PASS:-}" ]; then
	# Password auth via sshpass.
	if ! command -v sshpass >/dev/null 2>&1; then
		echo "error: DEPLOY_PASS is set but 'sshpass' is not installed." >&2
		echo "       Install it (brew install hudochenkov/sshpass/sshpass) or use an SSH key (DEPLOY_SSH_KEY)." >&2
		exit 1
	fi
	sshpass -p "$DEPLOY_PASS" scp "${SCP_OPTS[@]}" "$OUT" "$TARGET"
else
	# Neither set — rely on the ssh-agent (e.g. 1Password) configured in
	# ~/.ssh/config. May surface an interactive approval prompt (Touch ID).
	scp "${SCP_OPTS[@]}" "$OUT" "$TARGET"
fi

echo "Uploaded ${REMOTE_FILENAME} — the update server now advertises v${VERSION}."
