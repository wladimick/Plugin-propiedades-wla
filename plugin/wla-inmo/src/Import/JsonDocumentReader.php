<?php

namespace WLA\Inmo\Import;

use JsonException as NativeJsonException;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Parser exceptions are internal control-flow messages and are never rendered by this class.
final class JsonDocumentReader
{
	public const FORMAT_VERSION = 1;
	private const DEFAULT_MAX_BYTES = 10485760;
	private const DEFAULT_MAX_PROPERTIES = 10000;
	private const DEFAULT_MAX_DEPTH = 16;
	private const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

	private int $maxBytes;
	private int $maxProperties;
	private int $maxDepth;

	/** @var callable(string):bool */
	private $targetAllowed;

	/** @var callable(string):bool */
	private $targetMultiple;

	/**
	 * @param callable(string):bool|null $targetAllowed Target allowlist seam for tests.
	 * @param callable(string):bool|null $targetMultiple Multiple-value target seam for tests.
	 */
	public function __construct(
		int $maxBytes = self::DEFAULT_MAX_BYTES,
		int $maxProperties = self::DEFAULT_MAX_PROPERTIES,
		int $maxDepth = self::DEFAULT_MAX_DEPTH,
		?callable $targetAllowed = null,
		?callable $targetMultiple = null
	) {
		if ($maxBytes < 1 || $maxProperties < 1 || $maxDepth < 4) {
			throw new \InvalidArgumentException('JSON limits are invalid.');
		}

		$this->maxBytes = $maxBytes;
		$this->maxProperties = $maxProperties;
		$this->maxDepth = $maxDepth;
		$this->targetAllowed = $targetAllowed ?? array(TargetRegistry::class, 'isAllowed');
		$this->targetMultiple = $targetMultiple ?? array(TargetRegistry::class, 'isMultiple');
	}

