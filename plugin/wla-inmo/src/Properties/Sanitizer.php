<?php

namespace WLA\Inmo\Properties;

final class Sanitizer
{
	public static function text($value): string
	{
		if (!is_scalar($value) && $value !== null) {
			return '';
		}

		$text = trim((string) $value);

		if (function_exists('sanitize_text_field')) {
			return sanitize_text_field($text);
		}

		$text = strip_tags($text);
		$text = preg_replace('/[\r\n\t]+/', ' ', $text) ?? $text;
		$text = preg_replace('/\s{2,}/', ' ', $text) ?? $text;

		return trim($text);
	}

	public static function textarea($value): string
	{
		if (!is_scalar($value) && $value !== null) {
			return '';
		}

		$text = trim((string) $value);

		if (function_exists('sanitize_textarea_field')) {
			return sanitize_textarea_field($text);
		}

		return trim(strip_tags($text));
	}

	public static function key($value): string
	{
		$value = self::text($value);

		if (function_exists('sanitize_key')) {
			return sanitize_key($value);
		}

		$value = strtolower($value);

		return preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '';
	}

	public static function boolean($value): bool
	{
		if (is_bool($value)) {
			return $value;
		}

		if (is_int($value) || is_float($value)) {
			return (float) $value === 1.0;
		}

		if (!is_string($value)) {
			return false;
		}

		$normalized = strtolower(trim($value));

		return in_array($normalized, array('1', 'true', 'yes', 'on', 'si', 'sí'), true);
	}

	public static function nonNegativeInteger($value): ?int
	{
		if ($value === '' || $value === null || is_bool($value)) {
			return null;
		}

		if (is_string($value)) {
			$value = trim($value);
		}

		if (filter_var($value, FILTER_VALIDATE_INT) === false) {
			return null;
		}

		$integer = (int) $value;

		return $integer >= 0 ? $integer : null;
	}

	public static function nonNegativeNumber($value): ?float
	{
		$number = self::number($value);

		if ($number === null || $number < 0) {
			return null;
		}

		return $number;
	}

	public static function number($value): ?float
	{
		if ($value === '' || $value === null || is_bool($value)) {
			return null;
		}

		if (is_int($value) || is_float($value)) {
			return is_finite((float) $value) ? (float) $value : null;
		}

		if (!is_string($value)) {
			return null;
		}

		$normalized = trim($value);

		if ($normalized === '' || !is_numeric($normalized)) {
			return null;
		}

		$number = (float) $normalized;

		return is_finite($number) ? $number : null;
	}

	public static function currency($value): string
	{
		$currency = strtoupper(self::text($value));

		return in_array($currency, array('CLP', 'UF', 'USD'), true) ? $currency : '';
	}

	public static function date($value): string
	{
		$date = self::text($value);

		return self::isValidDate($date) ? $date : '';
	}

	public static function latitude($value): ?float
	{
		$number = self::number($value);

		return $number !== null && $number >= -90 && $number <= 90 ? $number : null;
	}

	public static function longitude($value): ?float
	{
		$number = self::number($value);

		return $number !== null && $number >= -180 && $number <= 180 ? $number : null;
	}

	/**
	 * @return array<int, int>
	 */
	public static function positiveIntegerArray($value): array
	{
		if (!is_array($value)) {
			return array();
		}

		$clean = array();

		foreach ($value as $item) {
			$integer = self::nonNegativeInteger($item);

			if ($integer !== null && $integer > 0) {
				$clean[] = $integer;
			}
		}

		return array_values(array_unique($clean));
	}

	/**
	 * @return array<int, string>
	 */
	public static function httpUrlArray($value): array
	{
		if (!is_array($value)) {
			return array();
		}

		$clean = array();

		foreach ($value as $item) {
			$url = self::httpUrl($item);

			if ($url !== '') {
				$clean[] = $url;
			}
		}

		return array_values(array_unique($clean));
	}

	public static function httpUrl($value): string
	{
		if (!is_scalar($value) && $value !== null) {
			return '';
		}

		$url = trim((string) $value);

		if ($url === '') {
			return '';
		}

		if (function_exists('esc_url_raw')) {
			return (string) esc_url_raw($url, array('http', 'https'));
		}

		$validated = filter_var($url, FILTER_VALIDATE_URL);

		if (!is_string($validated)) {
			return '';
		}

		$scheme = strtolower((string) parse_url($validated, PHP_URL_SCHEME));

		return in_array($scheme, array('http', 'https'), true) ? $validated : '';
	}

	public static function isValidDate(string $value): bool
	{
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches)) {
			return false;
		}

		return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]);
	}
}
