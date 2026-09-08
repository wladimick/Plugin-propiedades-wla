<?php

namespace WLA\Inmo\Import;

interface RollbackJournalRecorderInterface
{
	public function prepare(string $batchUuid, DryRunResult $dryRun): bool;

	public function finalize(string $batchUuid, DryRunResult $dryRun, RowExecutionResult $execution): bool;
}
