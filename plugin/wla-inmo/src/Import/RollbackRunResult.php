<?php

namespace WLA\Inmo\Import;

final class RollbackRunResult
{
	public const STARTED = 'started';
	public const PROCESSING = 'processing';
	public const ROLLED_BACK = 'rolled_back';
	public const BLOCKED = 'blocked';
	public const CONFLICT = 'conflict';
	public const FAILED = 'failed';
	public const ALREADY_ROLLED_BACK = 'already_rolled_back';

	public function __construct(
		private string $batchUuid,
		private string $status,
		private int $processedRows = 0,
		private string $reason = ''
	) {
	}

	public function batchUuid(): string { return $this->batchUuid; }
	public function status(): string { return $this->status; }
	public function processedRows(): int { return $this->processedRows; }
	public function reason(): string { return $this->reason; }

	/** @return array{batch_uuid:string,status:string,processed_rows:int,reason:string} */
	public function toArray(): array
	{
		return array(
			'batch_uuid'     => $this->batchUuid,
			'status'         => $this->status,
			'processed_rows' => max(0, $this->processedRows),
			'reason'         => $this->reason,
		);
	}
}
