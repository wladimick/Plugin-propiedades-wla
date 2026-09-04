<?php

declare(strict_types=1);

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		unset($domain);
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

$root = dirname(__DIR__, 2) . '/plugin/wla-inmo/src/';
require_once $root . 'Properties/Sanitizer.php';
require_once $root . 'Quality/Evaluator.php';
require_once $root . 'Quality/Schema.php';

use WLA\Inmo\Quality\Evaluator;
use WLA\Inmo\Quality\Schema;

function wlaQualityExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$description = str_repeat('Descripción inmobiliaria útil para completar correctamente la ficha. ', 3);
$perfect = Evaluator::evaluateSnapshot(
	array(
		'property_code' => 'QA-100',
		'operation_count' => 1,
		'type_count' => 1,
		'commune_count' => 1,
		'locality' => '',
		'public_address' => '',
		'price_on_request' => false,
		'price_clp' => 250000000,
		'price_uf' => '',
		'price_usd' => '',
		'land_area_m2' => 500,
		'built_area_m2' => 180,
		'usable_area_m2' => 160,
		'description' => $description,
		'featured_image_id' => 11,
		'image_ids' => array(11, 12, 13),
		'image_alt' => array(11 => 'Fachada', 12 => 'Living', 13 => 'Patio'),
		'last_verified_date' => '2026-09-04',
	)
);

wlaQualityExpect(($perfect['score'] ?? null) === 100, 'A property passing every documented check must score 100.');
wlaQualityExpect(($perfect['is_complete'] ?? null) === 1, 'A 100% property must be marked complete.');
wlaQualityExpect(($perfect['missing_codes'] ?? null) === '', 'Complete property must not expose missing codes.');
wlaQualityExpect(($perfect['has_price'] ?? null) === 1, 'Positive canonical price must satisfy price quality.');
wlaQualityExpect(($perfect['has_image'] ?? null) === 1, 'Featured image must satisfy main image quality.');

$empty = Evaluator::evaluateSnapshot(array());
wlaQualityExpect(($empty['score'] ?? null) === 0, 'An empty property snapshot must score 0.');
wlaQualityExpect(($empty['is_complete'] ?? null) === 0, 'An empty property must remain incomplete without blocking storage.');
wlaQualityExpect(count((array) ($empty['checks'] ?? array())) === 11, 'Phase 2.5 must expose exactly the documented 11 initial quality checks.');
wlaQualityExpect(count(explode(',', (string) ($empty['missing_codes'] ?? ''))) === 11, 'Empty property must explain every missing check.');

$onRequest = Evaluator::evaluateSnapshot(
	array(
		'price_on_request' => true,
		'locality' => 'Curicó',
		'built_area_m2' => 90,
	)
);
wlaQualityExpect(($onRequest['checks']['price'] ?? false) === true, 'Price on request must be a valid catalogue-quality state.');
wlaQualityExpect(($onRequest['checks']['location'] ?? false) === true, 'Locality must satisfy the sufficient-location quality check.');
wlaQualityExpect(($onRequest['checks']['surface'] ?? false) === true, 'Any approved positive surface must satisfy the surface check.');

$missingAlt = $perfect;
$missingAlt = Evaluator::evaluateSnapshot(
	array(
		'property_code' => 'QA-ALT',
		'operation_count' => 1,
		'type_count' => 1,
		'commune_count' => 1,
		'price_clp' => 1,
		'land_area_m2' => 1,
		'description' => $description,
		'featured_image_id' => 11,
		'image_ids' => array(11, 12, 13),
		'image_alt' => array(11 => 'Fachada', 12 => '', 13 => 'Patio'),
		'last_verified_date' => '2026-09-04',
	)
);
wlaQualityExpect(($missingAlt['checks']['image_alt'] ?? true) === false, 'One missing ALT must fail the explainable ALT check.');
wlaQualityExpect(str_contains((string) $missingAlt['missing_codes'], 'image_alt'), 'Missing ALT must be represented by a stable missing code.');
wlaQualityExpect((int) $missingAlt['score'] < 100, 'A failed quality check must reduce the score.');

$definitions = Evaluator::definitions();
wlaQualityExpect(count($definitions) === 11, 'Each quality check must have one human explanation.');
wlaQualityExpect(!array_key_exists('seo', $definitions), 'Phase 2.5 must not invent an SEO score before the real SEO module exists.');

$schemaSource = Schema::sql(new class {
	public string $prefix = 'wp_';
	public function get_charset_collate(): string
	{
		return '';
	}
});
wlaQualityExpect(strpos($schemaSource, 'private_address') === false, 'Quality projection must never copy private address.');
wlaQualityExpect(strpos($schemaSource, 'internal_notes') === false, 'Quality projection must never copy internal notes.');
wlaQualityExpect(strpos($schemaSource, 'missing_codes') !== false, 'Quality projection must persist only stable missing-check codes for explanations.');

echo "WLA Inmo catalogue quality smoke tests passed.\n";
