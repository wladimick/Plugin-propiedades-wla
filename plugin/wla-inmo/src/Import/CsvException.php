<?php

namespace WLA\Inmo\Import;

use RuntimeException;

final class CsvException extends RuntimeException
{
	private string $reason;

	private ?int $rowNumber;

	public function __construct(string $reason, string $message, ?int $rowNumber = null)
	{
		parent::__construct($message);
		$this->reason = $reason;
		$this->rowNumber = $rowNumber;
	}

	public function reason(): string
	{
		return $this->reason;
	}

	public function rowNumber(): ?int
	{
		return $this->rowNumber;
	}
}
