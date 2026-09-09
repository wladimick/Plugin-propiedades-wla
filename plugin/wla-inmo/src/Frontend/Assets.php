<?php

namespace WLA\Inmo\Frontend;

use WLA\Inmo\Properties\PostType;

final class Assets
{
	public const STYLE_HANDLE = 'wla-inmo-frontend';

	public static function enqueue(): void
	{
		if (!self::isPropertyContext()) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			WLA_INMO_URL . 'assets/css/frontend.css',
			array(),
			WLA_INMO_VERSION
		);
	}

	public static function isPropertyContext(): bool
	{
		if (function_exists('is_post_type_archive') && is_post_type_archive(PostType::POST_TYPE)) {
			return true;
		}

		return function_exists('is_singular') && is_singular(PostType::POST_TYPE);
	}
}
