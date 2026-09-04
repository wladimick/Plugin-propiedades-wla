<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Properties\PostType;

final class Assets
{
	public static function enqueue(string $hookSuffix): void
	{
		if (!self::isWlaContext($hookSuffix)) {
			return;
		}

		wp_enqueue_style(
			'wla-inmo-admin',
			WLA_INMO_URL . 'assets/admin/admin.css',
			array(),
			WLA_INMO_VERSION
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
}
