<?php

namespace WLA\Inmo\Activity;

final class RollbackObserver
{
	private static bool $registered = false;

	public static function register(): void
	{
		if (self::$registered) {
			return;
		}
		self::$registered = true;

		add_action('wla_inmo_import_rollback_previewed', array(self::class, 'onPreviewed'), 10, 2);
		add_action('wla_inmo_import_rollback_started', array(self::class, 'onStarted'), 10, 2);
		add_action('wla_inmo_import_rollback_completed', array(self::class, 'onCompleted'), 10, 2);
		add_action('wla_inmo_import_rollback_blocked', array(self::class, 'onBlocked'), 10, 3);
	}

	/** @param array<string,mixed> $preview */
	public static function onPreviewed(string $batchUuid, array $preview): void
	{
		Recorder::record(
			EventTypes::IMPORT_ROLLBACK_PREVIEWED,
			'import_batch',
			null,
			array(
				'batch_ref' => self::batchRef($batchUuid),
				'safe'      => (int) ($preview['safe'] ?? 0),
				'noop'      => (int) ($preview['noop'] ?? 0),
				'blocked'   => (int) ($preview['blocked'] ?? 0),
				'errors'    => (int) ($preview['errors'] ?? 0),
				'created'   => (int) ($preview['created_to_delete'] ?? 0),
				'updated'   => (int) ($preview['updates_to_restore'] ?? 0),
			)
		);
	}

	public static function onStarted(string $batchUuid, int $revision): void
	{
		Recorder::record(
			EventTypes::IMPORT_ROLLBACK_STARTED,
			'import_batch',
			null,
			array('batch_ref' => self::batchRef($batchUuid), 'revision' => $revision)
		);
	}

	public static function onCompleted(string $batchUuid, int $revision): void
	{
		Recorder::record(
			EventTypes::IMPORT_ROLLBACK_COMPLETED,
			'import_batch',
			null,
			array('batch_ref' => self::batchRef($batchUuid), 'revision' => $revision)
		);
	}

	public static function onBlocked(string $batchUuid, int $rowNumber, string $reason): void
	{
		Recorder::record(
			EventTypes::IMPORT_ROLLBACK_BLOCKED,
			'import_batch',
			null,
			array(
				'batch_ref' => self::batchRef($batchUuid),
				'row'       => max(0, $rowNumber),
				'reason'    => $reason,
			)
		);
	}

	private static function batchRef(string $batchUuid): string
	{
		return substr(hash('sha256', strtolower(trim($batchUuid))), 0, 16);
	}

	public static function resetForTests(): void
	{
		self::$registered = false;
	}
}
