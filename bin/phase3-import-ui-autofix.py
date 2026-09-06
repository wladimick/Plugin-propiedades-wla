from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def replace_once(path: Path, old: str, new: str) -> None:
    text = path.read_text()
    if old not in text:
        raise SystemExit(f"Expected fragment not found in {path}: {old[:100]!r}")
    path.write_text(text.replace(old, new, 1))


page = ROOT / "plugin/wla-inmo/src/Admin/ImportExportPage.php"
replace_once(
    page,
    "\t\t// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified immediately above; upload metadata/content is validated by Workspace before use.\n\t\t$file = isset($_FILES['wla_import_file']) && is_array($_FILES['wla_import_file']) ? $_FILES['wla_import_file'] : array();",
    "\t\t$file = ImportRequest::uploadedFile('wla_import_file');",
)
replace_once(
    page,
    "\t\t// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above; every scalar target is sanitized and checked against TargetRegistry below.\n\t\t$rawMapping = isset($_POST['wla_mapping']) && is_array($_POST['wla_mapping']) ? wp_unslash($_POST['wla_mapping']) : array();\n\t\t// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified above; separators are sanitized, bounded and only applied to allowlisted multi-value targets.\n\t\t$rawSeparators = isset($_POST['wla_separator']) && is_array($_POST['wla_separator']) ? wp_unslash($_POST['wla_separator']) : array();",
    "\t\t$rawMapping = ImportRequest::postTextArray('wla_mapping');\n\t\t$rawSeparators = ImportRequest::postTextArray('wla_separator');",
)
replace_once(
    page,
    "\t\t$issues = array();\n\t\t$processed = 0;",
    "\t\t$issues = array();\n\t\t$issueCount = 0;\n\t\t$processed = 0;",
)
replace_once(
    page,
    "\t\t\t\tself::collectIssues($issues, $result, 'warning', $result->warnings());\n\t\t\t\tself::collectIssues($issues, $result, 'error', $result->errors());",
    "\t\t\t\tself::collectIssues($issues, $issueCount, $result, 'warning', $result->warnings());\n\t\t\t\tself::collectIssues($issues, $issueCount, $result, 'error', $result->errors());",
)
replace_once(
    page,
    "\t\t\t'issues'       => array_slice($issues, 0, self::ISSUE_LIMIT),\n\t\t\t'issue_count'  => count($issues),",
    "\t\t\t'issues'       => $issues,\n\t\t\t'issue_count'  => $issueCount,",
)
replace_once(
    page,
    "\t/** @param array<int,array<string,mixed>> $issues @param array<int,array{code:string,target:string}> $messages */\n\tprivate static function collectIssues(array &$issues, DryRunResult $result, string $kind, array $messages): void\n\t{\n\t\tforeach ($messages as $message) {\n\t\t\t$issues[] = array(\n\t\t\t\t'row'    => $result->rowNumber(),\n\t\t\t\t'kind'   => sanitize_key($kind),\n\t\t\t\t'code'   => sanitize_key((string) ($message['code'] ?? '')),\n\t\t\t\t'target' => sanitize_text_field((string) ($message['target'] ?? '')),\n\t\t\t);\n\t\t}\n\t}",
    "\t/** @param array<int,array<string,mixed>> $issues @param array<int,array{code:string,target:string}> $messages */\n\tprivate static function collectIssues(array &$issues, int &$issueCount, DryRunResult $result, string $kind, array $messages): void\n\t{\n\t\tforeach ($messages as $message) {\n\t\t\t++$issueCount;\n\t\t\tif (count($issues) >= self::ISSUE_LIMIT) {\n\t\t\t\tcontinue;\n\t\t\t}\n\n\t\t\t$issues[] = array(\n\t\t\t\t'row'    => $result->rowNumber(),\n\t\t\t\t'kind'   => sanitize_key($kind),\n\t\t\t\t'code'   => sanitize_key((string) ($message['code'] ?? '')),\n\t\t\t\t'target' => sanitize_text_field((string) ($message['target'] ?? '')),\n\t\t\t);\n\t\t}\n\t}",
)
replace_once(
    page,
    "\tprivate static function queryArg(string $key): string\n\t{\n\t\t// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only navigation/filter state; dynamic key is immediately converted to a sanitized scalar.\n\t\t$value = isset($_GET[$key]) && is_scalar($_GET[$key]) ? wp_unslash((string) $_GET[$key]) : '';\n\n\t\treturn sanitize_text_field($value);\n\t}\n\n\tprivate static function postScalar(string $key): string\n\t{\n\t\t// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Callers verify the action nonce first; dynamic scalar is sanitized before use.\n\t\t$value = isset($_POST[$key]) && is_scalar($_POST[$key]) ? wp_unslash((string) $_POST[$key]) : '';\n\n\t\treturn sanitize_text_field($value);\n\t}",
    "\tprivate static function queryArg(string $key): string\n\t{\n\t\treturn ImportRequest::queryScalar($key);\n\t}\n\n\tprivate static function postScalar(string $key): string\n\t{\n\t\treturn ImportRequest::postScalar($key);\n\t}",
)

