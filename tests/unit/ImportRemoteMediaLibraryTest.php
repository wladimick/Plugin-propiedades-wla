<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\RemoteMediaAttachmentStoreInterface;
use WLA\Inmo\Import\RemoteMediaDownloadedFile;
use WLA\Inmo\Import\RemoteMediaException;
use WLA\Inmo\Import\RemoteMediaLibrary;

final class ImportRemoteMediaLibraryTest extends TestCase
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

	public function testExistingAttachmentIsReusedWithoutCreateAndTempIsCleaned(): void
	{
		$store = $this->store(77, 99);
		$file = $this->downloadedFile();
		$path = $file->path();

		$attachmentId = (new RemoteMediaLibrary($store))->ingest($file, 123);

		self::assertSame(77, $attachmentId);
		self::assertSame(1, $store->findCalls);
		self::assertSame(0, $store->createCalls);
		self::assertFileDoesNotExist($path);
	}

	public function testNewAttachmentIsCreatedAndTempIsCleaned(): void
	{
		$store = $this->store(null, 91);
		$file = $this->downloadedFile();
		$path = $file->path();

		$attachmentId = (new RemoteMediaLibrary($store))->ingest($file, 123);

		self::assertSame(91, $attachmentId);
		self::assertSame(1, $store->findCalls);
		self::assertSame(1, $store->createCalls);
		self::assertFileDoesNotExist($path);
	}

	public function testStoreFailureDoesNotLeakTemporaryFile(): void
	{
		$store = new class implements RemoteMediaAttachmentStoreInterface {
			public function findBySha256(string $sha256): ?int
			{
				unset($sha256);
				return null;
			}

			public function create(RemoteMediaDownloadedFile $file, int $propertyId): int
			{
				unset($file, $propertyId);
				throw new RemoteMediaException('media_sideload_failed', 'Synthetic failure.');
			}
		};
		$file = $this->downloadedFile();
		$path = $file->path();

		$this->expectException(RemoteMediaException::class);
		try {
			(new RemoteMediaLibrary($store))->ingest($file, 123);
		} finally {
			self::assertFileDoesNotExist($path);
		}
	}

	public function testPersistedPropertyIsRequiredAndNoStoreCallOccurs(): void
	{
		$store = $this->store(null, 91);
		$file = $this->downloadedFile();
		$path = $file->path();

		try {
			(new RemoteMediaLibrary($store))->ingest($file, 0);
			self::fail('Expected media_property_invalid.');
		} catch (RemoteMediaException $exception) {
			self::assertSame('media_property_invalid', $exception->reason());
		}

		self::assertSame(0, $store->findCalls);
		self::assertSame(0, $store->createCalls);
		self::assertFileDoesNotExist($path);
	}

	private function store(?int $existingId, int $createdId): RemoteMediaAttachmentStoreInterface
	{
		return new class($existingId, $createdId) implements RemoteMediaAttachmentStoreInterface {
			public int $findCalls = 0;
			public int $createCalls = 0;

			public function __construct(private ?int $existingId, private int $createdId)
			{
			}

			public function findBySha256(string $sha256): ?int
			{
				++$this->findCalls;
				self::assertSha($sha256);
				return $this->existingId;
			}

			public function create(RemoteMediaDownloadedFile $file, int $propertyId): int
			{
				++$this->createCalls;
				self::assertSha($file->sha256());
				if ($propertyId < 1) {
					throw new RuntimeException('Invalid property ID in test store.');
				}
				return $this->createdId;
			}

			private static function assertSha(string $sha256): void
			{
				if (preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
					throw new RuntimeException('Invalid test SHA-256.');
				}
			}
		};
	}

	private function downloadedFile(): RemoteMediaDownloadedFile
	{
		$path = tempnam(sys_get_temp_dir(), 'wla-library-');
		if (!is_string($path)) {
			throw new RuntimeException('Could not create test temporary file.');
		}
		file_put_contents($path, 'validated-image-payload');
		$this->temporaryFiles[] = $path;
		$sha256 = hash_file('sha256', $path);
		self::assertIsString($sha256);

		return new RemoteMediaDownloadedFile(
			'https://images.example.com/casa.jpg',
			$path,
			'image/jpeg',
			'jpg',
			(int) filesize($path),
			$sha256,
			100,
			100
		);
	}
}
