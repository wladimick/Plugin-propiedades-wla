<?php

namespace WLA\Inmo\Settings;

use WLA\Inmo\Localization\ChilePreset;

final class Schema
{
	public const OPTION_NAME = 'wla_inmo_settings';
	public const OPTION_GROUP = 'wla_inmo';

	/**
	 * Default installation preset. Chile is the first supported preset, but the
	 * schema itself contains no Propiedades Martínez/WLA branding.
	 *
	 * @return array<string,string>
	 */
	public static function defaults(): array
	{
		$defaults = array_merge(
			ChilePreset::settings(),
			array(
				'property_base' => 'propiedades',
				'business_name' => '',
			)
		);

		if (function_exists('apply_filters')) {
			$filtered = apply_filters('wla_inmo_settings_defaults', $defaults);
			if (is_array($filtered)) {
				$defaults = $filtered;
			}
		}

		return self::sanitize($defaults, false);
	}

	/**
	 * Sanitize a complete settings payload. Unknown keys are discarded.
	 *
	 * @param mixed $value Incoming value.
	 * @return array<string,string>
	 */
	public static function sanitize($value, bool $mergeDefaults = true): array
	{
		$value = is_array($value) ? $value : array();
		$base = $mergeDefaults ? self::baseDefaults() : self::baseDefaults();

		$result = array(
			'country_code'     => self::countryCode($value['country_code'] ?? $base['country_code']),
			'currency_primary' => self::currency($value['currency_primary'] ?? $base['currency_primary']),
			'area_unit'        => self::areaUnit($value['area_unit'] ?? $base['area_unit']),
			'map_provider'     => self::mapProvider($value['map_provider'] ?? $base['map_provider']),
			'property_base'    => self::propertyBase($value['property_base'] ?? $base['property_base']),
			'business_name'    => self::text($value['business_name'] ?? ''),
		);

		return $result;
	}

	/** @return array<string,string> */
	private static function baseDefaults(): array
	{
		return array_merge(
			ChilePreset::settings(),
			array(
				'property_base' => 'propiedades',
				'business_name' => '',
			)
		);
	}

	private static function countryCode($value): string
	{
		$value = strtoupper(self::text($value));
		$value = preg_replace('/[^A-Z]/', '', $value) ?? '';

		return strlen($value) === 2 ? $value : ChilePreset::COUNTRY_CODE;
	}

	private static function currency($value): string
	{
		$value = strtoupper(self::text($value));
		$value = preg_replace('/[^A-Z]/', '', $value) ?? '';

		return strlen($value) === 3 ? $value : 'CLP';
	}

	private static function areaUnit($value): string
	{
		$value = strtolower(self::text($value));

		return in_array($value, array('m2', 'ft2'), true) ? $value : 'm2';
	}

	private static function mapProvider($value): string
	{
		$value = strtolower(self::text($value));

		return in_array($value, array('osm', 'google', 'none'), true) ? $value : 'osm';
	}

	private static function propertyBase($value): string
	{
		$value = self::text($value);

		if (function_exists('sanitize_title')) {
			$value = sanitize_title($value);
		} else {
			$value = strtolower($value);
			$value = preg_replace('/[^a-z0-9\-]+/', '-', $value) ?? '';
			$value = trim($value, '-');
		}

		return $value !== '' ? $value : 'propiedades';
	}

	private static function text($value): string
	{
		if (!is_scalar($value) && $value !== null) {
			return '';
		}

		$value = trim((string) $value);

		return function_exists('sanitize_text_field') ? sanitize_text_field($value) : trim(strip_tags($value));
	}
}
