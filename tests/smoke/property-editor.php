<?php

declare(strict_types=1);

$GLOBALS['wla_editor_duplicate_owner'] = null;

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		unset($domain);
		return $text;
	}
}
if (!function_exists('_x')) {
	function _x($text, $context, $domain = 'default')
	{
		unset($context, $domain);
		return $text;
	}
}
if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value)
	{
		return trim(strip_tags((string) $value));
	}
}
if (!function_exists('sanitize_textarea_field')) {
	function sanitize_textarea_field($value)
	{
		return trim(strip_tags((string) $value));
	}
}
if (!function_exists('sanitize_key')) {
	function sanitize_key($value)
	{
		return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)) ?? '';
	}
}
if (!function_exists('absint')) {
	function absint($value)
	{
		return abs((int) $value);
	}
}
if (!function_exists('get_posts')) {
	function get_posts($args = array())
	{
		unset($args);
		$owner = $GLOBALS['wla_editor_duplicate_owner'];
		return $owner === null ? array() : array((int) $owner);
	}
}

$root = dirname(__DIR__, 2) . '/plugin/wla-inmo/src/';
require_once $root . 'Properties/Sanitizer.php';
require_once $root . 'Properties/Capabilities.php';
require_once $root . 'Properties/PostType.php';
require_once $root . 'Properties/MetaSchema.php';
require_once $root . 'Properties/Validator.php';
require_once $root . 'Taxonomies/Registry.php';
require_once $root . 'Admin/PropertyEditor.php';

use WLA\Inmo\Admin\PropertyEditor;
use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;

function wlaPropertyEditorExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$controls = PropertyEditor::controls();
$sections = PropertyEditor::sections();
$editable = PropertyEditor::editableFields();

wlaPropertyEditorExpect(count($sections) === 12, 'Guided editor must expose the documented 12 sections.');
wlaPropertyEditorExpect(count($controls) === 34, 'Guided editor control set changed unexpectedly.');
wlaPropertyEditorExpect(count($editable) === count(array_unique($editable)), 'Editable field list must not contain duplicates.');

$sectionFields = array();
foreach ($sections as $section) {
	foreach (($section['fields'] ?? array()) as $field) {
		$sectionFields[] = (string) $field;
	}
}
sort($sectionFields);
$sortedEditable = $editable;
sort($sortedEditable);
wlaPropertyEditorExpect($sectionFields === $sortedEditable, 'Every editor field must belong to exactly one guided section.');

foreach ($editable as $field) {
	wlaPropertyEditorExpect(MetaSchema::metaKey($field) !== null, "Editor field {$field} must exist in canonical MetaSchema.");
}

wlaPropertyEditorExpect(!isset($controls['gallery_ids']), 'Gallery management belongs to PR 2.4, not the guided editor core.');
wlaPropertyEditorExpect(!isset($controls['video_urls']), 'Video management belongs to PR 2.4, not the guided editor core.');
wlaPropertyEditorExpect(($controls['external_id']['private'] ?? false) === true, 'External ID must be visually marked private.');
wlaPropertyEditorExpect(($controls['private_address']['private'] ?? false) === true, 'Exact address must be visually marked private.');
wlaPropertyEditorExpect(($controls['internal_notes']['private'] ?? false) === true, 'Internal notes must be visually marked private.');

wlaPropertyEditorExpect(PropertyEditor::useBlockEditor(true, PostType::POST_TYPE) === false, 'Property editor should use the focused native classic edit screen.');
wlaPropertyEditorExpect(PropertyEditor::useBlockEditor(true, 'post') === true, 'Editor mode must not affect unrelated post types.');

$clean = PropertyEditor::sanitizeFields(
	array(
		'property_code' => '  COD-001  ',
		'currency_primary' => 'uf',
		'price_clp' => '390000000',
		'featured' => '1',
		'unknown_field' => '<script>bad</script>',
	)
);
wlaPropertyEditorExpect(($clean['property_code'] ?? '') === 'COD-001', 'Property code must use canonical text sanitizer.');
wlaPropertyEditorExpect(($clean['currency_primary'] ?? '') === 'UF', 'Currency must use canonical currency sanitizer.');
wlaPropertyEditorExpect(($clean['price_clp'] ?? null) === 390000000, 'CLP price must use canonical integer sanitizer.');
wlaPropertyEditorExpect(($clean['featured'] ?? null) === true, 'Boolean controls must use canonical boolean sanitizer.');
wlaPropertyEditorExpect(!array_key_exists('unknown_field', $clean), 'Unknown submitted fields must be ignored.');

$invalid = PropertyEditor::validateSubmission(array('price_clp' => '-1', 'latitude' => '91'), array(), 100);
wlaPropertyEditorExpect(($invalid['price_clp'] ?? '') === 'invalid_non_negative_integer', 'Negative CLP price must be rejected before persistence.');
wlaPropertyEditorExpect(($invalid['latitude'] ?? '') === 'invalid_latitude', 'Out-of-range latitude must be rejected before persistence.');

$GLOBALS['wla_editor_duplicate_owner'] = 88;
$duplicate = PropertyEditor::validateSubmission(array('property_code' => 'DUP-001'), array(), 100);
wlaPropertyEditorExpect(($duplicate['property_code'] ?? '') === 'duplicate_property_code', 'Duplicate property code must be rejected before persistence.');
wlaPropertyEditorExpect(PropertyEditor::duplicateCodeOwner('DUP-001', 100) === 88, 'Duplicate guard must return the conflicting property ID.');

$GLOBALS['wla_editor_duplicate_owner'] = null;
wlaPropertyEditorExpect(PropertyEditor::duplicateCodeOwner('UNIQUE-001', 100) === null, 'Unique property code must pass duplicate guard.');

echo "WLA Inmo guided property editor smoke tests passed.\n";
