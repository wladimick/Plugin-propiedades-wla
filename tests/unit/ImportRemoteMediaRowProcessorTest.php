<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\RemoteMediaDownloadedFile;
use WLA\Inmo\Import\RemoteMediaDownloaderInterface;
use WLA\Inmo\Import\RemoteMediaException;
use WLA\Inmo\Import\RemoteMediaLibraryInterface;
use WLA\Inmo\Import\RemoteMediaPropertyStoreInterface;
use WLA\Inmo\Import\RemoteMediaRowProcessor;
use WLA\Inmo\Import\TargetRegistry;
use WLA\Inmo\Import\ValueNormalizer;

final class ImportRemoteMediaRowProcessorTest extends TestCase
{
	public function testPortableTargetsNormalizeWithoutNetworkPolicy(): void
	{
		$gallery = TargetRegistry::definition(TargetRegistry::MEDIA_GALLERY_URLS);
		$featured = TargetRegistry::definition(TargetRegistry::MEDIA_FEATURED_IMAGE_URL);
		self::assertIsArray($gallery);
		self::assertIsArray($featured);

		$normalized = ValueNormalizer::normalize(
			'https://cdn.example.com/a.jpg|https://cdn.example.com/b.webp',
			$gallery,
			'|'
		);
		self::assertTrue($normalized->isValid());
		self::assertSame(
			array('https://cdn.example.com/a.jpg', 'https://cdn.example.com/b.webp'),
			$normalized->value()
		);

		$single = ValueNormalizer::normalize('https://cdn.example.com/hero.jpg', $featured);
		self::assertTrue($single->isValid());
		self::assertSame('https://cdn.example.com/hero.jpg', $single->value());
	}

	public function testGalleryAndFeaturedReuseSameResolvedUrl(): void
	{
		$downloader = new RemoteMediaProcessorFakeDownloader();
		$library = new RemoteMediaProcessorFakeLibrary();
		$property = new RemoteMediaProcessorFakePropertyStore();
		$processor = new RemoteMediaRowProcessor($downloader, $library, $property);

		$warnings = $processor->process(10, array(
			TargetRegistry::MEDIA_GALLERY_URLS => array('https://cdn.example.com/a.jpg'),
			TargetRegistry::MEDIA_FEATURED_IMAGE_URL => 'https://cdn.example.com/a.jpg',
		));

		self::assertSame(array(), $warnings);
		self::assertSame(1, $downloader->calls['https://cdn.example.com/a.jpg'] ?? 0);
		self::assertSame(array(501), $property->galleryIds);
		self::assertSame(501, $property->featuredId);
	}

	public function testPermanentFailureBecomesWarningAndOtherImagesContinue(): void
	{
		$downloader = new RemoteMediaProcessorFakeDownloader();
		$downloader->failures['https://cdn.example.com/bad.svg'] = array('media_mime_not_allowed');
		$library = new RemoteMediaProcessorFakeLibrary();
		$property = new RemoteMediaProcessorFakePropertyStore();
		$processor = new RemoteMediaRowProcessor($downloader, $library, $property);

		$warnings = $processor->process(10, array(
			TargetRegistry::MEDIA_GALLERY_URLS => array(
				'https://cdn.example.com/bad.svg',
				'https://cdn.example.com/good.jpg',
			),
		));

		self::assertSame(array(array('code' => 'media_mime_not_allowed', 'target' => TargetRegistry::MEDIA_GALLERY_URLS)), $warnings);
		self::assertSame(array(501), $property->galleryIds);
	}

	public function testTransientFailureRetriesOnceThenSucceeds(): void
	{
		$downloader = new RemoteMediaProcessorFakeDownloader();
		$downloader->failures['https://cdn.example.com/flaky.jpg'] = array('media_http_failed');
		$processor = new RemoteMediaRowProcessor($downloader, new RemoteMediaProcessorFakeLibrary(), new RemoteMediaProcessorFakePropertyStore(), 2);

		$warnings = $processor->process(10, array(TargetRegistry::MEDIA_GALLERY_URLS => array('https://cdn.example.com/flaky.jpg')));

		self::assertSame(array(), $warnings);
		self::assertSame(2, $downloader->calls['https://cdn.example.com/flaky.jpg'] ?? 0);
	}

