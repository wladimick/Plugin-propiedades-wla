<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$exporterPath = $root . '/plugin/wla-inmo/src/Import/JsonExporter.php';
$sourcePath = $root . '/plugin/wla-inmo/src/Import/WordPressJsonExportSource.php';

function wlaJsonExportSmokeExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$exporter = file_get_contents($exporterPath);
$source = file_get_contents($sourcePath);

wlaJsonExportSmokeExpect(is_string($exporter), 'JsonExporter source missing.');
wlaJsonExportSmokeExpect(is_string($source), 'WordPressJsonExportSource source missing.');
wlaJsonExportSmokeExpect(str_contains($exporter, 'MAX_PAGE_SIZE'), 'JSON exporter has no bounded page size.');
wlaJsonExportSmokeExpect(str_contains($exporter, 'JsonDocumentReader::FORMAT_VERSION'), 'JSON exporter does not bind output to the shared format version.');
wlaJsonExportSmokeExpect(str_contains($exporter, 'JSON_UNESCAPED_SLASHES'), 'JSON exporter does not use the documented portable encoding.');
wlaJsonExportSmokeExpect(!str_contains($exporter, 'file_put_contents('), 'JSON exporter should stream instead of building the whole document in memory.');
wlaJsonExportSmokeExpect(str_contains($source, "'posts_per_page'"), 'WordPress JSON source is not paginated.');
wlaJsonExportSmokeExpect(str_contains($source, "'no_found_rows'"), 'WordPress JSON source does not use a bounded no-count query.');
wlaJsonExportSmokeExpect(!str_contains($source, 'get_posts(-1'), 'WordPress JSON source must never load the full catalogue at once.');
wlaJsonExportSmokeExpect(str_contains($source, "!empty(\$definition['private'])"), 'JSON export does not explicitly exclude private targets.');
wlaJsonExportSmokeExpect(str_contains($source, 'wp_get_object_terms($ids'), 'Taxonomies are not loaded per bounded page.');
wlaJsonExportSmokeExpect(str_contains($source, "'all_with_object_id'"), 'Taxonomy projection does not group a page in one query per taxonomy.');

echo "WLA Inmo JSON export smoke tests passed.\n";
