<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Import\BatchRepository;
use WLA\Inmo\Import\BatchRunResult;
use WLA\Inmo\Import\BatchRunner;
use WLA\Inmo\Import\BatchStatus;
use WLA\Inmo\Import\DryRunEngine;
use WLA\Inmo\Import\DryRunResult;
use WLA\Inmo\Import\IdentityRepository;
use WLA\Inmo\Import\JsonDocumentReader;
use WLA\Inmo\Import\JsonExporter;
use WLA\Inmo\Import\JsonLinesReader;
use WLA\Inmo\Import\MappingProfile;
use WLA\Inmo\Import\MappingProfileCodec;
use WLA\Inmo\Import\WordPressJsonExportSource;
use WLA\Inmo\Import\WordPressTaxonomyLookup;

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};
$expect = static function (bool $condition, string $message) use ($fail): void {
	if (!$condition) {
		$fail($message);
	}
};
$confirm = static function (BatchRepository $batches, string $uuid) use ($fail): void {
	foreach (array(BatchStatus::MAPPED, BatchStatus::VALIDATED, BatchStatus::DRY_RUN_READY, BatchStatus::CONFIRMED) as $revision => $status) {
		if (!$batches->transition($uuid, $status, $revision)) {
			$fail('Unable to confirm JSON batch.');
		}
	}
};

$suffix = substr(str_replace('-', '', wp_generate_uuid4()), 0, 8);
$sourceKey = 'json_ci_' . strtolower($suffix);
$code = 'JSON-' . strtoupper($suffix);
$externalId = 'EXT-' . strtoupper($suffix);
$input = sys_get_temp_dir() . '/wla-json-' . $suffix . '.json';
$normalized = sys_get_temp_dir() . '/wla-json-' . $suffix . '.ndjson';
$exportedPath = sys_get_temp_dir() . '/wla-json-export-' . $suffix . '.json';
$roundTrip = sys_get_temp_dir() . '/wla-json-round-' . $suffix . '.ndjson';

$fixture = array(
	'format_version' => 1,
	'source_key' => $sourceKey,
	'properties' => array(array(
		'post' => array('title' => 'Casa JSON CI ' . $suffix),
		'meta' => array(
			'property_code' => $code,
			'external_id' => $externalId,
			'price_clp' => 123456789,
			'status' => 'available',
			'private_address' => 'Privada CI',
			'internal_notes' => 'Nota CI',
		),
	)),
);
$expect(file_put_contents($input, wp_json_encode($fixture)) !== false, 'Unable to write JSON fixture.');

$inspection = (new JsonDocumentReader())->normalizeToNdjson($input, $normalized);
$expect((int) $inspection['total_rows'] === 1, 'JSON normalization count mismatch.');
$expect(($inspection['mapping']['meta_property_code'] ?? '') === 'meta.property_code', 'JSON canonical mapping missing.');

$profile = new MappingProfile($sourceKey, $inspection['mapping'], 'JSON CI');
$identity = new IdentityRepository();
$factory = static function () use ($normalized, $inspection): iterable {
	return (new JsonLinesReader())->verifiedRows($normalized, (string) $inspection['source_hash']);
};
$dry = iterator_to_array((new DryRunEngine($profile, $identity->resolver(), array(WordPressTaxonomyLookup::class, 'lookup')))->results($factory), false);
$expect(count($dry) === 1 && $dry[0]->status() === DryRunResult::STATUS_NEW, 'JSON dry-run classification failed.');
$expect($identity->findPropertyIdByCode($code) === null, 'JSON dry-run mutated catalogue.');

$batches = new BatchRepository();
$uuid = $batches->create($sourceKey, (string) $inspection['source_hash'], MappingProfileCodec::encode($profile), 1, get_current_user_id(), null, 'json');
$expect(is_string($uuid) && $uuid !== '', 'JSON batch creation failed.');
$confirm($batches, $uuid);
$run = (new BatchRunner())->run($uuid, $normalized, 25, 60.0);
$expect($run->status() === BatchRunResult::STATUS_COMPLETED, 'JSON batch did not complete.');
$batch = $batches->find($uuid);
$expect($batch !== null && $batch['source_format'] === 'json' && (int) $batch['cursor_offset'] > 0, 'JSON batch format/checkpoint missing.');

$propertyId = $identity->findPropertyIdByCode($code);
$expect(is_int($propertyId) && $propertyId > 0, 'JSON property was not created.');
$expect(get_post_meta($propertyId, '_wla_inmo_external_id', true) === $externalId, 'JSON private identity was not imported.');

$export = (new JsonExporter(new WordPressJsonExportSource(array('draft'))))->export($exportedPath, 'wla_export_ci', 10);
$expect($export['count'] >= 1, 'JSON export returned no properties.');
$document = json_decode((string) file_get_contents($exportedPath), true, 16, JSON_THROW_ON_ERROR);
$match = null;
foreach ((array) ($document['properties'] ?? array()) as $property) {
	if (is_array($property) && ($property['meta']['property_code'] ?? '') === $code) {
		$match = $property;
		break;
	}
}
$expect(is_array($match), 'Imported property missing from JSON export.');
$meta = is_array($match['meta'] ?? null) ? $match['meta'] : array();
$expect(!isset($meta['external_id']) && !isset($meta['private_address']) && !isset($meta['internal_notes']), 'Private fields leaked into JSON export.');

$round = (new JsonDocumentReader())->normalizeToNdjson($exportedPath, $roundTrip);
$rows = iterator_to_array((new JsonLinesReader())->verifiedRows($roundTrip, (string) $round['source_hash']));
$found = false;
foreach ($rows as $row) {
	if (($row['data']['meta_property_code'] ?? '') === $code) {
		$found = true;
		break;
	}
}
$expect($found, 'JSON export did not round-trip through canonical reader.');

wp_delete_post($propertyId, true);
foreach (array($input, $normalized, $exportedPath, $roundTrip) as $path) {
	if (is_file($path)) {
		unlink($path);
	}
}

echo "WLA Inmo JSON WLA integration passed.\n";
