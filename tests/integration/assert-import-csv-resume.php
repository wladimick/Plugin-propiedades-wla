<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Import\CsvReader;

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

$expect = static function (bool $condition, string $message) use ($fail): void {
	if (!$condition) {
		$fail($message);
	}
};

$path = tempnam(sys_get_temp_dir(), 'wla-csv-lock-');
$expect(is_string($path) && $path !== '', 'Unable to create CSV path-replacement fixture.');

$original = "titulo,codigo\nCasa Uno,COD-LOCK-1\nCasa Dos,COD-LOCK-2\nCasa Tres,COD-LOCK-3\n";
file_put_contents($path, $original);
$hash = hash_file('sha256', $path);
$expect(is_string($hash), 'Unable to hash CSV path-replacement fixture.');

$reader = new CsvReader();
$rows = $reader->verifiedRows($path, $hash);
$rows->rewind();
$first = $rows->current();
$expect(is_array($first) && ($first['data']['codigo'] ?? '') === 'COD-LOCK-1', 'First verified row is incorrect.');
$firstOffset = (int) ($first['next_offset'] ?? 0);
$expect($firstOffset > 0, 'First verified row did not expose a durable byte offset.');

$originalPath = $path . '.verified-original';
$expect(rename($path, $originalPath), 'Unable to move original CSV while verified stream is open.');
file_put_contents($path, "titulo,codigo\nArchivo Reemplazado,MALICIOUS-1\n");

$rows->next();
$second = $rows->current();
$expect(
	is_array($second) && ($second['data']['codigo'] ?? '') === 'COD-LOCK-2',
	'Open verified stream followed a replaced pathname instead of its locked handle.'
);
$rows->next();
$third = $rows->current();
$expect(
	is_array($third) && ($third['data']['codigo'] ?? '') === 'COD-LOCK-3',
	'Open verified stream stopped reading the original verified file handle.'
);
unset($rows);

unlink($path);
$expect(rename($originalPath, $path), 'Unable to restore original CSV fixture.');

$resumed = iterator_to_array($reader->verifiedRows($path, $hash, $firstOffset, 1), false);
$expect(count($resumed) === 2, 'Byte-offset resume did not return the remaining two data rows.');
$expect(($resumed[0]['data']['codigo'] ?? '') === 'COD-LOCK-2', 'Byte-offset resume restarted from the wrong row.');
$expect(($resumed[1]['data']['codigo'] ?? '') === 'COD-LOCK-3', 'Byte-offset resume lost the final row.');

unlink($path);

echo "WLA Inmo verified CSV resume integration tests passed.\n";
