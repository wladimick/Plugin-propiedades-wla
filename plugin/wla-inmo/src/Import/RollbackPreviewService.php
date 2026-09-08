<?php

namespace WLA\Inmo\Import;

final class RollbackPreviewService
{
	private BatchRepository $batches;
	private RollbackJournalRepository $journal;
	private RollbackInspector $inspector;

	public function __construct(
		?BatchRepository $batches = null,
		?RollbackJournalRepository $journal = null,
		?RollbackInspector $inspector = null
	) {
		$this->batches = $batches ?? new BatchRepository();
		$this->journal = $journal ?? new RollbackJournalRepository();
		$this->inspector = $inspector ?? new RollbackInspector();
	}

	public function preview(string $batchUuid): RollbackPreview
	{
		$batch = $this->batches->find($batchUuid);
		if ($batch === null) {
			return $this->invalidPreview($batchUuid, 0, 'rollback_batch_not_found');
		}

		$revision = (int) ($batch['revision'] ?? 0);
		if ((string) ($batch['status'] ?? '') !== BatchStatus::COMPLETED) {
			return $this->invalidPreview($batchUuid, $revision, 'rollback_batch_not_completed');
		}

		$expectedMutations = max(0, (int) $batch['created_count']) + max(0, (int) $batch['updated_count']);
		$ready = $this->journal->countReady($batchUuid);
		if ($expectedMutations < 1 || $ready !== $expectedMutations) {
			return $this->invalidPreview($batchUuid, $revision, 'rollback_journal_incomplete');
		}

		$context = hash_init('sha256');
		hash_update($context, $batchUuid . '|' . $revision . '|' . $expectedMutations . "\n");

		$safe = 0;
		$noop = 0;
		$blocked = 0;
		$errors = 0;
		$createdToDelete = 0;
		$updatesToRestore = 0;
		/** @var array<string,int> $reasons */
		$reasons = array();
		$offset = 0;
		$seen = 0;

		while ($seen < $ready) {
			$rows = $this->journal->page($batchUuid, min(100, $ready - $seen), $offset);
			if ($rows === array()) {
				++$errors;
				self::bump($reasons, 'rollback_journal_page_missing');
				break;
			}

			foreach ($rows as $row) {
				++$seen;
				++$offset;
				$inspection = $this->inspector->inspect($row);
				$reason = $inspection->reason();
				$action = (string) ($row['original_action'] ?? '');
				hash_update(
					$context,
					implode(
						'|',
						array(
							(string) ($row['row_number'] ?? 0),
							(string) ($row['property_id'] ?? 0),
							$action,
							$inspection->status(),
							$reason,
							$inspection->currentHash(),
						)
					) . "\n"
				);

				if ($inspection->status() === RollbackInspection::SAFE) {
					++$safe;
					if ($action === RollbackJournalState::ACTION_CREATED) {
						++$createdToDelete;
					} elseif ($action === RollbackJournalState::ACTION_UPDATED) {
						++$updatesToRestore;
					}
					continue;
				}

				if ($inspection->status() === RollbackInspection::NOOP) {
					++$noop;
				} elseif ($inspection->status() === RollbackInspection::BLOCKED) {
					++$blocked;
				} else {
					++$errors;
				}
				if ($reason !== '') {
					self::bump($reasons, $reason);
				}
			}
		}

		if ($seen !== $ready) {
			++$errors;
			self::bump($reasons, 'rollback_journal_count_mismatch');
			hash_update($context, 'count_mismatch|' . $seen . '|' . $ready);
		}

		return new RollbackPreview(
			$batchUuid,
			$revision,
			$safe,
			$noop,
			$blocked,
			$errors,
			$createdToDelete,
			$updatesToRestore,
			hash_final($context),
			$reasons
		);
	}

	private function invalidPreview(string $batchUuid, int $revision, string $reason): RollbackPreview
	{
		return new RollbackPreview(
			$batchUuid,
			$revision,
			0,
			0,
			0,
			1,
			0,
			0,
			hash('sha256', $batchUuid . '|' . $revision . '|' . $reason),
			array($reason => 1)
		);
	}

	/** @param array<string,int> $reasons */
	private static function bump(array &$reasons, string $reason): void
	{
		$reasons[$reason] = (int) ($reasons[$reason] ?? 0) + 1;
	}
}
