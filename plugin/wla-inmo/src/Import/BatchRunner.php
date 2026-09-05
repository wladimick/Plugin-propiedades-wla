<?php

namespace WLA\Inmo\Import;

use Throwable;

final class BatchRunner
{
	private BatchRepository $batches;
	private BatchCheckpoint $checkpoint;
	private IdentityResolver $identityResolver;
	private RowExecutor $executor;
	private ?CsvReader $reader;

	/** @var callable(string,string):array<int,array<string,mixed>> */
	private $taxonomyLookup;

	/** @var callable():float */
	private $clock;

	/**
	 * @param callable(string,string):array<int,array<string,mixed>>|null $taxonomyLookup Taxonomy resolver.
	 * @param callable():float|null                                      $clock Monotonic-enough clock seam for tests.
	 */
	public function __construct(
		?BatchRepository $batches = null,
		?BatchCheckpoint $checkpoint = null,
		?IdentityResolver $identityResolver = null,
		?RowExecutor $executor = null,
		?CsvReader $reader = null,
		?callable $taxonomyLookup = null,
		?callable $clock = null
	) {
		$this->batches = $batches ?? new BatchRepository();
		$this->checkpoint = $checkpoint ?? new BatchCheckpoint($this->batches);

		if ($identityResolver === null) {
			$identityResolver = (new IdentityRepository())->resolver();
		}
		$this->identityResolver = $identityResolver;
		$this->executor = $executor ?? new RowExecutor($identityResolver, new WordPressPropertyWriter());
		$this->reader = $reader;
		$this->taxonomyLookup = $taxonomyLookup ?? array(WordPressTaxonomyLookup::class, 'lookup');
		$this->clock = $clock ?? static fn (): float => microtime(true);
	}

