<?php

namespace WLA\Inmo\Activity;

use WLA\Inmo\Settings\Repository as SettingsRepository;

final class Retention
{
	public const CRON_HOOK = 'wla_inmo_activity_cleanup';
	public const BATCH_SIZE = 500;

	private static bool $registered = false;

	public static function register(): void
	{
		if (self::$registered) {
			return;
		}
		self::$registered = true;
		add_action('init', array(self::class, 'maybeSchedule'), 40);
		add_action(self::CRON_HOOK, array(self::class, 'cleanup'));
	}

	public static function maybeSchedule(): void
	{
		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
		}
	}

	public static function cleanup(): int
	{
		$months = (int) SettingsRepository::get('activity_retention_months', '12');
		$months = max(1, min(120, $months));
		$cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . $months . ' months'));

		return Repository::deleteOlderThan($cutoff, self::BATCH_SIZE);
	}

	public static function unschedule(): void
	{
		$timestamp = wp_next_scheduled(self::CRON_HOOK);
		while ($timestamp !== false) {
			wp_unschedule_event($timestamp, self::CRON_HOOK);
			$timestamp = wp_next_scheduled(self::CRON_HOOK);
		}
	}

	public static function resetForTests(): void
	{
		self::$registered = false;
	}
}
