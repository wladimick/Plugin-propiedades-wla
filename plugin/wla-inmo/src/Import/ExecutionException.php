<?php

namespace WLA\Inmo\Import;

use RuntimeException;
use Throwable;

final class ExecutionException extends RuntimeException
{
	private string $reason;
	private string $target;

	public function __construct(string $reason, string $target = 'persistence', ?Throwable $previous = null)
	{
		$this->reason = $reason;
		$this->target = $target;

		parent::__construct($reason, 0, $previous);
	}

	public function reason(): string
	{
		return $this->reason;
	}

	public function target(): string
	{
		return $this->target;
	}
}
