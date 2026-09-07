<?php

namespace WLA\Inmo\Import;

final class RemoteMediaHttpResult
{
	private int $statusCode;
	private ?int $contentLength;

	public function __construct(int $statusCode, ?int $contentLength = null)
	{
		if ($statusCode < 100 || $statusCode > 599) {
			throw new \InvalidArgumentException('Remote media HTTP status must be between 100 and 599.');
		}
		if ($contentLength !== null && $contentLength < 0) {
			throw new \InvalidArgumentException('Remote media Content-Length cannot be negative.');
		}

		$this->statusCode = $statusCode;
		$this->contentLength = $contentLength;
	}

	public function statusCode(): int
	{
		return $this->statusCode;
	}

	public function contentLength(): ?int
	{
		return $this->contentLength;
	}
}
