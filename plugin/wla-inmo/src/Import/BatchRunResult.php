<?php

namespace WLA\Inmo\Import;

final class BatchRunResult
{
	public const STATUS_COMPLETED = 'completed';
	public const STATUS_PAUSED = 'paused';
	public const STATUS_FAILED = 'failed';
	public const STATUS_CONFLICT = 'conflict';
	public const STATUS_ALREADY_COMPLETED = 'already_completed';

	private string $batchUuid;
	private string $status;
	private int $processedThisRun;
	private int $cursorRow;
	private int $revision;
	private ?string $reason;
	private ?int $rowNumber;

	/** @var array<int,string> */
	private array $rowCodes;

	/**
	 * @param array<int,string> $rowCodes Sanitized row error/warning codes only.
	 */
	public function __construct(
		string $batchUuid,
		string $status,
		int $processedThisRun,
		int $cursorRow,
		int $revision,
		?string $reason = null,
		?int $rowNumber = null,
		array $rowCodes = array()
	) {
		$this->batchUuid = $batchUuid;
		$this->status = $status;
		$this->processedThisRun = max(0, $processedThisRun);
		$this->cursorRow = max(0, $cursorRow);
		$this->revision = max(0, $revision);
		$this->reason = $reason;
		$this->rowNumber = $rowNumber;
		$this->rowCodes = array_values(array_unique(array_filter(
			array_map('strval', $rowCodes),
			static fn (string $code): bool => $code !== ''
		)));
	}

	public function status(): string
	{
		return $this->status;
	}

	public function reason(): ?string
	{
		return $this->reason;
	}

	public function processedThisRun(): int
	{
		return $this->processedThisRun;
	}

	public function cursorRow(): int
	{
		return $this->cursorRow;
	}

	public function revision(): int
	{
		return $this->revision;
	}

	public function rowNumber(): ?int
	{
		return $this->rowNumber;
	}

	/** @return array<int,string> */
	public function rowCodes(): array
	{
		return $this->rowCodes;
	}

	public function isSuccessful(): bool
	{
		return in_array($this->status, array(self::STATUS_COMPLETED, self::STATUS_PAUSED, self::STATUS_ALREADY_COMPLETED), true);
	}

	/** @return array<string,mixed> */
	public function toArray(): array
	{
		return array(
			'batch_uuid'         => $this->batchUuid,
			'status'             => $this->status,
			'processed_this_run' => $this->processedThisRun,
			'cursor_row'         => $this->cursorRow,
			'revision'           => $this->revision,
			'reason'             => $this->reason,
			'row_number'         => $this->rowNumber,
			'row_codes'          => $this->rowCodes,
		);
	}
}
