<?php

namespace WLA\Inmo\Frontend;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Taxonomies\Registry;

/**
 * Narrow, read-only GET contract for the property archive.
 *
 * No request parameter can choose an arbitrary taxonomy, meta key, SQL
 * fragment, template, sort column or pagination size.
 */
final class SearchFilters
{
	public const TAXONOMIES = array(
		'operacion' => Registry::OPERATION,
		'tipo'      => Registry::PROPERTY_TYPE,
		'region'    => Registry::REGION,
		'comuna'    => Registry::COMMUNE,
		'sector'    => Registry::SECTOR,
	);

	/**
	 * @param array<string,mixed> $raw Untrusted query string parameters.
	 * @return array<string,string> Normalized allowlisted GET values.
	 */
	public static function normalize(array $raw): array
	{
		$normalized = array();

		if (isset($raw['q']) && is_string($raw['q'])) {
			$keyword = trim(wp_strip_all_tags($raw['q'], true));
			$keyword = preg_replace('/\s+/u', ' ', $keyword);
			if (is_string($keyword) && $keyword !== '') {
				$normalized['q'] = function_exists('mb_substr') ? mb_substr($keyword, 0, 100) : substr($keyword, 0, 100);
			}
		}

		foreach (self::TAXONOMIES as $parameter => $taxonomy) {
			if (!isset($raw[$parameter]) || !is_string($raw[$parameter])) {
				continue;
			}

			$value = trim($raw[$parameter]);
			if ($value !== '' && strlen($value) <= 100 && preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $value)) {
				$normalized[$parameter] = $value;
			}
		}

		if (isset($raw['moneda']) && is_string($raw['moneda'])) {
			$currency = strtoupper(trim($raw['moneda']));
			if (in_array($currency, array('CLP', 'UF', 'USD'), true)) {
				$normalized['moneda'] = $currency;
			}
		}

		$numeric = array(
			'precio_min'    => 2,
			'precio_max'    => 2,
			'dormitorios'   => 0,
			'banos'         => 0,
			'superficie_min' => 2,
		);

		foreach ($numeric as $parameter => $decimals) {
			if (!isset($raw[$parameter]) || !is_string($raw[$parameter])) {
				continue;
			}

			$value = trim($raw[$parameter]);
			$pattern = $decimals === 0
				? '/^(?:0|[1-9][0-9]{0,9})$/D'
				: '/^(?:0|[1-9][0-9]{0,11})(?:[.,][0-9]{1,2})?$/D';

			if (!preg_match($pattern, $value)) {
				continue;
			}

			$value = str_replace(',', '.', $value);
			$normalized[$parameter] = $value;
		}

		if (isset($normalized['precio_min']) || isset($normalized['precio_max'])) {
			if (!isset($normalized['moneda'])) {
				$normalized['moneda'] = 'CLP';
			}
		}

		if (isset($normalized['precio_min'], $normalized['precio_max'])
			&& (float) $normalized['precio_min'] > (float) $normalized['precio_max']) {
			$low = $normalized['precio_max'];
			$normalized['precio_max'] = $normalized['precio_min'];
			$normalized['precio_min'] = $low;
		}

		return $normalized;
	}

	/**
	 * Set WordPress query vars using only the canonical public schema.
	 * Query size, published status and stable ordering stay owned by ArchiveQuery.
	 *
	 * @param array<string,mixed> $request Raw GET values.
	 * @return array<string,string> Normalized inputs used by the search form.
	 */
	public static function apply(\WP_Query $query, array $request): array
	{
		$filters = self::normalize($request);

		if (isset($filters['q'])) {
			$query->set('s', $filters['q']);
		}

		$tax = array();
		foreach (self::TAXONOMIES as $parameter => $taxonomy) {
			if (!isset($filters[$parameter])) {
				continue;
			}
			$tax[] = array(
				'taxonomy' => $taxonomy,
				'field' => 'slug',
				'terms' => array($filters[$parameter]),
				'operator' => 'IN',
				'include_children' => false,
			);
		}
		if ($tax !== array()) {
			$tax['relation'] = 'AND';
			$query->set('tax_query', $tax);
		}

		$meta = array();
		if (isset($filters['moneda'])) {
			$currencyKey = MetaSchema::metaKey('currency_primary');
			if (is_string($currencyKey)) {
				$meta[] = array('key' => $currencyKey, 'value' => $filters['moneda'], 'compare' => '=');
			}
		}

		if (isset($filters['precio_min']) || isset($filters['precio_max'])) {
			$priceFields = array('CLP' => 'price_clp', 'UF' => 'price_uf', 'USD' => 'price_usd');
			$field = $priceFields[$filters['moneda'] ?? 'CLP'];
			$key = MetaSchema::metaKey($field);
			if (is_string($key)) {
				foreach (array('precio_min' => '>=', 'precio_max' => '<=') as $param => $operator) {
					if (isset($filters[$param])) {
						$meta[] = array(
							'key' => $key,
							'value' => $filters[$param],
							'compare' => $operator,
							'type' => 'DECIMAL(18,2)',
						);
					}
				}
			}

			// Exclude intentionally undisclosed prices from range filtering,
			// preventing a hidden amount from being inferred via result presence.
			foreach (array('hide_price', 'price_on_request') as $field) {
				$visibilityKey = MetaSchema::metaKey($field);
				if (is_string($visibilityKey)) {
					$meta[] = array(
						'relation' => 'OR',
						array('key' => $visibilityKey, 'compare' => 'NOT EXISTS'),
						array('key' => $visibilityKey, 'value' => '0', 'compare' => '='),
					);
				}
			}
		}

		foreach (array('dormitorios' => 'bedrooms', 'banos' => 'bathrooms', 'superficie_min' => 'built_area_m2') as $parameter => $field) {
			if (!isset($filters[$parameter])) {
				continue;
			}
			$key = MetaSchema::metaKey($field);
			if (is_string($key)) {
				$meta[] = array(
					'key' => $key,
					'value' => $filters[$parameter],
					'compare' => '>=',
					'type' => 'DECIMAL(18,2)',
				);
			}
		}

		if ($meta !== array()) {
			$meta['relation'] = 'AND';
			$query->set('meta_query', $meta);
		}

		return $filters;
	}
}