	public function run(string $batchUuid, string $sourcePath, int $maxRows = 25, float $maxSeconds = 5.0): BatchRunResult
	{
		if ($maxRows < 1 || $maxSeconds <= 0) {
			return new BatchRunResult($batchUuid, BatchRunResult::STATUS_FAILED, 0, 0, 0, 'invalid_run_budget');
		}

		$batch = $this->batches->find($batchUuid);
		if ($batch === null) {
			return new BatchRunResult($batchUuid, BatchRunResult::STATUS_FAILED, 0, 0, 0, 'batch_not_found');
		}

		if ((string) $batch['status'] === BatchStatus::COMPLETED) {
			return new BatchRunResult(
				$batchUuid,
				BatchRunResult::STATUS_ALREADY_COMPLETED,
				0,
				(int) $batch['cursor_row'],
				(int) $batch['revision']
			);
		}

		$batch = $this->claimProcessing($batchUuid, $batch);
		if ($batch === null) {
			return new BatchRunResult($batchUuid, BatchRunResult::STATUS_CONFLICT, 0, 0, 0, 'batch_claim_conflict');
		}

		$status = (string) $batch['status'];
		$revision = (int) $batch['revision'];
		$cursor = (int) $batch['cursor_row'];
		$cursorOffset = (int) ($batch['cursor_offset'] ?? 0);
		$processedTotal = (int) $batch['processed_rows'];
		$totalRows = (int) $batch['total_rows'];

		if ($status !== BatchStatus::PROCESSING) {
			return new BatchRunResult($batchUuid, BatchRunResult::STATUS_FAILED, 0, $cursor, $revision, 'invalid_batch_status');
		}

		if (
			$cursor < 0
			|| $cursorOffset < 0
			|| $processedTotal !== $cursor
			|| $totalRows < 0
			|| $cursor > $totalRows
			|| ($cursor === 0 && $cursorOffset !== 0)
			|| ($cursor > 0 && $cursorOffset === 0)
		) {
			return $this->failProcessing($batchUuid, $revision, 0, $cursor, 'invalid_batch_progress');
		}

		try {
			$profile = MappingProfileCodec::decode((string) $batch['profile_json']);
		} catch (MappingException $exception) {
			return $this->failProcessing($batchUuid, $revision, 0, $cursor, $exception->reason());
		}

		if ($profile->sourceKey() !== (string) $batch['source_key']) {
			return $this->failProcessing($batchUuid, $revision, 0, $cursor, 'profile_source_mismatch');
		}

		$this->emit('wla_inmo_import_batch_run_started', $batchUuid, $cursor, $revision);

		$processedThisRun = 0;
		$startedAt = ($this->clock)();
		$reader = $this->reader ?? new CsvReader(max(10000, $totalRows + 1));

		try {
			$rows = $reader->verifiedRows(
				$sourcePath,
				(string) $batch['source_hash'],
				$cursorOffset,
				$cursor
			);

			foreach ($rows as $row) {
				if ($cursor >= $totalRows) {
					return $this->failProcessing(
						$batchUuid,
						$revision,
						$processedThisRun,
						$cursor,
						'source_row_count_mismatch',
						(int) $row['row_number']
					);
				}

				if ($processedThisRun >= $maxRows || (($this->clock)() - $startedAt) >= $maxSeconds) {
					return $this->pauseProcessing($batchUuid, $revision, $processedThisRun, $cursor, 'run_budget_reached');
				}

				$dryRun = $this->prepareRow($profile, $row);
				if ($dryRun === null) {
					return $this->failProcessing(
						$batchUuid,
						$revision,
						$processedThisRun,
						$cursor,
						'row_prepare_failed',
						(int) $row['row_number']
					);
				}

				if ($dryRun->status() === DryRunResult::STATUS_ERROR || $dryRun->errors() !== array()) {
					return $this->failProcessing(
						$batchUuid,
						$revision,
						$processedThisRun,
						$cursor,
						'row_validation_failed',
						$dryRun->rowNumber(),
						self::codes($dryRun->errors())
					);
				}

				$execution = $this->executor->execute($dryRun, $profile->sourceKey());
				if ($execution->status() === RowExecutionResult::STATUS_ERROR) {
					return $this->failProcessing(
						$batchUuid,
						$revision,
						$processedThisRun,
						$cursor,
						'row_execution_failed',
						$execution->rowNumber(),
						self::codes($execution->errors())
					);
				}

				$nextOffset = (int) $row['next_offset'];
				if ($nextOffset <= $cursorOffset) {
					return $this->failProcessing(
						$batchUuid,
						$revision,
						$processedThisRun,
						$cursor,
						'invalid_source_offset',
						$execution->rowNumber()
					);
				}

				if (!$this->checkpoint->confirm($batchUuid, $revision, $execution, $nextOffset)) {
					return new BatchRunResult(
						$batchUuid,
						BatchRunResult::STATUS_CONFLICT,
						$processedThisRun,
						$cursor,
						$revision,
						'checkpoint_conflict',
						$execution->rowNumber()
					);
				}

				++$revision;
				++$cursor;
				$cursorOffset = $nextOffset;
				++$processedThisRun;
			}
		} catch (CsvException $exception) {
			$reason = self::sourceFailureReason($exception->reason());
			$rowCodes = $reason === 'source_parse_failed' ? array($exception->reason()) : array();

			return $this->failProcessing(
				$batchUuid,
				$revision,
				$processedThisRun,
				$cursor,
				$reason,
				$exception->rowNumber(),
				$rowCodes
			);
		} catch (Throwable) {
			return $this->failProcessing($batchUuid, $revision, $processedThisRun, $cursor, 'unexpected_runner_failure');
		}

		if ($cursor !== $totalRows) {
			return $this->failProcessing($batchUuid, $revision, $processedThisRun, $cursor, 'source_row_count_mismatch');
		}

		if (!$this->batches->transition($batchUuid, BatchStatus::COMPLETED, $revision)) {
			return new BatchRunResult(
				$batchUuid,
				BatchRunResult::STATUS_CONFLICT,
				$processedThisRun,
				$cursor,
				$revision,
				'completion_conflict'
			);
		}

		++$revision;
		$this->emit('wla_inmo_import_batch_completed', $batchUuid, $cursor, $revision);

		return new BatchRunResult(
			$batchUuid,
			BatchRunResult::STATUS_COMPLETED,
			$processedThisRun,
			$cursor,
			$revision
		);
	}

