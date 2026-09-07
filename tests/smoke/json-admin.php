<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$pagePath = $root . '/plugin/wla-inmo/src/Admin/JsonImportExportPage.php';
$hubPath = $root . '/plugin/wla-inmo/src/Admin/ImportExportHub.php';
$workspacePath = $root . '/plugin/wla-inmo/src/Import/Workspace.php';
$runnerPath = $root . '/plugin/wla-inmo/src/Import/BatchRunner.php';

function wlaJsonAdminSmokeExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$page = file_get_contents($pagePath);
$hub = file_get_contents($hubPath);
$workspace = file_get_contents($workspacePath);
$runner = file_get_contents($runnerPath);

wlaJsonAdminSmokeExpect(is_string($page), 'JSON admin page source missing.');
wlaJsonAdminSmokeExpect(is_string($hub), 'Import/export hub source missing.');
wlaJsonAdminSmokeExpect(is_string($workspace), 'Workspace source missing.');
wlaJsonAdminSmokeExpect(is_string($runner), 'BatchRunner source missing.');
wlaJsonAdminSmokeExpect(str_contains($hub, "JsonImportExportPage::render()"), 'Hub does not expose JSON on the existing import/export screen.');
wlaJsonAdminSmokeExpect(str_contains($hub, "ImportExportPage::render()"), 'Hub no longer preserves the CSV screen.');
wlaJsonAdminSmokeExpect(substr_count($page, 'check_admin_referer(') >= 7, 'JSON mutations/download are not all nonce protected.');
wlaJsonAdminSmokeExpect(str_contains($page, 'AccessCapabilities::IMPORT_PROPERTIES'), 'JSON import does not enforce import capability.');
wlaJsonAdminSmokeExpect(str_contains($page, 'AccessCapabilities::EXPORT_PROPERTIES'), 'JSON export does not enforce export capability.');
wlaJsonAdminSmokeExpect(str_contains($page, 'Workspace::storeUploadedJson'), 'JSON upload bypasses the server workspace.');
wlaJsonAdminSmokeExpect(str_contains($page, 'canonicalMapping($state, $headers)'), 'JSON import does not use the server-validated canonical mapping.');
wlaJsonAdminSmokeExpect(!str_contains($page, "postTextArray('wla_mapping')"), 'JSON mapping must not be accepted from POST.');
wlaJsonAdminSmokeExpect(!str_contains($page, "postScalar('source_key')"), 'JSON source_key must not be accepted from POST.');
wlaJsonAdminSmokeExpect(str_contains($page, 'DryRunEngine'), 'JSON import bypasses the canonical dry-run engine.');
wlaJsonAdminSmokeExpect(str_contains($page, "new JsonLinesReader(Workspace::maxRows())"), 'JSON dry-run does not use the resumable normalized source.');
wlaJsonAdminSmokeExpect(str_contains($page, "\$batchUuid,\n\t\t\t'json'"), 'Confirmed JSON batch does not persist source_format=json.');
wlaJsonAdminSmokeExpect(str_contains($page, 'new BatchRunner()'), 'JSON execution bypasses the shared batch runner.');
wlaJsonAdminSmokeExpect(str_contains($page, "batchSourcePath(\$batchUuid, 'json')"), 'JSON batch path is not resolved by format-aware workspace.');
wlaJsonAdminSmokeExpect(str_contains($page, 'JsonExporter(new WordPressJsonExportSource())'), 'JSON export does not use the bounded canonical exporter.');
wlaJsonAdminSmokeExpect(!preg_match('/\$_(?:GET|POST|REQUEST)\[[^\]]*(?:path|source_path|file_path)/i', $page), 'JSON admin accepts a filesystem path from the request.');
wlaJsonAdminSmokeExpect(str_contains($workspace, "'canonical_mapping' => \$inspection['mapping']"), 'Workspace does not persist the validated JSON mapping.');
wlaJsonAdminSmokeExpect(str_contains($workspace, "@unlink(\$uploadPath)"), 'Original JSON upload is not removed after normalization.');
wlaJsonAdminSmokeExpect(str_contains($runner, "\$format === 'json'"), 'Shared runner does not select the JSON reader from batch format.');

echo "WLA Inmo JSON admin smoke tests passed.\n";
