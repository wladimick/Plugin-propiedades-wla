<?php

namespace WLA\Inmo\Import;

final class NetworkAddressPolicy
{
	/** @var array<int,string> */
	private const BLOCKED_V4 = array(
		'0.0.0.0/8',
		'10.0.0.0/8',
		'100.64.0.0/10',
		'127.0.0.0/8',
		'169.254.0.0/16',
		'172.16.0.0/12',
		'192.0.0.0/24',
		'192.0.2.0/24',
		'192.168.0.0/16',
		'198.18.0.0/15',
		'198.51.100.0/24',
		'203.0.113.0/24',
		'224.0.0.0/4',
		'240.0.0.0/4',
	);

	/** @var array<int,string> */
	private const BLOCKED_V6 = array(
		'::/128',
		'::1/128',
		'::ffff:0:0/96',
		'64:ff9b:1::/48',
		'100::/64',
		'2001:db8::/32',
		'2001:10::/28',
		'fc00::/7',
		'fe80::/10',
		'ff00::/8',
	);

	public static function isPublic(string $address): bool
	{
		$address = trim($address);
		if (filter_var($address, FILTER_VALIDATE_IP) === false) {
			return false;
		}

		$ranges = filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
			? self::BLOCKED_V4
			: self::BLOCKED_V6;

		foreach ($ranges as $cidr) {
			if (self::inCidr($address, $cidr)) {
				return false;
			}
		}

		return filter_var(
			$address,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		) !== false;
	}

	private static function inCidr(string $address, string $cidr): bool
	{
		[$network, $prefix] = explode('/', $cidr, 2);
		$addressBytes = inet_pton($address);
		$networkBytes = inet_pton($network);
		if ($addressBytes === false || $networkBytes === false || strlen($addressBytes) !== strlen($networkBytes)) {
			return false;
		}

		$prefixBits = (int) $prefix;
		$maxBits = strlen($addressBytes) * 8;
		if ($prefixBits < 0 || $prefixBits > $maxBits) {
			return false;
		}

		$fullBytes = intdiv($prefixBits, 8);
		$remainingBits = $prefixBits % 8;
		if ($fullBytes > 0 && substr($addressBytes, 0, $fullBytes) !== substr($networkBytes, 0, $fullBytes)) {
			return false;
		}

		if ($remainingBits === 0) {
			return true;
		}

		$mask = (0xFF << (8 - $remainingBits)) & 0xFF;
		return (ord($addressBytes[$fullBytes]) & $mask) === (ord($networkBytes[$fullBytes]) & $mask);
	}
}
