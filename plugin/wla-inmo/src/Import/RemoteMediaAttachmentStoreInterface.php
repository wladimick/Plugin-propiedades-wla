<?php

namespace WLA\Inmo\Import;

interface RemoteMediaAttachmentStoreInterface
{
	public function findBySha256(string $sha256): ?int;

	public function create(RemoteMediaDownloadedFile $file, int $propertyId): int;
}
