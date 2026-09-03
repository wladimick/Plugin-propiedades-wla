<?php

namespace WLA\Inmo\Core;

final class Deactivator
{
	public static function deactivate(): void
	{
		/**
		 * Fires when WLA Inmo is deactivated.
		 *
		 * Deactivation intentionally does not remove properties, settings, media,
		 * logs or future index tables. Destructive cleanup belongs to an explicit
		 * uninstall flow and must remain opt-in.
		 */
		do_action('wla_inmo_deactivated');
	}
}
