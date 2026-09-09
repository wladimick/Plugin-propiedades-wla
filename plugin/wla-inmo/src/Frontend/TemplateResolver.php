<?php

namespace WLA\Inmo\Frontend;

final class TemplateResolver
{
	public const THEME_DIRECTORY = 'wla-inmo';

	/**
	 * @var array<int,string>
	 */
	private const ALLOWED_TEMPLATES = array(
		'archive-property.php',
		'single-property.php',
	);

	/**
	 * Locate an allowlisted template in child theme, parent theme or plugin.
	 */
	public static function locate(string $template): ?string
	{
		$template = self::normalizeSupported($template);
		if ($template === null) {
			return null;
		}

		$candidates = self::themeCandidates($template);
		if ($candidates !== array() && function_exists('locate_template')) {
			$located = locate_template($candidates, false, false);
			if (is_string($located) && $located !== '') {
				return self::filterPath($located, $template, $candidates);
			}
		}

		$fallback = self::pluginFallbackPath($template);
		if ($fallback !== '' && is_file($fallback)) {
			return self::filterPath($fallback, $template, $candidates);
		}

		return null;
	}

	public static function isSupportedTemplate(string $template): bool
	{
		return self::normalizeSupported($template) !== null;
	}

	public static function pluginFallbackPath(string $template): string
	{
		$template = self::normalizeSupported($template);
		if ($template === null) {
			return '';
		}

		return WLA_INMO_DIR . 'templates/' . $template;
	}

	/**
	 * @return array<int,string>
	 */
	private static function themeCandidates(string $template): array
	{
		$expected = self::THEME_DIRECTORY . '/' . $template;
		$candidates = array($expected);

		if (!function_exists('apply_filters')) {
			return $candidates;
		}

		$filtered = apply_filters('wla_inmo_template_candidates', $candidates, $template);
		if (!is_array($filtered) || $filtered === array()) {
			return $candidates;
		}

		$allowed = array();
		foreach ($filtered as $candidate) {
			if (!is_string($candidate)) {
				continue;
			}

			$candidate = str_replace('\\', '/', trim($candidate));
			$candidate = ltrim($candidate, '/');

			if ($candidate === $expected && !in_array($candidate, $allowed, true)) {
				$allowed[] = $candidate;
			}
		}

		return $allowed;
	}

	private static function normalizeSupported(string $template): ?string
	{
		$template = self::normalize($template);
		if ($template === null || !in_array($template, self::ALLOWED_TEMPLATES, true)) {
			return null;
		}

		return $template;
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
	private static function filterPath(string $path, string $template, array $candidates): ?string
	{
		$selected = $path;

		if (function_exists('apply_filters')) {
			$filtered = apply_filters('wla_inmo_template_path', $path, $template, $candidates);
			if (is_string($filtered) && trim($filtered) !== '') {
				$selected = $filtered;
			}
		}

		return self::validatedPath($selected, $template);
	}

	private static function validatedPath(string $path, string $template): ?string
	{
		if (!is_file($path)) {
			return null;
		}

		$realPath = realpath($path);
		if (!is_string($realPath)) {
			return null;
		}

		foreach (self::allowedRoots() as $root) {
			$realRoot = realpath($root);
			if (!is_string($realRoot) || !is_dir($realRoot)) {
				continue;
			}

			$prefix = rtrim($realRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
			if (!str_starts_with($realPath, $prefix)) {
				continue;
			}

			$relative = str_replace('\\', '/', substr($realPath, strlen($prefix)));
			if ($relative === $template) {
				return $realPath;
			}
		}

		return null;
	}

	/**
	 * @return array<int,string>
	 */
	private static function allowedRoots(): array
	{
		$roots = array();

		if (function_exists('get_stylesheet_directory')) {
			$roots[] = rtrim((string) get_stylesheet_directory(), '/\\') . '/' . self::THEME_DIRECTORY;
		}

		if (function_exists('get_template_directory')) {
			$roots[] = rtrim((string) get_template_directory(), '/\\') . '/' . self::THEME_DIRECTORY;
		}

		$roots[] = rtrim(WLA_INMO_DIR, '/\\') . '/templates';

		return array_values(array_unique($roots));
	}
}
