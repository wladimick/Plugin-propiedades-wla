<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Import/BatchStatus.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Import/SourceKey.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Import/CsvException.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Import/CsvReader.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Import/IdentityCandidate.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Import/IdentityResolution.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Import/IdentityResolver.php';

use WLA\Inmo\Import\BatchStatus;
use WLA\Inmo\Import\CsvReader;
use WLA\Inmo\Import\IdentityCandidate;
use WLA\Inmo\Import\IdentityResolution;
use WLA\Inmo\Import\IdentityResolver;

function wlaImportExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

wlaImportExpect(BatchStatus::canTransition('uploaded', 'mapped'), 'Uploaded batch must map before validation.');
wlaImportExpect(!BatchStatus::canTransition('uploaded', 'completed'), 'Batch must not skip the import pipeline.');

$resolver = new IdentityResolver(
	static fn (string $sourceKey, string $externalId): array => $sourceKey === 'portal_a' && $externalId === 'EXT-1' ? array(101) : array(),
	static fn (string $propertyCode): array => $propertyCode === 'COD-1' ? array(101) : array()
);
$resolution = $resolver->resolve(new IdentityCandidate('portal a', 'EXT-1', 'COD-1'));
wlaImportExpect($resolution->status() === IdentityResolution::MATCH, 'External identity must resolve read-only.');
wlaImportExpect($resolution->propertyId() === 101, 'Resolved property ID mismatch.');

$path = tempnam(sys_get_temp_dir(), 'wla-inmo-import-smoke-');
wlaImportExpect(is_string($path), 'Unable to create CSV fixture.');

$handle = fopen($path, 'wb');
wlaImportExpect($handle !== false, 'Unable to open CSV fixture.');
fwrite($handle, "\xEF\xBB\xBFcodigo;precio_clp;nota\n");
for ($index = 1; $index <= 250; ++$index) {
	fwrite($handle, "COD-{$index};{$index}000000;=DATA({$index})\n");
}
fclose($handle);

$reader = new CsvReader(500, 10, 1024);
$rows = $reader->rows($path);
wlaImportExpect($rows instanceof Generator, 'CSV reader must expose lazy Generator iteration.');

$count = 0;
foreach ($rows as $row) {
	++$count;
	if ($count === 1) {
		wlaImportExpect($row['data']['codigo'] === 'COD-1', 'CSV header/data mapping failed.');
		wlaImportExpect($row['data']['nota'] === '=DATA(1)', 'Formula-like input must remain inert data.');
	}
}

unlink($path);
wlaImportExpect($count === 250, 'CSV smoke fixture row count mismatch.');

echo "WLA Inmo import CSV foundation smoke tests passed.\n";
