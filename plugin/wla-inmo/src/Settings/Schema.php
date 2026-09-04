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
		$defaults = self::baseDefaults();

		if (function_exists('apply_filters')) {
			$filtered = apply_filters('wla_inmo_settings_defaults', $defaults);
			if (is_array($filtered)) {
				$defaults = $filtered;
			}
		}

		return self::sanitize($defaults);
	}

	/**
	 * Sanitize a complete settings payload. Unknown keys are discarded.
	 *
	 * @param mixed $value Incoming value.
	 * @return array<string,string>
	 */
	public static function sanitize($value, bool $mergeDefaults = true): array
	{
		unset($mergeDefaults);
		$value = is_array($value) ? $value : array();
		$base = self::baseDefaults();

		return array(
			'country_code'              => self::countryCode($value['country_code'] ?? $base['country_code']),
			'currency_primary'          => self::currency($value['currency_primary'] ?? $base['currency_primary']),
			'area_unit'                 => self::areaUnit($value['area_unit'] ?? $base['area_unit']),
			'map_provider'              => self::mapProvider($value['map_provider'] ?? $base['map_provider']),
			'property_base'             => self::propertyBase($value['property_base'] ?? $base['property_base']),
			'business_name'             => self::text($value['business_name'] ?? $base['business_name']),
			'business_email'            => self::email($value['business_email'] ?? $base['business_email']),
			'business_phone'            => self::phone($value['business_phone'] ?? $base['business_phone']),
			'whatsapp_number'           => self::whatsapp($value['whatsapp_number'] ?? $base['whatsapp_number']),
			'business_address'          => self::text($value['business_address'] ?? $base['business_address']),
			'lead_retention_months'     => self::months($value['lead_retention_months'] ?? $base['lead_retention_months'], 24),
			'activity_retention_months' => self::months($value['activity_retention_months'] ?? $base['activity_retention_months'], 12),
		);
	}

	/** @return array<string,string> */
	private static function baseDefaults(): array
	{
		return array_merge(
			ChilePreset::settings(),
			array(
				'property_base'             => 'propiedades',
				'business_name'             => '',
				'business_email'            => '',
				'business_phone'            => '',
				'whatsapp_number'           => '',
				'business_address'          => '',
				'lead_retention_months'     => '24',
				'activity_retention_months' => '12',
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

	private static function email($value): string
	{
		$value = self::text($value);
		if ($value === '') {
			return '';
		}

		if (function_exists('sanitize_email')) {
			return sanitize_email($value);
		}

		return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
	}

	private static function phone($value): string
	{
		$value = self::text($value);
		$value = preg_replace('/[^0-9+()\- .]/', '', $value) ?? '';

		return substr(trim($value), 0, 40);
	}

	private static function whatsapp($value): string
	{
		$value = self::text($value);
		$hasPlus = strpos($value, '+') === 0;
		$digits = preg_replace('/\D+/', '', $value) ?? '';
		$digits = substr($digits, 0, 15);

		if ($digits === '') {
			return '';
		}

		return ($hasPlus ? '+' : '') . $digits;
	}

	private static function months($value, int $fallback): string
	{
		$value = is_scalar($value) ? (int) $value : $fallback;
		$value = max(1, min(120, $value));

		return (string) $value;
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
