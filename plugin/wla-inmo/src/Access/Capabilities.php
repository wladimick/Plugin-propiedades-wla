<?php

namespace WLA\Inmo\Access;

final class Capabilities
{
	public const VIEW_DASHBOARD = 'view_wla_inmo_dashboard';
	public const MANAGE_HOME = 'manage_wla_inmo_home';
	public const IMPORT_PROPERTIES = 'import_wla_properties';
	public const EXPORT_PROPERTIES = 'export_wla_properties';
	public const VIEW_LEADS = 'view_wla_inmo_leads';
	public const EDIT_LEADS = 'edit_wla_inmo_leads';
	public const MANAGE_LEADS = 'manage_wla_inmo_leads';
	public const MANAGE_SEO = 'manage_wla_inmo_seo';
	public const VIEW_ACTIVITY = 'view_wla_inmo_activity';
	public const MANAGE_SETTINGS = 'manage_wla_inmo_settings';
	public const MANAGE_TOOLS = 'manage_wla_inmo_tools';

	/**
	 * Capabilities for modules that arrive after the Core phase.
	 *
	 * Defining these now lets future admin sections enforce least privilege
	 * without falling back to manage_options.
	 *
	 * @return array<int, string>
	 */
	public static function all(): array
	{
		return array(
			self::VIEW_DASHBOARD,
			self::MANAGE_HOME,
			self::IMPORT_PROPERTIES,
			self::EXPORT_PROPERTIES,
			self::VIEW_LEADS,
			self::EDIT_LEADS,
			self::MANAGE_LEADS,
			self::MANAGE_SEO,
			self::VIEW_ACTIVITY,
			self::MANAGE_SETTINGS,
			self::MANAGE_TOOLS,
		);
	}
}
