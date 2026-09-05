<?php

namespace WLA\Inmo\Import;

final class WordPressTaxonomyLookup
{
	/**
	 * Resolve a human source value to exact WordPress terms without creating any.
	 *
	 * @return array<int,array{id:int,slug:string,name:string}>
	 */
	public static function lookup(string $taxonomy, string $value): array
	{
		$value = trim($value);
		if ($taxonomy === '' || $value === '' || !taxonomy_exists($taxonomy)) {
			return array();
		}

		$matches = array();
		$queries = array(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'name'       => $value,
			),
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'slug'       => sanitize_title($value),
			),
		);

		foreach ($queries as $args) {
			$terms = get_terms($args);
			if (is_wp_error($terms) || !is_array($terms)) {
				continue;
			}

			foreach ($terms as $term) {
				if (!is_object($term) || !isset($term->term_id, $term->slug, $term->name)) {
					continue;
				}

				$termId = (int) $term->term_id;
				$slug = trim((string) $term->slug);
				if ($termId < 1 || $slug === '') {
					continue;
				}

				$matches[$termId] = array(
					'id'   => $termId,
					'slug' => $slug,
					'name' => (string) $term->name,
				);
			}
		}

		ksort($matches, SORT_NUMERIC);

		return array_values($matches);
	}
}