	public function testTransientFailureExhaustionThrowsForBatchRetry(): void
	{
		$downloader = new RemoteMediaProcessorFakeDownloader();
		$downloader->failures['https://cdn.example.com/down.jpg'] = array('media_http_failed', 'media_http_failed');
		$processor = new RemoteMediaRowProcessor($downloader, new RemoteMediaProcessorFakeLibrary(), new RemoteMediaProcessorFakePropertyStore(), 2);

		try {
			$processor->process(10, array(TargetRegistry::MEDIA_GALLERY_URLS => array('https://cdn.example.com/down.jpg')));
			self::fail('Expected transient remote media failure.');
		} catch (RemoteMediaException $exception) {
			self::assertSame('media_http_failed', $exception->reason());
		}
	}

	public function testExplicitClearDoesNotDownload(): void
	{
		$downloader = new RemoteMediaProcessorFakeDownloader();
		$property = new RemoteMediaProcessorFakePropertyStore();
		$processor = new RemoteMediaRowProcessor($downloader, new RemoteMediaProcessorFakeLibrary(), $property);

		$processor->process(10, array(
			TargetRegistry::MEDIA_GALLERY_URLS => array(),
			TargetRegistry::MEDIA_FEATURED_IMAGE_URL => null,
		));

		self::assertSame(array(), $downloader->calls);
		self::assertSame(array(), $property->galleryIds);
		self::assertNull($property->featuredId);
		self::assertTrue($property->featuredWasSet);
	}
}

final class RemoteMediaProcessorFakeDownloader implements RemoteMediaDownloaderInterface
{
	/** @var array<string,int> */
	public array $calls = array();
	/** @var array<string,array<int,string>> */
	public array $failures = array();

	public function download(string $url): RemoteMediaDownloadedFile
	{
		$this->calls[$url] = ($this->calls[$url] ?? 0) + 1;
		if (isset($this->failures[$url]) && $this->failures[$url] !== array()) {
			$reason = array_shift($this->failures[$url]);
			throw new RemoteMediaException((string) $reason, 'Synthetic remote media failure.');
		}

		$path = tempnam(sys_get_temp_dir(), 'wla-proc-');
		if (!is_string($path)) {
			throw new RuntimeException('Could not create processor test file.');
		}
		file_put_contents($path, $url);
		$sha = hash_file('sha256', $path);
		if (!is_string($sha)) {
			throw new RuntimeException('Could not hash processor test file.');
		}

		return new RemoteMediaDownloadedFile($url, $path, 'image/jpeg', 'jpg', (int) filesize($path), $sha, 100, 100);
	}
}

final class RemoteMediaProcessorFakeLibrary implements RemoteMediaLibraryInterface
{
	private int $nextId = 501;

	public function ingest(RemoteMediaDownloadedFile $file, int $propertyId): int
	{
		if ($propertyId < 1) {
			throw new RuntimeException('Invalid processor test property.');
		}
		$file->cleanup();
		return $this->nextId++;
	}
}

final class RemoteMediaProcessorFakePropertyStore implements RemoteMediaPropertyStoreInterface
{
	/** @var array<int,int>|null */
	public ?array $galleryIds = null;
	public ?int $featuredId = null;
	public bool $featuredWasSet = false;

	public function setGalleryIds(int $propertyId, array $attachmentIds): void
	{
		if ($propertyId < 1) {
			throw new RuntimeException('Invalid processor test property.');
		}
		$this->galleryIds = $attachmentIds;
	}

	public function setFeaturedImageId(int $propertyId, ?int $attachmentId): void
	{
		if ($propertyId < 1) {
			throw new RuntimeException('Invalid processor test property.');
		}
		$this->featuredWasSet = true;
		$this->featuredId = $attachmentId;
	}
}
