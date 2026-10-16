<?php

namespace WLA\Inmo\Frontend;

use Throwable;

final class Renderer
{
	/**
	 * Render an allowlisted WLA template inside an isolated local scope.
	 *
	 * Template data is exposed only through $wla_args. Callers cannot provide
	 * filesystem paths; TemplateResolver remains the single path authority.
	 *
	 * @param array<string,mixed> $args Explicit template arguments.
	 */
	public static function render(string $template, array $args = array()): ?string
	{
		$path = TemplateResolver::locate($template);
		if ($path === null) {
			return null;
		}

		if (function_exists('apply_filters')) {
			$filtered = apply_filters('wla_inmo_template_args', $args, $template);
			if (is_array($filtered)) {
				$args = $filtered;
			}
		}

		ob_start();

		try {
			if (function_exists('do_action')) {
				do_action('wla_inmo_before_template', $template, $args);
			}

			self::includeTemplate($path, $args);

			if (function_exists('do_action')) {
				do_action('wla_inmo_after_template', $template, $args);
			}

			$output = ob_get_clean();
		} catch (Throwable $throwable) {
			ob_end_clean();
			throw $throwable;
		}

		return is_string($output) ? $output : '';
	}

	/**
	 * @param array<string,mixed> $args Explicit template arguments.
	 */
	private static function includeTemplate(string $path, array $args): void
	{
		$wla_args = $args;
		include $path;
	}
}
