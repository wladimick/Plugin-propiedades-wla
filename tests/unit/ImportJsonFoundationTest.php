<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\JsonDocumentReader;
use WLA\Inmo\Import\JsonException;
use WLA\Inmo\Import\JsonLinesReader;

final class ImportJsonFoundationTest extends TestCase
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

	public function testWlaJsonV1NormalizesToTypedResumableRows(): void
	{
		$input = $this->writeDocument(array(
			'format_version' => 1,
			'source_key' => ' Portal Proveedor Ñ ',
			'exported_at' => '2026-09-07T13:00:00Z',
			'properties' => array(
				array(
					'post' => array('title' => 'Casa Uno'),
					'meta' => array(
						'property_code' => 'COD-1',
						'price_clp' => 120000000,
						'video_urls' => array('https://example.com/video-1'),
					),
					'taxonomies' => array(
						'operation' => 'Venta',
						'feature' => array('Piscina', 'Terraza'),
					),
				),
				array(
					'post' => array('title' => 'Casa Dos'),
					'meta' => array('property_code' => 'COD-2'),
				),
			),
		));
		$output = $this->newOutputPath();
		$reader = $this->documentReader();
		$inspection = $reader->normalizeToNdjson($input, $output);

		self::assertSame(1, $inspection['format_version']);
		self::assertSame('portal_proveedor_n', $inspection['source_key']);
		self::assertSame(2, $inspection['total_rows']);
		self::assertSame('2026-09-07T13:00:00Z', $inspection['exported_at']);
		self::assertSame('post.title', $inspection['mapping']['post.title']);
		self::assertSame('taxonomy.feature', $inspection['mapping']['taxonomy.feature']);
		self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $inspection['source_hash']);

		$rows = iterator_to_array((new JsonLinesReader())->verifiedRows($output, $inspection['source_hash']));
		self::assertCount(2, $rows);
		self::assertSame(120000000, $rows[1]['data']['meta.price_clp']);
		self::assertSame(array('https://example.com/video-1'), $rows[1]['data']['meta.video_urls']);
		self::assertSame(array('Piscina', 'Terraza'), $rows[1]['data']['taxonomy.feature']);
		self::assertGreaterThan(0, $rows[1]['next_offset']);
	}

	public function testNormalizedJsonResumesFromPhysicalOffset(): void
	{
		$input = $this->writeDocument(array(
			'format_version' => 1,
			'source_key' => 'crm_inmobiliaria',
			'properties' => array(
				array('post' => array('title' => 'Uno'), 'meta' => array('property_code' => 'A-1')),
				array('post' => array('title' => 'Dos'), 'meta' => array('property_code' => 'A-2')),
				array('post' => array('title' => 'Tres'), 'meta' => array('property_code' => 'A-3')),
			),
		));
		$output = $this->newOutputPath();
		$inspection = $this->documentReader()->normalizeToNdjson($input, $output);
		$all = iterator_to_array((new JsonLinesReader())->verifiedRows($output, $inspection['source_hash']));
		$offset = (int) $all[1]['next_offset'];

		$resumed = iterator_to_array((new JsonLinesReader())->verifiedRows($output, $inspection['source_hash'], $offset, 1));
		self::assertSame(array(2, 3), array_keys($resumed));
		self::assertSame('A-2', $resumed[2]['data']['meta.property_code']);
		self::assertSame('A-3', $resumed[3]['data']['meta.property_code']);
	}

	public function testUnsupportedVersionIsRejected(): void
	{
		$input = $this->writeDocument(array(
			'format_version' => 2,
			'source_key' => 'portal_a',
			'properties' => array(array('post' => array('title' => 'Casa'))),
		));

		$this->expectJsonReason('unsupported_format_version');
		$this->documentReader()->normalizeToNdjson($input, $this->newOutputPath());
	}

	public function testUnknownCanonicalTargetIsRejected(): void
	{
		$input = $this->writeDocument(array(
			'format_version' => 1,
			'source_key' => 'portal_a',
			'properties' => array(array('meta' => array('evil_dynamic_meta' => 'x'))),
		));

		$this->expectJsonReason('unknown_target');
		$this->documentReader()->normalizeToNdjson($input, $this->newOutputPath());
	}

	public function testStructuredValueIsAllowedOnlyForMultipleTargets(): void
	{
		$input = $this->writeDocument(array(
			'format_version' => 1,
			'source_key' => 'portal_a',
			'properties' => array(array('meta' => array('property_code' => array('A', 'B')))),
		));

		$this->expectJsonReason('invalid_target_value');
		$this->documentReader()->normalizeToNdjson($input, $this->newOutputPath());
	}

	public function testMalformedJsonIsRejectedWithoutLeavingNormalizedSource(): void
	{
		$input = $this->newInputPath();
		file_put_contents($input, '{"format_version":1,');
		$output = $this->newOutputPath();

		try {
			$this->documentReader()->normalizeToNdjson($input, $output);
			self::fail('Expected malformed JSON exception.');
		} catch (JsonException $exception) {
			self::assertSame('malformed_json', $exception->reason());
		}

		self::assertFileDoesNotExist($output);
	}

	public function testNormalizedSourceHashDetectsTampering(): void
	{
		$input = $this->writeDocument(array(
			'format_version' => 1,
			'source_key' => 'portal_a',
			'properties' => array(array('post' => array('title' => 'Casa'))),
		));
		$output = $this->newOutputPath();
		$inspection = $this->documentReader()->normalizeToNdjson($input, $output);
		file_put_contents($output, "{}\n", FILE_APPEND);

		$this->expectException(JsonException::class);
		$this->expectExceptionMessage('hash does not match');
		iterator_to_array((new JsonLinesReader())->verifiedRows($output, $inspection['source_hash']));
	}

	private function documentReader(): JsonDocumentReader
	{
		$allowed = array(
			'post.title',
			'post.content',
			'post.excerpt',
			'meta.property_code',
			'meta.external_id',
			'meta.price_clp',
			'meta.video_urls',
			'taxonomy.operation',
			'taxonomy.feature',
		);
		$multiple = array('meta.video_urls', 'taxonomy.feature');

		return new JsonDocumentReader(
			1048576,
			100,
			16,
			static fn (string $target): bool => in_array($target, $allowed, true),
			static fn (string $target): bool => in_array($target, $multiple, true)
		);
	}

	/** @param array<string,mixed> $document */
	private function writeDocument(array $document): string
	{
		$path = $this->newInputPath();
		file_put_contents($path, json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
		return $path;
	}

	private function newInputPath(): string
	{
		$path = tempnam(sys_get_temp_dir(), 'wla-json-input-');
		self::assertNotFalse($path);
		$this->temporaryFiles[] = $path;
		return $path;
	}

	private function newOutputPath(): string
	{
		$path = sys_get_temp_dir() . '/wla-json-source-' . bin2hex(random_bytes(8)) . '.ndjson';
		$this->temporaryFiles[] = $path;
		return $path;
	}

	private function expectJsonReason(string $reason): void
	{
		try {
			throw new \LogicException('sentinel');
		} catch (\LogicException) {
			// Keep PHPUnit expectation logic out of the production exception contract.
		}

		$this->expectException(JsonException::class);
		$this->expectExceptionMessageMatches('/.+/');
		$this->expectExceptionObject(new JsonException($reason, $this->messageForReason($reason)));
	}

	private function messageForReason(string $reason): string
	{
		return match ($reason) {
			'unsupported_format_version' => 'JSON format_version is not supported.',
			'unknown_target' => 'JSON property contains an unsupported canonical target.',
			'invalid_target_value' => 'JSON target contains an unsupported structured value.',
			default => 'JSON error.',
		};
	}
}