request = ROOT / "plugin/wla-inmo/src/Admin/ImportRequest.php"
request.write_text(r'''<?php

namespace WLA\Inmo\Admin;

final class ImportRequest
{
	/** @return array<string,mixed> */
	public static function uploadedFile(string $key): array
	{
		// PHP's upload transport cannot be sanitized like ordinary text before
		// `is_uploaded_file()` validates tmp_name. We copy only known keys and
		// sanitize every user-controlled scalar before handing it to Workspace.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$entry = isset($_FILES[$key]) && is_array($_FILES[$key]) ? $_FILES[$key] : array();
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return array(
			'name'     => isset($entry['name']) && is_scalar($entry['name']) ? sanitize_file_name(wp_unslash((string) $entry['name'])) : '',
			'tmp_name' => isset($entry['tmp_name']) && is_scalar($entry['tmp_name']) ? sanitize_text_field(wp_unslash((string) $entry['tmp_name'])) : '',
			'size'     => isset($entry['size']) && is_scalar($entry['size']) ? absint($entry['size']) : 0,
			'error'    => isset($entry['error']) && is_scalar($entry['error']) ? absint($entry['error']) : UPLOAD_ERR_NO_FILE,
		);
	}

	/** @return array<int|string,string> */
	public static function postTextArray(string $key): array
	{
		// Callers verify their action nonce before invoking this method.
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$value = isset($_POST[$key]) && is_array($_POST[$key]) ? wp_unslash($_POST[$key]) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return map_deep($value, 'sanitize_text_field');
	}

	public static function queryScalar(string $key): string
	{
		// Read-only navigation/filter state. The dynamic key is internal and the
		// scalar is sanitized before leaving this boundary.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$value = isset($_GET[$key]) && is_scalar($_GET[$key]) ? wp_unslash((string) $_GET[$key]) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return sanitize_text_field($value);
	}

	public static function postScalar(string $key): string
	{
		// Callers verify their action nonce before invoking this method.
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$value = isset($_POST[$key]) && is_scalar($_POST[$key]) ? wp_unslash((string) $_POST[$key]) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return sanitize_text_field($value);
	}
}
''')

janitor = ROOT / "plugin/wla-inmo/src/Import/WorkspaceJanitor.php"
janitor.write_text(r'''<?php

namespace WLA\Inmo\Import;

final class WorkspaceJanitor
{
	private const CRON_HOOK = 'wla_inmo_import_workspace_cleanup';
	private const DRAFT_RETENTION_SECONDS = 7200;
	private const MAX_FILES_PER_RUN = 250;

	public static function register(): void
	{
		add_action(self::CRON_HOOK, array(self::class, 'cleanup'));
		add_action('init', array(self::class, 'schedule'));
	}

	public static function schedule(): void
	{
		if (wp_next_scheduled(self::CRON_HOOK) === false) {
			wp_schedule_event(time() + 300, 'hourly', self::CRON_HOOK);
		}
	}

	public static function unschedule(): void
	{
		wp_clear_scheduled_hook(self::CRON_HOOK);
	}

	public static function cleanup(): void
	{
		$root = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();
		$files = glob(trailingslashit($root) . 'wla-inmo-import-draft-*.csv');
		if (!is_array($files)) {
			return;
		}

		$cutoff = time() - self::DRAFT_RETENTION_SECONDS;
		$processed = 0;
		foreach ($files as $path) {
			if ($processed >= self::MAX_FILES_PER_RUN) {
				break;
			}
			++$processed;

			if (!is_string($path) || !is_file($path)) {
				continue;
			}

			$modified = filemtime($path);
			if ($modified !== false && $modified < $cutoff) {
				@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort cleanup of plugin-owned stale draft files.
			}
		}
	}
}
''')

bootstrap = ROOT / "plugin/wla-inmo/src/Admin/Bootstrap.php"
replace_once(
    bootstrap,
    "\t\tOnboarding::register();",
    "\t\t\\WLA\\Inmo\\Import\\WorkspaceJanitor::register();\n\t\tOnboarding::register();",
)

deactivator = ROOT / "plugin/wla-inmo/src/Core/Deactivator.php"
replace_once(
    deactivator,
    "use WLA\\Inmo\\Activity\\Retention as ActivityRetention;\nuse WLA\\Inmo\\Properties\\PostType;",
    "use WLA\\Inmo\\Activity\\Retention as ActivityRetention;\nuse WLA\\Inmo\\Import\\WorkspaceJanitor;\nuse WLA\\Inmo\\Properties\\PostType;",
)
replace_once(
    deactivator,
    "\t\tActivityRetention::unschedule();",
    "\t\tActivityRetention::unschedule();\n\t\tWorkspaceJanitor::unschedule();",
)

