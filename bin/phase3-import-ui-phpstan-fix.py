from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

workspace = ROOT / 'plugin/wla-inmo/src/Import/Workspace.php'
text = workspace.read_text()
old = "\t\tforeach ($files as $path) {\n\t\t\tif (!is_string($path) || !is_file($path)) {"
new = "\t\tforeach ($files as $path) {\n\t\t\tif (!is_file($path)) {"
if old not in text:
    raise SystemExit('Workspace cleanup fragment not found')
workspace.write_text(text.replace(old, new, 1))

janitor = ROOT / 'plugin/wla-inmo/src/Import/WorkspaceJanitor.php'
text = janitor.read_text()
old = "\t\t\tif (!is_string($path) || !is_file($path)) {"
new = "\t\t\tif (!is_file($path)) {"
if old not in text:
    raise SystemExit('WorkspaceJanitor cleanup fragment not found')
janitor.write_text(text.replace(old, new, 1))

stubs = ROOT / 'tests/phpstan/wordpress-import-stubs.php'
text = stubs.read_text()
addition = r'''

/** @param array<int,mixed> $args */
function wp_next_scheduled(string $hook, array $args = array()): int|false
{
	return false;
}

/** @param array<int,mixed> $args */
function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = array(), bool $wp_error = false): bool
{
	return true;
}

/** @param array<int,mixed> $args */
function wp_clear_scheduled_hook(string $hook, array $args = array(), bool $wp_error = false): int|false
{
	return 0;
}
'''
if 'function wp_next_scheduled(' not in text:
    text = text.rstrip() + addition + "\n"
stubs.write_text(text)

workflow = ROOT / '.github/workflows/phase3-import-ui-phpstan-fix.yml'
if workflow.exists():
    workflow.unlink()
Path(__file__).unlink()
