<?php

namespace WLA\Inmo\Import;

final class SystemDnsResolver implements DnsResolverInterface
{
	/** @return array<int,string> */
	public function resolve(string $host): array
	{
		$host = trim($host, " \t\n\r\0\x0B[]");
		if ($host === '') {
			return array();
		}

		if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
			return array($host);
		}

		$addresses = array();
		if (function_exists('dns_get_record')) {
			$records = @dns_get_record($host, DNS_A | DNS_AAAA); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DNS failure is represented by an empty result.
			if (is_array($records)) {
				foreach ($records as $record) {
					if (isset($record['ip']) && is_string($record['ip'])) {
						$addresses[] = $record['ip'];
					}
					if (isset($record['ipv6']) && is_string($record['ipv6'])) {
						$addresses[] = $record['ipv6'];
					}
				}
			}
		}

		if ($addresses === array() && function_exists('gethostbynamel')) {
			$ipv4 = @gethostbynamel($host); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DNS failure is represented by an empty result.
			if (is_array($ipv4)) {
				$addresses = array_merge($addresses, $ipv4);
			}
		}

		$addresses = array_values(array_unique(array_filter(
			array_map('strval', $addresses),
			static fn (string $address): bool => filter_var($address, FILTER_VALIDATE_IP) !== false
		)));

		return $addresses;
	}
}
