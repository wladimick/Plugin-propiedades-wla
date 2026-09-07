<?php

namespace WLA\Inmo\Import;

use Generator;
use JsonException as NativeJsonException;
use SplFileObject;

final class JsonLinesReader
{
	private const HASH_CHUNK_BYTES = 1048576;
	private const DEFAULT_MAX_ROWS = 10000;
	public const DEFAULT_MAX_LINE_BYTES = 2097152;
	private const DECODE_DEPTH = 8;

	private int $maxRows;
	private int $maxLineBytes;

	public function __construct(int $maxRows = self::DEFAULT_MAX_ROWS, int $maxLineBytes = self::DEFAULT_MAX_LINE_BYTES)
	{
		if ($maxRows < 1 || $maxLineBytes < 1) {
			throw new \InvalidArgumentException('Normalized JSON limits must be positive integers.');
		}

		$this->maxRows = $maxRows;
		$this->maxLineBytes = $maxLineBytes;
	}

	/**
	 * Read normalized NDJSON from a durable byte checkpoint.
	 *
	 * @return Generator<int,array{row_number:int,data:array<string,mixed>,next_offset:int}>
	 */
	public function verifiedRows(
		string $path,
		string $expectedHash,
		int $startOffset = 0,
		int $startDataRow = 0
	): Generator {
		if ($startOffset < 0 || $startDataRow < 0 || $startDataRow > $this->maxRows) {
			throw new JsonException('invalid_resume_cursor', 'JSON resume cursor is invalid.');
		}
		if ($startDataRow > 0 && $startOffset === 0) {
			throw new JsonException('resume_offset_missing', 'JSON resume offset is missing.');
		}
		if ($path === '' || !is_file($path) || !is_readable($path)) {
			throw new JsonException('source_unreadable', 'Normalized JSON source is not readable.');
		}

		$file = new SplFileObject($path, 'rb');
		if (!$file->flock(LOCK_SH)) {
			throw new JsonException('source_lock_failed', 'Normalized JSON source could not be locked for reading.');
		}

		try {
			$this->verifyHash($file, $expectedHash);
			if ($startOffset > 0 && $file->fseek($startOffset) !== 0) {
				throw new JsonException('invalid_resume_offset', 'JSON resume offset is outside the data area.');
			}

			$dataRows = $startDataRow;
			while (!$file->eof()) {
				$offsetBefore = $file->ftell();
				if ($offsetBefore === false) {
					throw new JsonException('source_offset_failed', 'JSON source position could not be read.');
				}

				$line = $file->fgets();
				$nextOffset = $file->ftell();
				if ($nextOffset === false) {
					throw new JsonException('source_offset_failed', 'JSON source position could not be read.');
				}
				if ($line === '') {
					if ($file->eof()) {
						break;
					}
					continue;
				}
				if (strlen($line) > $this->maxLineBytes) {
					throw new JsonException('line_limit_exceeded', 'Normalized JSON row exceeds the byte limit.', $dataRows + 1);
				}

				$line = trim($line);
				if ($line === '') {
					continue;
				}

				++$dataRows;
				if ($dataRows > $this->maxRows) {
					throw new JsonException('row_limit_exceeded', 'Normalized JSON row limit exceeded.', $dataRows);
				}

				try {
					$row = json_decode($line, true, self::DECODE_DEPTH, JSON_THROW_ON_ERROR);
				} catch (NativeJsonException) {
					throw new JsonException('source_parse_failed', 'Normalized JSON row is malformed.', $dataRows);
				}

				if (!is_array($row) || array_is_list($row)) {
					throw new JsonException('source_row_invalid', 'Normalized JSON row must be a non-empty object.', $dataRows);
				}
				if ((int) $nextOffset <= (int) $offsetBefore) {
					throw new JsonException('invalid_source_offset', 'Normalized JSON source did not advance.', $dataRows);
				}

				yield $dataRows => array(
					'row_number' => $dataRows,
					'data'        => $row,
					'next_offset' => (int) $nextOffset,
				);
			}
		} finally {
			$file->flock(LOCK_UN);
		}
	}

	private function verifyHash(SplFileObject $file, string $expectedHash): void
	{
		$expectedHash = strtolower(trim($expectedHash));
		if (preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1) {
			throw new JsonException('source_hash_failed', 'Normalized JSON source hash is invalid.');
		}

		$before = $file->fstat();
		$context = hash_init('sha256');
		$file->rewind();

		while (!$file->eof()) {
			$chunk = $file->fread(self::HASH_CHUNK_BYTES);
			if ($chunk === '') {
				if ($file->eof()) {
					break;
				}
				continue;
			}
			hash_update($context, $chunk);
		}

		$actualHash = hash_final($context);
		$after = $file->fstat();
		$file->rewind();

		if (!$this->sameFileState($before, $after)) {
			throw new JsonException('source_changed_during_validation', 'Normalized JSON source changed during validation.');
		}
		if (!hash_equals($expectedHash, $actualHash)) {
			throw new JsonException('source_hash_mismatch', 'Normalized JSON source hash does not match the confirmed batch.');
		}
	}

	/**
	 * @param array<string|int,mixed> $before File state before hashing.
	 * @param array<string|int,mixed> $after File state after hashing.
	 */
	private function sameFileState(array $before, array $after): bool
	{
		foreach (array('dev', 'ino', 'size', 'mtime') as $field) {
			if (isset($before[$field], $after[$field]) && (string) $before[$field] !== (string) $after[$field]) {
				return false;
			}
		}

		return true;
	}
}
