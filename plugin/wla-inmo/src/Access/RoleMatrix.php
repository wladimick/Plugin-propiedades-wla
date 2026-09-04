<?php

namespace WLA\Inmo\Access;

use WLA\Inmo\Properties\Capabilities as PropertyCapabilities;
use WLA\Inmo\Taxonomies\Capabilities as TaxonomyCapabilities;

final class RoleMatrix
{
	public const ROLE_MANAGER = 'wla_inmo_manager';
	public const ROLE_EDITOR = 'wla_property_editor';
	public const ROLE_LEAD_MANAGER = 'wla_lead_manager';

	/**
	 * Custom role definitions and their intended capabilities.
	 *
	 * @return array<string, array{label:string,capabilities:array<int,string>}>
	 */
	public static function definitions(): array
	{
		return array(
			self::ROLE_MANAGER => array(
				'label'        => __('Administrador inmobiliario', 'wla-inmo'),
				'capabilities' => self::managerCapabilities(),
			),
			self::ROLE_EDITOR => array(
				'label'        => __('Editor de propiedades', 'wla-inmo'),
				'capabilities' => self::editorCapabilities(),
			),
			self::ROLE_LEAD_MANAGER => array(
				'label'        => __('Gestor de leads', 'wla-inmo'),
				'capabilities' => self::leadManagerCapabilities(),
			),
		);
	}

	/**
	 * Every WLA-specific primitive capability managed by this plugin.
	 *
	 * @return array<int, string>
	 */
	public static function managedCapabilities(): array
	{
		return array_values(
			array_unique(
				array_merge(
					PropertyCapabilities::primitive(),
					TaxonomyCapabilities::all(),
					Capabilities::all()
				)
			)
		);
	}

	/**
	 * Capabilities added to the native Administrator role.
	 *
	 * @return array<int, string>
	 */
	public static function administratorCapabilities(): array
	{
		return self::managedCapabilities();
	}

	/**
	 * @return array<int, string>
	 */
	public static function managerCapabilities(): array
	{
		$moduleCaps = array_values(
			array_diff(
				Capabilities::all(),
				array(Capabilities::MANAGE_TOOLS)
			)
		);

		return self::withCore(
			array_merge(
				PropertyCapabilities::primitive(),
				TaxonomyCapabilities::all(),
				$moduleCaps
			),
			true
		);
	}

	/**
	 * Editors manage their own property records, can publish and attach media,
	 * and can assign existing classifications. They cannot edit other authors'
	 * properties or administer taxonomy structures/settings/imports/leads.
	 *
	 * @return array<int, string>
	 */
	public static function editorCapabilities(): array
	{
		return self::withCore(
			array(
				PropertyCapabilities::EDIT_POSTS,
				PropertyCapabilities::PUBLISH_POSTS,
				PropertyCapabilities::READ_PRIVATE_POSTS,
				PropertyCapabilities::DELETE_POSTS,
				PropertyCapabilities::DELETE_PRIVATE_POSTS,
				PropertyCapabilities::DELETE_PUBLISHED_POSTS,
				PropertyCapabilities::EDIT_PRIVATE_POSTS,
				PropertyCapabilities::EDIT_PUBLISHED_POSTS,
				TaxonomyCapabilities::ASSIGN_TERMS,
				Capabilities::VIEW_DASHBOARD,
			),
			true
		);
	}

	/**
	 * @return array<int, string>
	 */
	public static function leadManagerCapabilities(): array
	{
		return self::withCore(
			array(
				Capabilities::VIEW_DASHBOARD,
				Capabilities::VIEW_LEADS,
				Capabilities::EDIT_LEADS,
				Capabilities::MANAGE_LEADS,
			),
			false
		);
	}

	/**
	 * @param array<int, string> $capabilities WLA capabilities.
	 * @return array<int, string>
	 */
	private static function withCore(array $capabilities, bool $uploadFiles): array
	{
		$core = array('read');

		if ($uploadFiles) {
			$core[] = 'upload_files';
		}

		return array_values(array_unique(array_merge($core, $capabilities)));
	}
}
