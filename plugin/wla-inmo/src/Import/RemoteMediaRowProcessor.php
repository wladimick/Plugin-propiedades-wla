<?php

namespace WLA\Inmo\Import;

final class RemoteMediaRowProcessor implements RemoteMediaRowProcessorInterface
{
	private const TRANSIENT_REASONS = array(
		'media_dns_failed',
		'media_http_failed',
		'media_http_transient_status',
		'media_download_unexpected',
	);

	public function __construct(
		private RemoteMediaDownloaderInterface $downloader,
		private RemoteMediaLibraryInterface $library,
		private RemoteMediaPropertyStoreInterface $propertyStore,
		private int $maxAttempts = 2
	) {
		if ($this->maxAttempts < 1 || $this->maxAttempts > 5) {
			throw new \InvalidArgumentException('Remote media retry attempts must be between 1 and 5.');
		}
	}

	/**
	 * @param array<string,mixed> $mediaValues Canonical import-only media values.
	 * @return array<int,array{code:string,target:string}>
	 */
	public function process(int $propertyId, array $mediaValues): array
	{
		if ($propertyId < 1) {
			throw new RemoteMediaException('media_property_invalid', 'Remote media requires a persisted property ID.');
		}

		$warnings = array();
		$resolvedByUrl = array();

		foreach ($mediaValues as $target => $value) {
			$definition = TargetRegistry::definition((string) $target);
			if ($definition === null || ($definition['kind'] ?? '') !== 'media') {
				throw new RemoteMediaException('media_target_invalid', 'Remote media processor received an invalid target.');
			}
		}

		if (array_key_exists(TargetRegistry::MEDIA_GALLERY_URLS, $mediaValues)) {
			$value = $mediaValues[TargetRegistry::MEDIA_GALLERY_URLS];
			if (!is_array($value)) {
				throw new RemoteMediaException('media_gallery_payload_invalid', 'Remote media gallery payload must be a URL list.');
			}

			$attachmentIds = array();
			foreach ($value as $url) {
				if (!is_string($url) || trim($url) === '') {
					throw new RemoteMediaException('media_gallery_payload_invalid', 'Remote media gallery contains an invalid URL value.');
				}
				$attachmentId = $this->resolveUrl($propertyId, $url, TargetRegistry::MEDIA_GALLERY_URLS, $warnings, $resolvedByUrl);
				if ($attachmentId !== null) {
					$attachmentIds[] = $attachmentId;
				}
			}
			$this->propertyStore->setGalleryIds($propertyId, array_values(array_unique($attachmentIds)));
		}

		if (array_key_exists(TargetRegistry::MEDIA_FEATURED_IMAGE_URL, $mediaValues)) {
			$value = $mediaValues[TargetRegistry::MEDIA_FEATURED_IMAGE_URL];
			if ($value === null || $value === '') {
				$this->propertyStore->setFeaturedImageId($propertyId, null);
			} elseif (is_string($value)) {
				$attachmentId = $this->resolveUrl($propertyId, $value, TargetRegistry::MEDIA_FEATURED_IMAGE_URL, $warnings, $resolvedByUrl);
				if ($attachmentId !== null) {
					$this->propertyStore->setFeaturedImageId($propertyId, $attachmentId);
				}
			} else {
				throw new RemoteMediaException('media_featured_payload_invalid', 'Featured image payload must be one URL.');
			}
		}

		return $warnings;
	}

	/**
	 * @param array<int,array{code:string,target:string}> $warnings
	 * @param array<string,int> $resolvedByUrl
	 */
	private function resolveUrl(
		int $propertyId,
		string $url,
		string $target,
		array &$warnings,
		array &$resolvedByUrl
	): ?int {
		if (isset($resolvedByUrl[$url])) {
			return $resolvedByUrl[$url];
		}

		for ($attempt = 1; $attempt <= $this->maxAttempts; ++$attempt) {
			try {
				$downloaded = $this->downloader->download($url);
				$attachmentId = $this->library->ingest($downloaded, $propertyId);
				$resolvedByUrl[$url] = $attachmentId;
				return $attachmentId;
			} catch (RemoteMediaException $exception) {
				$transient = in_array($exception->reason(), self::TRANSIENT_REASONS, true);
				if ($transient && $attempt < $this->maxAttempts) {
					continue;
				}
				if ($transient) {
					throw $exception;
				}

				$warnings[] = array('code' => $exception->reason(), 'target' => $target);
				return null;
			}
		}

		throw new RemoteMediaException('media_retry_exhausted', 'Remote media retry budget was exhausted.');
	}
}
