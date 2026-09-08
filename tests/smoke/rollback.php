<?php

$root = dirname(__DIR__, 2);

$requiredFiles = array(
	'plugin/wla-inmo/src/Import/RollbackJournalSchema.php',
	'plugin/wla-inmo/src/Import/RollbackSnapshotCodec.php',
	'plugin/wla-inmo/src/Import/RollbackSnapshotter.php',
	'plugin/wla-inmo/src/Import/RollbackJournalRecorder.php',
	'plugin/wla-inmo/src/Import/RollbackInspector.php',
	'plugin/wla-inmo/src/Import/RollbackRestorer.php',
	'plugin/wla-inmo/src/Import/RollbackPreviewService.php',
);

foreach ($requiredFiles as $relative) {
	if (!is_file($root . '/' . $relative)) {
		fwrite(STDERR, "Rollback smoke missing {$relative}\n");
		exit(1);
	}
}

$runner = (string) file_get_contents($root . '/plugin/wla-inmo/src/Import/BatchRunner.php');
$status = (string) file_get_contents($root . '/plugin/wla-inmo/src/Import/BatchStatus.php');
$capabilities = (string) file_get_contents($root . '/plugin/wla-inmo/src/Access/Capabilities.php');
$roles = (string) file_get_contents($root . '/plugin/wla-inmo/src/Access/RoleMatrix.php');
$snapshotter = (string) file_get_contents($root . '/plugin/wla-inmo/src/Import/RollbackSnapshotter.php');
$inspector = (string) file_get_contents($root . '/plugin/wla-inmo/src/Import/RollbackInspector.php');
$restorer = (string) file_get_contents($root . '/plugin/wla-inmo/src/Import/RollbackRestorer.php');

$guards = array(
	'journal before executor' => strpos($runner, 'rollbackJournal->prepare') < strpos($runner, 'executor->execute'),
	'journal final before checkpoint' => strpos($runner, 'rollbackJournal->finalize') < strpos($runner, 'checkpoint->confirm'),
	'rollback processing state' => str_contains($status, "ROLLBACK_PROCESSING = 'rollback_processing'"),
	'dedicated rollback capability' => str_contains($capabilities, "ROLLBACK_IMPORTS = 'rollback_wla_imports'"),
	'manager excluded by default' => str_contains($roles, 'Capabilities::ROLLBACK_IMPORTS'),
	'created object full hash' => str_contains($snapshotter, 'createdObjectHash'),
	'created changes block deletion' => str_contains($inspector, 'rollback_created_property_changed'),
	'updated scope changes block restore' => str_contains($inspector, 'rollback_touched_scope_changed'),
	'attachments not deleted by restorer' => !str_contains($restorer, 'wp_delete_attachment'),
	'update compensation path' => str_contains($restorer, 'rollback_restore_partial_failure'),
);

foreach ($guards as $label => $ok) {
	if (!$ok) {
		fwrite(STDERR, "Rollback smoke failed: {$label}\n");
		exit(1);
	}
}

echo "WLA Inmo rollback smoke guards passed.\n";
