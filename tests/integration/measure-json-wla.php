<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Import\JsonDocumentReader;
use WLA\Inmo\Import\JsonLinesReader;

$size = (int) getenv('WLA_JSON_DATASET_SIZE');
if (!in_array($size, array(100, 1000, 5000), true)) {
	fwrite(STDERR, "FAIL: WLA_JSON_DATASET_SIZE must be 100, 1000 or 5000.\n");
	exit(1);
}

$suffix = substr(str_replace('-', '', wp_generate_uuid4()), 0, 8);
$input = sys_get_temp_dir() . '/wla-json-benchmark-' . $size . '-' . $suffix . '.json';
$normalized = sys_get_temp_dir() . '/wla-json-benchmark-' . $size . '-' . $suffix . '.ndjson';
$handle = fopen($input, 'xb');
if ($handle === false) {
	fwrite(STDERR, "FAIL: unable to create benchmark JSON fixture.\n");
	exit(1);
}

$write = static function ($stream, string $bytes): void {
	if ($bytes !== '' && fwrite($stream, $bytes) !== strlen($bytes)) {
		fclose($stream);
		fwrite(STDERR, "FAIL: unable to write benchmark JSON fixture.\n");
		exit(1);
	}
};

$write($handle, '{"format_version":1,"source_key":"benchmark_' . $size . '","properties":[');
for ($index = 1; $index <= $size; ++$index) {
	$property = array(
		'post' => array(
			'title' => 'Propiedad benchmark ' . $index,
			'content' => 'Descripción sintética UTF-8 Ñuñoa número ' . $index,
		),
		'meta' => array(
			'property_code' => sprintf('BENCH-%05d', $index),
			'price_clp' => 100000000 + $index,
			'status' => 'available',
		),
	);
	$encoded = json_encode($property, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
	$write($handle, ($index > 1 ? ',' : '') . $encoded);
}
$write($handle, ']}');
fclose($handle);

clearstatcache(true, $input);
$inputBytes = filesize($input);
if ($inputBytes === false) {
	@unlink($input);
	fwrite(STDERR, "FAIL: benchmark input size unavailable.\n");
	exit(1);
}

if (function_exists('gc_collect_cycles')) {
	gc_collect_cycles();
}
$baselineMemory = memory_get_usage(true);
$startedAt = microtime(true);

try {
	$inspection = (new JsonDocumentReader())->normalizeToNdjson($input, $normalized);
	$count = 0;
	foreach ((new JsonLinesReader())->verifiedRows($normalized, (string) $inspection['source_hash']) as $row) {
		if ((int) $row['row_number'] !== $count + 1) {
			throw new RuntimeException('Normalized benchmark row sequence is not monotonic.');
		}
		++$count;
	}
} catch (Throwable $exception) {
	@unlink($input);
	@unlink($normalized);
	fwrite(STDERR, 'FAIL: JSON benchmark failed: ' . $exception->getMessage() . "\n");
	exit(1);
}

$elapsedMs = (microtime(true) - $startedAt) * 1000;
$peakMemory = memory_get_peak_usage(true);
$peakDelta = max(0, $peakMemory - $baselineMemory);
clearstatcache(true, $normalized);
$normalizedBytes = filesize($normalized);

if ($count !== $size || (int) $inspection['total_rows'] !== $size || $normalizedBytes === false) {
	@unlink($input);
	@unlink($normalized);
	fwrite(STDERR, "FAIL: benchmark row count or output size mismatch.\n");
	exit(1);
}

$result = array(
	'dataset_rows' => $size,
	'input_bytes' => (int) $inputBytes,
	'normalized_bytes' => (int) $normalizedBytes,
	'elapsed_ms' => round($elapsedMs, 2),
	'baseline_memory_bytes' => $baselineMemory,
	'peak_memory_bytes' => $peakMemory,
	'peak_delta_bytes' => $peakDelta,
	'php_version' => PHP_VERSION,
	'wordpress_version' => get_bloginfo('version'),
);

echo wp_json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

@unlink($input);
@unlink($normalized);
