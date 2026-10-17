<?php

namespace WLA\Inmo\Frontend;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Taxonomies\Registry as Taxonomies;

/**
 * Explicit public-only property projection for frontend cards.
 *
 * This class deliberately never accepts arbitrary meta keys from callers.
 */
final class PropertyCard
{
	/**
	 * @return array<string,mixed>|null
	 */
	public static function forPost(int $postId): ?array
	{
		if ($postId < 1 || get_post_type($postId) !== PostType::POST_TYPE || get_post_status($postId) !== 'publish') {
			return null;
		}

		$permalink = get_permalink($postId);
		if (!is_string($permalink) || $permalink === '') {
			return null;
		}

		$description = wp_strip_all_tags((string) get_the_excerpt($postId), true);
		$description = function_exists('wp_trim_words') ? wp_trim_words($description, 24, '…') : $description;

		$details = array();
		foreach (array(
			'bedrooms' => __('Dormitorios', 'wla-inmo'),
			'bathrooms' => __('Baños', 'wla-inmo'),
			'parking' => __('Estacionamientos', 'wla-inmo'),
		) as $field => $label) {
			$value = self::numberMeta($postId, $field);
			if ($value !== null && $value > 0) {
				$details[] = array('label' => $label, 'value' => number_format_i18n($value, 0));
			}
		}

		$builtArea = self::numberMeta($postId, 'built_area_m2');
		if ($builtArea !== null && $builtArea > 0) {
			$details[] = array(
				'label' => __('Construidos', 'wla-inmo'),
				'value' => number_format_i18n($builtArea, $builtArea == (int) $builtArea ? 0 : 1) . ' m²',
			);
		}

		return array(
			'id'          => $postId,
			'title'       => get_the_title($postId),
			'url'         => $permalink,
			'image'       => get_the_post_thumbnail(
				$postId,
				'medium_large',
				array('class' => 'wla-inmo-card__image', 'loading' => 'lazy')
			),
			'excerpt'     => $description,
			'operation'   => self::termName($postId, Taxonomies::OPERATION),
			'type'        => self::termName($postId, Taxonomies::PROPERTY_TYPE),
			'commune'     => self::termName($postId, Taxonomies::COMMUNE),
			'sector'      => self::termName($postId, Taxonomies::SECTOR),
			'price'       => self::formattedPrice($postId),
			'details'     => $details,
		);
	}

	private static function termName(int $postId, string $taxonomy): string
	{
		$terms = get_the_terms($postId, $taxonomy);
		if (is_wp_error($terms) || !is_array($terms) || $terms === array()) {
			return '';
		}

		$term = reset($terms);
		return is_object($term) && isset($term->name) ? (string) $term->name : '';
	}

	private static function meta(int $postId, string $field): mixed
	{
		$key = MetaSchema::metaKey($field);
		return $key === null ? null : get_post_meta($postId, $key, true);
	}

	private static function numberMeta(int $postId, string $field): ?float
	{
		$value = self::meta($postId, $field);
		if (!is_scalar($value) || $value === '' || !is_numeric($value)) {
			return null;
		}

		$number = (float) $value;
		return is_finite($number) && $number >= 0 ? $number : null;
	}

	private static function formattedPrice(int $postId): string
	{
		if (self::meta($postId, 'price_on_request') || self::meta($postId, 'hide_price')) {
			return __('Precio a consultar', 'wla-inmo');
		}

		$currency = strtoupper(trim((string) self::meta($postId, 'currency_primary')));
		$fields = array('CLP' => 'price_clp', 'UF' => 'price_uf', 'USD' => 'price_usd');

		if (!isset($fields[$currency])) {
			return '';
		}

		$amount = self::numberMeta($postId, $fields[$currency]);
		if ($amount === null) {
			return '';
		}

		$prefixes = array('CLP' => '$', 'UF' => 'UF ', 'USD' => 'US$ ');
		$decimals = $currency === 'UF' ? 2 : 0;
		return $prefixes[$currency] . number_format_i18n($amount, $decimals);
	}
}
