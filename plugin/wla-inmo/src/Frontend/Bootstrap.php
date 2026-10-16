<?php

namespace WLA\Inmo\Frontend;

use WLA\Inmo\Properties\PostType;

final class Bootstrap
{
	private static bool $registered = false;

	public static function register(): void
	{
		if (self::$registered) {
			return;
		}

		self::$registered = true;

		add_filter('template_include', array(self::class, 'filterTemplate'), 99);
		add_action('wp_enqueue_scripts', array(Assets::class, 'enqueue'));
	}

	public static function filterTemplate(string $template): string
	{
		if (self::isBlockedContext()) {
			return $template;
		}

		$wlaTemplate = self::requestedTemplate();
		if ($wlaTemplate === null) {
			return $template;
		}

		$located = TemplateResolver::locate($wlaTemplate);

		return $located ?? $template;
	}

	private static function requestedTemplate(): ?string
	{
		if (function_exists('is_post_type_archive') && is_post_type_archive(PostType::POST_TYPE)) {
			return 'archive-property.php';
		}

		if (function_exists('is_singular') && is_singular(PostType::POST_TYPE)) {
			return 'single-property.php';
		}

		return null;
	}

	private static function isBlockedContext(): bool
	{
		if (function_exists('is_admin') && is_admin()) {
			return true;
		}

		if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
			return true;
		}

		if (defined('REST_REQUEST') && REST_REQUEST) {
			return true;
		}

		return function_exists('is_feed') && is_feed();
	}
}
