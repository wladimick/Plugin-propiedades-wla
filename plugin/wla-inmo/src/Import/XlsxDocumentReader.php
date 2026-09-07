<?php

namespace WLA\Inmo\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exceptions are internal parser/control-flow messages and are never rendered here.
final class XlsxDocumentReader
{
	private const DEFAULT_CHUNK_ROWS = 500;
	private const DEFAULT_MAX_ROWS = 10000;
	private const DEFAULT_MAX_COLUMNS = 100;
	private const DEFAULT_MAX_CELL_BYTES = 65535;
	private const ENCODE_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

	private XlsxArchiveInspector $inspector;
	private int $chunkRows;
	private int $maxRows;
	private int $maxColumns;
	private int $maxCellBytes;

	public function __construct(
		?XlsxArchiveInspector $inspector = null,
		int $chunkRows = self::DEFAULT_CHUNK_ROWS,
		int $maxRows = self::DEFAULT_MAX_ROWS,
		int $maxColumns = self::DEFAULT_MAX_COLUMNS,
		int $maxCellBytes = self::DEFAULT_MAX_CELL_BYTES
	) {
		if ($chunkRows < 1 || $maxRows < 1 || $maxColumns < 1 || $maxCellBytes < 1) {
			throw new \InvalidArgumentException('XLSX reader limits must be positive integers.');
		}

		$this->inspector = $inspector ?? new XlsxArchiveInspector();
		$this->chunkRows = $chunkRows;
		$this->maxRows = $maxRows;
		$this->maxColumns = $maxColumns;
		$this->maxCellBytes = $maxCellBytes;
	}

