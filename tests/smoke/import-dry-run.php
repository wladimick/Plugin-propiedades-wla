<?php

declare(strict_types=1);

if (!defined('ABSPATH')) {
	define('ABSPATH', dirname(__DIR__, 2) . '/');
}

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		return $text;
	}
}

require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/includes/autoload.php';

use WLA\Inmo\Import\DryRunEngine;
use WLA\Inmo\Import\DryRunResult;
use WLA\Inmo\Import\DryRunSummary;
use WLA\Inmo\Import\IdentityResolver;
use WLA\Inmo\Import\MappingProfile;

function wlaDryRunExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$profile = new MappingProfile(
	'portal_a',
	array(
		'titulo' => 'post.title',
		'external_id' => 'meta.external_id',
		'codigo' => 'meta.property_code',
		'precio' => 'meta.price_clp',
		'operacion' => 'taxonomy.operation',
		'features' => 'taxonomy.feature',
	),
	'Portal A',
	MappingProfile::EMPTY_PRESERVE,
	array('features' => '|')
);

$resolver = new IdentityResolver(
	static fn (string $sourceKey, string $externalId): array => $sourceKey === 'portal_a' && $externalId === 'EXT-2' ? array(22) : array(),
	static fn (string $propertyCode): array => $propertyCode === 'COD-2' ? array(22) : array()
);

$taxonomyLookup = static function (string $taxonomy, string $value): array {
	$terms = array(
		'wla_operation:Venta' => array(array('id' => 1, 'slug' => 'venta')),
		'wla_feature:Piscina' => array(array('id' => 2, 'slug' => 'piscina')),
	);

	return $terms[$taxonomy . ':' . $value] ?? array();
};

$rows = array(
	array('row_number' => 2, 'data' => array('titulo' => 'Casa nueva', 'external_id' => 'EXT-1', 'codigo' => 'COD-1', 'precio' => '100', 'operacion' => 'Venta', 'features' => 'Piscina')),
	array('row_number' => 3, 'data' => array('titulo' => 'Casa existente', 'external_id' => 'EXT-2', 'codigo' => 'COD-2', 'precio' => '200', 'operacion' => 'Venta', 'features' => 'Piscina')),
);
$factoryCalls = 0;
$factory = static function () use (&$factoryCalls, $rows): iterable {
	++$factoryCalls;
	return $rows;
};

$engine = new DryRunEngine(
	$profile,
	$resolver,
	$taxonomyLookup,
	static fn (int $propertyId): array => $propertyId === 22 ? array('meta.price_clp' => 150) : array()
);
$summary = new DryRunSummary();
$statuses = array();
foreach ($engine->results($factory) as $result) {
	$summary->consume($result);
	$statuses[] = $result->status();
}

wlaDryRunExpect($factoryCalls === 2, 'Dry-run must use deterministic two-pass iteration.');
wlaDryRunExpect($statuses === array(DryRunResult::STATUS_NEW, DryRunResult::STATUS_UPDATE), 'Dry-run classification mismatch.');
wlaDryRunExpect($summary->toArray() === array('new' => 1, 'update' => 1, 'warnings' => 0, 'errors' => 0, 'skipped' => 0), 'Dry-run summary mismatch.');

echo "WLA Inmo mapping and dry-run smoke tests passed.\n";
