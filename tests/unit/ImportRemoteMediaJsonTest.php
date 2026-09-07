<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\JsonDocumentReader;
use WLA\Inmo\Import\JsonLinesReader;
use WLA\Inmo\Import\TargetRegistry;

final class ImportRemoteMediaJsonTest extends TestCase
{
	/** @var array<int,string> */
	private array $files = array();

	protected function tearDown(): void
	{
		foreach ($this->files as $path) {
			if (is_file($path)) {
				unlink($path);
			}
		}
		$this->files = array();
	}

	public function testJsonMediaSectionNormalizesToPortableTargetsWithoutHttp(): void
	{
		$source = $this->path('source.json');
		$normalized = $this->path('normalized.ndjson', false);
		$document = array(
			'format_version' => 1,
			'source_key' => 'portal_media',
			'properties' => array(
				array(
					'post' => array('title' => 'Casa JSON'),
					'meta' => array('property_code' => 'JSON-MEDIA-1'),
					'media' => array(
						'gallery_urls' => array(
							'https://cdn.example.com/a.jpg',
							'https://cdn.example.com/b.webp',
						),
						'featured_image_url' => 'https://cdn.example.com/a.jpg',
					),
				),
			),
		);
		file_put_contents($source, json_encode($document, JSON_UNESCAPED_SLASHES));

		$result = (new JsonDocumentReader())->normalizeToNdjson($source, $normalized);

		self::assertSame(TargetRegistry::MEDIA_GALLERY_URLS, $result['mapping']['media_gallery_urls']);
		self::assertSame(TargetRegistry::MEDIA_FEATURED_IMAGE_URL, $result['mapping']['media_featured_image_url']);

		$rows = array_values(iterator_to_array((new JsonLinesReader())->verifiedRows($normalized, $result['source_hash'])));
		self::assertCount(1, $rows);
		self::assertSame(
			array('https://cdn.example.com/a.jpg', 'https://cdn.example.com/b.webp'),
			$rows[0]['data']['media_gallery_urls']
		);
		self::assertSame('https://cdn.example.com/a.jpg', $rows[0]['data']['media_featured_image_url']);
	}

	public function testJsonRejectsUnknownMediaFieldThroughCanonicalAllowlist(): void
	{
		$source = $this->path('bad.json');
		$normalized = $this->path('bad.ndjson', false);
		file_put_contents($source, json_encode(array(
			'format_version' => 1,
			'source_key' => 'portal_media',
			'properties' => array(array(
				'post' => array('title' => 'Casa'),
				'meta' => array('property_code' => 'JSON-MEDIA-2'),
				'media' => array('arbitrary_attachment_id' => 99),
			)),
		)));

		try {
			(new JsonDocumentReader())->normalizeToNdjson($source, $normalized);
			self::fail('Expected unknown media target rejection.');
		} catch (\WLA\Inmo\Import\JsonException $exception) {
			self::assertSame('unknown_target', $exception->reason());
			self::assertFileDoesNotExist($normalized);
		}
	}

	private function path(string $suffix, bool $create = true): string
	{
		$path = sys_get_temp_dir() . '/wla-' . bin2hex(random_bytes(8)) . '-' . $suffix;
		if ($create) {
			file_put_contents($path, '');
		}
		$this->files[] = $path;
		return $path;
	}
}
