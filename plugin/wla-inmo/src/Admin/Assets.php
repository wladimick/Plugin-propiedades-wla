<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Properties\PostType;

final class Assets
{
	public static function enqueue(string $hookSuffix): void
	{
		$screen = function_exists('get_current_screen') ? get_current_screen() : null;
		if (!self::isWlaContext($hookSuffix, $screen)) {
			return;
		}

		wp_enqueue_style(
			'wla-inmo-admin',
			WLA_INMO_URL . 'assets/admin/admin.css',
			array(),
			WLA_INMO_VERSION
		);

		if (self::isDashboardContext($hookSuffix)) {
			wp_enqueue_style(
				'wla-inmo-dashboard',
				WLA_INMO_URL . 'assets/admin/dashboard.css',
				array('wla-inmo-admin'),
				WLA_INMO_VERSION
			);
		}

		if (self::isHelpContext($hookSuffix)) {
			wp_enqueue_style(
				'wla-inmo-help-center',
				WLA_INMO_URL . 'assets/admin/help-center.css',
				array('wla-inmo-admin'),
				WLA_INMO_VERSION
			);
			wp_enqueue_script(
				'wla-inmo-help-center',
				WLA_INMO_URL . 'assets/admin/help-center.js',
				array(),
				WLA_INMO_VERSION,
				true
			);
		}

		if (self::isSettingsContext($hookSuffix)) {
			wp_enqueue_style(
				'wla-inmo-settings',
				WLA_INMO_URL . 'assets/admin/settings.css',
				array('wla-inmo-admin'),
				WLA_INMO_VERSION
			);
		}

		if (self::isImportExportContext($hookSuffix)) {
			wp_enqueue_style(
				'wla-inmo-import-export',
				WLA_INMO_URL . 'assets/admin/import-export.css',
				array('wla-inmo-admin'),
				WLA_INMO_VERSION
			);
		}

		if (self::isActivityContext($hookSuffix) || PropertyMedia::isPropertyEditorContext($hookSuffix, $screen)) {
			wp_enqueue_style(
				'wla-inmo-activity',
				WLA_INMO_URL . 'assets/admin/activity.css',
				array('wla-inmo-admin'),
				WLA_INMO_VERSION
			);
		}

		if (!PropertyMedia::isPropertyEditorContext($hookSuffix, $screen)) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style(
			'wla-inmo-property-media',
			WLA_INMO_URL . 'assets/admin/property-media.css',
			array('wla-inmo-admin'),
			WLA_INMO_VERSION
		);
		wp_enqueue_script(
			'wla-inmo-property-media',
			WLA_INMO_URL . 'assets/admin/property-media.js',
			array('media-editor'),
			WLA_INMO_VERSION,
			true
		);
	}

	public static function isWlaContext(string $hookSuffix, $screen = null): bool
	{
		if (in_array($hookSuffix, Menu::hookSuffixes(), true)) {
			return true;
		}

		if (strpos($hookSuffix, 'wla-inmo') !== false) {
			return true;
		}

		if ($screen === null && function_exists('get_current_screen')) {
			$screen = get_current_screen();
		}

		return is_object($screen)
			&& isset($screen->post_type)
			&& $screen->post_type === PostType::POST_TYPE;
	}

	public static function isDashboardContext(string $hookSuffix): bool
	{
		return strpos($hookSuffix, 'toplevel_page_' . ScreenRegistry::ROOT_SLUG) !== false;
	}

	public static function isHelpContext(string $hookSuffix): bool
	{
		return strpos($hookSuffix, 'wla-inmo-help') !== false;
	}

	public static function isSettingsContext(string $hookSuffix): bool
	{
		return strpos($hookSuffix, 'wla-inmo-settings') !== false;
	}

	public static function isImportExportContext(string $hookSuffix): bool
	{
		return strpos($hookSuffix, 'wla-inmo-import-export') !== false;
	}

	public static function isActivityContext(string $hookSuffix): bool
	{
		return strpos($hookSuffix, 'wla-inmo-activity') !== false;
	}
}
