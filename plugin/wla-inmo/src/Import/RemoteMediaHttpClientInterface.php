<?php

namespace WLA\Inmo\Import;

interface RemoteMediaHttpClientInterface
{
	public function download(
		string $url,
		string $destination,
		int $maxBytes,
		int $timeoutSeconds,
		int $maxRedirects
	): RemoteMediaHttpResult;
}
