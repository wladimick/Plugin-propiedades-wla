<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\CsvException;
use WLA\Inmo\Import\CsvReader;

final class ImportCsvResumeTest extends TestCase
{
	private string $path;

	protected function setUp(): void
	{
		$this->path = tempnam(sys_get_temp_dir(), 'wla-csv-resume-');
		if ($this->path === false) {
			self::fail('Unable to create temporary CSV fixture.');
		}

		file_put_contents(
			$this->path,
			"titulo,codigo\nCasa Uno,COD-1\nCasa Dos,COD-2\nCasa Tres,COD-3\n"
		);
	}

	protected function tearDown(): void
	{
		if (isset($this->path) && is_file($this->path)) {
			unlink($this->path);
		}
	}

	public function testVerifiedRowsResumeFromDurableByteOffset(): void
	{
		$hash = hash_file('sha256', $this->path);
		self::assertIsString($hash);

		$reader = new CsvReader();
		$allRows = iterator_to_array($reader->verifiedRows($this->path, $hash), false);
		self::assertCount(3, $allRows);
		self::assertSame('COD-1', $allRows[0]['data']['codigo']);
		self::assertGreaterThan(0, $allRows[0]['next_offset']);
		self::assertGreaterThan($allRows[0]['next_offset'], $allRows[1]['next_offset']);

		$resumed = iterator_to_array(
			$reader->verifiedRows($this->path, $hash, $allRows[0]['next_offset'], 1),
			false
		);

		self::assertCount(2, $resumed);
		self::assertSame('COD-2', $resumed[0]['data']['codigo']);
		self::assertSame('COD-3', $resumed[1]['data']['codigo']);
		self::assertSame(3, $resumed[0]['row_number']);
	}

	public function testVerifiedRowsRejectHashMismatchBeforeYieldingData(): void
	{
		$reader = new CsvReader();

		try {
			iterator_to_array($reader->verifiedRows($this->path, str_repeat('a', 64)), false);
			self::fail('Hash mismatch should reject the confirmed source.');
		} catch (CsvException $exception) {
			self::assertSame('source_hash_mismatch', $exception->reason());
		}
	}

	public function testVerifiedRowsRejectResumeWithoutByteOffset(): void
	{
		$hash = hash_file('sha256', $this->path);
		self::assertIsString($hash);
		$reader = new CsvReader();

		try {
			iterator_to_array($reader->verifiedRows($this->path, $hash, 0, 1), false);
			self::fail('A resumed logical cursor without byte offset must fail safe.');
		} catch (CsvException $exception) {
			self::assertSame('resume_offset_missing', $exception->reason());
		}
	}
}
