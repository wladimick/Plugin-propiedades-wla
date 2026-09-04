<?php

namespace WLA\Inmo\Properties;

use WLA\Inmo\Settings\Repository as SettingsRepository;

final class PostType
{
	public const POST_TYPE = 'wla_property';
	public const ARCHIVE_SLUG = 'propiedades';
	public const REST_BASE = 'wla-properties';

	public static function register(): void
	{
		register_post_type(self::POST_TYPE, self::arguments());
	}

	/**
	 * @return array<string, string>
	 */
	public static function labels(): array
	{
		return array(
			'name'                     => _x('Propiedades', 'post type general name', 'wla-inmo'),
			'singular_name'            => _x('Propiedad', 'post type singular name', 'wla-inmo'),
			'menu_name'                => _x('Propiedades', 'admin menu', 'wla-inmo'),
			'name_admin_bar'           => _x('Propiedad', 'add new on admin bar', 'wla-inmo'),
			'add_new'                  => __('Nueva propiedad', 'wla-inmo'),
			'add_new_item'             => __('Crear nueva propiedad', 'wla-inmo'),
			'new_item'                 => __('Nueva propiedad', 'wla-inmo'),
			'edit_item'                => __('Editar propiedad', 'wla-inmo'),
			'view_item'                => __('Ver propiedad', 'wla-inmo'),
			'all_items'                => __('Todas las propiedades', 'wla-inmo'),
			'search_items'             => __('Buscar propiedades', 'wla-inmo'),
			'parent_item_colon'        => __('Propiedad superior:', 'wla-inmo'),
			'not_found'                => __('No se encontraron propiedades.', 'wla-inmo'),
			'not_found_in_trash'       => __('No hay propiedades en la papelera.', 'wla-inmo'),
			'featured_image'           => __('Imagen principal', 'wla-inmo'),
			'set_featured_image'       => __('Definir imagen principal', 'wla-inmo'),
			'remove_featured_image'    => __('Quitar imagen principal', 'wla-inmo'),
			'use_featured_image'       => __('Usar como imagen principal', 'wla-inmo'),
			'archives'                 => __('Archivo de propiedades', 'wla-inmo'),
			'insert_into_item'         => __('Insertar en la propiedad', 'wla-inmo'),
			'uploaded_to_this_item'    => __('Subido a esta propiedad', 'wla-inmo'),
			'filter_items_list'        => __('Filtrar propiedades', 'wla-inmo'),
			'items_list_navigation'    => __('Navegación de propiedades', 'wla-inmo'),
			'items_list'               => __('Listado de propiedades', 'wla-inmo'),
			'item_published'           => __('Propiedad publicada.', 'wla-inmo'),
			'item_published_privately' => __('Propiedad publicada de forma privada.', 'wla-inmo'),
			'item_reverted_to_draft'   => __('Propiedad devuelta a borrador.', 'wla-inmo'),
			'item_scheduled'           => __('Propiedad programada.', 'wla-inmo'),
			'item_updated'             => __('Propiedad actualizada.', 'wla-inmo'),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function arguments(): array
	{
		$archiveSlug = self::ARCHIVE_SLUG;

		if (class_exists(SettingsRepository::class)) {
			$configured = SettingsRepository::get('property_base', self::ARCHIVE_SLUG);
			if (is_string($configured) && $configured !== '') {
				$archiveSlug = $configured;
			}
		}

		return array(
			'labels'              => self::labels(),
			'description'         => __('Propiedades inmobiliarias administradas por WLA Inmo.', 'wla-inmo'),
			'public'              => true,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'show_in_rest'        => true,
			'rest_base'           => self::REST_BASE,
			'menu_position'       => 25,
			'menu_icon'           => 'dashicons-admin-home',
			'capability_type'     => array('wla_property', 'wla_properties'),
			'capabilities'        => Capabilities::postTypeMap(),
			'map_meta_cap'        => true,
			'hierarchical'        => false,
			'supports'            => array('title', 'editor', 'excerpt', 'thumbnail', 'revisions'),
			'has_archive'         => $archiveSlug,
			'rewrite'             => array(
				'slug'       => $archiveSlug,
				'with_front' => false,
				'feeds'      => false,
				'pages'      => true,
			),
			'query_var'           => self::POST_TYPE,
			'exclude_from_search' => false,
			'can_export'          => true,
			'delete_with_user'    => false,
		);
	}
}
