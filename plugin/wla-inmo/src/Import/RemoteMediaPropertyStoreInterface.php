<?php

namespace WLA\Inmo\Import;

interface RemoteMediaPropertyStoreInterface
{
	/** @param array<int,int> $attachmentIds */
	public function setGalleryIds(int $propertyId, array $attachmentIds): void;

	public function setFeaturedImageId(int $propertyId, ?int $attachmentId): void;
}
