<?php

namespace WLA\Inmo\Import;

final class WorkspaceJanitor
{
	private const CRON_HOOK = 'wla_inmo_import_workspace_cleanup';
	private const DRAFT_RETENTION_SECONDS = 7200;
	private const MAX_FILES_PER_RUN = 250;

	public static function register(): void
	{
		add_action(self::CRON_HOOK, array(self::class, 'cleanup'));
		add_action('init', array(self::class, 'schedule'));
	}

	public static function schedule(): void
	{
		if (wp_next_scheduled(self::CRON_HOOK) === false) {
			wp_schedule_event(time() + 300, 'hourly', self::CRON_HOOK);
		}
	}

	public static function unschedule(): void
	{
		wp_clear_scheduled_hook(self::CRON_HOOK);
	}

	public static function cleanup(): void
	{
		$root = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();
		$files = glob(trailingslashit($root) . 'wla-inmo-import-draft-*.csv');
		if (!is_array($files)) {
			return;
		}

		$cutoff = time() - self::DRAFT_RETENTION_SECONDS;
		$processed = 0;
		foreach ($files as $path) {
			if ($processed >= self::MAX_FILES_PER_RUN) {
				break;
			}
			++$processed;

			if (!is_file($path)) {
				continue;
			}

			$modified = filemtime($path);
			if ($modified !== false && $modified < $cutoff) {
				@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort cleanup of plugin-owned stale draft files.
			}
		}
	}
}
