<?php

namespace WLA\Inmo\Settings;

final class Repository
{
	/** @var array<string,string>|null */
	private static ?array $cache = null;

	/** @return array<string,string> */
	public static function all(): array
	{
		if (self::$cache !== null) {
			return self::$cache;
		}

		$stored = function_exists('get_option') ? get_option(Schema::OPTION_NAME, array()) : array();
		$stored = is_array($stored) ? $stored : array();
		$defaults = Schema::defaults();

		self::$cache = Schema::sanitize(array_merge($defaults, $stored));

		return self::$cache;
	}

	public static function get(string $key, $fallback = null)
	{
		$settings = self::all();

		return array_key_exists($key, $settings) ? $settings[$key] : $fallback;
	}

	public static function resetCache(): void
	{
		self::$cache = null;
	}
}
