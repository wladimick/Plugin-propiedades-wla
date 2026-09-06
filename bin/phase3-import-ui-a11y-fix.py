from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

page = ROOT / 'plugin/wla-inmo/src/Admin/ImportExportPage.php'
text = page.read_text()
old = "\t\t\techo '<tr><th scope=\"row\">' . esc_html($header) . '</th><td><select name=\"wla_mapping[' . esc_attr((string) $index) . ']\">';"
new = "\t\t\techo '<tr><th scope=\"row\">' . esc_html($header) . '</th><td><select name=\"wla_mapping[' . esc_attr((string) $index) . ']\" aria-label=\"' . esc_attr(sprintf(__('Campo WLA Inmo para %s', 'wla-inmo'), $header)) . '\">';"
if old not in text:
    raise SystemExit('Mapping select fragment not found')
page.write_text(text.replace(old, new, 1))

smoke = ROOT / 'tests/smoke/import-ui.php'
text = smoke.read_text()
needle = "wlaImportUiSmokeExpect(str_contains($page, 'unsafe_cancel_state'), 'Cancellation is not restricted to safe checkpoints.');\n"
addition = needle + "wlaImportUiSmokeExpect(str_contains($page, \"aria-label=\\\"' . esc_attr(sprintf(__('Campo WLA Inmo para %s'\"), 'Mapping selects do not expose an accessible name.');\n"
if needle not in text:
    raise SystemExit('Import UI smoke insertion point not found')
smoke.write_text(text.replace(needle, addition, 1))

workflow = ROOT / '.github/workflows/import-ui-integration.yml'
text = workflow.read_text()
anchor = "      - 'plugin/wla-inmo/src/Admin/ImportExportPage.php'\n"
extra = anchor + "      - 'plugin/wla-inmo/src/Admin/ImportRequest.php'\n      - 'plugin/wla-inmo/src/Import/WorkspaceJanitor.php'\n      - 'plugin/wla-inmo/src/Core/Plugin.php'\n      - 'plugin/wla-inmo/src/Core/Deactivator.php'\n"
if "src/Admin/ImportRequest.php" not in text:
    if anchor not in text:
        raise SystemExit('Import UI workflow insertion point not found')
    text = text.replace(anchor, extra, 1)
workflow.write_text(text)

fix_workflow = ROOT / '.github/workflows/phase3-import-ui-a11y-fix.yml'
if fix_workflow.exists():
    fix_workflow.unlink()
Path(__file__).unlink()
