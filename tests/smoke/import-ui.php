<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$pagePath = $root . '/plugin/wla-inmo/src/Admin/ImportExportPage.php';
$workspacePath = $root . '/plugin/wla-inmo/src/Import/Workspace.php';
$historyPath = $root . '/plugin/wla-inmo/src/Import/BatchHistoryRepository.php';
$assetsPath = $root . '/plugin/wla-inmo/src/Admin/Assets.php';
$cssPath = $root . '/plugin/wla-inmo/assets/admin/import-export.css';

function wlaImportUiSmokeExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$page = file_get_contents($pagePath);
$workspace = file_get_contents($workspacePath);
$history = file_get_contents($historyPath);
$assets = file_get_contents($assetsPath);
$css = file_get_contents($cssPath);

wlaImportUiSmokeExpect(is_string($page), 'ImportExportPage source missing.');
wlaImportUiSmokeExpect(is_string($workspace), 'Workspace source missing.');
wlaImportUiSmokeExpect(is_string($history), 'BatchHistoryRepository source missing.');
wlaImportUiSmokeExpect(is_string($assets), 'Assets source missing.');
wlaImportUiSmokeExpect(is_string($css), 'Import UI CSS missing.');

wlaImportUiSmokeExpect(str_contains($page, 'AccessCapabilities::IMPORT_PROPERTIES'), 'Import screen does not enforce the exact import capability.');
wlaImportUiSmokeExpect(substr_count($page, 'check_admin_referer(') >= 6, 'Import mutations are not all nonce protected.');
wlaImportUiSmokeExpect(str_contains($page, 'DryRunEngine'), 'Import UI bypasses the canonical dry-run engine.');
wlaImportUiSmokeExpect(str_contains($page, 'MappingProfileCodec::encode'), 'Import UI does not freeze the mapping snapshot.');
wlaImportUiSmokeExpect(str_contains($page, 'hash_equals'), 'Import UI does not bind confirmation to the reviewed source/profile hash.');
wlaImportUiSmokeExpect(str_contains($page, 'BatchRunner'), 'Import UI bypasses the resumable batch runner.');
wlaImportUiSmokeExpect(str_contains($page, 'Workspace::batchSourcePath'), 'Batch source path is not resolved through the server-controlled workspace.');
wlaImportUiSmokeExpect(str_contains($page, 'BatchStatus::CONFIRMED'), 'Import UI does not use the batch state machine.');
wlaImportUiSmokeExpect(str_contains($page, 'dry_run_has_errors'), 'Dry-run errors do not block confirmation.');
wlaImportUiSmokeExpect(str_contains($page, 'unsafe_cancel_state'), 'Cancellation is not restricted to safe checkpoints.');
wlaImportUiSmokeExpect(!preg_match('/\$_(?:GET|POST|REQUEST)\[[^\]]*(?:path|source_path|file_path)/i', $page), 'Import UI accepts a filesystem path from the request.');
wlaImportUiSmokeExpect(!preg_match('/wp_remote_|curl_|XMLHttpRequest|axios|\.ajax\s*\(/i', $page), 'Import UI must not perform remote requests in the CSV phase.');

wlaImportUiSmokeExpect(str_contains($workspace, 'is_uploaded_file'), 'Workspace does not require a genuine HTTP upload.');
wlaImportUiSmokeExpect(str_contains($workspace, 'move_uploaded_file'), 'Workspace does not move uploads through the PHP upload boundary.');
wlaImportUiSmokeExpect(str_contains($workspace, 'MAX_UPLOAD_BYTES'), 'Workspace has no explicit upload byte limit.');
wlaImportUiSmokeExpect(str_contains($workspace, 'MAX_ROWS'), 'Workspace has no explicit row limit.');
wlaImportUiSmokeExpect(str_contains($workspace, "pathinfo(\$name, PATHINFO_EXTENSION)"), 'Workspace does not enforce the CSV extension.');
wlaImportUiSmokeExpect(str_contains($workspace, 'finfo(FILEINFO_MIME_TYPE)'), 'Workspace lacks MIME inspection.');
wlaImportUiSmokeExpect(str_contains($workspace, 'get_temp_dir'), 'Workspace source is not isolated in server temporary storage.');
wlaImportUiSmokeExpect(str_contains($workspace, "'wla-inmo-import-batch-' . \$batchUuid . '.csv'"), 'Batch path is not derived only from the server UUID.');
wlaImportUiSmokeExpect(!str_contains($workspace, "\$file['name'] ."), 'Original upload name participates in a filesystem path.');
wlaImportUiSmokeExpect(!str_contains($workspace, "'rows'       =>"), 'Draft transient appears to persist source row payloads.');

wlaImportUiSmokeExpect(str_contains($history, 'LIMIT %d OFFSET %d'), 'Batch history is not bounded/paginated.');
wlaImportUiSmokeExpect(str_contains($history, 'BatchStatus::isValid'), 'Batch history status filter is not allowlisted.');
wlaImportUiSmokeExpect(!str_contains($history, 'profile_json'), 'Batch history query exposes mapping profile payload.');
wlaImportUiSmokeExpect(!str_contains($history, 'source_hash'), 'Batch history query exposes source hash unnecessarily.');
wlaImportUiSmokeExpect(str_contains($assets, 'isImportExportContext'), 'Import stylesheet is not scoped to the import screen.');
wlaImportUiSmokeExpect(str_contains($css, '@media (max-width: 782px)'), 'Import UI lacks a mobile administration layout.');

echo "WLA Inmo import UI smoke tests passed.\n";