	/**
	 * Normalize one selected worksheet to private NDJSON rows.
	 *
	 * @return array{
	 *   original_hash:string,
	 *   source_hash:string,
	 *   total_rows:int,
	 *   headers:array<int,string>,
	 *   sheet_name:string,
	 *   source_bytes:int,
	 *   archive_entries:int
	 * }
	 */
	public function normalizeToNdjson(string $xlsxPath, string $ndjsonPath, ?string $sheetName = null): array
	{
		$inspection = $this->inspector->inspect($xlsxPath);
		$handle = fopen($xlsxPath, 'rb');
		if ($handle === false || !flock($handle, LOCK_SH)) {
			if (is_resource($handle)) {
				fclose($handle);
			}
			throw new XlsxException('source_lock_failed', 'XLSX source could not be locked for normalization.');
		}

		$output = null;
		try {
			if (!hash_equals($inspection['source_hash'], $this->hashLockedHandle($handle))) {
				throw new XlsxException('source_changed_before_normalization', 'XLSX source changed before normalization.');
			}

			$workbookInfo = $this->worksheetInfo($xlsxPath);
			$selected = $this->selectWorksheet($workbookInfo, $sheetName);
			$totalRows = (int) $selected['totalRows'];
			$totalColumns = (int) $selected['totalColumns'];

			if ($totalColumns < 1 || $totalColumns > $this->maxColumns) {
				throw new XlsxException('column_limit_exceeded', 'XLSX worksheet column limit exceeded.');
			}
			if ($totalRows < 1) {
				throw new XlsxException('missing_header', 'XLSX worksheet does not contain a header row.');
			}
			if (($totalRows - 1) > $this->maxRows) {
				throw new XlsxException('row_limit_exceeded', 'XLSX worksheet row limit exceeded.');
			}

			$output = $this->openOutput($ndjsonPath);
			$filter = new XlsxChunkReadFilter();
			$headers = array();
			$dataRows = 0;

			for ($startRow = 1; $startRow <= $totalRows; $startRow += $this->chunkRows) {
				$endRow = min($totalRows, $startRow + $this->chunkRows - 1);
				$filter->setRows($startRow, $endRow);
				$reader = $this->configuredReader($filter, (string) $selected['worksheetName']);
				$spreadsheet = $reader->load($xlsxPath);
				$worksheet = $spreadsheet->getSheetByName((string) $selected['worksheetName']);
				if ($worksheet === null) {
					$spreadsheet->disconnectWorksheets();
					throw new XlsxException('sheet_load_failed', 'Selected XLSX worksheet could not be loaded.');
				}

				try {
					for ($rowNumber = $startRow; $rowNumber <= $endRow; ++$rowNumber) {
						$values = $this->rowValues($worksheet, $rowNumber, $totalColumns);

						if ($rowNumber === 1) {
							$headers = $this->normalizeHeaders($values);
							continue;
						}

						if ($this->isEmptyRow($values)) {
							continue;
						}
						if ($headers === array()) {
							throw new XlsxException('missing_header', 'XLSX worksheet header was not initialized.');
						}

						++$dataRows;
						if ($dataRows > $this->maxRows) {
							throw new XlsxException('row_limit_exceeded', 'XLSX worksheet row limit exceeded.', $rowNumber);
						}

						$data = array_combine($headers, $values);
						if (!is_array($data)) {
							throw new XlsxException('row_shape_failed', 'XLSX row could not be associated with its headers.', $rowNumber);
						}

						try {
							$encoded = json_encode($data, self::ENCODE_FLAGS);
						} catch (\JsonException) {
							throw new XlsxException('normalized_encode_failed', 'Normalized XLSX row could not be encoded.', $rowNumber);
						}

						$line = $encoded . "\n";
						if (strlen($line) > JsonLinesReader::DEFAULT_MAX_LINE_BYTES) {
							throw new XlsxException('line_limit_exceeded', 'Normalized XLSX row exceeds the byte limit.', $rowNumber);
						}
						if (fwrite($output, $line) !== strlen($line)) {
							throw new XlsxException('normalized_write_failed', 'Normalized XLSX source could not be written.', $rowNumber);
						}
					}
				} finally {
					$spreadsheet->disconnectWorksheets();
					unset($worksheet, $spreadsheet, $reader);
					gc_collect_cycles();
				}
			}

			if ($headers === array()) {
				throw new XlsxException('missing_header', 'XLSX worksheet does not contain a usable header row.');
			}
			if ($dataRows < 1) {
				throw new XlsxException('empty_worksheet', 'XLSX worksheet does not contain importable data rows.');
			}
			if (!fflush($output)) {
				throw new XlsxException('normalized_flush_failed', 'Normalized XLSX source could not be finalized.');
			}
			fclose($output);
			$output = null;

			if (!hash_equals($inspection['source_hash'], $this->hashLockedHandle($handle))) {
				@unlink($ndjsonPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Plugin-created invalid normalized source cleanup.
				throw new XlsxException('source_changed_during_normalization', 'XLSX source changed during normalization.');
			}

			$sourceHash = hash_file('sha256', $ndjsonPath);
			if (!is_string($sourceHash) || preg_match('/^[a-f0-9]{64}$/', $sourceHash) !== 1) {
				@unlink($ndjsonPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid generated source cleanup.
				throw new XlsxException('source_hash_failed', 'Normalized XLSX source hash could not be generated.');
			}

			return array(
				'original_hash'   => $inspection['source_hash'],
				'source_hash'     => $sourceHash,
				'total_rows'      => $dataRows,
				'headers'         => $headers,
				'sheet_name'      => (string) $selected['worksheetName'],
				'source_bytes'    => $inspection['source_bytes'],
				'archive_entries' => $inspection['entries'],
			);
		} catch (\Throwable $exception) {
			if (is_resource($output)) {
				fclose($output);
			}
			if (is_file($ndjsonPath)) {
				@unlink($ndjsonPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Plugin-created incomplete source cleanup.
			}
			throw $exception;
		} finally {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	/** @return array<int,array{worksheetName:string,lastColumnLetter:string,lastColumnIndex:int,totalRows:int,totalColumns:int}> */
	private function worksheetInfo(string $path): array
	{
		try {
			$reader = new Xlsx();
			$info = $reader->listWorksheetInfo($path);
		} catch (\Throwable $exception) {
			throw new XlsxException('workbook_metadata_failed', 'XLSX workbook metadata could not be read safely.');
		}

		if (!is_array($info) || $info === array()) {
			throw new XlsxException('missing_worksheet', 'XLSX workbook does not contain a usable worksheet.');
		}

		return $info;
	}

	/**
	 * @param array<int,array{worksheetName:string,lastColumnLetter:string,lastColumnIndex:int,totalRows:int,totalColumns:int}> $info
	 * @return array{worksheetName:string,lastColumnLetter:string,lastColumnIndex:int,totalRows:int,totalColumns:int}
	 */
	private function selectWorksheet(array $info, ?string $requested): array
	{
		if ($requested === null || trim($requested) === '') {
			return $info[0];
		}

		foreach ($info as $worksheet) {
			if (hash_equals((string) $worksheet['worksheetName'], $requested)) {
				return $worksheet;
			}
		}

		throw new XlsxException('unknown_sheet', 'Requested XLSX worksheet does not exist.');
	}

	private function configuredReader(XlsxChunkReadFilter $filter, string $sheetName): Xlsx
	{
		$reader = new Xlsx();
		$reader->setReadDataOnly(true);
		$reader->setReadEmptyCells(false);
		$reader->setLoadSheetsOnly($sheetName);
		$reader->setReadFilter($filter);
		return $reader;
	}

	/** @return array<int,string> */
	private function rowValues($worksheet, int $rowNumber, int $columns): array
	{
		$values = array();
		for ($column = 1; $column <= $columns; ++$column) {
			$coordinate = Coordinate::stringFromColumnIndex($column) . $rowNumber;
			$value = $worksheet->getCell($coordinate)->getValue();
			$values[] = $this->portableCellValue($value, $rowNumber);
		}
		return $values;
	}

	private function portableCellValue(mixed $value, int $rowNumber): string
	{
		if ($value === null) {
			return '';
		}
		if ($value instanceof RichText) {
			$value = $value->getPlainText();
		}
		if (!is_scalar($value)) {
			throw new XlsxException('unsupported_cell_value', 'XLSX cell contains an unsupported value type.', $rowNumber);
		}

		$string = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
		if (strlen($string) > $this->maxCellBytes) {
			throw new XlsxException('cell_limit_exceeded', 'XLSX cell exceeds the byte limit.', $rowNumber);
		}
		return $string;
	}

	/** @param array<int,string> $values @return array<int,string> */
	private function normalizeHeaders(array $values): array
	{
		$headers = array();
		$seen = array();
		foreach ($values as $value) {
			$header = HeaderNormalizer::normalize($value);
			if ($header === '') {
				throw new XlsxException('invalid_header', 'XLSX header contains an empty or invalid column name.', 1);
			}
			if (isset($seen[$header])) {
				throw new XlsxException('duplicate_header', 'XLSX header contains duplicate normalized column names.', 1);
			}
			$seen[$header] = true;
			$headers[] = $header;
		}
		return $headers;
	}

	/** @param array<int,string> $values */
	private function isEmptyRow(array $values): bool
	{
		foreach ($values as $value) {
			if ($value !== '') {
				return false;
			}
		}
		return true;
	}

	private function openOutput(string $path)
	{
		if ($path === '' || is_file($path)) {
			throw new XlsxException('normalized_path_invalid', 'Normalized XLSX source path is invalid.');
		}

		$directory = dirname($path);
		if (!is_dir($directory) || !is_writable($directory)) {
			throw new XlsxException('normalized_directory_unwritable', 'Normalized XLSX source directory is not writable.');
		}

		$oldUmask = umask(0077);
		$handle = fopen($path, 'xb');
		umask($oldUmask);
		if ($handle === false) {
			throw new XlsxException('normalized_open_failed', 'Normalized XLSX source could not be created.');
		}
		if (!chmod($path, 0600)) {
			fclose($handle);
			@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Fail-closed cleanup of plugin-created source.
			throw new XlsxException('normalized_permissions_failed', 'Normalized XLSX source permissions could not be restricted.');
		}
		return $handle;
	}

	private function hashLockedHandle($handle): string
	{
		if (fseek($handle, 0) !== 0) {
			throw new XlsxException('source_read_failed', 'XLSX source could not be rewound for hashing.');
		}
		$context = hash_init('sha256');
		while (!feof($handle)) {
			$chunk = fread($handle, 1048576);
			if ($chunk === false) {
				throw new XlsxException('source_read_failed', 'XLSX source could not be read for hashing.');
			}
			if ($chunk === '') {
				break;
			}
			hash_update($context, $chunk);
		}
		return hash_final($context);
	}
}
