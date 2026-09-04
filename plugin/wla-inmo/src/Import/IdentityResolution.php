<?php

namespace WLA\Inmo\Import;

use InvalidArgumentException;

final class IdentityResolution
{
	public const NEW = 'new';
	public const MATCH = 'match';
	public const CONFLICT = 'conflict';

	private string $status;

	private ?int $propertyId;

	private string $reason;

	private function __construct(string $status, ?int $propertyId, string $reason)
	{
		if (!in_array($status, array(self::NEW, self::MATCH, self::CONFLICT), true)) {
			throw new InvalidArgumentException('Invalid identity resolution status.');
		}

		if ($status === self::MATCH && ($propertyId === null || $propertyId < 1)) {
			throw new InvalidArgumentException('Matched identity requires a positive property ID.');
		}

		$this->status = $status;
		$this->propertyId = $propertyId;
		$this->reason = $reason;
	}

	public static function newProperty(): self
	{
		return new self(self::NEW, null, 'not_found');
	}

	public static function match(int $propertyId, string $reason): self
	{
		return new self(self::MATCH, $propertyId, $reason);
	}

	public static function conflict(string $reason): self
	{
		return new self(self::CONFLICT, null, $reason);
	}

	public function status(): string
	{
		return $this->status;
	}

	public function propertyId(): ?int
	{
		return $this->propertyId;
	}

	public function reason(): string
	{
		return $this->reason;
	}
}
