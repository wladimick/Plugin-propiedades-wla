<?php

namespace WLA\Inmo\Import;

final class RollbackInspection
{
	public const SAFE = 'safe';
	public const NOOP = 'noop';
	public const BLOCKED = 'blocked';
	public const ERROR = 'error';

	private string $status;
	private string $reason;
	private string $currentHash;

	public function __construct(string $status, string $reason = '', string $currentHash = '')
	{
		if (!in_array($status, array(self::SAFE, self::NOOP, self::BLOCKED, self::ERROR), true)) {
			throw new \InvalidArgumentException('Invalid rollback inspection status.');
		}
		$this->status = $status;
		$this->reason = $reason;
		$this->currentHash = $currentHash;
	}

	public function status(): string
	{
		return $this->status;
	}

	public function reason(): string
	{
		return $this->reason;
	}

	public function currentHash(): string
	{
		return $this->currentHash;
	}

	public function isSafe(): bool
	{
		return in_array($this->status, array(self::SAFE, self::NOOP), true);
	}
}
