<?php

namespace WLA\Inmo\Import;

final class RollbackJournalRecorder implements RollbackJournalRecorderInterface
{
	private RollbackJournalRepository $repository;
	private RollbackSnapshotter $snapshotter;

	public function __construct(?RollbackJournalRepository $repository = null, ?RollbackSnapshotter $snapshotter = null)
	{
		$this->repository = $repository ?? new RollbackJournalRepository();
		$this->snapshotter = $snapshotter ?? new RollbackSnapshotter();
	}

	public function prepare(string $batchUuid, DryRunResult $dryRun): bool
	{
		$rowNumber = $dryRun->rowNumber();
		if ($rowNumber < 1) {
			return false;
		}

		$existing = $this->repository->findRow($batchUuid, $rowNumber);
		if ($existing !== null) {
			return $this->existingIntentStillSafe($existing);
		}

		$action = match ($dryRun->status()) {
			DryRunResult::STATUS_NEW => RollbackJournalState::ACTION_CREATED,
			DryRunResult::STATUS_UPDATE => RollbackJournalState::ACTION_UPDATED,
			default => '',
		};
		if ($action === '') {
			return false;
		}

		$targets = array_keys($dryRun->values());
		$targets = array_values(array_unique(array_map('strval', $targets)));
		sort($targets, SORT_STRING);
		if ($targets === array()) {
			return false;
		}

		$beforeJson = null;
		if ($action === RollbackJournalState::ACTION_UPDATED) {
			$propertyId = $dryRun->propertyId();
			if ($propertyId === null || $propertyId < 1) {
				return false;
			}
			try {
				$beforeJson = RollbackSnapshotCodec::encode($this->snapshotter->captureScope($propertyId, $targets));
			} catch (RollbackException) {
				return false;
			}
		}

		try {
			$targetsJson = RollbackSnapshotCodec::encode(array('targets' => $targets));
		} catch (RollbackException) {
			return false;
		}

		return $this->repository->prepareIntent($batchUuid, $rowNumber, $action, $targetsJson, $beforeJson);
	}

	public function finalize(string $batchUuid, DryRunResult $dryRun, RowExecutionResult $execution): bool
	{
		if ($execution->status() === RowExecutionResult::STATUS_ERROR) {
			return false;
		}

		$propertyId = $execution->propertyId();
		if ($propertyId === null || $propertyId < 1) {
			return false;
		}

		$row = $this->repository->findRow($batchUuid, $dryRun->rowNumber());
		if ($row === null || (string) $row['rollback_status'] !== RollbackJournalState::ROLLBACK_PENDING) {
			return false;
		}

		try {
			$targetsDocument = RollbackSnapshotCodec::decode((string) $row['targets_json']);
			$targets = $targetsDocument['targets'] ?? null;
			if (!is_array($targets) || $targets === array()) {
				return false;
			}
			$targets = array_values(array_map('strval', $targets));
			$after = $this->snapshotter->captureScope($propertyId, $targets);
			$afterJson = RollbackSnapshotCodec::encode($after);
			$afterHash = RollbackSnapshotCodec::hash($after);
			$createdObjectHash = (string) $row['original_action'] === RollbackJournalState::ACTION_CREATED
				? $this->snapshotter->createdObjectHash($propertyId)
				: '';
		} catch (RollbackException) {
			return false;
		}

		return $this->repository->finalize(
			$batchUuid,
			$dryRun->rowNumber(),
			$propertyId,
			$afterJson,
			$afterHash,
			$createdObjectHash
		);
	}

	/** @param array<string,mixed> $row */
	private function existingIntentStillSafe(array $row): bool
	{
		if ((string) $row['rollback_status'] !== RollbackJournalState::ROLLBACK_PENDING) {
			return false;
		}

		if ((string) $row['journal_state'] === RollbackJournalState::PREPARED) {
			return true;
		}

		if ((string) $row['journal_state'] !== RollbackJournalState::READY) {
			return false;
		}

		$propertyId = (int) $row['property_id'];
		if ($propertyId < 1 || !is_string($row['after_json']) || $row['after_json'] === '') {
			return false;
		}

		try {
			$after = RollbackSnapshotCodec::decode($row['after_json']);
			$targets = $after['targets'] ?? null;
			if (!is_array($targets) || $targets === array()) {
				return false;
			}
			$current = $this->snapshotter->captureScope($propertyId, array_values(array_map('strval', $targets)));
		} catch (RollbackException) {
			return false;
		}

		return hash_equals((string) $row['after_hash'], RollbackSnapshotCodec::hash($current));
	}
}
