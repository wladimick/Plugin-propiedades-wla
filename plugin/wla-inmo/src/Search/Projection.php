<?php

namespace WLA\Inmo\Search;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Properties\Sanitizer;
use WLA\Inmo\Taxonomies\Registry as TaxonomyRegistry;

final class Projection
{
	/**
	 * Build the search projection from canonical WordPress data.
	 *
	 * Only published properties belong to the public search index. Returning
	 * null instructs the indexer to remove any stale row.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function fromProperty(int $postId): ?array
	{
		if ($postId < 1 || get_post_type($postId) !== PostType::POST_TYPE || get_post_status($postId) !== 'publish') {
			return null;
		}

		return array(
			'property_id'    => $postId,
			'property_code'  => self::nullableTextMeta($postId, 'property_code'),
			'external_id'    => self::nullableTextMeta($postId, 'external_id'),
			'status'         => self::nullableKeyMeta($postId, 'status'),
			'operation_slug' => self::termSlug($postId, TaxonomyRegistry::OPERATION),
			'type_slug'      => self::termSlug($postId, TaxonomyRegistry::PROPERTY_TYPE),
			'region_slug'    => self::termSlug($postId, TaxonomyRegistry::REGION),
			'commune_slug'   => self::termSlug($postId, TaxonomyRegistry::COMMUNE),
			'sector_slug'    => self::termSlug($postId, TaxonomyRegistry::SECTOR),
			'price_clp'      => self::nullableIntegerMeta($postId, 'price_clp'),
			'price_uf'       => self::nullableNumberMeta($postId, 'price_uf'),
			'price_usd'      => self::nullableNumberMeta($postId, 'price_usd'),
			'bedrooms'       => self::nullableIntegerMeta($postId, 'bedrooms'),
			'bathrooms'      => self::nullableIntegerMeta($postId, 'bathrooms'),
			'parking'        => self::nullableIntegerMeta($postId, 'parking'),
			'land_area_m2'   => self::nullableNumberMeta($postId, 'land_area_m2'),
			'built_area_m2'  => self::nullableNumberMeta($postId, 'built_area_m2'),
			'latitude'       => self::nullableLatitude($postId),
			'longitude'      => self::nullableLongitude($postId),
			'featured'       => Sanitizer::boolean(self::meta($postId, 'featured')) ? 1 : 0,
			'updated_at'     => self::modifiedAt($postId),
		);
	}

	private static function meta(int $postId, string $field)
	{
		$metaKey = MetaSchema::metaKey($field);

		if ($metaKey === null) {
			return null;
		}

		return get_post_meta($postId, $metaKey, true);
	}

	private static function nullableTextMeta(int $postId, string $field): ?string
	{
		$value = Sanitizer::text(self::meta($postId, $field));

		return $value === '' ? null : $value;
	}

	private static function nullableKeyMeta(int $postId, string $field): ?string
	{
		$value = Sanitizer::key(self::meta($postId, $field));

		return $value === '' ? null : $value;
	}

	private static function nullableIntegerMeta(int $postId, string $field): ?int
	{
		return Sanitizer::nonNegativeInteger(self::meta($postId, $field));
	}

	private static function nullableNumberMeta(int $postId, string $field): ?float
	{
		return Sanitizer::nonNegativeNumber(self::meta($postId, $field));
	}

	private static function nullableLatitude(int $postId): ?float
	{
		return Sanitizer::latitude(self::meta($postId, 'latitude'));
	}

	private static function nullableLongitude(int $postId): ?float
	{
		return Sanitizer::longitude(self::meta($postId, 'longitude'));
	}

	private static function termSlug(int $postId, string $taxonomy): ?string
	{
		$slugs = wp_get_object_terms(
			$postId,
			$taxonomy,
			array(
				'fields'  => 'slugs',
				'orderby' => 'term_id',
				'order'   => 'ASC',
			)
		);

		if (is_wp_error($slugs) || !is_array($slugs) || empty($slugs)) {
			return null;
		}

		$slug = Sanitizer::key($slugs[0]);

		return $slug === '' ? null : $slug;
	}

	private static function modifiedAt(int $postId): string
	{
		$modified = get_post_modified_time('Y-m-d H:i:s', true, $postId);

		return is_string($modified) && $modified !== '' ? $modified : gmdate('Y-m-d H:i:s');
	}
}
