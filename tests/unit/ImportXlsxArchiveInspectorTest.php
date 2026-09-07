<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\XlsxArchiveInspector;
use WLA\Inmo\Import\XlsxException;

final class ImportXlsxArchiveInspectorTest extends TestCase
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

	public function testValidMinimalXlsxPassesPreflight(): void
	{
		$path = $this->writeWorkbook();
		$result = (new XlsxArchiveInspector())->inspect($path);

		self::assertSame(filesize($path), $result['source_bytes']);
		self::assertSame(1, $result['worksheet_parts']);
		self::assertGreaterThanOrEqual(5, $result['entries']);
		self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['source_hash']);
	}

	public function testMalformedZipIsRejected(): void
	{
		$path = $this->temporaryPath();
		file_put_contents($path, 'not-a-zip');

		$this->expectReason('invalid_zip', static fn () => (new XlsxArchiveInspector())->inspect($path));
	}

	public function testPathTraversalIsRejected(): void
	{
		$path = $this->writeWorkbook(array('../outside.xml' => '<x/>'));
		$this->expectReason('unsafe_entry_path', static fn () => (new XlsxArchiveInspector())->inspect($path));
	}

	public function testMacroOrBinaryPartIsRejected(): void
	{
		$path = $this->writeWorkbook(array('xl/vbaProject.bin' => 'binary'));
		$this->expectReason('unsupported_executable_part', static fn () => (new XlsxArchiveInspector())->inspect($path));
	}

	public function testExternalRelationshipIsRejected(): void
	{
		$external = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.com" TargetMode="External"/>'
			. '</Relationships>';
		$path = $this->writeWorkbook(array('xl/worksheets/_rels/sheet1.xml.rels' => $external));
		$this->expectReason('external_relationship', static fn () => (new XlsxArchiveInspector())->inspect($path));
	}

	public function testExpansionBombIsRejectedBeforeReader(): void
	{
		$path = $this->writeWorkbook(array('docProps/bomb.xml' => str_repeat('A', 131072)));
		$inspector = new XlsxArchiveInspector(10485760, 256, 67108864, 33554432, 5.0);
		$this->expectReason('expansion_ratio_exceeded', static fn () => $inspector->inspect($path));
	}

	public function testEntryAndSheetLimitsAreEnforced(): void
	{
		$path = $this->writeWorkbook(array(
			'xl/worksheets/sheet2.xml' => $this->worksheetXml(),
		));
		$inspector = new XlsxArchiveInspector(10485760, 256, 67108864, 33554432, 100.0, 1048576, 1);
		$this->expectReason('sheet_limit_exceeded', static fn () => $inspector->inspect($path));
	}

	public function testMissingRequiredWorkbookPartIsRejected(): void
	{
		$path = $this->temporaryPath();
		$zip = new ZipArchive();
		self::assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
		$zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
		$zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
		$zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml());
		self::assertTrue($zip->close());

		$this->expectReason('missing_required_part', static fn () => (new XlsxArchiveInspector())->inspect($path));
	}

	/** @param array<string,string> $extraParts */
	private function writeWorkbook(array $extraParts = array()): string
	{
		$path = $this->temporaryPath();
		$zip = new ZipArchive();
		self::assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
		$zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
		$zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
		$zip->addFromString('xl/workbook.xml', $this->workbookXml());
		$zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
		$zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml());
		foreach ($extraParts as $name => $contents) {
			$zip->addFromString($name, $contents);
		}
		self::assertTrue($zip->close());
		return $path;
	}

	private function contentTypesXml(): string
	{
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
			. '</Types>';
	}

	private function rootRelationshipsXml(): string
	{
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>';
	}

	private function workbookXml(): string
	{
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets><sheet name="Properties" sheetId="1" r:id="rId1"/></sheets>'
			. '</workbook>';
	}

	private function workbookRelationshipsXml(): string
	{
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
			. '</Relationships>';
	}

	private function worksheetXml(): string
	{
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
			. '<row r="1"><c r="A1" t="inlineStr"><is><t>property_code</t></is></c></row>'
			. '</sheetData></worksheet>';
	}

	private function temporaryPath(): string
	{
		$path = tempnam(sys_get_temp_dir(), 'wla-xlsx-test-');
		self::assertIsString($path);
		$this->temporaryFiles[] = $path;
		return $path;
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
