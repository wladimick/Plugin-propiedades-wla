<?php

namespace WLA\Inmo\Import;

final class DryRunResult
{
	public const STATUS_NEW = 'new';
	public const STATUS_UPDATE = 'update';
	public const STATUS_ERROR = 'error';

	private int $rowNumber;
	private string $status;
	private ?int $propertyId;

	/** @var array<string,mixed> */
	private array $values;

	/** @var array<int,string> */
	private array $preservedTargets;

	/** @var array<int,string> */
	private array $changedTargets;

	/** @var array<int,array{code:string,target:string}> */
	private array $warnings;

	/** @var array<int,array{code:string,target:string}> */
	private array $errors;

	/**
	 * @param array<string,mixed>                         $values Canonical normalized values.
	 * @param array<int,string>                          $preservedTargets Preserved targets.
	 * @param array<int,string>                          $changedTargets Changed targets for updates.
	 * @param array<int,array{code:string,target:string}> $warnings Warnings without raw payloads.
	 * @param array<int,array{code:string,target:string}> $errors Errors without raw payloads.
	 */
	public function __construct(
		int $rowNumber,
		string $status,
		?int $propertyId,
		array $values,
		array $preservedTargets,
		array $changedTargets,
		array $warnings,
		array $errors
	) {
		$this->rowNumber = $rowNumber;
		$this->status = $status;
		$this->propertyId = $propertyId;
		$this->values = $values;
		$this->preservedTargets = array_values(array_unique($preservedTargets));
		$this->changedTargets = array_values(array_unique($changedTargets));
		$this->warnings = array_values($warnings);
		$this->errors = array_values($errors);
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

	/** @return array<string,mixed> */
	public function values(): array
	{
		return $this->values;
	}

	/** @return array<int,string> */
	public function preservedTargets(): array
	{
		return $this->preservedTargets;
	}

	/** @return array<int,string> */
	public function changedTargets(): array
	{
		return $this->changedTargets;
	}

	/**
	 * Public-safe representation. Private canonical fields are omitted unless explicitly requested.
	 *
	 * @return array<string,mixed>
	 */
	public function toArray(bool $includePrivate = false): array
	{
		$values = array();
		foreach ($this->values as $target => $value) {
			if ($includePrivate || !TargetRegistry::isPrivate($target)) {
				$values[$target] = $value;
			}
		}

		$preserved = array_values(array_filter(
			$this->preservedTargets,
			static fn (string $target): bool => $includePrivate || !TargetRegistry::isPrivate($target)
		));
		$changed = array_values(array_filter(
			$this->changedTargets,
			static fn (string $target): bool => $includePrivate || !TargetRegistry::isPrivate($target)
		));

		return array(
			'row_number' => $this->rowNumber,
			'status' => $this->status,
			'property_id' => $this->propertyId,
			'values' => $values,
			'preserved_targets' => $preserved,
			'changed_targets' => $changed,
			'warnings' => $this->warnings,
			'errors' => $this->errors,
		);
	}
}
