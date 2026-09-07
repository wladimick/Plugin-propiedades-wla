<?php

namespace WLA\Inmo\Import;

final class RemoteMediaWordPressTempFileFactory implements RemoteMediaTempFileFactoryInterface
{
	public function create(): string
	{
		$path = wp_tempnam('wla-inmo-remote-image');
		if (!is_string($path) || $path === '' || !is_file($path)) {
			throw new RemoteMediaException('media_temp_failed', 'A server-generated remote media temporary file could not be created.');
		}

		if (!chmod($path, 0600)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Private import temp must be fail-closed.
			@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Cleanup of plugin-created temporary file.
			throw new RemoteMediaException('media_temp_permissions_failed', 'Remote media temporary file could not be made private.');
		}

		clearstatcache(true, $path);
		$permissions = fileperms($path);
		if ($permissions === false || (($permissions & 0777) !== 0600)) {
			@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Cleanup of plugin-created temporary file.
			throw new RemoteMediaException('media_temp_permissions_failed', 'Remote media temporary file permissions are not private.');
		}

		return $path;
	}
}
