#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ZIP_PATH="${1:-}"

if [[ -z "$ZIP_PATH" || ! -f "$ZIP_PATH" ]]; then
	echo "Frontend smoke requires an existing release ZIP path." >&2
	exit 1
fi

for command in php unzip grep; do
	if ! command -v "$command" >/dev/null 2>&1; then
		echo "Missing frontend smoke command: $command" >&2
		exit 1
	fi
done

STAGE_ROOT="$(mktemp -d)"
trap 'rm -rf "$STAGE_ROOT"' EXIT
unzip -q "$ZIP_PATH" -d "$STAGE_ROOT"
PLUGIN_DIR="$STAGE_ROOT/wla-inmo"

required_files=(
	"src/Frontend/TemplateResolver.php"
	"src/Frontend/Renderer.php"
	"src/Frontend/Bootstrap.php"
	"src/Frontend/Assets.php"
	"templates/archive-property.php"
	"templates/single-property.php"
	"assets/css/frontend.css"
)

for relative in "${required_files[@]}"; do
	if [[ ! -f "$PLUGIN_DIR/$relative" ]]; then
		echo "Missing frontend release file: $relative" >&2
		exit 1
	fi
done

if [[ -f "$PLUGIN_DIR/assets/js/frontend.js" ]]; then
	echo "PR 4.1 must not ship a placeholder frontend JavaScript file." >&2
	exit 1
fi

php -l "$PLUGIN_DIR/src/Frontend/TemplateResolver.php" >/dev/null
php -l "$PLUGIN_DIR/src/Frontend/Renderer.php" >/dev/null
php -l "$PLUGIN_DIR/src/Frontend/Bootstrap.php" >/dev/null
php -l "$PLUGIN_DIR/src/Frontend/Assets.php" >/dev/null
php -l "$PLUGIN_DIR/templates/archive-property.php" >/dev/null
php -l "$PLUGIN_DIR/templates/single-property.php" >/dev/null

php -r "require '$PLUGIN_DIR/vendor/autoload.php'; foreach (['WLA\\Inmo\\Frontend\\TemplateResolver','WLA\\Inmo\\Frontend\\Renderer','WLA\\Inmo\\Frontend\\Bootstrap','WLA\\Inmo\\Frontend\\Assets'] as \$class) { if (!class_exists(\$class)) { fwrite(STDERR, 'Frontend Composer autoload failed for '.\$class.'\\n'); exit(1); } }"

for archive_contract in 'have_posts' 'get_permalink' 'get_the_excerpt' 'the_posts_pagination'; do
	if ! grep -q "$archive_contract" "$PLUGIN_DIR/templates/archive-property.php"; then
		echo "Archive fallback lost Issue #74 contract: $archive_contract" >&2
		exit 1
	fi
done

for single_contract in 'get_the_title' 'get_the_content'; do
	if ! grep -q "$single_contract" "$PLUGIN_DIR/templates/single-property.php"; then
		echo "Single fallback lost Issue #74 contract: $single_contract" >&2
		exit 1
	fi
done

if grep -RIEq 'private_address|internal_notes|external_id|get_post_meta[[:space:]]*\(' "$PLUGIN_DIR/templates" "$PLUGIN_DIR/src/Frontend"; then
	echo "Frontend foundation references private or arbitrary property meta." >&2
	exit 1
fi

if grep -RIEq 'jquery|woocommerce|elementor|wpcode|get_field[[:space:]]*\(' "$PLUGIN_DIR/src/Frontend" "$PLUGIN_DIR/templates" "$PLUGIN_DIR/assets/css/frontend.css"; then
	echo "Frontend foundation contains a forbidden legacy/runtime dependency." >&2
	exit 1
fi

php -r '
$css = file_get_contents($argv[1]);
if (!is_string($css) || $css === "") { fwrite(STDERR, "Frontend CSS missing.\n"); exit(1); }
if (preg_match("/(^|})\\s*(html|body|h[1-6]|a|button|input|select|textarea|\\*)\\b/im", $css)) { fwrite(STDERR, "Unscoped global frontend selector found.\n"); exit(1); }
' "$PLUGIN_DIR/assets/css/frontend.css"

echo "WLA Inmo frontend release smoke tests passed: $ZIP_PATH"
