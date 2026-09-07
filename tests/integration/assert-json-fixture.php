<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Import\JsonDocumentReader;
use WLA\Inmo\Import\JsonLinesReader;

$workspace = getenv('GITHUB_WORKSPACE');
if (!is_string($workspace) || $workspace === '') {
	fwrite(STDERR, "FAIL: GITHUB_WORKSPACE is unavailable.\n");
	exit(1);
}

$fixture = $workspace . '/tests/fixtures/json/wla-v1-minimal.json';
if (!is_file($fixture)) {
	fwrite(STDERR, "FAIL: versioned JSON v1 fixture is missing.\n");
	exit(1);
}

$normalized = sys_get_temp_dir() . '/wla-json-fixture-' . substr(str_replace('-', '', wp_generate_uuid4()), 0, 8) . '.ndjson';

try {
	$inspection = (new JsonDocumentReader())->normalizeToNdjson($fixture, $normalized);
	$rows = iterator_to_array((new JsonLinesReader())->verifiedRows($normalized, (string) $inspection['source_hash']));
} catch (Throwable $exception) {
	@unlink($normalized);
	fwrite(STDERR, 'FAIL: versioned JSON v1 fixture is not accepted: ' . $exception->getMessage() . "\n");
	exit(1);
}

if (
	(int) $inspection['format_version'] !== 1
	|| (string) $inspection['source_key'] !== 'fixture_wla_v1'
	|| count($rows) !== 1
	|| ($rows[1]['data']['meta_property_code'] ?? '') !== 'FIXTURE-V1-001'
	|| ($rows[1]['data']['post_title'] ?? '') !== 'Casa Ñuñoa — Fixture v1'
) {
	@unlink($normalized);
	fwrite(STDERR, "FAIL: versioned JSON v1 fixture contract mismatch.\n");
	exit(1);
}

@unlink($normalized);
echo "WLA Inmo versioned JSON v1 fixture passed.\n";
