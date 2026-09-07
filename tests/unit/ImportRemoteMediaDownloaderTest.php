<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\DnsResolverInterface;
use WLA\Inmo\Import\RemoteMediaDownloader;
use WLA\Inmo\Import\RemoteMediaException;
use WLA\Inmo\Import\RemoteMediaHttpClientInterface;
use WLA\Inmo\Import\RemoteMediaHttpResult;
use WLA\Inmo\Import\RemoteMediaImageInspector;
use WLA\Inmo\Import\RemoteMediaTempFileFactoryInterface;
use WLA\Inmo\Import\RemoteMediaUrlPolicy;

final class ImportRemoteMediaDownloaderTest extends TestCase
{
	/** @var array<int,string> */
	private array $temporaryFiles = array();

	protected function tearDown(): void
	{
		foreach ($this->temporaryFiles as $path) {
			if (is_file($path)) {
				unlink($path);
			}
		$this->temporaryFiles = array();
	}

	public function testValidPngIsDownloadedBoundedAndInspected(): void
	{
		$client = $this->client($this->pngBytes(), 200, null);
		$downloader = $this->downloader($client, 1024 * 1024);
		$file = $downloader->download('https://images.example.com/casa.png');

		self::assertSame('image/png', $file->mime());
		self::assertSame('png', $file->extension());
		self::assertSame(1, $file->width());
		self::assertSame(1, $file->height());
		self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $file->sha256());
		self::assertSame(1024 * 1024, $client->maxBytes);
		self::assertSame(15, $client->timeoutSeconds);
		self::assertSame(3, $client->maxRedirects);
		self::assertFileExists($file->path());
		$file->cleanup();
		self::assertFileDoesNotExist($file->path());
	}

	public function testPolicyFailureOccursBeforeHttpAndTempCreation(): void
	{
		$client = $this->client($this->pngBytes(), 200, null);
		$factory = $this->tempFactory();
		$downloader = $this->downloader($client, 1024, $factory);

		$this->expectReason('blocked_media_address', fn () => $downloader->download('http://127.0.0.1/image.png'));
		self::assertSame(0, $client->calls);
		self::assertSame(0, $factory->calls);
	}

	public function testContentLengthOverLimitFailsAndCleansTemporaryFile(): void
	{
		$client = $this->client($this->pngBytes(), 200, 2049);
		$factory = $this->tempFactory();
		$downloader = $this->downloader($client, 2048, $factory);

		$this->expectReason('media_size_exceeded', fn () => $downloader->download('https://images.example.com/a.png'));
		self::assertNotNull($factory->lastPath);
		self::assertFileDoesNotExist((string) $factory->lastPath);
	}

	public function testStreamOverrunFailsEvenWithoutContentLength(): void
	{
		$client = $this->client(str_repeat('A', 2049), 200, null);
		$factory = $this->tempFactory();
		$downloader = $this->downloader($client, 2048, $factory);

		$this->expectReason('media_size_exceeded', fn () => $downloader->download('https://images.example.com/a.png'));
		self::assertFileDoesNotExist((string) $factory->lastPath);
	}

	public function testSvgAndNonRasterPayloadsAreRejectedAndCleaned(): void
	{
		$client = $this->client('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 200, null);
		$factory = $this->tempFactory();
		$downloader = $this->downloader($client, 4096, $factory);

		$this->expectReason('media_mime_not_allowed', fn () => $downloader->download('https://images.example.com/a.svg'));
		self::assertFileDoesNotExist((string) $factory->lastPath);
	}

	public function testNonSuccessHttpStatusIsRejectedAndCleaned(): void
	{
		$client = $this->client($this->pngBytes(), 404, null);
		$factory = $this->tempFactory();
		$downloader = $this->downloader($client, 4096, $factory);

		$this->expectReason('media_http_status', fn () => $downloader->download('https://images.example.com/missing.png'));
		self::assertFileDoesNotExist((string) $factory->lastPath);
	}

	private function downloader(object $client, int $maxBytes, ?object $factory = null): RemoteMediaDownloader
	{
		$resolver = new class implements DnsResolverInterface {
			public function resolve(string $host): array
			{
				return $host === 'images.example.com' ? array('93.184.216.34') : array();
			}
		};

		return new RemoteMediaDownloader(
			new RemoteMediaUrlPolicy($resolver),
			$client,
			$factory ?? $this->tempFactory(),
			new RemoteMediaImageInspector(),
			$maxBytes,
			15,
			3
		);
	}

	private function client(string $payload, int $status, ?int $contentLength): object
	{
		return new class($payload, $status, $contentLength) implements RemoteMediaHttpClientInterface {
			public int $calls = 0;
			public int $maxBytes = 0;
			public int $timeoutSeconds = 0;
			public int $maxRedirects = 0;

			public function __construct(private string $payload, private int $status, private ?int $contentLength)
			{
			}

			public function download(string $url, string $destination, int $maxBytes, int $timeoutSeconds, int $maxRedirects): RemoteMediaHttpResult
			{
				unset($url);
				++$this->calls;
				$this->maxBytes = $maxBytes;
				$this->timeoutSeconds = $timeoutSeconds;
				$this->maxRedirects = $maxRedirects;
				file_put_contents($destination, $this->payload);
				return new RemoteMediaHttpResult($this->status, $this->contentLength);
			}
		};
	}

	private function tempFactory(): object
	{
		$test = $this;
		return new class($test) implements RemoteMediaTempFileFactoryInterface {
			public int $calls = 0;
			public ?string $lastPath = null;

			public function __construct(private ImportRemoteMediaDownloaderTest $test)
			{
			}

			public function create(): string
			{
				++$this->calls;
				$path = tempnam(sys_get_temp_dir(), 'wla-media-');
				if (!is_string($path)) {
					throw new RuntimeException('Could not create test temporary file.');
				}
				chmod($path, 0600);
				$this->lastPath = $path;
				$this->test->rememberTemporaryFile($path);
				return $path;
			}
		};
	}

	public function rememberTemporaryFile(string $path): void
	{
		$this->temporaryFiles[] = $path;
	}

	private function pngBytes(): string
	{
		$bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WlZ+kcAAAAASUVORK5CYII=', true);
		self::assertIsString($bytes);
		return $bytes;
	}

	private function expectReason(string $reason, callable $callback): void
	{
		try {
			$callback();
			self::fail('Expected RemoteMediaException: ' . $reason);
		} catch (RemoteMediaException $exception) {
			self::assertSame($reason, $exception->reason());
		}
	}
}
