<?php

namespace WLA\Inmo\Import;

final class NormalizedValue
{
	private bool $valid;
	private $value;
	private ?string $errorCode;

	private function __construct(bool $valid, $value, ?string $errorCode)
	{
		$this->valid = $valid;
		$this->value = $value;
		$this->errorCode = $errorCode;
	}

	public static function valid($value): self
	{
		return new self(true, $value, null);
	}

	public static function invalid(string $errorCode): self
	{
		return new self(false, null, $errorCode);
	}

	public function isValid(): bool
	{
		return $this->valid;
	}

	public function value()
	{
		return $this->value;
	}

	public function errorCode(): ?string
	{
		return $this->errorCode;
	}
}
