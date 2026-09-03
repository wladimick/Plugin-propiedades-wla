<?php

namespace WLA\Inmo\Taxonomies;

use WLA\Inmo\Properties\PostType;

final class Registry
{
	public const OPERATION = 'wla_operation';
	public const PROPERTY_TYPE = 'wla_property_type';
	public const REGION = 'wla_region';
	public const COMMUNE = 'wla_commune';
	public const SECTOR = 'wla_sector';

	public static function register(): void
	{
		foreach (self::definitions() as $taxonomy => $definition) {
			register_taxonomy(
				$taxonomy,
				array(PostType::POST_TYPE),
				self::arguments($taxonomy, $definition)
			);
		}
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions(): array
	{
		return array(
			self::OPERATION => array(
				'singular'   => __('Operación', 'wla-inmo'),
				'plural'     => __('Operaciones', 'wla-inmo'),
				'rewrite'    => 'operacion',
				'rest_base'  => 'wla-operations',
				'hierarchical' => false,
			),
			self::PROPERTY_TYPE => array(
				'singular'   => __('Tipo de propiedad', 'wla-inmo'),
				'plural'     => __('Tipos de propiedad', 'wla-inmo'),
				'rewrite'    => 'tipo',
				'rest_base'  => 'wla-property-types',
				'hierarchical' => true,
			),
			self::REGION => array(
				'singular'   => __('Región', 'wla-inmo'),
				'plural'     => __('Regiones', 'wla-inmo'),
				'rewrite'    => 'region',
				'rest_base'  => 'wla-regions',
				'hierarchical' => false,
			),
			self::COMMUNE => array(
				'singular'   => __('Comuna', 'wla-inmo'),
				'plural'     => __('Comunas', 'wla-inmo'),
				'rewrite'    => 'comuna',
				'rest_base'  => 'wla-communes',
				'hierarchical' => false,
			),
			self::SECTOR => array(
				'singular'   => __('Sector', 'wla-inmo'),
				'plural'     => __('Sectores', 'wla-inmo'),
				'rewrite'    => 'sector',
				'rest_base'  => 'wla-sectors',
				'hierarchical' => false,
			),
		);
	}

	/**
	 * @param string               $taxonomy   Taxonomy key.
	 * @param array<string, mixed> $definition Taxonomy definition.
	 * @return array<string, mixed>
	 */
	public static function arguments(string $taxonomy, array $definition): array
	{
		$hierarchical = !empty($definition['hierarchical']);

		return array(
			'labels'             => self::labels((string) $definition['singular'], (string) $definition['plural']),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_nav_menus'  => true,
			'show_tagcloud'      => false,
			'show_in_quick_edit' => true,
			'show_admin_column'  => true,
			'show_in_rest'       => true,
			'rest_base'          => (string) $definition['rest_base'],
			'hierarchical'       => $hierarchical,
			'capabilities'       => Capabilities::map(),
			'query_var'          => $taxonomy,
			'rewrite'            => array(
				'slug'         => (string) $definition['rewrite'],
				'with_front'   => false,
				'hierarchical' => $hierarchical,
			),
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function labels(string $singular, string $plural): array
	{
		return array(
			'name'              => $plural,
			'singular_name'     => $singular,
			'search_items'      => sprintf(__('Buscar %s', 'wla-inmo'), $plural),
			'all_items'         => sprintf(__('Ver %s', 'wla-inmo'), $plural),
			'parent_item'       => sprintf(__('%s superior', 'wla-inmo'), $singular),
			'parent_item_colon' => sprintf(__('%s superior:', 'wla-inmo'), $singular),
			'edit_item'         => sprintf(__('Editar %s', 'wla-inmo'), $singular),
			'view_item'         => sprintf(__('Ver %s', 'wla-inmo'), $singular),
			'update_item'       => sprintf(__('Actualizar %s', 'wla-inmo'), $singular),
			'add_new_item'      => sprintf(__('Agregar %s', 'wla-inmo'), $singular),
			'new_item_name'     => sprintf(__('Nombre de %s', 'wla-inmo'), $singular),
			'not_found'         => sprintf(__('No se encontraron %s.', 'wla-inmo'), $plural),
			'no_terms'          => sprintf(__('Sin %s', 'wla-inmo'), $plural),
			'items_list'        => sprintf(__('Listado de %s', 'wla-inmo'), $plural),
			'menu_name'         => $plural,
		);
	}

	/**
	 * @return array<int, string>
	 */
	public static function keys(): array
	{
		return array_keys(self::definitions());
	}
}
