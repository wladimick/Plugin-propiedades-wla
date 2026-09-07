<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\JsonLinesReader;
use WLA\Inmo\Import\XlsxArchiveInspector;
use WLA\Inmo\Import\XlsxDocumentReader;
use WLA\Inmo\Import\XlsxException;

final class ImportXlsxDocumentReaderTest extends TestCase
{
	/** @var array<int,string> */
	private array $temporaryFiles = array();

	protected function tearDown(): void
	{
		foreach ($this->temporaryFiles as $path) {
			if (is_file($path)) {
				unlink($path);
			}
		}
		$this->temporaryFiles = array();
	}

	public function testWorkbookNormalizesToResumableNdjsonWithoutEvaluatingFormula(): void
	{
		$input = $this->writeWorkbook(array(
			array('property_code', 'title', 'price_clp'),
			array('PX-1', 'Casa Ñ', '=1+1'),
			array('PX-2', 'Depto', 125000000),
		));
		$output = $this->outputPath();

		$result = (new XlsxDocumentReader())->normalizeToNdjson($input, $output);
		self::assertSame(2, $result['total_rows']);
		self::assertSame(array('property_code', 'title', 'price_clp'), $result['headers']);
		self::assertSame('Properties', $result['sheet_name']);
		self::assertSame(hash_file('sha256', $input), $result['original_hash']);

		$rows = iterator_to_array((new JsonLinesReader())->verifiedRows($output, $result['source_hash']));
		self::assertCount(2, $rows);
		self::assertSame('PX-1', $rows[1]['data']['property_code']);
		self::assertSame('Casa Ñ', $rows[1]['data']['title']);
		self::assertSame('=1+1', $rows[1]['data']['price_clp']);
		self::assertSame('125000000', $rows[2]['data']['price_clp']);
		self::assertGreaterThan(0, $rows[1]['next_offset']);
	}

	public function testNormalizedSourceUsesPrivatePermissions(): void
	{
		$input = $this->writeWorkbook(array(
			array('property_code'),
			array('PX-1'),
		));
		$output = $this->outputPath();
		(new XlsxDocumentReader())->normalizeToNdjson($input, $output);

		if (DIRECTORY_SEPARATOR === '/') {
			self::assertSame(0600, fileperms($output) & 0777);
		}
	}

	public function testRequestedWorksheetIsSelectedByExactName(): void
	{
		$input = $this->writeWorkbook(
			array(array('ignore'), array('one')),
			array('Second' => array(array('property_code'), array('SECOND-1')))
		);
		$output = $this->outputPath();

		$result = (new XlsxDocumentReader())->normalizeToNdjson($input, $output, 'Second');
		$rows = iterator_to_array((new JsonLinesReader())->verifiedRows($output, $result['source_hash']));
		self::assertSame('Second', $result['sheet_name']);
		self::assertSame('SECOND-1', $rows[1]['data']['property_code']);
	}

	public function testUnknownWorksheetIsRejectedBeforeOutputSurvives(): void
	{
		$input = $this->writeWorkbook(array(array('property_code'), array('PX-1')));
		$output = $this->outputPath();
		$this->expectReason('unknown_sheet', static fn () => (new XlsxDocumentReader())->normalizeToNdjson($input, $output, 'Missing'));
		self::assertFileDoesNotExist($output);
	}

	public function testDuplicateNormalizedHeadersAreRejected(): void
	{
		$input = $this->writeWorkbook(array(
			array('Property Code', 'property-code'),
			array('PX-1', 'PX-2'),
		));
		$output = $this->outputPath();
		$this->expectReason('duplicate_header', static fn () => (new XlsxDocumentReader())->normalizeToNdjson($input, $output));
		self::assertFileDoesNotExist($output);
	}

	public function testColumnAndRowLimitsAreEnforced(): void
	{
		$input = $this->writeWorkbook(array(
			array('a', 'b'),
			array('1', '2'),
			array('3', '4'),
		));

		$columnOutput = $this->outputPath();
		$columnReader = new XlsxDocumentReader(new XlsxArchiveInspector(), 500, 10, 1, 65535);
		$this->expectReason('column_limit_exceeded', static fn () => $columnReader->normalizeToNdjson($input, $columnOutput));
		self::assertFileDoesNotExist($columnOutput);

		$rowOutput = $this->outputPath();
		$rowReader = new XlsxDocumentReader(new XlsxArchiveInspector(), 500, 1, 10, 65535);
		$this->expectReason('row_limit_exceeded', static fn () => $rowReader->normalizeToNdjson($input, $rowOutput));
		self::assertFileDoesNotExist($rowOutput);
	}

	public function testCellByteLimitIsEnforced(): void
	{
		$input = $this->writeWorkbook(array(
			array('property_code'),
			array('TOO-LONG'),
		));
		$output = $this->outputPath();
		$reader = new XlsxDocumentReader(new XlsxArchiveInspector(), 500, 10, 10, 4);
		$this->expectReason('cell_limit_exceeded', static fn () => $reader->normalizeToNdjson($input, $output));
		self::assertFileDoesNotExist($output);
	}

	/**
	 * @param array<int,array<int,mixed>> $primaryRows
	 * @param array<string,array<int,array<int,mixed>>> $extraSheets
	 */
	private function writeWorkbook(array $primaryRows, array $extraSheets = array()): string
	{
		$spreadsheet = new Spreadsheet();
		$primary = $spreadsheet->getActiveSheet();
		$primary->setTitle('Properties');
		$this->populate($primary, $primaryRows);

		foreach ($extraSheets as $name => $rows) {
			$sheet = $spreadsheet->createSheet();
			$sheet->setTitle($name);
			$this->populate($sheet, $rows);
		}

		$path = $this->temporaryPath('.xlsx');
		(new XlsxWriter($spreadsheet))->save($path);
		$spreadsheet->disconnectWorksheets();
		return $path;
	}

	/** @param array<int,array<int,mixed>> $rows */
	private function populate($worksheet, array $rows): void
	{
		foreach ($rows as $rowIndex => $row) {
			foreach ($row as $columnIndex => $value) {
				$coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnIndex + 1) . ($rowIndex + 1);
				$worksheet->setCellValue($coordinate, $value);
			}
		}
	}

	private function temporaryPath(string $suffix = ''): string
	{
		$base = tempnam(sys_get_temp_dir(), 'wla-xlsx-reader-');
		self::assertIsString($base);
		if ($suffix !== '') {
			$path = $base . $suffix;
			unlink($base);
		} else {
			$path = $base;
		}
		$this->temporaryFiles[] = $path;
		return $path;
	}

	private function outputPath(): string
	{
		return $this->temporaryPath('.ndjson');
	}

	private function expectReason(string $reason, callable $callback): void
	{
		try {
			$callback();
			self::fail('Expected XLSX exception.');
		} catch (XlsxException $exception) {
			self::assertSame($reason, $exception->reason());
		}
	}
}
