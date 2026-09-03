#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PLUGIN_DIR="$ROOT_DIR/plugin/wla-inmo"
DIST_DIR="$ROOT_DIR/dist"
PLUGIN_FILE="$PLUGIN_DIR/wla-inmo.php"

for command in php composer zip; do
	if ! command -v "$command" >/dev/null 2>&1; then
		echo "Missing required build command: $command" >&2
		exit 1
	fi
done

if [[ ! -f "$PLUGIN_FILE" ]]; then
	echo "Plugin bootstrap not found: $PLUGIN_FILE" >&2
	exit 1
fi

VERSION="$(php -r '$c=file_get_contents($argv[1]); preg_match("/^[[:space:]]*\\*[[:space:]]*Version:[[:space:]]*(.+)$/mi", $c, $m); echo isset($m[1]) ? trim($m[1]) : "";' "$PLUGIN_FILE")"

if [[ -z "$VERSION" ]]; then
	echo "Unable to read plugin version from $PLUGIN_FILE" >&2
	exit 1
fi

STAGE_ROOT="$(mktemp -d)"
trap 'rm -rf "$STAGE_ROOT"' EXIT
STAGE_PLUGIN="$STAGE_ROOT/wla-inmo"

mkdir -p "$STAGE_PLUGIN" "$DIST_DIR"
cp -R "$PLUGIN_DIR/." "$STAGE_PLUGIN/"
rm -rf "$STAGE_PLUGIN/vendor"

composer install \
	--working-dir="$STAGE_PLUGIN" \
	--no-dev \
	--prefer-dist \
	--no-interaction \
	--no-progress \
	--optimize-autoloader

find "$STAGE_PLUGIN" -name '.DS_Store' -delete

ZIP_PATH="$DIST_DIR/wla-inmo-$VERSION.zip"
rm -f "$ZIP_PATH"

(
	cd "$STAGE_ROOT"
	zip -qr "$ZIP_PATH" wla-inmo
)

echo "$ZIP_PATH"
