<?php

namespace WLA\Inmo\Import;

use Throwable;

final class RemoteMediaDownloader implements RemoteMediaDownloaderInterface
{
	private const DEFAULT_MAX_BYTES = 10485760;
	private const DEFAULT_TIMEOUT_SECONDS = 15;
	private const DEFAULT_MAX_REDIRECTS = 3;

	public function __construct(
		private RemoteMediaUrlPolicy $urlPolicy,
		private RemoteMediaHttpClientInterface $httpClient,
		private RemoteMediaTempFileFactoryInterface $tempFactory,
		private RemoteMediaImageInspector $imageInspector,
		private int $maxBytes = self::DEFAULT_MAX_BYTES,
		private int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS,
		private int $maxRedirects = self::DEFAULT_MAX_REDIRECTS
	) {
		if ($this->maxBytes < 1 || $this->timeoutSeconds < 1 || $this->maxRedirects < 0) {
			throw new \InvalidArgumentException('Remote media downloader limits are invalid.');
		}
	}

	public function download(string $url): RemoteMediaDownloadedFile
	{
		$validatedUrl = $this->urlPolicy->validate($url);
		$path = $this->tempFactory->create();

		try {
			$response = $this->httpClient->download(
				$validatedUrl,
				$path,
				$this->maxBytes,
				$this->timeoutSeconds,
				$this->maxRedirects
			);

			$statusCode = $response->statusCode();
			if ($statusCode < 200 || $statusCode >= 300) {
				$reason = in_array($statusCode, array(408, 425, 429), true) || $statusCode >= 500
					? 'media_http_transient_status'
					: 'media_http_status';
				throw new RemoteMediaException($reason, 'Remote media server returned a non-success HTTP status.');
			}
			$contentLength = $response->contentLength();
			if ($contentLength !== null && $contentLength > $this->maxBytes) {
				throw new RemoteMediaException('media_size_exceeded', 'Remote media Content-Length exceeds the configured byte limit.');
			}

			$this->assertPrivateTemporaryFile($path);
			$inspection = $this->imageInspector->inspect($path, $this->maxBytes);

			return new RemoteMediaDownloadedFile(
				$validatedUrl,
				$path,
				$inspection['mime'],
				$inspection['extension'],
				$inspection['bytes'],
				$inspection['sha256'],
				$inspection['width'],
				$inspection['height']
			);
		} catch (Throwable $exception) {
			$this->cleanup($path);
			if ($exception instanceof RemoteMediaException) {
				throw $exception;
			}
			throw new RemoteMediaException('media_download_unexpected', 'Unexpected remote media download failure.', $exception);
		}
	}

	private function assertPrivateTemporaryFile(string $path): void
	{
		if (!is_file($path)) {
			throw new RemoteMediaException('media_file_missing', 'Remote media transport did not produce the expected temporary file.');
		}

		if (!chmod($path, 0600)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Downloaded temp must remain private after transport.
			throw new RemoteMediaException('media_temp_permissions_failed', 'Downloaded remote media file could not be kept private.');
		}
		clearstatcache(true, $path);
		$permissions = fileperms($path);
		if ($permissions === false || (($permissions & 0777) !== 0600)) {
			throw new RemoteMediaException('media_temp_permissions_failed', 'Downloaded remote media file permissions are not private.');
		}
	}

	private function cleanup(string $path): void
	{
		if ($path !== '' && is_file($path)) {
			@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Fail-closed cleanup of plugin-created temporary file.
		}
	}
}
