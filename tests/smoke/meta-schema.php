<?php

declare(strict_types=1);

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		return $text;
	}
}

if (!function_exists('register_post_meta')) {
	function register_post_meta($postType, $metaKey, $args = array())
	{
		$GLOBALS['wla_inmo_smoke_meta'][$metaKey] = array(
			'post_type' => $postType,
			'args'      => $args,
		);

		return true;
	}
}

if (!function_exists('current_user_can')) {
	function current_user_can($capability, ...$args)
	{
		$GLOBALS['wla_inmo_smoke_current_user_can'] = array($capability, $args);
		return $capability === 'edit_post' && isset($args[0]) && (int) $args[0] === 55;
	}
}

require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Properties/PostType.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Properties/Sanitizer.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Properties/Validator.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Properties/MetaSchema.php';

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Properties\Sanitizer;
use WLA\Inmo\Properties\Validator;

function wlaMetaSmokeExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$definitions = MetaSchema::definitions();

wlaMetaSmokeExpect(count($definitions) === 37, 'Canonical Phase 1.4 schema field count changed unexpectedly.');

foreach (array('operation', 'property_type', 'region', 'commune', 'sector') as $duplicatedTaxonomyField) {
	wlaMetaSmokeExpect(!isset($definitions[$duplicatedTaxonomyField]), "$duplicatedTaxonomyField must remain a taxonomy, not post meta.");
}

$metaKeys = array();

foreach ($definitions as $field => $definition) {
	wlaMetaSmokeExpect($definition['field'] === $field, "$field domain key mismatch.");
	wlaMetaSmokeExpect(str_starts_with($definition['meta_key'], MetaSchema::META_PREFIX), "$field storage key must be namespaced.");
	wlaMetaSmokeExpect(is_callable($definition['sanitize_callback']), "$field sanitizer must be callable.");
	wlaMetaSmokeExpect(in_array($definition['type'], array('string', 'boolean', 'integer', 'number', 'array'), true), "$field has unsupported meta type.");
	$metaKeys[] = $definition['meta_key'];
}

wlaMetaSmokeExpect(count($metaKeys) === count(array_unique($metaKeys)), 'Canonical storage meta keys must be unique.');
wlaMetaSmokeExpect(MetaSchema::metaKey('property_code') === '_wla_inmo_property_code', 'property_code storage key changed unexpectedly.');
wlaMetaSmokeExpect(MetaSchema::metaKey('does_not_exist') === null, 'Unknown domain field must not generate a meta key.');

$privateFields = MetaSchema::privateFields();
$publicFields = MetaSchema::publicFields();

foreach (array('external_id', 'private_address', 'home_order', 'indexable', 'internal_notes') as $privateField) {
	wlaMetaSmokeExpect(in_array($privateField, $privateFields, true), "$privateField must remain internal/private by default.");
	wlaMetaSmokeExpect(!in_array($privateField, $publicFields, true), "$privateField leaked into public field contract.");
}

foreach (array('property_code', 'status', 'price_clp', 'public_address', 'land_area_m2', 'gallery_ids', 'last_verified_date') as $publicField) {
	wlaMetaSmokeExpect(in_array($publicField, $publicFields, true), "$publicField must remain eligible for public presentation.");
}

MetaSchema::register();
$registered = $GLOBALS['wla_inmo_smoke_meta'] ?? array();

wlaMetaSmokeExpect(count($registered) === count($definitions), 'Every canonical field must be registered with WordPress.');

foreach ($definitions as $field => $definition) {
	$metaKey = $definition['meta_key'];
	wlaMetaSmokeExpect(isset($registered[$metaKey]), "$field was not registered.");
	wlaMetaSmokeExpect($registered[$metaKey]['post_type'] === PostType::POST_TYPE, "$field must only register on wla_property.");
	wlaMetaSmokeExpect($registered[$metaKey]['args']['single'] === true, "$field must be single-value meta.");
	wlaMetaSmokeExpect($registered[$metaKey]['args']['show_in_rest'] === false, "$field must not be exposed as raw public REST meta in Phase 1.4.");
	wlaMetaSmokeExpect($registered[$metaKey]['args']['type'] === $definition['type'], "$field registered type mismatch.");
}

