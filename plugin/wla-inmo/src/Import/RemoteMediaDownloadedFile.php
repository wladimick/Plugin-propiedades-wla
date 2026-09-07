<?php

namespace WLA\Inmo\Import;

final class RemoteMediaDownloadedFile
{
	public function __construct(
		private string $url,
		private string $path,
		private string $mime,
		private string $extension,
		private int $bytes,
		private string $sha256,
		private int $width,
		private int $height
	) {
	}

	public function url(): string { return $this->url; }
	public function path(): string { return $this->path; }
	public function mime(): string { return $this->mime; }
	public function extension(): string { return $this->extension; }
	public function bytes(): int { return $this->bytes; }
	public function sha256(): string { return $this->sha256; }
	public function width(): int { return $this->width; }
	public function height(): int { return $this->height; }

	public function cleanup(): void
	{
		if ($this->path !== '' && is_file($this->path)) {
			@unlink($this->path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.unlink_unlink -- Explicit cleanup of plugin-created temporary file.
		}
	}
}
