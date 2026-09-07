<?php

namespace WLA\Inmo\Import;

use WLA\Inmo\Properties\PostType;

final class WordPressRemoteMediaAttachmentStore implements RemoteMediaAttachmentStoreInterface
{
	public const HASH_META = '_wla_inmo_remote_media_sha256';
	public const SOURCE_URL_HASH_META = '_wla_inmo_remote_media_source_url_sha256';

	public function findBySha256(string $sha256): ?int
	{
		$this->assertSha256($sha256);

		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 5,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'meta_key'       => self::HASH_META,
				'meta_value'     => $sha256,
			)
		);

		if (!is_array($ids)) {
			return null;
		}

		foreach ($ids as $id) {
			$attachmentId = (int) $id;
			if ($attachmentId < 1 || get_post_type($attachmentId) !== 'attachment') {
				continue;
			}
			$mime = get_post_mime_type($attachmentId);
			if (!is_string($mime) || !str_starts_with($mime, 'image/')) {
				continue;
			}
			$path = get_attached_file($attachmentId);
			if (!is_string($path) || $path === '' || !is_file($path)) {
				continue;
			}

			return $attachmentId;
		}

		return null;
	}

	public function create(RemoteMediaDownloadedFile $file, int $propertyId): int
	{
		if ($propertyId < 1 || get_post_type($propertyId) !== PostType::POST_TYPE) {
			throw new RemoteMediaException('media_property_invalid', 'Remote media target property does not exist.');
		}
		$this->assertSha256($file->sha256());
		$this->loadMediaFunctions();

		$name = 'wla-remote-' . substr($file->sha256(), 0, 24) . '.' . $file->extension();
		$fileArray = array(
			'name'     => $name,
			'type'     => $file->mime(),
			'tmp_name' => $file->path(),
			'error'    => UPLOAD_ERR_OK,
			'size'     => $file->bytes(),
		);

		$attachmentId = media_handle_sideload(
			$fileArray,
			$propertyId,
			__('Imagen de propiedad', 'wla-inmo')
		);
		if (is_wp_error($attachmentId) || (int) $attachmentId < 1) {
			throw new RemoteMediaException('media_sideload_failed', 'WordPress could not create the remote media attachment.');
		}
		$attachmentId = (int) $attachmentId;

		try {
			$this->assertCreatedAttachment($attachmentId, $file);
			update_post_meta($attachmentId, self::HASH_META, $file->sha256());
			update_post_meta($attachmentId, self::SOURCE_URL_HASH_META, hash('sha256', $file->url()));

			if ((string) get_post_meta($attachmentId, self::HASH_META, true) !== $file->sha256()) {
				throw new RemoteMediaException('media_attachment_meta_failed', 'Remote media attachment hash metadata could not be persisted.');
			}
		} catch (\Throwable $exception) {
			wp_delete_attachment($attachmentId, true);
			if ($exception instanceof RemoteMediaException) {
				throw $exception;
			}
			throw new RemoteMediaException('media_attachment_verify_failed', 'Remote media attachment verification failed.', $exception);
		}

		return $attachmentId;
	}

	private function assertCreatedAttachment(int $attachmentId, RemoteMediaDownloadedFile $file): void
	{
		if (get_post_type($attachmentId) !== 'attachment') {
			throw new RemoteMediaException('media_attachment_invalid', 'Created remote media object is not an attachment.');
		}
		$mime = get_post_mime_type($attachmentId);
		if (!is_string($mime) || $mime !== $file->mime()) {
			throw new RemoteMediaException('media_attachment_mime_mismatch', 'Created attachment MIME does not match the validated remote image.');
		}
		$path = get_attached_file($attachmentId);
		if (!is_string($path) || $path === '' || !is_file($path)) {
			throw new RemoteMediaException('media_attachment_file_missing', 'Created attachment file is missing from WordPress uploads.');
		}
	}

	private function assertSha256(string $sha256): void
	{
		if (preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
			throw new RemoteMediaException('media_hash_invalid', 'Remote media SHA-256 is invalid.');
		}
	}

	private function loadMediaFunctions(): void
	{
		if (function_exists('media_handle_sideload')) {
			return;
		}

		$root = defined('ABSPATH') ? (string) constant('ABSPATH') : '';
		if ($root === '') {
			throw new RemoteMediaException('media_wordpress_unavailable', 'WordPress media APIs are unavailable.');
		}

		foreach (array('media.php', 'file.php', 'image.php') as $include) {
			$path = rtrim($root, '/\\') . '/wp-admin/includes/' . $include;
			if (!is_file($path)) {
				throw new RemoteMediaException('media_wordpress_unavailable', 'Required WordPress media API file is unavailable.');
			}
			require_once $path;
		}

		if (!function_exists('media_handle_sideload')) {
			throw new RemoteMediaException('media_wordpress_unavailable', 'WordPress media sideload API is unavailable.');
		}
	}
}
