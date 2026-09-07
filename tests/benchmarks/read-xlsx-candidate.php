<?php

declare(strict_types=1);

if ($argc < 3) {
	fwrite(STDERR, "Usage: php read-xlsx-candidate.php <candidate> <file.xlsx> [mode]\n");
	exit(2);
}

$candidate = (string) $argv[1];
$path = (string) $argv[2];
$mode = isset($argv[3]) ? (string) $argv[3] : 'native';
$vendorDir = getenv('WLA_XLSX_VENDOR');
if (!is_string($vendorDir) || $vendorDir === '') {
	fwrite(STDERR, "WLA_XLSX_VENDOR is required.\n");
	exit(2);
}
$autoload = rtrim($vendorDir, '/\\') . '/autoload.php';
if (!is_file($autoload)) {
	fwrite(STDERR, "Candidate autoload file not found.\n");
	exit(2);
}
if (!is_file($path)) {
	fwrite(STDERR, "XLSX fixture not found.\n");
	exit(2);
}
if (!in_array($mode, array('native', 'chunked'), true)) {
	fwrite(STDERR, "Mode must be native or chunked.\n");
	exit(2);
}

require $autoload;

$baseline = memory_get_usage(true);
$started = hrtime(true);
$rowCount = 0;
$cellCount = 0;
$checksumContext = hash_init('sha256');
$chunkSize = 500;

$consumeWorksheetRows = static function ($worksheet, int $startRow, int $endRow) use (&$rowCount, &$cellCount, $checksumContext): void {
	foreach ($worksheet->getRowIterator($startRow, $endRow) as $row) {
		$cellIterator = $row->getCellIterator();
		$cellIterator->setIterateOnlyExistingCells(true);
		$rowHasCells = false;
		foreach ($cellIterator as $cell) {
			$rowHasCells = true;
			++$cellCount;
			$value = $cell->getValue();
			hash_update($checksumContext, is_scalar($value) || $value === null ? (string) $value : get_debug_type($value));
		}
		if ($rowHasCells) {
			++$rowCount;
		}
	}
};

try {
	if (str_starts_with($candidate, 'openspout-')) {
		if ($mode !== 'native') {
			throw new InvalidArgumentException('OpenSpout is already streaming; chunked mode is not applicable.');
		}
		$reader = new OpenSpout\Reader\XLSX\Reader();
		$reader->open($path);
		foreach ($reader->getSheetIterator() as $sheet) {
			foreach ($sheet->getRowIterator() as $row) {
				++$rowCount;
				foreach ($row->getCells() as $cell) {
					++$cellCount;
					$value = $cell->getValue();
					hash_update($checksumContext, is_scalar($value) || $value === null ? (string) $value : get_debug_type($value));
				}
			}
		}
		$reader->close();
	} elseif (str_starts_with($candidate, 'phpspreadsheet-')) {
		if ($mode === 'native') {
			$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
			$reader->setReadDataOnly(true);
			$reader->setReadEmptyCells(false);
			$spreadsheet = $reader->load($path);
			foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
				$consumeWorksheetRows($worksheet, 1, $worksheet->getHighestDataRow());
			}
			$spreadsheet->disconnectWorksheets();
			unset($spreadsheet, $reader);
		} else {
			final class WlaXlsxBenchmarkReadFilter implements PhpOffice\PhpSpreadsheet\Reader\IReadFilter {
				private int $startRow = 1;
				private int $endRow = 1;

				public function setRows(int $startRow, int $endRow): void {
					$this->startRow = $startRow;
					$this->endRow = $endRow;
				}

				public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool {
					return $row >= $this->startRow && $row <= $this->endRow;
				}
			}

			$metadataReader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
			$worksheetInfo = $metadataReader->listWorksheetInfo($path);
			unset($metadataReader);
			$filter = new WlaXlsxBenchmarkReadFilter();

			foreach ($worksheetInfo as $sheetInfo) {
				$sheetName = isset($sheetInfo['worksheetName']) ? (string) $sheetInfo['worksheetName'] : '';
				$totalRows = isset($sheetInfo['totalRows']) ? (int) $sheetInfo['totalRows'] : 0;
				if ($sheetName === '' || $totalRows < 1) {
					continue;
				}

				for ($startRow = 1; $startRow <= $totalRows; $startRow += $chunkSize) {
					$endRow = min($totalRows, $startRow + $chunkSize - 1);
					$filter->setRows($startRow, $endRow);
					$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
					$reader->setReadDataOnly(true);
					$reader->setReadEmptyCells(false);
					$reader->setLoadSheetsOnly($sheetName);
					$reader->setReadFilter($filter);
					$spreadsheet = $reader->load($path);
					$worksheet = $spreadsheet->getSheetByName($sheetName);
					if ($worksheet === null) {
						throw new RuntimeException('Expected worksheet was not loaded.');
					}
					$consumeWorksheetRows($worksheet, $startRow, $endRow);
					$spreadsheet->disconnectWorksheets();
					unset($worksheet, $spreadsheet, $reader);
					gc_collect_cycles();
				}
			}
		}
	} else {
		throw new InvalidArgumentException('Unknown XLSX candidate.');
	}
} catch (Throwable $exception) {
	fwrite(STDERR, get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);
	exit(1);
}

$elapsedMs = (hrtime(true) - $started) / 1_000_000;
$peak = memory_get_peak_usage(true);
$fileBytes = filesize($path);

$result = array(
	'candidate' => $candidate,
	'mode' => $mode,
	'chunk_size' => $mode === 'chunked' ? $chunkSize : null,
	'php' => PHP_VERSION,
	'file_bytes' => is_int($fileBytes) ? $fileBytes : 0,
	'rows_read' => $rowCount,
	'cells_read' => $cellCount,
	'elapsed_ms' => round($elapsedMs, 2),
	'memory_baseline_bytes' => $baseline,
	'memory_peak_bytes' => $peak,
	'memory_delta_bytes' => max(0, $peak - $baseline),
	'value_checksum' => hash_final($checksumContext),
);

echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
