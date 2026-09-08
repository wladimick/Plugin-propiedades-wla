<?php

namespace WLA\Inmo\Import;

use WLA\Inmo\Properties\PostType;

final class RollbackInspector
{
	private RollbackSnapshotter $snapshotter;

	public function __construct(?RollbackSnapshotter $snapshotter = null)
	{
		$this->snapshotter = $snapshotter ?? new RollbackSnapshotter();
	}

	/** @param array<string,mixed> $journalRow */
	public function inspect(array $journalRow): RollbackInspection
	{
		if ((string) ($journalRow['journal_state'] ?? '') !== RollbackJournalState::READY) {
			return new RollbackInspection(RollbackInspection::BLOCKED, 'rollback_journal_not_ready');
		}
		if ((string) ($journalRow['rollback_status'] ?? '') !== RollbackJournalState::ROLLBACK_PENDING) {
			return new RollbackInspection(RollbackInspection::BLOCKED, 'rollback_row_not_pending');
		}

		$propertyId = (int) ($journalRow['property_id'] ?? 0);
		$action = (string) ($journalRow['original_action'] ?? '');
		if ($propertyId < 1 || !RollbackJournalState::isAction($action)) {
			return new RollbackInspection(RollbackInspection::ERROR, 'rollback_journal_invalid');
		}

		if ($action === RollbackJournalState::ACTION_CREATED) {
			return $this->inspectCreated($propertyId, $journalRow);
		}

		return $this->inspectUpdated($propertyId, $journalRow);
	}

	/** @param array<string,mixed> $journalRow */
	private function inspectCreated(int $propertyId, array $journalRow): RollbackInspection
	{
		$post = get_post($propertyId);
		if (!is_object($post)) {
			return new RollbackInspection(RollbackInspection::NOOP, 'rollback_created_property_already_absent', 'absent');
		}
		if ((string) ($post->post_type ?? '') !== PostType::POST_TYPE) {
			return new RollbackInspection(RollbackInspection::BLOCKED, 'rollback_property_type_changed');
		}

		$expected = strtolower((string) ($journalRow['created_object_hash'] ?? ''));
		if (preg_match('/^[a-f0-9]{64}$/', $expected) !== 1) {
			return new RollbackInspection(RollbackInspection::ERROR, 'rollback_created_hash_invalid');
		}

		try {
			$current = $this->snapshotter->createdObjectHash($propertyId);
		} catch (RollbackException) {
			return new RollbackInspection(RollbackInspection::ERROR, 'rollback_created_state_unreadable');
		}

		if (!hash_equals($expected, $current)) {
			return new RollbackInspection(RollbackInspection::BLOCKED, 'rollback_created_property_changed', $current);
		}

		return new RollbackInspection(RollbackInspection::SAFE, '', $current);
	}

	/** @param array<string,mixed> $journalRow */
	private function inspectUpdated(int $propertyId, array $journalRow): RollbackInspection
	{
		$afterJson = $journalRow['after_json'] ?? null;
		$beforeJson = $journalRow['before_json'] ?? null;
		$expectedHash = strtolower((string) ($journalRow['after_hash'] ?? ''));
		if (!is_string($afterJson) || $afterJson === '' || !is_string($beforeJson) || $beforeJson === '') {
			return new RollbackInspection(RollbackInspection::ERROR, 'rollback_snapshot_missing');
		}
		if (preg_match('/^[a-f0-9]{64}$/', $expectedHash) !== 1) {
			return new RollbackInspection(RollbackInspection::ERROR, 'rollback_after_hash_invalid');
		}

		try {
			$after = RollbackSnapshotCodec::decode($afterJson);
			$before = RollbackSnapshotCodec::decode($beforeJson);
			if (!hash_equals($expectedHash, RollbackSnapshotCodec::hash($after))) {
				return new RollbackInspection(RollbackInspection::ERROR, 'rollback_after_snapshot_corrupt');
			}
			$targets = $after['targets'] ?? null;
			$beforeTargets = $before['targets'] ?? null;
			if (!is_array($targets) || !is_array($beforeTargets) || $targets !== $beforeTargets || $targets === array()) {
				return new RollbackInspection(RollbackInspection::ERROR, 'rollback_snapshot_scope_mismatch');
			}
			$current = $this->snapshotter->captureScope($propertyId, array_values(array_map('strval', $targets)));
			$currentHash = RollbackSnapshotCodec::hash($current);
		} catch (RollbackException) {
			return new RollbackInspection(RollbackInspection::BLOCKED, 'rollback_updated_property_missing');
		}

		if (!hash_equals($expectedHash, $currentHash)) {
			return new RollbackInspection(RollbackInspection::BLOCKED, 'rollback_touched_scope_changed', $currentHash);
		}

		return new RollbackInspection(RollbackInspection::SAFE, '', $currentHash);
	}
}
