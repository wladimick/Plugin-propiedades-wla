<?php

namespace WLA\Inmo\Core;

final class Requirements
{
	public const MIN_PHP = '8.1';
	public const MIN_WP = '6.6';

	public static function supportsPhp(string $version): bool
	{
		return version_compare($version, self::MIN_PHP, '>=');
	}

	public static function supportsWordPress(string $version): bool
	{
		return version_compare($version, self::MIN_WP, '>=');
	}

	/**
	 * @return array<int, string>
	 */
	public static function failures(string $phpVersion, string $wordpressVersion): array
	{
		$failures = array();

		if (!self::supportsPhp($phpVersion)) {
			$failures[] = sprintf('PHP %s or newer is required.', self::MIN_PHP);
		}

		if (!self::supportsWordPress($wordpressVersion)) {
			$failures[] = sprintf('WordPress %s or newer is required.', self::MIN_WP);
		}

		return $failures;
	}
}
