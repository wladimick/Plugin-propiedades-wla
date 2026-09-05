<?php

namespace WLA\Inmo\Import;

use Generator;
use SplFileObject;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Parser exceptions use static messages plus internal numeric row metadata; no exception is rendered in this class.
final class CsvReader
{
	private const UTF8_BOM = "\xEF\xBB\xBF";
	private const DETECTION_BYTES = 65536;
	private const HASH_CHUNK_BYTES = 1048576;

	private int $maxRows;

	private int $maxColumns;

	private int $maxCellBytes;

	private ?string $delimiter;

	public function __construct(
		int $maxRows = 10000,
		int $maxColumns = 100,
		int $maxCellBytes = 65535,
		?string $delimiter = null
	) {
		if ($maxRows < 1 || $maxColumns < 1 || $maxCellBytes < 1) {
			throw new \InvalidArgumentException('CSV limits must be positive integers.');
		}

		if ($delimiter !== null && !in_array($delimiter, self::supportedDelimiters(), true)) {
			throw new \InvalidArgumentException('Unsupported CSV delimiter.');
		}

		$this->maxRows = $maxRows;
		$this->maxColumns = $maxColumns;
		$this->maxCellBytes = $maxCellBytes;
		$this->delimiter = $delimiter;
	}

	/**
	 * Read data rows incrementally.
	 *
	 * @return Generator<int,array{row_number:int,data:array<string,string>}>
	 */
	public function rows(string $path): Generator
	{
		$this->assertReadableFile($path);
		$delimiter = $this->delimiter ?? $this->detectDelimiter($path);
		$file = new SplFileObject($path, 'rb');
		$file->setFlags(SplFileObject::READ_CSV | SplFileObject::DROP_NEW_LINE);
		$file->setCsvControl($delimiter, '"', '\\');

		$headers = null;
		$recordNumber = 0;
		$dataRows = 0;

		while (!$file->eof()) {
			$row = $file->fgetcsv();
			++$recordNumber;

			if (!is_array($row) || $row === array(null) || self::isEmptyRow($row)) {
				continue;
			}

			$values = $this->normalizeCells($row, $recordNumber);

			if ($headers === null) {
				$headers = $this->normalizeHeaders($values, $recordNumber);
				continue;
			}

			if (count($values) > count($headers)) {
				throw new CsvException(
					'column_count_mismatch',
					'CSV row has more columns than the header.',
					$recordNumber
				);
			}

			if (count($values) < count($headers)) {
				$values = array_pad($values, count($headers), '');
			}

			++$dataRows;
			if ($dataRows > $this->maxRows) {
				throw new CsvException(
					'row_limit_exceeded',
					'CSV row limit exceeded.',
					$recordNumber
				);
			}

			$data = array_combine($headers, $values);

			yield $dataRows => array(
				'row_number' => $recordNumber,
				'data'       => $data,
			);
		}

		if ($headers === null) {
			throw new CsvException('missing_header', 'CSV file does not contain a usable header row.');
		}
	}

