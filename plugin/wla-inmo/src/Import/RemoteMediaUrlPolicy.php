<?php

namespace WLA\Inmo\Import;

final class RemoteMediaUrlPolicy
{
	private const DEFAULT_MAX_URLS = 20;
	private const MAX_URL_BYTES = 2048;

	private DnsResolverInterface $resolver;
	private int $maxUrls;

	public function __construct(?DnsResolverInterface $resolver = null, int $maxUrls = self::DEFAULT_MAX_URLS)
	{
		if ($maxUrls < 1 || $maxUrls > 100) {
			throw new \InvalidArgumentException('Remote media URL limit must be between 1 and 100.');
		}

		$this->resolver = $resolver ?? new SystemDnsResolver();
		$this->maxUrls = $maxUrls;
	}

	/**
	 * Validate and deduplicate remote image URLs without performing HTTP requests.
	 *
	 * @param array<int,string> $urls
	 * @return array<int,string>
	 */
	public function validateList(array $urls): array
	{
		if (count($urls) > $this->maxUrls) {
			throw new RemoteMediaException('media_url_limit_exceeded', 'Remote media URL count exceeds the configured limit.');
		}

		$validated = array();
		foreach ($urls as $url) {
			$validated[] = $this->validate($url);
		}

		return array_values(array_unique($validated));
	}

	public function validate(string $url): string
	{
		$url = trim($url);
		if ($url === '' || strlen($url) > self::MAX_URL_BYTES || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
			throw new RemoteMediaException('invalid_media_url', 'Remote media URL is empty, too long or contains control characters.');
		}

		$parts = parse_url($url);
		if (!is_array($parts)) {
			throw new RemoteMediaException('invalid_media_url', 'Remote media URL cannot be parsed.');
		}

		$scheme = strtolower((string) ($parts['scheme'] ?? ''));
		if (!in_array($scheme, array('http', 'https'), true)) {
			throw new RemoteMediaException('invalid_media_scheme', 'Only HTTP and HTTPS remote media URLs are allowed.');
		}
		if (isset($parts['user']) || isset($parts['pass'])) {
			throw new RemoteMediaException('media_credentials_forbidden', 'Remote media URLs cannot contain embedded credentials.');
		}
		if (isset($parts['fragment'])) {
			throw new RemoteMediaException('media_fragment_forbidden', 'Remote media URLs cannot contain fragments.');
		}

		$host = trim((string) ($parts['host'] ?? ''), '[]');
		$host = strtolower(rtrim($host, '.'));
		if ($host === '') {
			throw new RemoteMediaException('missing_media_host', 'Remote media URL must contain a hostname.');
		}
		if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
			throw new RemoteMediaException('blocked_media_host', 'Local hostnames are not allowed for remote media.');
		}
		if (filter_var($host, FILTER_VALIDATE_IP) === false && !str_contains($host, '.')) {
			throw new RemoteMediaException('blocked_media_host', 'Single-label hostnames are not allowed for remote media.');
		}

		$port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
		if (!in_array($port, array(80, 443), true)) {
			throw new RemoteMediaException('blocked_media_port', 'Remote media URL uses a port that is not allowed.');
		}

		$addresses = filter_var($host, FILTER_VALIDATE_IP) !== false
			? array($host)
			: $this->resolver->resolve($host);
		if ($addresses === array()) {
			throw new RemoteMediaException('media_dns_failed', 'Remote media hostname could not be resolved.');
		}

		foreach ($addresses as $address) {
			if (!NetworkAddressPolicy::isPublic($address)) {
				throw new RemoteMediaException('blocked_media_address', 'Remote media hostname resolves to a non-public network address.');
			}
		}

		if (filter_var($url, FILTER_VALIDATE_URL) === false) {
			throw new RemoteMediaException('invalid_media_url', 'Remote media URL is not a valid absolute URL.');
		}

		return $url;
	}
}
