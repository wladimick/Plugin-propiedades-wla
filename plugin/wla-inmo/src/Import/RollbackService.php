<?php

namespace WLA\Inmo\Import;

use Throwable;

final class RollbackService
{
	private BatchRepository $batches;
	private RollbackJournalRepository $journal;
	private RollbackPreviewService $previewService;
	private RollbackInspector $inspector;
	private RollbackRestorer $restorer;
	private RollbackLockInterface $lock;

	/** @var callable():float */
	private $clock;

	public function __construct(
		?BatchRepository $batches = null,
		?RollbackJournalRepository $journal = null,
		?RollbackPreviewService $previewService = null,
		?RollbackInspector $inspector = null,
		?RollbackRestorer $restorer = null,
		?RollbackLockInterface $lock = null,
		?callable $clock = null
	) {
		$this->batches = $batches ?? new BatchRepository();
		$this->journal = $journal ?? new RollbackJournalRepository();
		$this->inspector = $inspector ?? new RollbackInspector();
		$this->previewService = $previewService ?? new RollbackPreviewService($this->batches, $this->journal, $this->inspector);
		$this->restorer = $restorer ?? new RollbackRestorer($this->inspector);
		$this->lock = $lock ?? new OptionRollbackLock();
		$this->clock = $clock ?? static fn (): float => microtime(true);
	}

	public function begin(string $batchUuid, int $expectedRevision, string $expectedPreviewHash): RollbackRunResult
	{
		$batch = $this->batches->find($batchUuid);
		if ($batch === null) {
			return new RollbackRunResult($batchUuid, RollbackRunResult::FAILED, 0, 'rollback_batch_not_found');
		}
		if ((string) $batch['status'] === BatchStatus::ROLLED_BACK) {
			return new RollbackRunResult($batchUuid, RollbackRunResult::ALREADY_ROLLED_BACK);
		}
		if ((string) $batch['status'] !== BatchStatus::COMPLETED || (int) $batch['revision'] !== $expectedRevision) {
			return new RollbackRunResult($batchUuid, RollbackRunResult::CONFLICT, 0, 'rollback_batch_changed_since_preview');
		}

		$preview = $this->previewService->preview($batchUuid);
		if (
			$preview->revision() !== $expectedRevision
			|| $expectedPreviewHash === ''
			|| !hash_equals($preview->hash(), strtolower($expectedPreviewHash))
		) {
			return new RollbackRunResult($batchUuid, RollbackRunResult::CONFLICT, 0, 'rollback_preview_stale');
		}
		if (!$preview->canConfirm()) {
			return new RollbackRunResult($batchUuid, RollbackRunResult::BLOCKED, 0, 'rollback_preview_not_safe');
		}

		if (!$this->batches->transition($batchUuid, BatchStatus::ROLLBACK_PROCESSING, $expectedRevision)) {
			return new RollbackRunResult($batchUuid, RollbackRunResult::CONFLICT, 0, 'rollback_start_conflict');
		}

		$this->emit('wla_inmo_import_rollback_started', $batchUuid, $expectedRevision + 1);

		return new RollbackRunResult($batchUuid, RollbackRunResult::STARTED);
	}

	public function run(string $batchUuid, int $maxRows = 25, float $maxSeconds = 5.0): RollbackRunResult
	{
		if ($maxRows < 1 || $maxRows > 250 || $maxSeconds <= 0 || $maxSeconds > 15) {
			return new RollbackRunResult($batchUuid, RollbackRunResult::FAILED, 0, 'rollback_invalid_budget');
		}

		$token = $this->lock->acquire($batchUuid, max(30, (int) ceil($maxSeconds) + 15));
		if ($token === null) {
			return new RollbackRunResult($batchUuid, RollbackRunResult::CONFLICT, 0, 'rollback_batch_locked');
		}

		try {
			return $this->runLocked($batchUuid, $maxRows, $maxSeconds);
		} finally {
			$this->lock->release($batchUuid, $token);
		}
	}