	/**
	 * Validate a WLA JSON document and materialize a server-controlled NDJSON source.
	 *
	 * Source headers in NDJSON are normalized independently from canonical WLA
	 * targets. The returned mapping is therefore ready for MappingProfile without
	 * bypassing HeaderNormalizer or TargetRegistry.
	 *
	 * @return array{
	 *   format_version:int,
	 *   source_key:string,
	 *   original_hash:string,
	 *   source_hash:string,
	 *   total_rows:int,
	 *   mapping:array<string,string>,
	 *   headers:array<int,string>,
	 *   exported_at:string
	 * }
	 */
	public function normalizeToNdjson(string $jsonPath, string $ndjsonPath): array
	{
		[$payload, $originalHash] = $this->readLocked($jsonPath);
		$document = $this->decodeDocument($payload);
		$sourceKey = $this->sourceKey($document);
		$properties = $this->properties($document);
		$exportedAt = $this->exportedAt($document);
		$mapping = array();
		$handle = $this->openOutput($ndjsonPath);
		$written = 0;

		try {
			foreach ($properties as $index => $property) {
				$rowNumber = $index + 1;
				$canonicalRow = $this->flattenProperty($property, $rowNumber);
				$sourceRow = array();

				foreach ($canonicalRow as $target => $value) {
					$header = HeaderNormalizer::normalize($target);
					if ($header === '') {
						throw new JsonException('invalid_source_header', 'JSON target could not be normalized to a source header.', $rowNumber);
					}
					if (isset($mapping[$header]) && $mapping[$header] !== $target) {
						throw new JsonException('source_header_collision', 'JSON targets collide after source-header normalization.', $rowNumber);
					}

					$mapping[$header] = $target;
					$sourceRow[$header] = $value;
				}

				try {
					$encoded = json_encode($sourceRow, self::ENCODE_FLAGS);
				} catch (NativeJsonException) {
					throw new JsonException('normalized_encode_failed', 'Normalized JSON row could not be encoded.', $rowNumber);
				}
				if (!is_string($encoded)) {
					throw new JsonException('normalized_encode_failed', 'Normalized JSON row could not be encoded.', $rowNumber);
				}

				$line = $encoded . "\n";
				if (fwrite($handle, $line) !== strlen($line)) {
					throw new JsonException('normalized_write_failed', 'Normalized JSON source could not be written.', $rowNumber);
				}
				++$written;
			}
		} catch (\Throwable $exception) {
			fclose($handle);
			@unlink($ndjsonPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup of plugin-created incomplete source.
			throw $exception;
		}

		if (!fflush($handle)) {
			fclose($handle);
			@unlink($ndjsonPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup of plugin-created incomplete source.
			throw new JsonException('normalized_flush_failed', 'Normalized JSON source could not be finalized.');
		}
		fclose($handle);

		if ($written < 1 || $mapping === array()) {
			@unlink($ndjsonPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Empty normalized source must not survive validation.
			throw new JsonException('empty_properties', 'JSON document must contain at least one property.');
		}

		$sourceHash = hash_file('sha256', $ndjsonPath);
		if (!is_string($sourceHash) || preg_match('/^[a-f0-9]{64}$/', $sourceHash) !== 1) {
			@unlink($ndjsonPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Hash failure invalidates generated source.
			throw new JsonException('source_hash_failed', 'Normalized JSON source hash could not be generated.');
		}

		return array(
			'format_version' => self::FORMAT_VERSION,
			'source_key'     => $sourceKey,
			'original_hash'  => $originalHash,
			'source_hash'    => $sourceHash,
			'total_rows'     => $written,
			'mapping'        => $mapping,
			'headers'        => array_keys($mapping),
			'exported_at'    => $exportedAt,
		);
	}

	/**
	 * @return array{0:string,1:string}
	 */
	private function readLocked(string $path): array
	{
		if ($path === '' || !is_file($path) || !is_readable($path)) {
			throw new JsonException('unreadable_file', 'JSON file is not readable.');
		}

		$handle = fopen($path, 'rb');
		if ($handle === false || !flock($handle, LOCK_SH)) {
			if (is_resource($handle)) {
				fclose($handle);
			}
			throw new JsonException('source_lock_failed', 'JSON source could not be locked for reading.');
		}

		try {
			$before = fstat($handle);
			$context = hash_init('sha256');
			$payload = '';

			while (!feof($handle)) {
				$remaining = ($this->maxBytes + 1) - strlen($payload);
				if ($remaining < 1) {
					throw new JsonException('file_too_large', 'JSON file exceeds the allowed byte limit.');
				}

				$chunk = fread($handle, min(1048576, $remaining));
				if ($chunk === false) {
					throw new JsonException('source_read_failed', 'JSON source could not be read.');
				}
				if ($chunk === '') {
					break;
				}
				$payload .= $chunk;
				hash_update($context, $chunk);
				if (strlen($payload) > $this->maxBytes) {
					throw new JsonException('file_too_large', 'JSON file exceeds the allowed byte limit.');
				}
			}

			$after = fstat($handle);
			if (!$this->sameFileState($before, $after)) {
				throw new JsonException('source_changed_during_validation', 'JSON source changed during validation.');
			}
			if ($payload === '') {
				throw new JsonException('empty_file', 'JSON file is empty.');
			}

			return array($payload, hash_final($context));
		} finally {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	/** @return array<string,mixed> */
	private function decodeDocument(string $payload): array
	{
		try {
			$document = json_decode($payload, true, $this->maxDepth, JSON_THROW_ON_ERROR);
		} catch (NativeJsonException) {
			throw new JsonException('malformed_json', 'JSON document is malformed or exceeds the supported depth.');
		}

		if (!is_array($document) || array_is_list($document)) {
			throw new JsonException('invalid_root', 'JSON root must be an object.');
		}

		$allowed = array('format_version', 'source_key', 'exported_at', 'properties');
		foreach (array_keys($document) as $key) {
			if (!in_array((string) $key, $allowed, true)) {
				throw new JsonException('unknown_root_key', 'JSON root contains an unknown key.');
			}
		}

		if (!isset($document['format_version']) || !is_int($document['format_version'])) {
			throw new JsonException('missing_format_version', 'JSON format_version is required.');
		}
		if ($document['format_version'] !== self::FORMAT_VERSION) {
			throw new JsonException('unsupported_format_version', 'JSON format_version is not supported.');
		}

		return $document;
	}

	/** @param array<string,mixed> $document Decoded document. */
	private function sourceKey(array $document): string
	{
		$value = $document['source_key'] ?? null;
		if (!is_string($value)) {
			throw new JsonException('missing_source_key', 'JSON source_key is required.');
		}

		$normalized = SourceKey::normalize($value);
		if (!SourceKey::isValid($normalized)) {
			throw new JsonException('invalid_source_key', 'JSON source_key is invalid.');
		}

		return $normalized;
	}

	/**
	 * @param array<string,mixed> $document Decoded document.
	 * @return array<int,array<string,mixed>>
	 */
	private function properties(array $document): array
	{
		$properties = $document['properties'] ?? null;
		if (!is_array($properties) || !array_is_list($properties)) {
			throw new JsonException('invalid_properties', 'JSON properties must be an array.');
		}
		if ($properties === array()) {
			throw new JsonException('empty_properties', 'JSON document must contain at least one property.');
		}
		if (count($properties) > $this->maxProperties) {
			throw new JsonException('property_limit_exceeded', 'JSON property limit exceeded.');
		}

		foreach ($properties as $index => $property) {
			if (!is_array($property) || array_is_list($property)) {
				throw new JsonException('invalid_property', 'Each JSON property must be an object.', $index + 1);
			}
		}

		/** @var array<int,array<string,mixed>> $properties */
		return $properties;
	}

	/**
	 * @param array<string,mixed> $property Decoded property.
	 * @return array<string,mixed>
	 */
	private function flattenProperty(array $property, int $rowNumber): array
	{
		$allowedSections = array('post', 'meta', 'taxonomies');
		foreach (array_keys($property) as $section) {
			if (!in_array((string) $section, $allowedSections, true)) {
				throw new JsonException('unknown_property_section', 'JSON property contains an unknown section.', $rowNumber);
			}
		}

		$row = array();
		$this->flattenSection($row, $property['post'] ?? array(), 'post', $rowNumber);
		$this->flattenSection($row, $property['meta'] ?? array(), 'meta', $rowNumber);
		$this->flattenSection($row, $property['taxonomies'] ?? array(), 'taxonomy', $rowNumber);

		if ($row === array()) {
			throw new JsonException('empty_property', 'JSON property contains no importable values.', $rowNumber);
		}

		return $row;
	}

	/**
	 * @param array<string,mixed> $row Flattened canonical row being built.
	 * @param mixed               $section Raw JSON section.
	 */
	private function flattenSection(array &$row, mixed $section, string $prefix, int $rowNumber): void
	{
		if (!is_array($section) || ($section !== array() && array_is_list($section))) {
			throw new JsonException('invalid_property_section', 'JSON property section must be an object.', $rowNumber);
		}

		foreach ($section as $field => $value) {
			$field = trim((string) $field);
			if ($field === '') {
				throw new JsonException('invalid_property_key', 'JSON property contains an invalid field name.', $rowNumber);
			}

			$target = $prefix . '.' . $field;
			if (!(($this->targetAllowed)($target))) {
				throw new JsonException('unknown_target', 'JSON property contains an unsupported canonical target.', $rowNumber);
			}

			$this->assertPortableValue($target, $value, $rowNumber);
			$row[$target] = $value;
		}
	}

	private function assertPortableValue(string $target, mixed $value, int $rowNumber): void
	{
		if (is_array($value)) {
			if (!(($this->targetMultiple)($target)) || !array_is_list($value)) {
				throw new JsonException('invalid_target_value', 'JSON target contains an unsupported structured value.', $rowNumber);
			}

			foreach ($value as $item) {
				if (!is_scalar($item) && $item !== null) {
					throw new JsonException('invalid_target_value', 'JSON multiple target contains a non-scalar value.', $rowNumber);
				}
			}
			return;
		}

		if (!is_scalar($value) && $value !== null) {
			throw new JsonException('invalid_target_value', 'JSON target contains a non-scalar value.', $rowNumber);
		}
	}

	/** @param array<string,mixed> $document Decoded document. */
	private function exportedAt(array $document): string
	{
		$value = $document['exported_at'] ?? '';
		if ($value === '') {
			return '';
		}
		if (!is_string($value) || strlen($value) > 64) {
			throw new JsonException('invalid_exported_at', 'JSON exported_at metadata is invalid.');
		}

		return trim($value);
	}

	/** @return resource */
	private function openOutput(string $path)
	{
		if ($path === '' || file_exists($path)) {
			throw new JsonException('normalized_path_invalid', 'Normalized JSON destination is invalid.');
		}

		$handle = fopen($path, 'xb');
		if ($handle === false) {
			throw new JsonException('normalized_open_failed', 'Normalized JSON source could not be created.');
		}

		return $handle;
	}

	/**
	 * @param array<string|int,mixed>|false $before File state before reading.
	 * @param array<string|int,mixed>|false $after File state after reading.
	 */
	private function sameFileState(array|false $before, array|false $after): bool
	{
		if (!is_array($before) || !is_array($after)) {
			return false;
		}

		foreach (array('dev', 'ino', 'size', 'mtime') as $field) {
			if (isset($before[$field], $after[$field]) && (string) $before[$field] !== (string) $after[$field]) {
				return false;
			}
		}

		return true;
	}
}
