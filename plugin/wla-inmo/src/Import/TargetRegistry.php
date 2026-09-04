<?php

namespace WLA\Inmo\Import;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Taxonomies\Registry as TaxonomyRegistry;

final class TargetRegistry
{
	public const POST_TITLE = 'post.title';
	public const POST_CONTENT = 'post.content';
	public const POST_EXCERPT = 'post.excerpt';

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public static function definitions(): array
	{
		$definitions = array(
			self::POST_TITLE => array(
				'kind'       => 'post',
				'field'      => 'title',
				'type'       => 'string',
				'private'    => false,
				'multiple'   => false,
				'validator'  => 'text',
			),
			self::POST_CONTENT => array(
				'kind'       => 'post',
				'field'      => 'content',
				'type'       => 'string',
				'private'    => false,
				'multiple'   => false,
				'validator'  => 'textarea',
			),
			self::POST_EXCERPT => array(
				'kind'       => 'post',
				'field'      => 'excerpt',
				'type'       => 'string',
				'private'    => false,
				'multiple'   => false,
				'validator'  => 'textarea',
			),
		);

		foreach (MetaSchema::definitions() as $field => $definition) {
			$validator = self::metaValidator((string) $field, (string) $definition['type']);
			$definitions['meta.' . $field] = array(
				'kind'      => 'meta',
				'field'     => (string) $field,
				'meta_key'  => (string) $definition['meta_key'],
				'type'      => (string) $definition['type'],
				'private'   => empty($definition['public']),
				'multiple'  => in_array($field, array('video_urls'), true),
				'validator' => $validator,
			);
		}

		$taxonomies = array(
			'operation'     => TaxonomyRegistry::OPERATION,
			'property_type' => TaxonomyRegistry::PROPERTY_TYPE,
			'region'        => TaxonomyRegistry::REGION,
			'commune'       => TaxonomyRegistry::COMMUNE,
			'sector'        => TaxonomyRegistry::SECTOR,
			'feature'       => TaxonomyRegistry::FEATURE,
		);

		foreach ($taxonomies as $logical => $taxonomy) {
			$definitions['taxonomy.' . $logical] = array(
				'kind'      => 'taxonomy',
				'field'     => $logical,
				'taxonomy'  => $taxonomy,
				'type'      => 'string',
				'private'   => false,
				'multiple'  => $logical === 'feature',
				'validator' => 'taxonomy',
			);
		}

		return $definitions;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function definition(string $target): ?array
	{
		$definitions = self::definitions();

		return $definitions[$target] ?? null;
	}

	public static function isAllowed(string $target): bool
	{
		return self::definition($target) !== null;
	}

	public static function isMultiple(string $target): bool
	{
		$definition = self::definition($target);

		return $definition !== null && !empty($definition['multiple']);
	}

	public static function isPrivate(string $target): bool
	{
		$definition = self::definition($target);

		return $definition !== null && !empty($definition['private']);
	}

	private static function metaValidator(string $field, string $type): string
	{
		if ($field === 'status') {
			return 'status';
		}

		if ($field === 'currency_primary') {
			return 'currency';
		}

		if ($field === 'latitude') {
			return 'latitude';
		}

		if ($field === 'longitude') {
			return 'longitude';
		}

		if ($field === 'video_urls') {
			return 'url_list';
		}

		if (in_array($field, array('price_clp', 'common_expenses_clp', 'bedrooms', 'bathrooms', 'parking', 'storage_units', 'construction_year', 'home_order'), true)) {
			return 'non_negative_integer';
		}

		if (in_array($field, array('price_uf', 'price_usd', 'land_area_m2', 'built_area_m2', 'usable_area_m2', 'terrace_area_m2'), true)) {
			return 'non_negative_number';
		}

		return match ($type) {
			'boolean' => 'boolean',
			'integer' => 'integer',
			'number'  => 'number',
			'array'   => 'array',
			default   => in_array($field, array('availability_date', 'last_verified_date'), true) ? 'date' : 'text',
		};
	}
}
