<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/plugin/wla-inmo/src/Import/RowExecutionResult.php';

use WLA\Inmo\Import\RowExecutionResult;

function wlaRowExecutorExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$result = RowExecutionResult::created(2, 99, 'not_found');
wlaRowExecutorExpect($result->status() === 'created', 'Created result status contract changed.');
wlaRowExecutorExpect($result->propertyId() === 99, 'Created result property ID contract changed.');
wlaRowExecutorExpect($result->isSuccessful(), 'Created result must be successful.');

$error = RowExecutionResult::error(3, 'identity_disagreement', 'identity');
wlaRowExecutorExpect(!$error->isSuccessful(), 'Error result must not be successful.');
wlaRowExecutorExpect($error->errors()[0]['code'] === 'identity_disagreement', 'Stable error code missing.');

$executorSource = file_get_contents($root . '/plugin/wla-inmo/src/Import/RowExecutor.php');
$writerSource = file_get_contents($root . '/plugin/wla-inmo/src/Import/WordPressPropertyWriter.php');
$checkpointSource = file_get_contents($root . '/plugin/wla-inmo/src/Import/BatchCheckpoint.php');

wlaRowExecutorExpect(is_string($executorSource), 'Unable to inspect RowExecutor source.');
wlaRowExecutorExpect(str_contains($executorSource, 'identity_changed_since_dry_run'), 'Stale identity protection missing.');
wlaRowExecutorExpect(str_contains($executorSource, 'wla_inmo_import_before_row_execute'), 'Safe pre-execution hook missing.');
wlaRowExecutorExpect(str_contains($executorSource, 'wla_inmo_import_after_row_execute'), 'Safe post-execution hook missing.');
wlaRowExecutorExpect(is_string($writerSource), 'Unable to inspect WordPressPropertyWriter source.');
wlaRowExecutorExpect(str_contains($writerSource, 'rollback_failed'), 'Writer rollback contract missing.');
wlaRowExecutorExpect(str_contains($writerSource, 'IdentityProjection::fromProperty'), 'Identity projection verification missing.');
wlaRowExecutorExpect(is_string($checkpointSource), 'Unable to inspect BatchCheckpoint source.');
wlaRowExecutorExpect(str_contains($checkpointSource, 'advanceProgress'), 'Batch checkpoint must use optimistic progress repository.');

echo "WLA Inmo row executor smoke tests passed.\n";
