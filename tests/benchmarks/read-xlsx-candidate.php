<?php

declare(strict_types=1);

if ($argc < 3) {
	fwrite(STDERR, "Usage: php read-xlsx-candidate.php <candidate> <file.xlsx>\n");
	exit(2);
}

$candidate = (string) $argv[1];
$path = (string) $argv[2];
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

require $autoload;

$baseline = memory_get_usage(true);
$started = hrtime(true);
$rowCount = 0;
$cellCount = 0;
$checksumContext = hash_init('sha256');

try {
	if (str_starts_with($candidate, 'openspout-')) {
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
		$reader = new PhpOffice\PhpSpreadsheet\Reader\Xlsx();
		$reader->setReadDataOnly(true);
		$reader->setReadEmptyCells(false);
		$spreadsheet = $reader->load($path);
		foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
			foreach ($worksheet->getRowIterator() as $row) {
				++$rowCount;
				$cellIterator = $row->getCellIterator();
				$cellIterator->setIterateOnlyExistingCells(true);
				foreach ($cellIterator as $cell) {
					++$cellCount;
					$value = $cell->getValue();
					hash_update($checksumContext, is_scalar($value) || $value === null ? (string) $value : get_debug_type($value));
				}
			}
		}
		$spreadsheet->disconnectWorksheets();
		unset($spreadsheet, $reader);
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
