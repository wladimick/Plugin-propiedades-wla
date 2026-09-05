<?php

namespace WLA\Inmo\Import;

final class BatchCheckpoint
{
	private BatchRepository $batches;

	public function __construct(?BatchRepository $batches = null)
	{
		$this->batches = $batches ?? new BatchRepository();
	}

	/**
	 * Confirm exactly one completed row in a processing batch.
	 *
	 * Error results are deliberately not checkpointed: a caller may retry the
	 * row after correcting/recovering the failure without advancing the cursor.
	 * The byte offset is persisted atomically with the logical row checkpoint so
	 * resumed CSV slices do not need to rescan previously confirmed rows.
	 */
	public function confirm(
		string $batchUuid,
		int $expectedRevision,
		RowExecutionResult $result,
		?int $cursorOffset = null
	): bool {
		if (
			!$result->isSuccessful()
			|| $result->status() === RowExecutionResult::STATUS_ERROR
			|| ($cursorOffset !== null && $cursorOffset < 0)
		) {
			return false;
		}

		$batch = $this->batches->find($batchUuid);
		if (
			$batch === null
			|| (string) ($batch['status'] ?? '') !== BatchStatus::PROCESSING
			|| (int) ($batch['revision'] ?? -1) !== $expectedRevision
		) {
			return false;
		}

		$processed = (int) ($batch['processed_rows'] ?? 0) + 1;
		$cursor = (int) ($batch['cursor_row'] ?? 0) + 1;
		$total = (int) ($batch['total_rows'] ?? 0);
		if ($processed > $total || $cursor > $total) {
			return false;
		}

		$counters = array(
			'created'  => (int) ($batch['created_count'] ?? 0),
			'updated'  => (int) ($batch['updated_count'] ?? 0),
			'skipped'  => (int) ($batch['skipped_count'] ?? 0),
			'warnings' => (int) ($batch['warning_count'] ?? 0) + count($result->warnings()),
			'errors'   => (int) ($batch['error_count'] ?? 0),
		);

		if ($result->status() === RowExecutionResult::STATUS_CREATED) {
			++$counters['created'];
		} elseif ($result->status() === RowExecutionResult::STATUS_UPDATED) {
			++$counters['updated'];
		} elseif ($result->status() === RowExecutionResult::STATUS_SKIPPED) {
			++$counters['skipped'];
		} else {
			return false;
		}

		return $this->batches->advanceProgress(
			$batchUuid,
			$expectedRevision,
			$cursor,
			$processed,
			$counters,
			$cursorOffset
		);
	}
}
