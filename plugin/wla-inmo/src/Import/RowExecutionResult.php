<?php

namespace WLA\Inmo\Import;

final class RowExecutionResult
{
	public const STATUS_CREATED = 'created';
	public const STATUS_UPDATED = 'updated';
	public const STATUS_SKIPPED = 'skipped';
	public const STATUS_ERROR = 'error';

	private int $rowNumber;
	private string $status;
	private ?int $propertyId;
	private ?string $identityReason;

	/** @var array<int,array{code:string,target:string}> */
	private array $warnings;

	/** @var array<int,array{code:string,target:string}> */
	private array $errors;

	/**
	 * @param array<int,array{code:string,target:string}> $warnings Warning codes without raw payloads.
	 * @param array<int,array{code:string,target:string}> $errors Error codes without raw payloads.
	 */
	public function __construct(
		int $rowNumber,
		string $status,
		?int $propertyId = null,
		?string $identityReason = null,
		array $warnings = array(),
		array $errors = array()
	) {
		$this->rowNumber = $rowNumber;
		$this->status = $status;
		$this->propertyId = $propertyId;
		$this->identityReason = $identityReason;
		$this->warnings = array_values($warnings);
		$this->errors = array_values($errors);
	}

	/** @param array<int,array{code:string,target:string}> $warnings */
	public static function created(int $rowNumber, int $propertyId, ?string $identityReason, array $warnings = array()): self
	{
		return new self($rowNumber, self::STATUS_CREATED, $propertyId, $identityReason, $warnings);
	}

	/** @param array<int,array{code:string,target:string}> $warnings */
	public static function updated(int $rowNumber, int $propertyId, ?string $identityReason, array $warnings = array()): self
	{
		return new self($rowNumber, self::STATUS_UPDATED, $propertyId, $identityReason, $warnings);
	}

	/** @param array<int,array{code:string,target:string}> $warnings */
	public static function skipped(int $rowNumber, ?int $propertyId, string $reason, array $warnings = array()): self
	{
		return new self(
			$rowNumber,
			self::STATUS_SKIPPED,
			$propertyId,
			$reason,
			$warnings
		);
	}

	/** @param array<int,array{code:string,target:string}> $warnings */
	public static function error(int $rowNumber, string $code, string $target = 'execution', ?int $propertyId = null, array $warnings = array()): self
	{
		return new self(
			$rowNumber,
			self::STATUS_ERROR,
			$propertyId,
			null,
			$warnings,
			array(array('code' => $code, 'target' => $target))
		);
	}

	public function rowNumber(): int
	{
		return $this->rowNumber;
	}

	public function status(): string
	{
		return $this->status;
	}

	public function propertyId(): ?int
	{
		return $this->propertyId;
	}

	public function identityReason(): ?string
	{
		return $this->identityReason;
	}

	/** @return array<int,array{code:string,target:string}> */
	public function warnings(): array
	{
		return $this->warnings;
	}

	/** @return array<int,array{code:string,target:string}> */
	public function errors(): array
	{
		return $this->errors;
	}

	public function isSuccessful(): bool
	{
		return in_array($this->status, array(self::STATUS_CREATED, self::STATUS_UPDATED, self::STATUS_SKIPPED), true);
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return array(
			'row_number'      => $this->rowNumber,
			'status'          => $this->status,
			'property_id'     => $this->propertyId,
			'identity_reason' => $this->identityReason,
			'warnings'        => $this->warnings,
			'errors'          => $this->errors,
		);
	}
}