smoke = ROOT / "tests/smoke/import-ui.php"
replace_once(
    smoke,
    "$workspacePath = $root . '/plugin/wla-inmo/src/Import/Workspace.php';",
    "$workspacePath = $root . '/plugin/wla-inmo/src/Import/Workspace.php';\n$requestPath = $root . '/plugin/wla-inmo/src/Admin/ImportRequest.php';\n$janitorPath = $root . '/plugin/wla-inmo/src/Import/WorkspaceJanitor.php';",
)
replace_once(
    smoke,
    "$workspace = file_get_contents($workspacePath);\n$history = file_get_contents($historyPath);",
    "$workspace = file_get_contents($workspacePath);\n$request = file_get_contents($requestPath);\n$janitor = file_get_contents($janitorPath);\n$history = file_get_contents($historyPath);",
)
replace_once(
    smoke,
    "wlaImportUiSmokeExpect(is_string($workspace), 'Workspace source missing.');\nwlaImportUiSmokeExpect(is_string($history), 'BatchHistoryRepository source missing.');",
    "wlaImportUiSmokeExpect(is_string($workspace), 'Workspace source missing.');\nwlaImportUiSmokeExpect(is_string($request), 'ImportRequest source missing.');\nwlaImportUiSmokeExpect(is_string($janitor), 'WorkspaceJanitor source missing.');\nwlaImportUiSmokeExpect(is_string($history), 'BatchHistoryRepository source missing.');",
)
replace_once(
    smoke,
    "wlaImportUiSmokeExpect(str_contains($page, 'unsafe_cancel_state'), 'Cancellation is not restricted to safe checkpoints.');",
    "wlaImportUiSmokeExpect(str_contains($page, 'unsafe_cancel_state'), 'Cancellation is not restricted to safe checkpoints.');\nwlaImportUiSmokeExpect(str_contains($page, 'count($issues) >= self::ISSUE_LIMIT'), 'Dry-run issue retention is not bounded in memory.');\nwlaImportUiSmokeExpect(str_contains($page, \"'issue_count'  => $issueCount\"), 'Dry-run total issue count is not tracked separately from retained samples.');",
)
replace_once(
    smoke,
    "wlaImportUiSmokeExpect(!str_contains($workspace, \"'rows'       =>\"), 'Draft transient appears to persist source row payloads.');",
    "wlaImportUiSmokeExpect(!str_contains($workspace, \"'rows'       =>\"), 'Draft transient appears to persist source row payloads.');\nwlaImportUiSmokeExpect(str_contains($request, 'sanitize_file_name'), 'Upload request boundary does not sanitize the original filename.');\nwlaImportUiSmokeExpect(str_contains($request, \"map_deep($value, 'sanitize_text_field')\"), 'Mapping arrays are not sanitized at the request boundary.');\nwlaImportUiSmokeExpect(str_contains($janitor, 'wp_schedule_event'), 'Workspace janitor is not scheduled.');\nwlaImportUiSmokeExpect(str_contains($janitor, \"wla-inmo-import-draft-*.csv\"), 'Workspace janitor does not restrict cleanup to plugin-owned draft files.');\nwlaImportUiSmokeExpect(!str_contains($janitor, 'wla-inmo-import-batch-*'), 'Workspace janitor must never age-delete resumable batch sources.');",
)

release = ROOT / "bin/smoke-plugin.sh"
text = release.read_text()
if 'src/Admin/ImportRequest.php' not in text:
    text = text.replace('\t"src/Admin/ImportExportPage.php"\n', '\t"src/Admin/ImportExportPage.php"\n\t"src/Admin/ImportRequest.php"\n')
if 'src/Import/WorkspaceJanitor.php' not in text:
    marker = '\t"src/Import/Workspace.php"\n'
    if marker in text:
        text = text.replace(marker, marker + '\t"src/Import/WorkspaceJanitor.php"\n', 1)
    else:
        # Import files may be grouped differently; require janitor next to Admin assets as a fallback.
        text = text.replace('\t"src/Admin/ImportRequest.php"\n', '\t"src/Admin/ImportRequest.php"\n\t"src/Import/WorkspaceJanitor.php"\n', 1)
release.write_text(text)

# Remove this one-shot patcher and its workflow in the commit produced by CI.
workflow = ROOT / ".github/workflows/phase3-import-ui-autofix.yml"
if workflow.exists():
    workflow.unlink()
Path(__file__).unlink()
