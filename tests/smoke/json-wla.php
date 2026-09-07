<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$documentPath = $root . '/plugin/wla-inmo/src/Import/JsonDocumentReader.php';
$linesPath = $root . '/plugin/wla-inmo/src/Import/JsonLinesReader.php';
$exceptionPath = $root . '/plugin/wla-inmo/src/Import/JsonException.php';

function wlaJsonSmokeExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$document = file_get_contents($documentPath);
$lines = file_get_contents($linesPath);
$exception = file_get_contents($exceptionPath);

wlaJsonSmokeExpect(is_string($document), 'JsonDocumentReader source missing.');
wlaJsonSmokeExpect(is_string($lines), 'JsonLinesReader source missing.');
wlaJsonSmokeExpect(is_string($exception), 'JsonException source missing.');
wlaJsonSmokeExpect(str_contains($document, 'public const FORMAT_VERSION = 1'), 'WLA JSON v1 contract is not explicit.');
wlaJsonSmokeExpect(str_contains($document, 'JSON_THROW_ON_ERROR'), 'JSON decoding is not strict.');
wlaJsonSmokeExpect(str_contains($document, 'MAX_PROPERTIES') || str_contains($document, 'DEFAULT_MAX_PROPERTIES'), 'JSON property count is not bounded.');
wlaJsonSmokeExpect(str_contains($document, 'DEFAULT_MAX_BYTES'), 'JSON bytes are not bounded.');
wlaJsonSmokeExpect(str_contains($document, 'unknown_root_key'), 'Unknown root keys are not rejected.');
wlaJsonSmokeExpect(str_contains($document, 'unknown_target'), 'Canonical target allowlist is not enforced.');
wlaJsonSmokeExpect(str_contains($document, 'TargetRegistry::class'), 'JSON does not reuse the canonical TargetRegistry.');
wlaJsonSmokeExpect(str_contains($document, "'source_hash'"), 'Normalized source SHA-256 is not exposed to the batch pipeline.');
wlaJsonSmokeExpect(str_contains($document, "fopen(\$path, 'xb')"), 'Normalized source creation can overwrite an existing path.');
wlaJsonSmokeExpect(!preg_match('/unserialize\s*\(/i', $document), 'JSON path must not use PHP unserialize.');
wlaJsonSmokeExpect(!preg_match('/wp_remote_|curl_|XMLHttpRequest|axios/i', $document), 'JSON validation must not perform remote requests.');
wlaJsonSmokeExpect(str_contains($lines, 'flock(LOCK_SH)'), 'Normalized JSON reader does not hold a shared lock.');
wlaJsonSmokeExpect(str_contains($lines, 'hash_equals'), 'Normalized JSON reader does not bind execution to the confirmed hash.');
wlaJsonSmokeExpect(str_contains($lines, "'next_offset'"), 'Normalized JSON reader does not expose a durable byte checkpoint.');
wlaJsonSmokeExpect(str_contains($lines, 'startOffset'), 'Normalized JSON reader cannot resume from byte offset.');

echo "WLA Inmo JSON foundation smoke tests passed.\n";
