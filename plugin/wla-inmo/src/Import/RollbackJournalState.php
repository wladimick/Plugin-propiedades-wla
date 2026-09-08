<?php

namespace WLA\Inmo\Import;

final class RollbackJournalState
{
	public const ACTION_CREATED = 'created';
	public const ACTION_UPDATED = 'updated';

	public const PREPARED = 'prepared';
	public const READY = 'ready';

	public const ROLLBACK_PENDING = 'pending';
	public const ROLLBACK_ROLLED_BACK = 'rolled_back';
	public const ROLLBACK_BLOCKED = 'blocked';
	public const ROLLBACK_ERROR = 'error';

	public static function isAction(string $action): bool
	{
		return in_array($action, array(self::ACTION_CREATED, self::ACTION_UPDATED), true);
	}

	public static function isJournalState(string $state): bool
	{
		return in_array($state, array(self::PREPARED, self::READY), true);
	}

	public static function isRollbackStatus(string $status): bool
	{
		return in_array(
			$status,
			array(
				self::ROLLBACK_PENDING,
				self::ROLLBACK_ROLLED_BACK,
				self::ROLLBACK_BLOCKED,
				self::ROLLBACK_ERROR,
			),
			true
		);
	}
}
