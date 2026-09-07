<?php

namespace WLA\Inmo\Import;

use Throwable;

final class RemoteMediaLibrary
{
	public function __construct(private RemoteMediaAttachmentStoreInterface $store)
	{
	}

	public function ingest(RemoteMediaDownloadedFile $file, int $propertyId): int
	{
		try {
			if ($propertyId < 1) {
				throw new RemoteMediaException('media_property_invalid', 'Remote media requires a persisted property ID.');
			}

			$existing = $this->store->findBySha256($file->sha256());
			if ($existing !== null) {
				if ($existing < 1) {
					throw new RemoteMediaException('media_attachment_invalid', 'Remote media deduplication returned an invalid attachment ID.');
				}
				return $existing;
			}

			$attachmentId = $this->store->create($file, $propertyId);
			if ($attachmentId < 1) {
				throw new RemoteMediaException('media_attachment_invalid', 'Remote media attachment creation returned an invalid ID.');
			}

			return $attachmentId;
		} catch (Throwable $exception) {
			if ($exception instanceof RemoteMediaException) {
				throw $exception;
			}
			throw new RemoteMediaException('media_library_unexpected', 'Unexpected Media Library persistence failure.', $exception);
		} finally {
			$file->cleanup();
		}
	}
}