	/**
	 * Read a confirmed CSV from a durable byte checkpoint.
	 *
	 * The shared lock, SHA-256 verification and row iteration all use the same
	 * SplFileObject handle. Replacing the pathname after the handle is opened
	 * therefore cannot swap in unverified bytes for the current run.
	 *
	 * `startDataRow` is the number of data rows already checkpointed. For
	 * resumed batches, `startOffset` must point immediately after that row.
	 *
	 * @return Generator<int,array{row_number:int,data:array<string,string>,next_offset:int}>
	 */
	public function verifiedRows(
		string $path,
		string $expectedHash,
		int $startOffset = 0,
		int $startDataRow = 0
	): Generator {
		if ($startOffset < 0 || $startDataRow < 0 || $startDataRow > $this->maxRows) {
			throw new CsvException('invalid_resume_cursor', 'CSV resume cursor is invalid.');
		}

		if ($startDataRow > 0 && $startOffset === 0) {
			throw new CsvException('resume_offset_missing', 'CSV resume offset is missing.');
		}

		if ($path === '' || !is_file($path) || !is_readable($path)) {
			throw new CsvException('source_unreadable', 'CSV source is not readable.');
		}

		$file = new SplFileObject($path, 'rb');
		if (!$file->flock(LOCK_SH)) {
			throw new CsvException('source_lock_failed', 'CSV source could not be locked for reading.');
		}

		try {
			$this->verifyHash($file, $expectedHash);
			$delimiter = $this->delimiter ?? $this->detectDelimiterFromFile($file);
			$file->setFlags(SplFileObject::DROP_NEW_LINE);
			$file->setCsvControl($delimiter, '"', '\\');

			[$headers, $headerOffset] = $this->readHeadersFromFile($file);
			$effectiveOffset = $startOffset > 0 ? $startOffset : $headerOffset;

			if ($effectiveOffset < $headerOffset || $file->fseek($effectiveOffset) !== 0) {
				throw new CsvException('invalid_resume_offset', 'CSV resume offset is outside the data area.');
			}

			$dataRows = $startDataRow;
			while (!$file->eof()) {
				$row = $file->fgetcsv();
				$nextOffset = $file->ftell();
				if ($nextOffset === false) {
					throw new CsvException('source_offset_failed', 'CSV source position could not be read.');
				}

				if (!is_array($row) || $row === array(null) || self::isEmptyRow($row)) {
					continue;
				}

				$rowNumber = $dataRows + 2;
				$values = $this->normalizeCells($row, $rowNumber);
				if (count($values) > count($headers)) {
					throw new CsvException(
						'column_count_mismatch',
						'CSV row has more columns than the header.',
						$rowNumber
					);
				}

				if (count($values) < count($headers)) {
					$values = array_pad($values, count($headers), '');
				}

				++$dataRows;
				if ($dataRows > $this->maxRows) {
					throw new CsvException('row_limit_exceeded', 'CSV row limit exceeded.', $rowNumber);
				}

				$data = array_combine($headers, $values);
				yield $dataRows => array(
					'row_number' => $rowNumber,
					'data' => $data,
					'next_offset' => (int) $nextOffset,
				);
			}
		} finally {
			$file->flock(LOCK_UN);
		}
	}

	/**
	 * @return array<int,string>
	 */
	public static function supportedDelimiters(): array
	{
		return array(',', ';', "\t");
	}

	private function assertReadableFile(string $path): void
	{
		if ($path === '' || !is_file($path) || !is_readable($path)) {
			throw new CsvException('unreadable_file', 'CSV file is not readable.');
		}
	}

	private function detectDelimiter(string $path): string
	{
		$handle = fopen($path, 'rb');
		if ($handle === false) {
			throw new CsvException('unreadable_file', 'CSV file is not readable.');
		}

		$sample = null;
		$sampleRow = 0;

		while (!feof($handle)) {
			$candidate = fgets($handle, self::DETECTION_BYTES);
			++$sampleRow;
			if (!is_string($candidate)) {
				break;
			}

			$candidate = self::stripBom($candidate);
			$this->assertUtf8($candidate, $sampleRow);
			if (trim($candidate) === '') {
				continue;
			}

			$sample = $candidate;
			break;
		}

		fclose($handle);

		if ($sample === null) {
			throw new CsvException('missing_header', 'CSV file does not contain a usable header row.');
		}

		return $this->delimiterFromSample($sample);
	}

	private function detectDelimiterFromFile(SplFileObject $file): string
	{
		$file->rewind();
		$sample = null;
		$sampleRow = 0;

		while (!$file->eof()) {
			$candidate = $file->fgets();
			++$sampleRow;
			$candidate = substr($candidate, 0, self::DETECTION_BYTES);
			$candidate = self::stripBom($candidate);
			$this->assertUtf8($candidate, $sampleRow);
			if (trim($candidate) === '') {
				continue;
			}

			$sample = $candidate;
			break;
		}

		$file->rewind();
		if ($sample === null) {
			throw new CsvException('missing_header', 'CSV file does not contain a usable header row.');
		}

		return $this->delimiterFromSample($sample);
	}

