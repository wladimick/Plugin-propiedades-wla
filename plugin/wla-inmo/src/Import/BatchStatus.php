<?php

namespace WLA\Inmo\Import;

final class BatchStatus
{
	public const UPLOADED = 'uploaded';
	public const MAPPED = 'mapped';
	public const VALIDATED = 'validated';
	public const DRY_RUN_READY = 'dry_run_ready';
	public const CONFIRMED = 'confirmed';
	public const PROCESSING = 'processing';
	public const PAUSED = 'paused';
	public const FAILED = 'failed';
	public const COMPLETED = 'completed';
	public const CANCELLED = 'cancelled';
	public const ROLLED_BACK = 'rolled_back';
	public const ROLLBACK_BLOCKED = 'rollback_blocked';

	/**
	 * @return array<int,string>
	 */
	public static function all(): array
	{
		return array(
			self::UPLOADED,
			self::MAPPED,
			self::VALIDATED,
			self::DRY_RUN_READY,
			self::CONFIRMED,
			self::PROCESSING,
			self::PAUSED,
			self::FAILED,
			self::COMPLETED,
			self::CANCELLED,
			self::ROLLED_BACK,
			self::ROLLBACK_BLOCKED,
		);
	}

	/**
	 * @return array<int,string>
	 */
	public static function terminal(): array
	{
		return array(
			self::CANCELLED,
			self::ROLLED_BACK,
			self::ROLLBACK_BLOCKED,
		);
	}

	public static function isValid(string $status): bool
	{
		return in_array($status, self::all(), true);
	}

	public static function canTransition(string $from, string $to): bool
	{
		$transitions = array(
			self::UPLOADED => array(self::MAPPED, self::CANCELLED),
			self::MAPPED => array(self::VALIDATED, self::CANCELLED),
			self::VALIDATED => array(self::DRY_RUN_READY, self::CANCELLED),
			self::DRY_RUN_READY => array(self::CONFIRMED, self::CANCELLED),
			self::CONFIRMED => array(self::PROCESSING, self::CANCELLED),
			self::PROCESSING => array(self::PAUSED, self::FAILED, self::COMPLETED),
			self::PAUSED => array(self::PROCESSING, self::CANCELLED),
			self::FAILED => array(self::PROCESSING, self::CANCELLED),
			self::COMPLETED => array(self::ROLLED_BACK, self::ROLLBACK_BLOCKED),
			self::CANCELLED => array(),
			self::ROLLED_BACK => array(),
			self::ROLLBACK_BLOCKED => array(),
		);

		return isset($transitions[$from]) && in_array($to, $transitions[$from], true);
	}
}
