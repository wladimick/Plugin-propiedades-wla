<?php

namespace WLA\Inmo\Properties;

final class Validator
{
	/**
	 * Validate canonical property values before persistence.
	 *
	 * Empty optional values are valid. Completeness belongs to the catalogue
	 * quality layer, not to storage validation, so drafts remain possible.
	 *
	 * @param array<string, mixed> $values Domain field => value.
	 * @return array<string, string> Domain field => stable error code.
	 */
	public static function validate(array $values): array
	{
		$errors = array();

		self::validateTextLengths($values, $errors);
		self::validateStatus($values, $errors);
		self::validateCurrency($values, $errors);
		self::validateNonNegativeIntegers($values, $errors);
		self::validateNonNegativeNumbers($values, $errors);
		self::validateCoordinates($values, $errors);
		self::validateDates($values, $errors);
		self::validateConstructionYear($values, $errors);
		self::validateGallery($values, $errors);
		self::validateVideos($values, $errors);

		return $errors;
	}

	/**
	 * @param array<string, mixed>  $values Values.
	 * @param array<string, string> $errors Errors.
	 */
	private static function validateTextLengths(array $values, array &$errors): void
	{
		$limits = array(
			'property_code'   => 100,
			'external_id'     => 191,
			'status'          => 40,
			'locality'        => 191,
			'public_address'  => 255,
			'private_address' => 255,
			'heating'         => 100,
			'orientation'     => 100,
			'location_text'   => 1000,
			'internal_notes'  => 10000,
		);

		foreach ($limits as $field => $limit) {
			if (!array_key_exists($field, $values) || !self::isProvided($values[$field])) {
				continue;
			}

			if (!is_scalar($values[$field])) {
				$errors[$field] = 'invalid_text';
				continue;
			}

			$text = (string) $values[$field];
			$length = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);

			if ($length > $limit) {
				$errors[$field] = 'too_long';
			}
		}
	}

	/**
	 * @param array<string, mixed>  $values Values.
	 * @param array<string, string> $errors Errors.
	 */
	private static function validateStatus(array $values, array &$errors): void
	{
		if (!array_key_exists('status', $values) || !self::isProvided($values['status'])) {
			return;
		}

		if (Sanitizer::key($values['status']) === '') {
			$errors['status'] = 'invalid_key';
		}
	}

	/**
	 * @param array<string, mixed>  $values Values.
	 * @param array<string, string> $errors Errors.
	 */
	private static function validateCurrency(array $values, array &$errors): void
	{
		if (!array_key_exists('currency_primary', $values) || !self::isProvided($values['currency_primary'])) {
			return;
		}

		if (Sanitizer::currency($values['currency_primary']) === '') {
			$errors['currency_primary'] = 'unsupported_currency';
		}
	}

	/**
	 * @param array<string, mixed>  $values Values.
	 * @param array<string, string> $errors Errors.
	 */
	private static function validateNonNegativeIntegers(array $values, array &$errors): void
	{
		$fields = array(
			'price_clp',
			'common_expenses_clp',
			'bedrooms',
			'bathrooms',
			'parking',
			'storage_units',
			'home_order',
		);

		foreach ($fields as $field) {
			if (!array_key_exists($field, $values) || !self::isProvided($values[$field])) {
				continue;
			}

			if (Sanitizer::nonNegativeInteger($values[$field]) === null) {
				$errors[$field] = 'invalid_non_negative_integer';
			}
		}
	}

	/**
	 * @param array<string, mixed>  $values Values.
	 * @param array<string, string> $errors Errors.
	 */
	private static function validateNonNegativeNumbers(array $values, array &$errors): void
	{
		$fields = array(
			'price_uf',
			'price_usd',
			'land_area_m2',
			'built_area_m2',
			'usable_area_m2',
			'terrace_area_m2',
		);

		foreach ($fields as $field) {
			if (!array_key_exists($field, $values) || !self::isProvided($values[$field])) {
				continue;
			}

			if (Sanitizer::nonNegativeNumber($values[$field]) === null) {
				$errors[$field] = 'invalid_non_negative_number';
			}
		}
	}

	/**
	 * @param array<string, mixed>  $values Values.
	 * @param array<string, string> $errors Errors.
	 */
	private static function validateCoordinates(array $values, array &$errors): void
	{
		if (array_key_exists('latitude', $values) && self::isProvided($values['latitude']) && Sanitizer::latitude($values['latitude']) === null) {
			$errors['latitude'] = 'invalid_latitude';
		}

		if (array_key_exists('longitude', $values) && self::isProvided($values['longitude']) && Sanitizer::longitude($values['longitude']) === null) {
			$errors['longitude'] = 'invalid_longitude';
		}
	}

	/**
	 * @param array<string, mixed>  $values Values.
	 * @param array<string, string> $errors Errors.
	 */
	private static function validateDates(array $values, array &$errors): void
	{
		foreach (array('availability_date', 'last_verified_date') as $field) {
			if (!array_key_exists($field, $values) || !self::isProvided($values[$field])) {
				continue;
			}

			if (!is_scalar($values[$field]) || !Sanitizer::isValidDate(trim((string) $values[$field]))) {
				$errors[$field] = 'invalid_date';
			}
		}
	}

	/**
	 * @param array<string, mixed>  $values Values.
	 * @param array<string, string> $errors Errors.
	 */
	private static function validateConstructionYear(array $values, array &$errors): void
	{
		if (!array_key_exists('construction_year', $values) || !self::isProvided($values['construction_year'])) {
			return;
		}

		$year = Sanitizer::nonNegativeInteger($values['construction_year']);
		$maxYear = (int) gmdate('Y') + 20;

		if ($year === null || $year < 1000 || $year > $maxYear) {
			$errors['construction_year'] = 'invalid_year';
		}
	}

	/**
	 * @param array<string, mixed>  $values Values.
	 * @param array<string, string> $errors Errors.
	 */
	private static function validateGallery(array $values, array &$errors): void
	{
		if (!array_key_exists('gallery_ids', $values) || !self::isProvided($values['gallery_ids'])) {
			return;
		}

		if (!is_array($values['gallery_ids'])) {
			$errors['gallery_ids'] = 'invalid_array';
			return;
		}

		foreach ($values['gallery_ids'] as $id) {
			$integer = Sanitizer::nonNegativeInteger($id);

			if ($integer === null || $integer < 1) {
				$errors['gallery_ids'] = 'invalid_attachment_id';
				return;
			}
		}
	}

	/**
	 * @param array<string, mixed>  $values Values.
	 * @param array<string, string> $errors Errors.
	 */
	private static function validateVideos(array $values, array &$errors): void
	{
		if (!array_key_exists('video_urls', $values) || !self::isProvided($values['video_urls'])) {
			return;
		}

		if (!is_array($values['video_urls'])) {
			$errors['video_urls'] = 'invalid_array';
			return;
		}

		foreach ($values['video_urls'] as $url) {
			if (Sanitizer::httpUrl($url) === '') {
				$errors['video_urls'] = 'invalid_http_url';
				return;
			}
		}
	}

	private static function isProvided($value): bool
	{
		if ($value === null || $value === '') {
			return false;
		}

		return !is_array($value) || $value !== array();
	}
}
