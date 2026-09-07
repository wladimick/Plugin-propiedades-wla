<?php

namespace WLA\Inmo\Import;

final class RemoteMediaWordPressHttpClient implements RemoteMediaHttpClientInterface
{
	public function download(
		string $url,
		string $destination,
		int $maxBytes,
		int $timeoutSeconds,
		int $maxRedirects
	): RemoteMediaHttpResult {
		if ($destination === '' || $maxBytes < 1 || $timeoutSeconds < 1 || $maxRedirects < 0) {
			throw new \InvalidArgumentException('Remote media transport arguments are invalid.');
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => $timeoutSeconds,
				'redirection'         => $maxRedirects,
				'stream'              => true,
				'filename'            => $destination,
				'limit_response_size' => $maxBytes + 1,
				'reject_unsafe_urls'  => true,
				'sslverify'           => true,
				'headers'             => array(
					'Accept' => 'image/jpeg,image/png,image/webp',
				),
			)
		);

		if (is_wp_error($response)) {
			throw new RemoteMediaException('media_http_failed', 'WordPress safe HTTP transport failed.');
		}

		$statusCode = (int) wp_remote_retrieve_response_code($response);
		$contentLength = null;
		$header = wp_remote_retrieve_header($response, 'content-length');
		if (is_scalar($header)) {
			$header = trim((string) $header);
			if ($header !== '' && ctype_digit($header)) {
				$contentLength = (int) $header;
			}
		}

		return new RemoteMediaHttpResult($statusCode, $contentLength);
	}
}
