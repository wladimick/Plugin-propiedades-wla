<?php

namespace WLA\Inmo\Core;

use WLA\Inmo\Search\IndexSchema;

final class Installer
{
	public static function install(): void
	{
		global $wpdb;

		if (!isset($wpdb)) {
			return;
		}

		if (!function_exists('dbDelta')) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		dbDelta(IndexSchema::sql($wpdb));
		update_option(IndexSchema::DB_VERSION_OPTION, IndexSchema::DB_VERSION, false);
	}

	/**
	 * Plugin updates do not execute activation hooks. Check the tiny schema
	 * version only in admin requests and run dbDelta exclusively on mismatch.
	 */
	public static function maybeUpgrade(): void
	{
		$current = (string) get_option(IndexSchema::DB_VERSION_OPTION, '0');

		if ($current === IndexSchema::DB_VERSION) {
			return;
		}

		self::install();
	}
}
