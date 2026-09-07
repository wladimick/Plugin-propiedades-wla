<?php

namespace WLA\Inmo\Import;

use JsonException as NativeJsonException;

final class JsonExporter
{
	private const DEFAULT_PAGE_SIZE = 100;
	private const MAX_PAGE_SIZE = 250;
	private const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

	private JsonExportSourceInterface $source;

	public function __construct(JsonExportSourceInterface $source)
	{
		$this->source = $source;
	}

	/**
	 * Stream a WLA JSON v1 document without holding the full catalogue in memory.
	 *
	 * @return array{count:int,sha256:string,bytes:int}
	 */
	public function export(string $path, string $sourceKey, int $pageSize = self::DEFAULT_PAGE_SIZE): array
	{
		$sourceKey = SourceKey::normalize($sourceKey);
		if (!SourceKey::isValid($sourceKey)) {
			throw new \InvalidArgumentException('Invalid JSON export source key.');
		}

		$pageSize = max(1, min(self::MAX_PAGE_SIZE, $pageSize));
		if ($path === '' || file_exists($path)) {
			throw new JsonException('export_path_invalid', 'JSON export destination is invalid.');
		}

		$handle = fopen($path, 'xb');
		if ($handle === false) {
			throw new JsonException('export_open_failed', 'JSON export destination could not be created.');
		}

		$count = 0;
		$page = 1;
		$first = true;

		try {
			$this->write($handle, '{"format_version":' . JsonDocumentReader::FORMAT_VERSION);
			$this->write($handle, ',"source_key":' . $this->encode($sourceKey));
			$this->write($handle, ',"exported_at":' . $this->encode(gmdate('c')));
			$this->write($handle, ',"properties":[');

			while (true) {
				$properties = $this->source->page($page, $pageSize);
				if ($properties === array()) {
					break;
				}
				if (count($properties) > $pageSize) {
					throw new JsonException('export_page_unbounded', 'JSON export source returned an oversized page.');
				}

				foreach ($properties as $property) {
					if (!is_array($property) || array_is_list($property)) {
						throw new JsonException('export_property_invalid', 'JSON export source returned an invalid property.');
					}
					if (!$first) {
						$this->write($handle, ',');
					}
					$this->write($handle, $this->encode($property));
					$first = false;
					++$count;
				}

				if (count($properties) < $pageSize) {
					break;
				}
				++$page;
			}

			$this->write($handle, ']}');
			if (!fflush($handle)) {
				throw new JsonException('export_flush_failed', 'JSON export could not be finalized.');
			}
		} catch (\Throwable $exception) {
			fclose($handle);
			@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Remove incomplete plugin-created export.
			throw $exception;
		}

		fclose($handle);
		$hash = hash_file('sha256', $path);
		$bytes = filesize($path);
		if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1 || $bytes === false) {
			@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid export artifact must not survive.
			throw new JsonException('export_hash_failed', 'JSON export checksum could not be generated.');
		}

		return array('count' => $count, 'sha256' => $hash, 'bytes' => (int) $bytes);
	}

	/** @param resource $handle */
	private function write($handle, string $bytes): void
	{
		if ($bytes !== '' && fwrite($handle, $bytes) !== strlen($bytes)) {
			throw new JsonException('export_write_failed', 'JSON export could not be written.');
		}
	}

	private function encode(mixed $value): string
	{
		try {
			$encoded = json_encode($value, self::ENCODE_FLAGS);
		} catch (NativeJsonException) {
			throw new JsonException('export_encode_failed', 'JSON export contains a value that cannot be encoded.');
		}

		if (!is_string($encoded)) {
			throw new JsonException('export_encode_failed', 'JSON export contains a value that cannot be encoded.');
		}

		return $encoded;
	}
}