	private function runLocked(string $batchUuid, int $maxRows, float $maxSeconds): RollbackRunResult
	{
		$batch = $this->batches->find($batchUuid);
		if ($batch === null) {
			return new RollbackRunResult($batchUuid, RollbackRunResult::FAILED, 0, 'rollback_batch_not_found');
		}
		$status = (string) $batch['status'];
		if ($status === BatchStatus::ROLLED_BACK) {
			return new RollbackRunResult($batchUuid, RollbackRunResult::ALREADY_ROLLED_BACK);
		}
		if ($status === BatchStatus::ROLLBACK_BLOCKED) {
			return new RollbackRunResult($batchUuid, RollbackRunResult::BLOCKED, 0, 'rollback_batch_blocked');
		}
		if ($status !== BatchStatus::ROLLBACK_PROCESSING) {
			return new RollbackRunResult($batchUuid, RollbackRunResult::FAILED, 0, 'rollback_not_started');
		}

		$revision = (int) $batch['revision'];
		$processed = 0;
		$startedAt = ($this->clock)();

		while ($processed < $maxRows && (($this->clock)() - $startedAt) < $maxSeconds) {
			$rows = $this->journal->pendingPageDescending($batchUuid, 1);
			if ($rows === array()) {
				return $this->finish($batchUuid, $revision, $processed);
			}

			$row = $rows[0];
			$rowNumber = (int) ($row['row_number'] ?? 0);
			$inspection = $this->inspector->inspect($row);
			if (!$inspection->isSafe()) {
				$reason = $inspection->reason() !== '' ? $inspection->reason() : 'rollback_row_not_safe';
				$markStatus = $inspection->status() === RollbackInspection::ERROR
					? RollbackJournalState::ROLLBACK_ERROR
					: RollbackJournalState::ROLLBACK_BLOCKED;
				$this->journal->markRollback($batchUuid, $rowNumber, $markStatus, $reason);
				$this->batches->transition($batchUuid, BatchStatus::ROLLBACK_BLOCKED, $revision);
				$this->emit('wla_inmo_import_rollback_blocked', $batchUuid, $rowNumber, $reason);
				return new RollbackRunResult($batchUuid, RollbackRunResult::BLOCKED, $processed, $reason);
			}

			try {
				$this->restorer->restore($row);
			} catch (RollbackException $exception) {
				$reason = $exception->reason();
				$this->journal->markRollback($batchUuid, $rowNumber, RollbackJournalState::ROLLBACK_ERROR, $reason);
				$this->batches->transition($batchUuid, BatchStatus::ROLLBACK_BLOCKED, $revision);
				$this->emit('wla_inmo_import_rollback_blocked', $batchUuid, $rowNumber, $reason);
				return new RollbackRunResult($batchUuid, RollbackRunResult::BLOCKED, $processed, $reason);
			} catch (Throwable) {
				$reason = 'rollback_unexpected_restore_failure';
				$this->journal->markRollback($batchUuid, $rowNumber, RollbackJournalState::ROLLBACK_ERROR, $reason);
				$this->batches->transition($batchUuid, BatchStatus::ROLLBACK_BLOCKED, $revision);
				$this->emit('wla_inmo_import_rollback_blocked', $batchUuid, $rowNumber, $reason);
				return new RollbackRunResult($batchUuid, RollbackRunResult::BLOCKED, $processed, $reason);
			}

			if (!$this->journal->markRollback($batchUuid, $rowNumber, RollbackJournalState::ROLLBACK_ROLLED_BACK)) {
				$this->batches->transition($batchUuid, BatchStatus::ROLLBACK_BLOCKED, $revision);
				return new RollbackRunResult($batchUuid, RollbackRunResult::BLOCKED, $processed, 'rollback_journal_commit_failed');
			}

			++$processed;
			$this->emit('wla_inmo_import_rollback_row_completed', $batchUuid, $rowNumber);
		}

		if ($this->journal->countRollbackStatus($batchUuid, RollbackJournalState::ROLLBACK_PENDING) === 0) {
			return $this->finish($batchUuid, $revision, $processed);
		}

		return new RollbackRunResult($batchUuid, RollbackRunResult::PROCESSING, $processed, 'rollback_budget_reached');
	}

	private function finish(string $batchUuid, int $revision, int $processed): RollbackRunResult
	{
		$blocked = $this->journal->countRollbackStatus($batchUuid, RollbackJournalState::ROLLBACK_BLOCKED);
		$errors = $this->journal->countRollbackStatus($batchUuid, RollbackJournalState::ROLLBACK_ERROR);
		if ($blocked > 0 || $errors > 0) {
			$this->batches->transition($batchUuid, BatchStatus::ROLLBACK_BLOCKED, $revision);
			$this->emit('wla_inmo_import_rollback_blocked', $batchUuid, 0, 'rollback_rows_blocked');
			return new RollbackRunResult($batchUuid, RollbackRunResult::BLOCKED, $processed, 'rollback_rows_blocked');
		}

		if (!$this->batches->transition($batchUuid, BatchStatus::ROLLED_BACK, $revision)) {
			return new RollbackRunResult($batchUuid, RollbackRunResult::CONFLICT, $processed, 'rollback_completion_conflict');
		}

		$this->emit('wla_inmo_import_rollback_completed', $batchUuid, $revision + 1);
		return new RollbackRunResult($batchUuid, RollbackRunResult::ROLLED_BACK, $processed);
	}

	private function emit(string $hook, mixed ...$args): void
	{
		if (function_exists('do_action')) {
			do_action($hook, ...$args);
		}
	}
}
