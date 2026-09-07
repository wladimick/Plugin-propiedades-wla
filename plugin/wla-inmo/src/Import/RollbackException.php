<?php

namespace WLA\Inmo\Import;

use RuntimeException;
use Throwable;

final class RollbackException extends RuntimeException
{
	private string $reason;

	public function __construct(string $reason, string $message, ?Throwable $previous = null)
	{
		parent::__construct($message, 0, $previous);
		$this->reason = $reason;
	}

	public function reason(): string
	{
		return $this->reason;
	}
}
