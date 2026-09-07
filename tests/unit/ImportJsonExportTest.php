<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\JsonDocumentReader;
use WLA\Inmo\Import\JsonException;
use WLA\Inmo\Import\JsonExporter;
use WLA\Inmo\Import\JsonExportSourceInterface;
use WLA\Inmo\Import\JsonLinesReader;

final class ImportJsonExportTest extends TestCase
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

	public function testExportIsPagedAndRoundTripsThroughCanonicalJsonReader(): void
	{
		$source = new class implements JsonExportSourceInterface {
			/** @var array<int,array{page:int,size:int}> */
			public array $calls = array();

			public function page(int $page, int $pageSize): array
			{
				$this->calls[] = array('page' => $page, 'size' => $pageSize);
				$all = array(
					array('post' => array('title' => 'Casa Uno'), 'meta' => array('property_code' => 'P-1', 'price_clp' => 1000)),
					array('post' => array('title' => 'Casa Dos'), 'meta' => array('property_code' => 'P-2'), 'taxonomies' => array('feature' => array('Piscina'))),
					array('post' => array('title' => 'Casa Tres'), 'meta' => array('property_code' => 'P-3')),
				);
				$offset = ($page - 1) * $pageSize;

				return array_slice($all, $offset, $pageSize);
			}
		};

		$exportPath = $this->path('export', '.json');
		$result = (new JsonExporter($source))->export($exportPath, ' Respaldo WLA 2026 ', 2);

		self::assertSame(3, $result['count']);
		self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result['sha256']);
		self::assertGreaterThan(0, $result['bytes']);
		self::assertSame(array(
			array('page' => 1, 'size' => 2),
			array('page' => 2, 'size' => 2),
		), $source->calls);

		$decoded = json_decode((string) file_get_contents($exportPath), true, 16, JSON_THROW_ON_ERROR);
		self::assertSame(1, $decoded['format_version']);
		self::assertSame('respaldo_wla_2026', $decoded['source_key']);
		self::assertCount(3, $decoded['properties']);

		$normalized = $this->path('normalized', '.ndjson');
		$allowed = array('post.title', 'meta.property_code', 'meta.price_clp', 'taxonomy.feature');
		$reader = new JsonDocumentReader(
			1048576,
			100,
			16,
			static fn (string $target): bool => in_array($target, $allowed, true),
			static fn (string $target): bool => $target === 'taxonomy.feature'
		);
		$inspection = $reader->normalizeToNdjson($exportPath, $normalized);
		$rows = iterator_to_array((new JsonLinesReader())->verifiedRows($normalized, $inspection['source_hash']));

		self::assertCount(3, $rows);
		self::assertSame('P-1', $rows[1]['data']['meta_property_code']);
		self::assertSame(array('Piscina'), $rows[2]['data']['taxonomy_feature']);
	}

	public function testOversizedSourcePageIsRejectedAndIncompleteFileIsRemoved(): void
	{
		$source = new class implements JsonExportSourceInterface {
			public function page(int $page, int $pageSize): array
			{
				unset($page);
				return array_fill(0, $pageSize + 1, array('post' => array('title' => 'X')));
			}
		};
		$path = $this->path('oversized', '.json');

		try {
			(new JsonExporter($source))->export($path, 'wla_export', 2);
			self::fail('Expected bounded-page exception.');
		} catch (JsonException $exception) {
			self::assertSame('export_page_unbounded', $exception->reason());
		}

		self::assertFileDoesNotExist($path);
	}

	private function path(string $prefix, string $extension): string
	{
		$path = sys_get_temp_dir() . '/wla-' . $prefix . '-' . bin2hex(random_bytes(8)) . $extension;
		$this->temporaryFiles[] = $path;

		return $path;
	}
}
