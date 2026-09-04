<?php

namespace WLA\Inmo\Frontend;

final class TemplateResolver
{
	public const THEME_DIRECTORY = 'wla-inmo';

	/**
	 * Locate a template override in the active/parent theme and then in plugin.
	 */
	public static function locate(string $template): ?string
	{
		$template = self::normalize($template);
		if ($template === null) {
			return null;
		}

		$candidates = array(self::THEME_DIRECTORY . '/' . $template);
		if (function_exists('apply_filters')) {
			$filtered = apply_filters('wla_inmo_template_candidates', $candidates, $template);
			if (is_array($filtered) && $filtered !== array()) {
				$candidates = array_values(array_filter($filtered, 'is_string'));
			}
		}

		if (function_exists('locate_template')) {
			$located = locate_template($candidates, false, false);
			if (is_string($located) && $located !== '') {
				return self::filterPath($located, $template, $candidates);
			}
		}

		$fallback = self::pluginFallbackPath($template);
		if (is_file($fallback)) {
			return self::filterPath($fallback, $template, $candidates);
		}

		return null;
	}

	public static function pluginFallbackPath(string $template): string
	{
		$template = self::normalize($template) ?? '';
		return WLA_INMO_DIR . 'templates/' . $template;
	}

	private static function normalize(string $template): ?string
	{
		$template = str_replace('\\', '/', trim($template));
		$template = ltrim($template, '/');

		if ($template === '' || str_contains($template, '../') || str_contains($template, '/..') || str_contains($template, "\0")) {
			return null;
		}

		$segments = explode('/', $template);
		foreach ($segments as $segment) {
			if ($segment === '' || $segment === '.' || $segment === '..' || !preg_match('/^[A-Za-z0-9._-]+$/', $segment)) {
				return null;
			}
		}

		if (!str_ends_with(strtolower($template), '.php')) {
			return null;
		}

		return $template;
	}

	/**
	 * @param array<int,string> $candidates Candidate paths.
	 */
	private static function filterPath(string $path, string $template, array $candidates): string
	{
		if (!function_exists('apply_filters')) {
			return $path;
		}

		$filtered = apply_filters('wla_inmo_template_path', $path, $template, $candidates);

		return is_string($filtered) && $filtered !== '' ? $filtered : $path;
	}
}
