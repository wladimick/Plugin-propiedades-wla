<?php

namespace WLA\Inmo\Settings;

use WLA\Inmo\Access\Capabilities;

final class Registry
{
	public static function register(): void
	{
		register_setting(
			Schema::OPTION_GROUP,
			Schema::OPTION_NAME,
			array(
				'type'              => 'array',
				'description'       => __('Configuración general de WLA Inmo.', 'wla-inmo'),
				'sanitize_callback' => array(Schema::class, 'sanitize'),
				'default'           => Schema::defaults(),
				'show_in_rest'      => false,
			)
		);

		add_filter(
			'option_page_capability_' . Schema::OPTION_GROUP,
			array(self::class, 'settingsCapability')
		);
	}

	public static function settingsCapability($capability): string
	{
		unset($capability);
		return Capabilities::MANAGE_SETTINGS;
	}
}
