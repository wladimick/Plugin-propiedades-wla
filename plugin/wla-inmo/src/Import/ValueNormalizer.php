<?php

namespace WLA\Inmo\Import;

use WLA\Inmo\Properties\Sanitizer;

final class ValueNormalizer
{
	private const ALLOWED_STATUSES = array('available', 'reserved', 'sold', 'rented', 'unavailable');

	/**
	 * @param array<string,mixed> $definition Canonical target definition.
	 */
	public static function normalize($rawValue, array $definition, ?string $separator = null): NormalizedValue
	{
		$validator = (string) ($definition['validator'] ?? 'text');

		return match ($validator) {
			'text'                 => NormalizedValue::valid(Sanitizer::text($rawValue)),
			'textarea'             => NormalizedValue::valid(Sanitizer::textarea($rawValue)),
			'boolean'              => self::boolean($rawValue),
			'integer'              => self::integer($rawValue),
			'number'               => self::number($rawValue),
			'non_negative_integer' => self::nonNegativeInteger($rawValue),
			'non_negative_number'  => self::nonNegativeNumber($rawValue),
			'date'                 => self::date($rawValue),
			'currency'             => self::currency($rawValue),
			'status'               => self::status($rawValue),
			'latitude'             => self::latitude($rawValue),
			'longitude'            => self::longitude($rawValue),
			'url_list'             => self::urlList($rawValue, $separator),
			'taxonomy'             => self::taxonomyValue($rawValue, $separator, !empty($definition['multiple'])),
			default                => NormalizedValue::invalid('unsupported_validator'),
		};
	}

	private static function boolean($value): NormalizedValue
	{
		if (is_bool($value)) {
			return NormalizedValue::valid($value);
		}

		if (is_int($value) || is_float($value)) {
			if ((float) $value === 1.0) {
				return NormalizedValue::valid(true);
			}

			if ((float) $value === 0.0) {
				return NormalizedValue::valid(false);
			}

			return NormalizedValue::invalid('invalid_boolean');
		}

		if (!is_string($value)) {
			return NormalizedValue::invalid('invalid_boolean');
		}

		$normalized = strtolower(trim($value));
		if (in_array($normalized, array('1', 'true', 'yes', 'on', 'si', 'sí'), true)) {
			return NormalizedValue::valid(true);
		}

		if (in_array($normalized, array('0', 'false', 'no', 'off'), true)) {
			return NormalizedValue::valid(false);
		}

		return NormalizedValue::invalid('invalid_boolean');
	}

	private static function integer($value): NormalizedValue
	{
		if ($value === '' || $value === null || is_bool($value) || filter_var($value, FILTER_VALIDATE_INT) === false) {
			return NormalizedValue::invalid('invalid_integer');
		}

		return NormalizedValue::valid((int) $value);
	}

	private static function number($value): NormalizedValue
	{
		$number = Sanitizer::number($value);

		return $number === null ? NormalizedValue::invalid('invalid_number') : NormalizedValue::valid($number);
	}

	private static function nonNegativeInteger($value): NormalizedValue
	{
		$integer = Sanitizer::nonNegativeInteger($value);

		return $integer === null ? NormalizedValue::invalid('invalid_non_negative_integer') : NormalizedValue::valid($integer);
	}

	private static function nonNegativeNumber($value): NormalizedValue
	{
		$number = Sanitizer::nonNegativeNumber($value);

		return $number === null ? NormalizedValue::invalid('invalid_non_negative_number') : NormalizedValue::valid($number);
	}

	private static function date($value): NormalizedValue
	{
		$date = Sanitizer::date($value);

		return $date === '' ? NormalizedValue::invalid('invalid_date') : NormalizedValue::valid($date);
	}

	private static function currency($value): NormalizedValue
	{
		$currency = Sanitizer::currency($value);

		return $currency === '' ? NormalizedValue::invalid('invalid_currency') : NormalizedValue::valid($currency);
	}

	private static function status($value): NormalizedValue
	{
		$status = Sanitizer::key($value);

		return in_array($status, self::ALLOWED_STATUSES, true)
			? NormalizedValue::valid($status)
			: NormalizedValue::invalid('invalid_status');
	}

	private static function latitude($value): NormalizedValue
	{
		$latitude = Sanitizer::latitude($value);

		return $latitude === null ? NormalizedValue::invalid('invalid_latitude') : NormalizedValue::valid($latitude);
	}

	private static function longitude($value): NormalizedValue
	{
		$longitude = Sanitizer::longitude($value);

		return $longitude === null ? NormalizedValue::invalid('invalid_longitude') : NormalizedValue::valid($longitude);
	}

	private static function urlList($value, ?string $separator): NormalizedValue
	{
		$items = self::splitValues($value, $separator);
		$urls = array();

		foreach ($items as $item) {
			$url = Sanitizer::httpUrl($item);
			if ($url === '') {
				return NormalizedValue::invalid('invalid_url');
			}
			$urls[] = $url;
		}

		return NormalizedValue::valid(array_values(array_unique($urls)));
	}

	private static function taxonomyValue($value, ?string $separator, bool $multiple): NormalizedValue
	{
		$items = self::splitValues($value, $multiple ? $separator : null);
		$items = array_values(array_filter(array_map(array(Sanitizer::class, 'text'), $items), static fn (string $item): bool => $item !== ''));

		if ($items === array()) {
			return NormalizedValue::invalid('empty_taxonomy_value');
		}

		if (!$multiple && count($items) > 1) {
			return NormalizedValue::invalid('multiple_values_for_single_target');
		}

		return NormalizedValue::valid($multiple ? array_values(array_unique($items)) : $items[0]);
	}

	/**
	 * @return array<int,string>
	 */
	private static function splitValues($value, ?string $separator): array
	{
		if (is_array($value)) {
			return array_map(static fn ($item): string => trim((string) $item), $value);
		}

		if (!is_scalar($value) && $value !== null) {
			return array();
		}

		$string = trim((string) $value);
		if ($string === '') {
			return array();
		}

		if ($separator === null || $separator === '') {
			return array($string);
		}

		return array_map('trim', explode($separator, $string));
	}
}
