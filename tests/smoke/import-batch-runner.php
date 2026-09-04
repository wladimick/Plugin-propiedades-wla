<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$runnerPath = $root . '/plugin/wla-inmo/src/Import/BatchRunner.php';
$codecPath = $root . '/plugin/wla-inmo/src/Import/MappingProfileCodec.php';
$resultPath = $root . '/plugin/wla-inmo/src/Import/BatchRunResult.php';

function wlaBatchRunnerSmokeExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$runner = file_get_contents($runnerPath);
$codec = file_get_contents($codecPath);
$result = file_get_contents($resultPath);

wlaBatchRunnerSmokeExpect(is_string($runner), 'BatchRunner source missing.');
wlaBatchRunnerSmokeExpect(is_string($codec), 'MappingProfileCodec source missing.');
wlaBatchRunnerSmokeExpect(is_string($result), 'BatchRunResult source missing.');

wlaBatchRunnerSmokeExpect(str_contains($runner, "hash_file('sha256', \$path)"), 'Source SHA-256 verification missing.');
wlaBatchRunnerSmokeExpect(str_contains($runner, 'hash_equals($expectedHash, $actualHash)'), 'Constant-time source hash comparison missing.');
wlaBatchRunnerSmokeExpect(str_contains($runner, 'MappingProfileCodec::decode'), 'Persisted mapping reconstruction missing.');
wlaBatchRunnerSmokeExpect(str_contains($runner, '$this->checkpoint->confirm'), 'Successful-row checkpoint missing.');
wlaBatchRunnerSmokeExpect(str_contains($runner, "BatchStatus::PAUSED"), 'Budget pause transition missing.');
wlaBatchRunnerSmokeExpect(str_contains($runner, "BatchStatus::COMPLETED"), 'Completion transition missing.');
wlaBatchRunnerSmokeExpect(str_contains($runner, "'checkpoint_conflict'"), 'Optimistic checkpoint conflict result missing.');
wlaBatchRunnerSmokeExpect(str_contains($runner, "wla_inmo_import_batch_paused"), 'Safe pause hook missing.');
wlaBatchRunnerSmokeExpect(str_contains($runner, "wla_inmo_import_batch_completed"), 'Safe completion hook missing.');
wlaBatchRunnerSmokeExpect(!str_contains($runner, "do_action('wla_inmo_import_batch_completed', \$sourcePath"), 'Source path leaked into batch hook.');
wlaBatchRunnerSmokeExpect(str_contains($codec, 'JSON_THROW_ON_ERROR'), 'Strict mapping profile JSON codec missing.');
wlaBatchRunnerSmokeExpect(!str_contains($result, "'values'"), 'BatchRunResult exposes canonical row values.');
wlaBatchRunnerSmokeExpect(!str_contains($result, "'source_path'"), 'BatchRunResult exposes source path.');

echo "WLA Inmo batch runner smoke tests passed.\n";
