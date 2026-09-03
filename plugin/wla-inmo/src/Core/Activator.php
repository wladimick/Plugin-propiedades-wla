<?php

namespace WLA\Inmo\Core;

use WLA\Inmo\Properties\PostType;

final class Activator
{
	public static function activate(): void
	{
		global $wp_version;

		$wordpressVersion = is_string($wp_version) ? $wp_version : '0';
		$failures = Requirements::failures(PHP_VERSION, $wordpressVersion);

		if (!empty($failures)) {
			if (function_exists('deactivate_plugins')) {
				deactivate_plugins(WLA_INMO_BASENAME);
			}

			$message = implode(' ', $failures);

			wp_die(
				esc_html($message),
				esc_html__('WLA Inmo could not be activated', 'wla-inmo'),
				array('back_link' => true)
			);
		}

		PostType::register();
		flush_rewrite_rules(false);

		update_option('wla_inmo_version', WLA_INMO_VERSION, false);

		/**
		 * Fires after WLA Inmo has been activated successfully.
		 */
		do_action('wla_inmo_activated', WLA_INMO_VERSION);
	}
}
