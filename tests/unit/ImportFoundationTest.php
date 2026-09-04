<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\BatchStatus;
use WLA\Inmo\Import\CsvException;
use WLA\Inmo\Import\CsvReader;
use WLA\Inmo\Import\IdentityCandidate;
use WLA\Inmo\Import\IdentityResolution;
use WLA\Inmo\Import\IdentityResolver;
use WLA\Inmo\Import\SourceKey;

final class ImportFoundationTest extends TestCase
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

	public function testBatchTransitionsAreExplicit(): void
	{
		self::assertTrue(BatchStatus::canTransition(BatchStatus::UPLOADED, BatchStatus::MAPPED));
		self::assertTrue(BatchStatus::canTransition(BatchStatus::PROCESSING, BatchStatus::PAUSED));
		self::assertTrue(BatchStatus::canTransition(BatchStatus::FAILED, BatchStatus::PROCESSING));
		self::assertFalse(BatchStatus::canTransition(BatchStatus::UPLOADED, BatchStatus::COMPLETED));
		self::assertFalse(BatchStatus::canTransition(BatchStatus::CANCELLED, BatchStatus::PROCESSING));
		self::assertTrue(BatchStatus::isValid(BatchStatus::DRY_RUN_READY));
		self::assertFalse(BatchStatus::isValid('done_with_errors'));
	}

	public function testSourceKeyNormalizesSpanishAndWhitespace(): void
	{
		$key = new SourceKey(' Portal Proveedor Ñ 2026 ');
		self::assertSame('portal_proveedor_n_2026', $key->value());
		self::assertTrue(SourceKey::isValid($key->value()));
		self::assertFalse(SourceKey::isValid('x'));
	}

	public function testIdentityResolverPrioritizesExternalIdentityAndDetectsDisagreement(): void
	{
		$resolver = new IdentityResolver(
			static function (string $sourceKey, string $externalId): array {
				return $sourceKey === 'portal_a' && $externalId === 'EXT-10' ? array(10) : array();
			},
			static function (string $propertyCode): array {
				return $propertyCode === 'COD-10' ? array(10) : ($propertyCode === 'COD-20' ? array(20) : array());
			}
		);

		$match = $resolver->resolve(new IdentityCandidate('portal_a', 'EXT-10', 'COD-10'));
		self::assertSame(IdentityResolution::MATCH, $match->status());
		self::assertSame(10, $match->propertyId());
		self::assertSame('external_identity', $match->reason());

		$conflict = $resolver->resolve(new IdentityCandidate('portal_a', 'EXT-10', 'COD-20'));
		self::assertSame(IdentityResolution::CONFLICT, $conflict->status());
		self::assertSame('identity_disagreement', $conflict->reason());
	}

	public function testIdentityResolverNeverUsesExternalIdWithoutSourceKey(): void
	{
		$resolver = new IdentityResolver(
			static fn (string $sourceKey, string $externalId): array => array(99),
			static fn (string $propertyCode): array => array(77)
		);

		$resolution = $resolver->resolve(new IdentityCandidate('', 'EXT-99', 'COD-77'));
		self::assertSame(IdentityResolution::CONFLICT, $resolution->status());
		self::assertSame('external_id_without_source_key', $resolution->reason());
		self::assertNull($resolution->propertyId());
	}

	public function testIdentityResolverFallsBackToCodeAndReturnsNewWhenAbsent(): void
	{
		$resolver = new IdentityResolver(
			static fn (string $sourceKey, string $externalId): array => array(),
			static fn (string $propertyCode): array => $propertyCode === 'COD-7' ? array(7) : array()
		);

		$byCode = $resolver->resolve(new IdentityCandidate('portal_a', '', 'COD-7'));
		self::assertSame(IdentityResolution::MATCH, $byCode->status());
		self::assertSame(7, $byCode->propertyId());
		self::assertSame('property_code', $byCode->reason());

		$new = $resolver->resolve(new IdentityCandidate('portal_a', '', 'COD-8'));
		self::assertSame(IdentityResolution::NEW, $new->status());
		self::assertNull($new->propertyId());
	}

	public function testCsvReaderSupportsBomSemicolonAndKeepsFormulaAsData(): void
	{
		$path = $this->temporaryFile(
			"\xEF\xBB\xBFCódigo;Precio CLP;Nota\nCOD-1;390000000;=SUM(1+1)\nCOD-2;450000000;Casa en Curicó\n"
		);
		$reader = new CsvReader(10, 10, 1024);
		$rows = iterator_to_array($reader->rows($path), false);

		self::assertCount(2, $rows);
		self::assertSame(2, $rows[0]['row_number']);
		self::assertSame('COD-1', $rows[0]['data']['codigo']);
		self::assertSame('390000000', $rows[0]['data']['precio_clp']);
		self::assertSame('=SUM(1+1)', $rows[0]['data']['nota']);
		self::assertSame('Casa en Curicó', $rows[1]['data']['nota']);
	}

	public function testCsvReaderSkipsLeadingBlankLinesAndNormalizesUppercaseSpanishHeaders(): void
	{
		$path = $this->temporaryFile("\n\nCÓDIGO;Ñandú;Precio\nCOD-1;Sí;100\n");
		$rows = iterator_to_array((new CsvReader())->rows($path), false);

		self::assertCount(1, $rows);
		self::assertSame(4, $rows[0]['row_number']);
		self::assertSame('COD-1', $rows[0]['data']['codigo']);
		self::assertSame('Sí', $rows[0]['data']['nandu']);
		self::assertSame('100', $rows[0]['data']['precio']);
	}

	public function testCsvReaderSupportsTabAndPadsMissingTrailingCells(): void
	{
		$path = $this->temporaryFile("codigo\tprecio\tcomuna\nA-1\t100\tCuricó\nA-2\t200\n");
		$rows = iterator_to_array((new CsvReader())->rows($path), false);

		self::assertSame('Curicó', $rows[0]['data']['comuna']);
		self::assertSame('', $rows[1]['data']['comuna']);
	}

	public function testCsvReaderRejectsDuplicateNormalizedHeaders(): void
	{
		$path = $this->temporaryFile("Código,codigo,precio\nA-1,A-1,100\n");

		try {
			iterator_to_array((new CsvReader())->rows($path), false);
			self::fail('Expected duplicate header exception.');
		} catch (CsvException $exception) {
			self::assertSame('duplicate_header', $exception->reason());
			self::assertSame(1, $exception->rowNumber());
		}
	}

	public function testCsvReaderEnforcesRowCellAndEncodingLimits(): void
	{
		$rowLimitPath = $this->temporaryFile("codigo,precio\nA-1,100\nA-2,200\n");
		try {
			iterator_to_array((new CsvReader(1, 10, 100))->rows($rowLimitPath), false);
			self::fail('Expected row limit exception.');
		} catch (CsvException $exception) {
			self::assertSame('row_limit_exceeded', $exception->reason());
			self::assertSame(3, $exception->rowNumber());
		}

		$cellLimitPath = $this->temporaryFile("codigo,nota\nA-1,123456789\n");
		try {
			iterator_to_array((new CsvReader(10, 10, 8))->rows($cellLimitPath), false);
			self::fail('Expected cell limit exception.');
		} catch (CsvException $exception) {
			self::assertSame('cell_limit_exceeded', $exception->reason());
			self::assertSame(2, $exception->rowNumber());
		}

		$invalidUtf8Path = $this->temporaryFile("codigo,nota\nA-1,\xC3\x28\n");
		try {
			iterator_to_array((new CsvReader())->rows($invalidUtf8Path), false);
			self::fail('Expected UTF-8 exception.');
		} catch (CsvException $exception) {
			self::assertSame('invalid_utf8', $exception->reason());
			self::assertSame(2, $exception->rowNumber());
		}
	}

	private function temporaryFile(string $contents): string
	{
		$path = tempnam(sys_get_temp_dir(), 'wla-inmo-csv-');
		self::assertIsString($path);
		file_put_contents($path, $contents);
		$this->temporaryFiles[] = $path;

		return $path;
	}
}
