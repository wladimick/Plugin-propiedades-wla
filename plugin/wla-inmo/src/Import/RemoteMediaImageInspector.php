<?php

namespace WLA\Inmo\Import;

final class RemoteMediaImageInspector
{
	private const DEFAULT_MAX_WIDTH = 12000;
	private const DEFAULT_MAX_HEIGHT = 12000;
	private const DEFAULT_MAX_PIXELS = 40000000;

	private int $maxWidth;
	private int $maxHeight;
	private int $maxPixels;

	public function __construct(
		int $maxWidth = self::DEFAULT_MAX_WIDTH,
		int $maxHeight = self::DEFAULT_MAX_HEIGHT,
		int $maxPixels = self::DEFAULT_MAX_PIXELS
	) {
		if ($maxWidth < 1 || $maxHeight < 1 || $maxPixels < 1) {
			throw new \InvalidArgumentException('Remote image dimension limits must be positive.');
		}
		$this->maxWidth = $maxWidth;
		$this->maxHeight = $maxHeight;
		$this->maxPixels = $maxPixels;
	}

	/**
	 * @return array{mime:string,extension:string,bytes:int,sha256:string,width:int,height:int}
	 */
	public function inspect(string $path, int $maxBytes): array
	{
		if ($path === '' || !is_file($path) || !is_readable($path)) {
			throw new RemoteMediaException('media_file_missing', 'Downloaded remote media file is missing or unreadable.');
		}

		$bytes = filesize($path);
		if ($bytes === false || $bytes < 1) {
			throw new RemoteMediaException('media_empty', 'Downloaded remote media file is empty.');
		}
		if ($bytes > $maxBytes) {
			throw new RemoteMediaException('media_size_exceeded', 'Downloaded remote media exceeds the configured byte limit.');
		}

		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$mime = $finfo->file($path);
		if (!is_string($mime)) {
			throw new RemoteMediaException('media_mime_failed', 'Downloaded remote media MIME type could not be determined.');
		}
		$mime = strtolower(trim($mime));
		$extension = match ($mime) {
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
			default      => '',
		};
		if ($extension === '') {
			throw new RemoteMediaException('media_mime_not_allowed', 'Remote media MIME type is not allowed.');
		}

		$image = getimagesize($path);
		if (!is_array($image)) {
			throw new RemoteMediaException('media_image_invalid', 'Downloaded remote media is not a valid raster image.');
		}
		$width = (int) ($image[0] ?? 0);
		$height = (int) ($image[1] ?? 0);
		$imageType = (int) ($image[2] ?? 0);
		$detectedMime = match ($imageType) {
			IMAGETYPE_JPEG => 'image/jpeg',
			IMAGETYPE_PNG  => 'image/png',
			IMAGETYPE_WEBP => 'image/webp',
			default        => '',
		};
		if ($detectedMime === '' || $detectedMime !== $mime) {
			throw new RemoteMediaException('media_type_mismatch', 'Remote media byte signature does not match the detected MIME type.');
		}
		if ($width < 1 || $height < 1 || $width > $this->maxWidth || $height > $this->maxHeight || ($width * $height) > $this->maxPixels) {
			throw new RemoteMediaException('media_dimensions_exceeded', 'Remote media image dimensions exceed the configured safety limits.');
		}

		$sha256 = hash_file('sha256', $path);
		if (!is_string($sha256) || preg_match('/^[a-f0-9]{64}$/', $sha256) !== 1) {
			throw new RemoteMediaException('media_hash_failed', 'Remote media SHA-256 could not be generated.');
		}

		return array(
			'mime'      => $mime,
			'extension' => $extension,
			'bytes'     => (int) $bytes,
			'sha256'    => $sha256,
			'width'     => $width,
			'height'    => $height,
		);
	}
}
