<?php

namespace WLA\Inmo\Core;

use WLA\Inmo\Activity\Schema as ActivitySchema;
use WLA\Inmo\Import\BatchSchema;
use WLA\Inmo\Import\IdentitySchema;
use WLA\Inmo\Quality\Schema as QualitySchema;
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
		dbDelta(QualitySchema::sql($wpdb));
		dbDelta(ActivitySchema::sql($wpdb));
		dbDelta(IdentitySchema::sql($wpdb));
		dbDelta(BatchSchema::sql($wpdb));
		update_option(IndexSchema::DB_VERSION_OPTION, IndexSchema::DB_VERSION, false);
		update_option(QualitySchema::DB_VERSION_OPTION, QualitySchema::DB_VERSION, false);
		update_option(ActivitySchema::DB_VERSION_OPTION, ActivitySchema::DB_VERSION, false);
		update_option(IdentitySchema::DB_VERSION_OPTION, IdentitySchema::DB_VERSION, false);
		update_option(BatchSchema::DB_VERSION_OPTION, BatchSchema::DB_VERSION, false);
	}

	/**
	 * Plugin updates do not execute activation hooks. Check the tiny schema
	 * versions only in admin requests and run dbDelta exclusively on mismatch.
	 */
	public static function maybeUpgrade(): void
	{
		$currentIndex = (string) get_option(IndexSchema::DB_VERSION_OPTION, '0');
		$currentQuality = (string) get_option(QualitySchema::DB_VERSION_OPTION, '0');
		$currentActivity = (string) get_option(ActivitySchema::DB_VERSION_OPTION, '0');
		$currentIdentity = (string) get_option(IdentitySchema::DB_VERSION_OPTION, '0');
		$currentBatch = (string) get_option(BatchSchema::DB_VERSION_OPTION, '0');

		if (
			$currentIndex === IndexSchema::DB_VERSION
			&& $currentQuality === QualitySchema::DB_VERSION
			&& $currentActivity === ActivitySchema::DB_VERSION
			&& $currentIdentity === IdentitySchema::DB_VERSION
			&& $currentBatch === BatchSchema::DB_VERSION
		) {
			return;
		}

		self::install();
	}
}
