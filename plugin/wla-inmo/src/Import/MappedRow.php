<?php

namespace WLA\Inmo\Import;

final class MappedRow
{
	private int $rowNumber;

	/** @var array<string,mixed> */
	private array $values;

	/** @var array<int,string> */
	private array $preservedTargets;

	/** @var array<int,array{code:string,target:string}> */
	private array $errors;

	/**
	 * @param array<string,mixed>                              $values Canonical normalized values.
	 * @param array<int,string>                               $preservedTargets Targets preserved due to empty policy.
	 * @param array<int,array{code:string,target:string}>      $errors Validation errors.
	 */
	public function __construct(int $rowNumber, array $values, array $preservedTargets, array $errors)
	{
		$this->rowNumber = $rowNumber;
		$this->values = $values;
		$this->preservedTargets = array_values(array_unique($preservedTargets));
		$this->errors = array_values($errors);
	}

	public function rowNumber(): int
	{
		return $this->rowNumber;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function values(): array
	{
		return $this->values;
	}

	/**
	 * @return array<int,string>
	 */
	public function preservedTargets(): array
	{
		return $this->preservedTargets;
	}

	/**
	 * @return array<int,array{code:string,target:string}>
	 */
	public function errors(): array
	{
		return $this->errors;
	}

	public function hasErrors(): bool
	{
		return $this->errors !== array();
	}
}