wlaMetaSmokeExpect(MetaSchema::authorize(false, '_wla_inmo_property_code', 55) === true, 'Editable property meta must authorize through edit_post capability.');
wlaMetaSmokeExpect(MetaSchema::authorize(true, '_wla_inmo_property_code', 0) === false, 'Meta authorization must reject missing property IDs.');

wlaMetaSmokeExpect(Sanitizer::text(" <b>Casa</b>\n amplia ") === 'Casa amplia', 'Text sanitizer must remove markup and normalize whitespace.');
wlaMetaSmokeExpect(Sanitizer::key('Disponible Premium') === 'disponiblepremium', 'Key sanitizer fallback contract changed unexpectedly.');
wlaMetaSmokeExpect(Sanitizer::boolean('sí') === true, 'Spanish affirmative boolean must be accepted.');
wlaMetaSmokeExpect(Sanitizer::boolean('false') === false, 'False string must remain false.');
wlaMetaSmokeExpect(Sanitizer::nonNegativeInteger('12') === 12, 'Integer sanitizer must accept normalized integer strings.');
wlaMetaSmokeExpect(Sanitizer::nonNegativeInteger('-1') === null, 'Negative integer must be rejected.');
wlaMetaSmokeExpect(Sanitizer::nonNegativeNumber('1610.5') === 1610.5, 'Number sanitizer must accept normalized decimals.');
wlaMetaSmokeExpect(Sanitizer::nonNegativeNumber('-0.1') === null, 'Negative number must be rejected.');
wlaMetaSmokeExpect(Sanitizer::currency('uf') === 'UF', 'Currency sanitizer must normalize supported currency.');
wlaMetaSmokeExpect(Sanitizer::currency('EUR') === '', 'Unsupported currency must be rejected.');
wlaMetaSmokeExpect(Sanitizer::date('2028-02-29') === '2028-02-29', 'Valid leap date must be accepted.');
wlaMetaSmokeExpect(Sanitizer::date('2027-02-29') === '', 'Invalid calendar date must be rejected.');
wlaMetaSmokeExpect(Sanitizer::latitude('-33.4489') === -33.4489, 'Valid latitude must be accepted.');
wlaMetaSmokeExpect(Sanitizer::latitude('91') === null, 'Latitude outside range must be rejected.');
wlaMetaSmokeExpect(Sanitizer::longitude('-70.6693') === -70.6693, 'Valid longitude must be accepted.');
wlaMetaSmokeExpect(Sanitizer::longitude('-181') === null, 'Longitude outside range must be rejected.');
wlaMetaSmokeExpect(Sanitizer::positiveIntegerArray(array(3, '2', 3, 0, -1, 'x')) === array(3, 2), 'Gallery sanitizer must keep unique positive attachment IDs.');
wlaMetaSmokeExpect(Sanitizer::httpUrl('https://example.com/video.mp4') !== '', 'HTTPS media URL must be accepted.');
wlaMetaSmokeExpect(Sanitizer::httpUrl('javascript:alert(1)') === '', 'Unsafe media URL scheme must be rejected.');

$valid = Validator::validate(array(
	'property_code'      => '001254',
	'status'             => 'disponible',
	'currency_primary'   => 'CLP',
	'price_clp'          => 390000000,
	'price_uf'           => 9500.25,
	'land_area_m2'       => 1610.5,
	'bedrooms'           => 0,
	'latitude'           => -34.9828,
	'longitude'          => -71.2394,
	'availability_date'  => '2026-09-03',
	'construction_year'  => 2026,
	'gallery_ids'        => array(10, 11),
	'video_urls'         => array('https://example.com/video'),
));

wlaMetaSmokeExpect($valid === array(), 'Valid canonical property values must pass validation.');

$invalid = Validator::validate(array(
	'currency_primary'  => 'EUR',
	'price_clp'         => -10,
	'land_area_m2'      => '-1.5',
	'latitude'          => 91,
	'longitude'         => -181,
	'availability_date' => '2026-02-30',
	'construction_year' => 999,
	'gallery_ids'       => array(0),
	'video_urls'        => array('ftp://example.com/video'),
));

foreach (array('currency_primary', 'price_clp', 'land_area_m2', 'latitude', 'longitude', 'availability_date', 'construction_year', 'gallery_ids', 'video_urls') as $invalidField) {
	wlaMetaSmokeExpect(isset($invalid[$invalidField]), "$invalidField invalid value must be reported.");
}

echo "WLA Inmo canonical meta schema smoke tests passed.\n";
