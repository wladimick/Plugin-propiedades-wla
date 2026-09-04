#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ZIP_PATH="${1:-}"

if [[ -z "$ZIP_PATH" ]]; then
	ZIP_PATH="$(bash "$ROOT_DIR/bin/build-plugin.sh")"
fi

if [[ ! -f "$ZIP_PATH" ]]; then
	echo "ZIP not found: $ZIP_PATH" >&2
	exit 1
fi

for command in php unzip grep; do
	if ! command -v "$command" >/dev/null 2>&1; then
		echo "Missing smoke-test command: $command" >&2
		exit 1
	fi
done

STAGE_ROOT="$(mktemp -d)"
trap 'rm -rf "$STAGE_ROOT"' EXIT
unzip -q "$ZIP_PATH" -d "$STAGE_ROOT"
PLUGIN_DIR="$STAGE_ROOT/wla-inmo"

required_files=(
	"wla-inmo.php"
	"uninstall.php"
	"vendor/autoload.php"
	"src/Core/Plugin.php"
	"src/Core/Requirements.php"
	"src/Core/Activator.php"
	"src/Core/Deactivator.php"
	"src/Core/Installer.php"
	"src/Access/Capabilities.php"
	"src/Access/RoleMatrix.php"
	"src/Access/RoleManager.php"
	"src/Admin/Bootstrap.php"
	"src/Admin/ScreenRegistry.php"
	"src/Admin/Menu.php"
	"src/Admin/PageRenderer.php"
	"src/Admin/Assets.php"
	"src/Admin/ContextHelp.php"
	"src/Admin/PropertyList.php"
	"src/Admin/PropertyEditor.php"
	"assets/admin/admin.css"
	"src/Localization/ChilePreset.php"
	"src/Settings/Schema.php"
	"src/Settings/Repository.php"
	"src/Settings/Registry.php"
	"src/Frontend/TemplateResolver.php"
	"src/Properties/PostType.php"
	"src/Properties/Capabilities.php"
	"src/Properties/MetaSchema.php"
	"src/Properties/Sanitizer.php"
	"src/Properties/Validator.php"
	"src/Taxonomies/Registry.php"
	"src/Taxonomies/Capabilities.php"
	"src/Search/IndexSchema.php"
	"src/Search/Projection.php"
	"src/Search/IndexRepository.php"
	"src/Search/Indexer.php"
	"src/Search/Rebuilder.php"
)

for relative in "${required_files[@]}"; do
	if [[ ! -f "$PLUGIN_DIR/$relative" ]]; then
		echo "Missing required release file: $relative" >&2
		exit 1
	fi
done

while IFS= read -r -d '' php_file; do
	php -l "$php_file" >/dev/null
done < <(find "$PLUGIN_DIR" -type f -name '*.php' -print0)

php -r "require '$PLUGIN_DIR/vendor/autoload.php'; foreach (['WLA\\Inmo\\Core\\Requirements','WLA\\Inmo\\Core\\Installer','WLA\\Inmo\\Access\\Capabilities','WLA\\Inmo\\Access\\RoleMatrix','WLA\\Inmo\\Access\\RoleManager','WLA\\Inmo\\Admin\\Bootstrap','WLA\\Inmo\\Admin\\ScreenRegistry','WLA\\Inmo\\Admin\\Menu','WLA\\Inmo\\Admin\\PageRenderer','WLA\\Inmo\\Admin\\Assets','WLA\\Inmo\\Admin\\ContextHelp','WLA\\Inmo\\Admin\\PropertyList','WLA\\Inmo\\Admin\\PropertyEditor','WLA\\Inmo\\Localization\\ChilePreset','WLA\\Inmo\\Settings\\Schema','WLA\\Inmo\\Settings\\Repository','WLA\\Inmo\\Settings\\Registry','WLA\\Inmo\\Frontend\\TemplateResolver','WLA\\Inmo\\Properties\\PostType','WLA\\Inmo\\Properties\\Capabilities','WLA\\Inmo\\Properties\\MetaSchema','WLA\\Inmo\\Properties\\Sanitizer','WLA\\Inmo\\Properties\\Validator','WLA\\Inmo\\Taxonomies\\Registry','WLA\\Inmo\\Taxonomies\\Capabilities','WLA\\Inmo\\Search\\IndexSchema','WLA\\Inmo\\Search\\Projection','WLA\\Inmo\\Search\\IndexRepository','WLA\\Inmo\\Search\\Indexer','WLA\\Inmo\\Search\\Rebuilder'] as \$class) { if (!class_exists(\$class)) { fwrite(STDERR, 'Composer autoload failed for '.\$class.'\\n'); exit(1); } }"

if grep -RIEq 'wc_get_|WooCommerce|Elementor|WPCode|get_field[[:space:]]*\(|product_cat' "$PLUGIN_DIR/src" "$PLUGIN_DIR/wla-inmo.php"; then
	echo "Forbidden legacy runtime dependency reference found in WLA Inmo core." >&2
	exit 1
fi

if grep -RIEq "add_cap[[:space:]]*\([[:space:]]*['\"]manage_options['\"]" "$PLUGIN_DIR/src/Access"; then
	echo "Custom WLA roles must not be granted manage_options." >&2
	exit 1
fi

echo "WLA Inmo release ZIP smoke tests passed: $ZIP_PATH"
