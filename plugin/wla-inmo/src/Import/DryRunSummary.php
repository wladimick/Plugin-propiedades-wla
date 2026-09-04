<?php

namespace WLA\Inmo\Import;

final class DryRunSummary
{
	private int $newCount = 0;
	private int $updateCount = 0;
	private int $warningCount = 0;
	private int $errorCount = 0;
	private int $skippedCount = 0;

	public function consume(DryRunResult $result): void
	{
		if ($result->status() === DryRunResult::STATUS_NEW) {
			++$this->newCount;
		} elseif ($result->status() === DryRunResult::STATUS_UPDATE) {
			++$this->updateCount;
		} else {
			++$this->errorCount;
		}

		if ($result->warnings() !== array()) {
			++$this->warningCount;
		}
	}

	public function markSkipped(int $count = 1): void
	{
		$this->skippedCount += max(0, $count);
	}

	/** @return array{new:int,update:int,warnings:int,errors:int,skipped:int} */
	public function toArray(): array
	{
		return array(
			'new' => $this->newCount,
			'update' => $this->updateCount,
			'warnings' => $this->warningCount,
			'errors' => $this->errorCount,
			'skipped' => $this->skippedCount,
		);
	}
}