	/**
	 * @param array<string,mixed> $batch Current batch row.
	 * @return array<string,mixed>|null
	 */
	private function claimProcessing(string $batchUuid, array $batch): ?array
	{
		$status = (string) ($batch['status'] ?? '');
		if ($status === BatchStatus::PROCESSING) {
			return $batch;
		}

		if (!in_array($status, array(BatchStatus::CONFIRMED, BatchStatus::PAUSED, BatchStatus::FAILED), true)) {
			return $batch;
		}

		$revision = (int) ($batch['revision'] ?? -1);
		if ($revision < 0 || !$this->batches->transition($batchUuid, BatchStatus::PROCESSING, $revision)) {
			return null;
		}

		return $this->batches->find($batchUuid);
	}

	/**
	 * @param array{row_number:int,data:array<string,mixed>,next_offset?:int} $row Source row.
	 */
	private function prepareRow(MappingProfile $profile, array $row): ?DryRunResult
	{
		$single = array(
			1 => array(
				'row_number' => (int) $row['row_number'],
				'data' => $row['data'],
			),
		);
		$factory = static fn (): iterable => $single;
		$engine = new DryRunEngine($profile, $this->identityResolver, $this->taxonomyLookup);
		$results = iterator_to_array($engine->results($factory), false);

		return count($results) === 1 ? $results[0] : null;
	}

	private static function sourceFailureReason(string $csvReason): string
	{
		$direct = array(
			'source_unreadable',
			'source_lock_failed',
			'source_hash_failed',
			'source_hash_mismatch',
			'source_changed_during_validation',
			'invalid_resume_cursor',
			'resume_offset_missing',
			'invalid_resume_offset',
			'source_offset_failed',
		);

		return in_array($csvReason, $direct, true) ? $csvReason : 'source_parse_failed';
	}

	/**
	 * @param array<int,string> $rowCodes Sanitized codes only.
	 */
	private function failProcessing(
		string $batchUuid,
		int $revision,
		int $processedThisRun,
		int $cursor,
		string $reason,
		?int $rowNumber = null,
		array $rowCodes = array()
	): BatchRunResult {
		if (!$this->batches->transition($batchUuid, BatchStatus::FAILED, $revision)) {
			return new BatchRunResult(
				$batchUuid,
				BatchRunResult::STATUS_CONFLICT,
				$processedThisRun,
				$cursor,
				$revision,
				'failure_transition_conflict',
				$rowNumber,
				$rowCodes
			);
		}

		++$revision;
		$this->emit('wla_inmo_import_batch_failed', $batchUuid, $cursor, $revision, $reason);

		return new BatchRunResult(
			$batchUuid,
			BatchRunResult::STATUS_FAILED,
			$processedThisRun,
			$cursor,
			$revision,
			$reason,
			$rowNumber,
			$rowCodes
		);
	}

	private function pauseProcessing(
		string $batchUuid,
		int $revision,
		int $processedThisRun,
		int $cursor,
		string $reason
	): BatchRunResult {
		if (!$this->batches->transition($batchUuid, BatchStatus::PAUSED, $revision)) {
			return new BatchRunResult(
				$batchUuid,
				BatchRunResult::STATUS_CONFLICT,
				$processedThisRun,
				$cursor,
				$revision,
				'pause_transition_conflict'
			);
		}

		++$revision;
		$this->emit('wla_inmo_import_batch_paused', $batchUuid, $cursor, $revision, $reason);

		return new BatchRunResult(
			$batchUuid,
			BatchRunResult::STATUS_PAUSED,
			$processedThisRun,
			$cursor,
			$revision,
			$reason
		);
	}

	/**
	 * @param array<int,array{code:string,target:string}> $messages Structured messages.
	 * @return array<int,string>
	 */
	private static function codes(array $messages): array
	{
		$codes = array();
		foreach ($messages as $message) {
			$code = trim((string) $message['code']);
			if ($code !== '') {
				$codes[] = $code;
			}
		}

		return array_values(array_unique($codes));
	}

	private function emit(string $hook, mixed ...$args): void
	{
		if (function_exists('do_action')) {
			do_action($hook, ...$args);
		}
	}
}
