<?php

namespace WLA\Inmo\Import;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Properties\Sanitizer;

final class WordPressRemoteMediaPropertyStore implements RemoteMediaPropertyStoreInterface
{
	/** @param array<int,int> $attachmentIds */
	public function setGalleryIds(int $propertyId, array $attachmentIds): void
	{
		$this->assertProperty($propertyId);
		$attachmentIds = Sanitizer::positiveIntegerArray($attachmentIds);
		foreach ($attachmentIds as $attachmentId) {
			$this->assertImageAttachment($attachmentId);
		}

		$metaKey = MetaSchema::metaKey('gallery_ids');
		if ($metaKey === null) {
			throw new RemoteMediaException('media_gallery_schema_missing', 'Gallery metadata schema is unavailable.');
		}

		update_post_meta($propertyId, $metaKey, $attachmentIds);
		$actual = Sanitizer::positiveIntegerArray(get_post_meta($propertyId, $metaKey, true));
		if ($actual !== $attachmentIds) {
			throw new RemoteMediaException('media_gallery_write_failed', 'Remote media gallery attachment IDs could not be persisted.');
		}
	}

	public function setFeaturedImageId(int $propertyId, ?int $attachmentId): void
	{
		$this->assertProperty($propertyId);
		if ($attachmentId === null) {
			delete_post_thumbnail($propertyId);
			if ((int) get_post_thumbnail_id($propertyId) !== 0) {
				throw new RemoteMediaException('media_featured_clear_failed', 'Featured image could not be cleared.');
			}
			return;
		}

		$this->assertImageAttachment($attachmentId);
		set_post_thumbnail($propertyId, $attachmentId);
		if ((int) get_post_thumbnail_id($propertyId) !== $attachmentId) {
			throw new RemoteMediaException('media_featured_write_failed', 'Featured image attachment could not be persisted.');
		}
	}

	private function assertProperty(int $propertyId): void
	{
		if ($propertyId < 1 || get_post_type($propertyId) !== PostType::POST_TYPE) {
			throw new RemoteMediaException('media_property_invalid', 'Remote media target property does not exist.');
		}
	}

	private function assertImageAttachment(int $attachmentId): void
	{
		if ($attachmentId < 1 || get_post_type($attachmentId) !== 'attachment') {
			throw new RemoteMediaException('media_attachment_invalid', 'Remote media attachment ID is invalid.');
		}
		$mime = get_post_mime_type($attachmentId);
		if (!is_string($mime) || !str_starts_with($mime, 'image/')) {
			throw new RemoteMediaException('media_attachment_invalid', 'Remote media attachment is not an image.');
		}
	}
}
