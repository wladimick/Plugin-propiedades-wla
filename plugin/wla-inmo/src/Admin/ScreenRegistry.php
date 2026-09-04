<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Properties\Capabilities as PropertyCapabilities;
use WLA\Inmo\Taxonomies\Capabilities as TaxonomyCapabilities;

final class ScreenRegistry
{
	public const ROOT_SLUG = 'wla-inmo';

	/**
	 * Declarative administration contract.
	 *
	 * `page` entries are rendered by WLA Inmo. `native` entries document
	 * WordPress-owned screens. Because wla_property uses this menu as its
	 * `show_in_menu` parent, WordPress adds those native submenus itself; WLA
	 * Inmo must not register duplicates.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function definitions(): array
	{
		return array(
			'dashboard' => self::page(
				self::ROOT_SLUG,
				__('Resumen', 'wla-inmo'),
				__('WLA Inmo — Resumen', 'wla-inmo'),
				AccessCapabilities::VIEW_DASHBOARD,
				__('Accesos rápidos y estado operativo de la gestión inmobiliaria.', 'wla-inmo')
			),
			'properties' => self::native(
				'edit.php?post_type=wla_property',
				__('Propiedades', 'wla-inmo'),
				PropertyCapabilities::EDIT_POSTS
			),
			'new_property' => self::native(
				'post-new.php?post_type=wla_property',
				__('Nueva propiedad', 'wla-inmo'),
				PropertyCapabilities::EDIT_POSTS
			),
			'home' => self::page(
				'wla-inmo-home',
				__('Inicio y destacados', 'wla-inmo'),
				__('Inicio y destacados', 'wla-inmo'),
				AccessCapabilities::MANAGE_HOME,
				__('Organización de propiedades destacadas y bloques de portada.', 'wla-inmo')
			),
			'import_export' => self::page(
				'wla-inmo-import-export',
				__('Importar / Exportar', 'wla-inmo'),
				__('Importar / Exportar', 'wla-inmo'),
				AccessCapabilities::IMPORT_PROPERTIES,
				__('Carga y exportación masiva. El módulo completo se implementará en Fase 3.', 'wla-inmo')
			),
			'leads' => self::page(
				'wla-inmo-leads',
				__('Consultas / Leads', 'wla-inmo'),
				__('Consultas / Leads', 'wla-inmo'),
				AccessCapabilities::VIEW_LEADS,
				__('Gestión de consultas asociadas a propiedades. El módulo funcional llegará en Fase 7.', 'wla-inmo')
			),
			'locations' => self::page(
				'wla-inmo-locations',
				__('Ubicaciones', 'wla-inmo'),
				__('Ubicaciones', 'wla-inmo'),
				TaxonomyCapabilities::MANAGE_TERMS,
				__('Regiones, comunas y sectores usados para clasificar propiedades.', 'wla-inmo')
			),
			'classifications' => self::page(
				'wla-inmo-classifications',
				__('Clasificaciones', 'wla-inmo'),
				__('Clasificaciones', 'wla-inmo'),
				TaxonomyCapabilities::MANAGE_TERMS,
				__('Tipos de propiedad, operaciones y otras clasificaciones controladas.', 'wla-inmo')
			),
			'multimedia' => self::page(
				'wla-inmo-media',
				__('Multimedia', 'wla-inmo'),
				__('Multimedia', 'wla-inmo'),
				'upload_files',
				__('Revisión de imágenes y videos asociados al catálogo inmobiliario.', 'wla-inmo')
			),
			'seo' => self::page(
				'wla-inmo-seo',
				__('SEO y visibilidad', 'wla-inmo'),
				__('SEO y visibilidad', 'wla-inmo'),
				AccessCapabilities::MANAGE_SEO,
				__('Configuración SEO/GEO/AEO. La implementación completa corresponde a Fase 6.', 'wla-inmo')
			),
			'indicators' => self::page(
				'wla-inmo-indicators',
				__('Indicadores', 'wla-inmo'),
				__('Indicadores', 'wla-inmo'),
				AccessCapabilities::MANAGE_SETTINGS,
				__('Estado de indicadores económicos e integraciones relacionadas.', 'wla-inmo')
			),
			'quality' => self::page(
				'wla-inmo-quality',
				__('Calidad del catálogo', 'wla-inmo'),
				__('Calidad del catálogo', 'wla-inmo'),
				PropertyCapabilities::EDIT_POSTS,
				__('Hallazgos accionables para completar y mantener las fichas de propiedades.', 'wla-inmo')
			),
			'activity' => self::page(
				'wla-inmo-activity',
				__('Actividad', 'wla-inmo'),
				__('Actividad', 'wla-inmo'),
				AccessCapabilities::VIEW_ACTIVITY,
				__('Bitácora de cambios relevantes y acciones administrativas.', 'wla-inmo')
			),
			'help' => self::page(
				'wla-inmo-help',
				__('Ayuda', 'wla-inmo'),
				__('Centro de ayuda', 'wla-inmo'),
				AccessCapabilities::VIEW_DASHBOARD,
				__('Guías para crear, actualizar y administrar propiedades sin conocimientos técnicos.', 'wla-inmo')
			),
			'tools' => self::page(
				'wla-inmo-tools',
				__('Herramientas', 'wla-inmo'),
				__('Herramientas', 'wla-inmo'),
				AccessCapabilities::MANAGE_TOOLS,
				__('Diagnóstico, integridad, reconstrucciones y operaciones técnicas controladas.', 'wla-inmo')
			),
			'settings' => self::page(
				'wla-inmo-settings',
				__('Ajustes', 'wla-inmo'),
				__('Ajustes', 'wla-inmo'),
				AccessCapabilities::MANAGE_SETTINGS,
				__('Configuración general del motor inmobiliario.', 'wla-inmo')
			),
		);
	}

	/**
	 * @return array<string, string>|null
	 */
	public static function findBySlug(string $slug): ?array
	{
		foreach (self::definitions() as $definition) {
			if ($definition['slug'] === $slug) {
				return $definition;
			}
		}

		return null;
	}

	public static function isPluginPage(string $slug): bool
	{
		$screen = self::findBySlug($slug);

		return $screen !== null && $screen['kind'] === 'page';
	}

	/**
	 * @return array<string, string>
	 */
	private static function page(string $slug, string $menuTitle, string $pageTitle, string $capability, string $description): array
	{
		return array(
			'kind'        => 'page',
			'slug'        => $slug,
			'menu_title'  => $menuTitle,
			'page_title'  => $pageTitle,
			'capability'  => $capability,
			'description' => $description,
		);
	}

	/**
	 * @return array<string, string>
	 */
	private static function native(string $slug, string $menuTitle, string $capability): array
	{
		return array(
			'kind'        => 'native',
			'slug'        => $slug,
			'menu_title'  => $menuTitle,
			'page_title'  => $menuTitle,
			'capability'  => $capability,
			'description' => '',
		);
	}
}