	private function delimiterFromSample(string $sample): string
	{
		$bestDelimiter = ',';
		$bestCount = 1;

		foreach (self::supportedDelimiters() as $delimiter) {
			$parsed = str_getcsv($sample, $delimiter, '"', '\\');
			$count = count($parsed);
			if ($count > $bestCount) {
				$bestCount = $count;
				$bestDelimiter = $delimiter;
			}
		}

		return $bestDelimiter;
	}

	private function verifyHash(SplFileObject $file, string $expectedHash): void
	{
		$expectedHash = strtolower(trim($expectedHash));
		if (preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1) {
			throw new CsvException('source_hash_failed', 'CSV source hash is invalid.');
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
			throw new CsvException('source_changed_during_validation', 'CSV source changed during validation.');
		}

		if (!hash_equals($expectedHash, $actualHash)) {
			throw new CsvException('source_hash_mismatch', 'CSV source hash does not match the confirmed batch.');
		}
	}

	/**
	 * @return array{0:array<int,string>,1:int}
	 */
	private function readHeadersFromFile(SplFileObject $file): array
	{
		$file->rewind();
		$recordNumber = 0;

		while (!$file->eof()) {
			$row = $file->fgetcsv();
			++$recordNumber;
			if (!is_array($row) || $row === array(null) || self::isEmptyRow($row)) {
				continue;
			}

			$values = $this->normalizeCells($row, $recordNumber);
			$headers = $this->normalizeHeaders($values, $recordNumber);
			$offset = $file->ftell();
			if ($offset === false) {
				throw new CsvException('source_offset_failed', 'CSV header position could not be read.');
			}

			return array($headers, (int) $offset);
		}

		throw new CsvException('missing_header', 'CSV file does not contain a usable header row.');
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

	/**
	 * @param array<int,mixed> $row Raw CSV cells.
	 * @return array<int,string>
	 */
	private function normalizeCells(array $row, int $recordNumber): array
	{
		if (count($row) > $this->maxColumns) {
			throw new CsvException('column_limit_exceeded', 'CSV column limit exceeded.', $recordNumber);
		}

		$values = array();
		foreach ($row as $index => $value) {
			$cell = $value === null ? '' : (string) $value;
			if ($recordNumber === 1 && $index === 0) {
				$cell = self::stripBom($cell);
			}

			$this->assertUtf8($cell, $recordNumber);
			if (strlen($cell) > $this->maxCellBytes) {
				throw new CsvException('cell_limit_exceeded', 'CSV cell byte limit exceeded.', $recordNumber);
			}

			$values[] = $cell;
		}

		return $values;
	}

	/**
	 * @param array<int,string> $values Header cells.
	 * @return array<int,string>
	 */
	private function normalizeHeaders(array $values, int $recordNumber): array
	{
		if (count($values) > $this->maxColumns) {
			throw new CsvException('column_limit_exceeded', 'CSV column limit exceeded.', $recordNumber);
		}

		$headers = array();
		foreach ($values as $index => $value) {
			if ($index === 0) {
				$value = self::stripBom($value);
			}

			$header = HeaderNormalizer::normalize($value);
			if ($header === '') {
				throw new CsvException('empty_header', 'CSV contains an empty header after normalization.', $recordNumber);
			}

			if (in_array($header, $headers, true)) {
				throw new CsvException('duplicate_header', 'CSV contains duplicate headers after normalization.', $recordNumber);
			}

			$headers[] = $header;
		}

		return $headers;
	}

	private function assertUtf8(string $value, int $recordNumber): void
	{
		if (preg_match('//u', $value) !== 1) {
			throw new CsvException('invalid_utf8', 'CSV contains invalid UTF-8 data.', $recordNumber);
		}
	}

	/**
	 * @param array<int,mixed> $row CSV row.
	 */
	private static function isEmptyRow(array $row): bool
	{
		foreach ($row as $value) {
			if ($value !== null && trim((string) $value) !== '') {
				return false;
			}
		}

		return true;
	}

	private static function stripBom(string $value): string
	{
		return str_starts_with($value, self::UTF8_BOM) ? substr($value, 3) : $value;
	}
}
// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
