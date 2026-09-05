<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Import\BatchRepository;
use WLA\Inmo\Import\BatchRunResult;
use WLA\Inmo\Import\BatchRunner;
use WLA\Inmo\Import\BatchStatus;
use WLA\Inmo\Import\DryRunEngine;
use WLA\Inmo\Import\IdentityRepository;
use WLA\Inmo\Import\MappingProfile;
use WLA\Inmo\Import\MappingProfileCodec;
use WLA\Inmo\Import\RowExecutionResult;
use WLA\Inmo\Import\RowExecutor;
use WLA\Inmo\Import\WordPressPropertyWriter;
use WLA\Inmo\Import\WordPressTaxonomyLookup;

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

$expect = static function (bool $condition, string $message) use ($fail): void {
	if (!$condition) {
		$fail($message);
	}
};

$term = static function (string $name, string $taxonomy, string $slug) use ($fail): int {
	$created = wp_insert_term($name, $taxonomy, array('slug' => $slug));
	if (is_wp_error($created)) {
		$existing = term_exists($slug, $taxonomy);
		if (is_array($existing) && !empty($existing['term_id'])) {
			return (int) $existing['term_id'];
		}
		$fail("Unable to create {$taxonomy}:{$slug} fixture.");
	}

	return (int) $created['term_id'];
};

$writeCsv = static function (array $rows) use ($fail): string {
	$path = tempnam(sys_get_temp_dir(), 'wla-runner-');
	if (!is_string($path) || $path === '') {
		$fail('Unable to create temporary CSV path.');
	}

	$handle = fopen($path, 'wb');
	if ($handle === false) {
		$fail('Unable to open temporary CSV.');
	}

	foreach ($rows as $row) {
		if (fputcsv($handle, $row) === false) {
			fclose($handle);
			$fail('Unable to write temporary CSV.');
		}
	}
	fclose($handle);

	return $path;
};

$confirmBatch = static function (BatchRepository $batches, string $uuid) use ($fail): void {
	$sequence = array(
		array(BatchStatus::MAPPED, 0),
		array(BatchStatus::VALIDATED, 1),
		array(BatchStatus::DRY_RUN_READY, 2),
		array(BatchStatus::CONFIRMED, 3),
	);

	foreach ($sequence as [$status, $revision]) {
		if (!$batches->transition($uuid, $status, $revision)) {
			$fail("Unable to transition batch to {$status}.");
		}
	}
};

$suffix = substr(str_replace('-', '', wp_generate_uuid4()), 0, 8);
$ventaName = 'Venta Runner ' . $suffix;
$casaName = 'Casa Runner ' . $suffix;
$curicoName = 'Curicó Runner ' . $suffix;
$ventaSlug = 'venta-runner-' . strtolower($suffix);
$casaSlug = 'casa-runner-' . strtolower($suffix);
$curicoSlug = 'curico-runner-' . strtolower($suffix);

$term($ventaName, 'wla_operation', $ventaSlug);
$term($casaName, 'wla_property_type', $casaSlug);
$term($curicoName, 'wla_commune', $curicoSlug);

$sourceKey = 'runner_ci_' . strtolower($suffix);
$profile = new MappingProfile(
	$sourceKey,
	array(
		'titulo' => 'post.title',
		'external_id' => 'meta.external_id',
		'codigo' => 'meta.property_code',
		'precio' => 'meta.price_clp',
		'estado' => 'meta.status',
		'operacion' => 'taxonomy.operation',
		'tipo' => 'taxonomy.property_type',
		'comuna' => 'taxonomy.commune',
	),
	'Runner CI'
);

$header = array('titulo', 'external_id', 'codigo', 'precio', 'estado', 'operacion', 'tipo', 'comuna');
$codes = array(
	'RUN-' . $suffix . '-1',
	'RUN-' . $suffix . '-2',
	'RUN-' . $suffix . '-3',
);
$externalIds = array(
	'EXT-' . $suffix . '-1',
	'EXT-' . $suffix . '-2',
	'EXT-' . $suffix . '-3',
);
$sourceRows = array(
	$header,
	array('Casa Runner Uno', $externalIds[0], $codes[0], '100000000', 'available', $ventaName, $casaName, $curicoName),
	array('Casa Runner Dos', $externalIds[1], $codes[1], '200000000', 'available', $ventaName, $casaName, $curicoName),
	array('Casa Runner Tres', $externalIds[2], $codes[2], '300000000', 'available', $ventaName, $casaName, $curicoName),
);
$sourcePath = $writeCsv($sourceRows);
$sourceHash = hash_file('sha256', $sourcePath);
$expect(is_string($sourceHash), 'Unable to hash main runner CSV.');

$batches = new BatchRepository();
$batchUuid = $batches->create(
	$sourceKey,
	$sourceHash,
	MappingProfileCodec::encode($profile),
	3,
	get_current_user_id()
);
$expect(is_string($batchUuid) && $batchUuid !== '', 'Unable to create main runner batch.');
$confirmBatch($batches, $batchUuid);

$runner = new BatchRunner();
$first = $runner->run($batchUuid, $sourcePath, 1, 60.0);
$expect($first->status() === BatchRunResult::STATUS_PAUSED, 'First one-row slice did not pause.');
$expect($first->processedThisRun() === 1 && $first->cursorRow() === 1, 'First slice progress is incorrect.');

$second = $runner->run($batchUuid, $sourcePath, 1, 60.0);
$expect($second->status() === BatchRunResult::STATUS_PAUSED, 'Second one-row slice did not pause.');
$expect($second->processedThisRun() === 1 && $second->cursorRow() === 2, 'Second slice progress is incorrect.');

$third = $runner->run($batchUuid, $sourcePath, 5, 60.0);
$expect($third->status() === BatchRunResult::STATUS_COMPLETED, 'Final slice did not complete batch.');
$expect($third->processedThisRun() === 1 && $third->cursorRow() === 3, 'Final slice progress is incorrect.');

$completed = $batches->find($batchUuid);
$expect($completed !== null, 'Completed batch cannot be loaded.');
$expect((string) $completed['status'] === BatchStatus::COMPLETED, 'Batch status is not completed.');
$expect((int) $completed['processed_rows'] === 3, 'Completed batch processed row count is wrong.');
$expect((int) $completed['created_count'] === 3, 'Completed batch created count is wrong.');
$expect((int) $completed['updated_count'] === 0, 'Completed batch updated count is wrong.');
$expect((int) $completed['cursor_row'] === 3, 'Completed batch cursor is wrong.');

$identity = new IdentityRepository();
$createdIds = array();
foreach ($codes as $index => $code) {
	$propertyId = $identity->findPropertyIdByCode($code);
	$expect(is_int($propertyId) && $propertyId > 0, "Identity projection missing for {$code}.");
	$expect($identity->findPropertyIdByExternalIdentity($sourceKey, $externalIds[$index]) === $propertyId, "External identity mismatch for {$code}.");
	$expect(get_post_status($propertyId) === 'draft', "Imported {$code} is not a draft.");
	$createdIds[] = $propertyId;
}

$expect(count(array_unique($createdIds)) === 3, 'Runner identities do not point to three unique properties.');

global $wpdb;
$searchTable = $wpdb->prefix . 'wla_property_index';
$qualityTable = $wpdb->prefix . 'wla_property_quality';
foreach ($createdIds as $propertyId) {
	$indexed = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$searchTable} WHERE property_id = %d", $propertyId));
	$quality = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$qualityTable} WHERE property_id = %d", $propertyId));
	$expect($indexed === 0, 'Draft imported by runner leaked into public search index.');
	$expect($quality === 1, 'Draft imported by runner is missing quality projection.');
}

$beforeReplay = $batches->find($batchUuid);
$replay = $runner->run($batchUuid, $sourcePath, 5, 60.0);
$afterReplay = $batches->find($batchUuid);
$expect($replay->status() === BatchRunResult::STATUS_ALREADY_COMPLETED, 'Completed batch replay was not a no-op.');
$expect($beforeReplay !== null && $afterReplay !== null && $beforeReplay['revision'] === $afterReplay['revision'], 'Completed batch replay changed revision.');
$expect($beforeReplay['processed_rows'] === $afterReplay['processed_rows'], 'Completed batch replay changed progress.');

// Crash simulation: persist a previously-NEW row without checkpoint, then let
// BatchRunner re-run the exact same unchanged source. Identity must resolve it
// to UPDATE and the batch must finish with exactly one property.
$crashCode = 'CRASH-' . $suffix;
$crashExternal = 'CRASH-EXT-' . $suffix;
$crashPath = $writeCsv(array(
	$header,
	array('Casa Crash Runner', $crashExternal, $crashCode, '410000000', 'available', $ventaName, $casaName, $curicoName),
));
$crashHash = hash_file('sha256', $crashPath);
$expect(is_string($crashHash), 'Unable to hash crash CSV.');
$crashUuid = $batches->create($sourceKey, $crashHash, MappingProfileCodec::encode($profile), 1, get_current_user_id());
$expect(is_string($crashUuid) && $crashUuid !== '', 'Unable to create crash batch.');
$confirmBatch($batches, $crashUuid);

$crashRow = null;
$reader = new WLA\Inmo\Import\CsvReader();
foreach ($reader->rows($crashPath) as $row) {
	$crashRow = $row;
	break;
}
$expect(is_array($crashRow), 'Unable to read crash fixture row.');
$factory = static fn (): iterable => array(1 => $crashRow);
$dryResults = iterator_to_array(
	(new DryRunEngine(
		$profile,
		$identity->resolver(),
		array(WordPressTaxonomyLookup::class, 'lookup')
	))->results($factory),
	false
);
$expect(count($dryResults) === 1 && $dryResults[0]->status() === WLA\Inmo\Import\DryRunResult::STATUS_NEW, 'Crash fixture was not classified as NEW before first write.');
$manualExecutor = new RowExecutor($identity->resolver(), new WordPressPropertyWriter());
$manual = $manualExecutor->execute($dryResults[0], $sourceKey);
$expect($manual->status() === RowExecutionResult::STATUS_CREATED && (int) $manual->propertyId() > 0, 'Crash simulation could not persist row before checkpoint.');
$crashPropertyId = (int) $manual->propertyId();

$crashRun = $runner->run($crashUuid, $crashPath, 5, 60.0);
$expect($crashRun->status() === BatchRunResult::STATUS_COMPLETED, 'Crash recovery batch did not complete.');
$crashBatch = $batches->find($crashUuid);
$expect($crashBatch !== null && (int) $crashBatch['processed_rows'] === 1, 'Crash recovery did not checkpoint one row.');
$expect($crashBatch !== null && (int) $crashBatch['updated_count'] === 1, 'Crash recovery did not classify retry as UPDATE.');
$crashMatches = get_posts(array(
	'post_type' => 'wla_property',
	'post_status' => 'any',
	'posts_per_page' => -1,
	'fields' => 'ids',
	'meta_key' => '_wla_inmo_property_code',
	'meta_value' => $crashCode,
	'no_found_rows' => true,
));
$expect(is_array($crashMatches) && count($crashMatches) === 1 && (int) $crashMatches[0] === $crashPropertyId, 'Crash recovery created a duplicate property.');

// Source integrity: same batch metadata with a changed source must fail without
// moving its cursor or creating a property.
$hashCode = 'HASH-' . $suffix;
$hashPath = $writeCsv(array(
	$header,
	array('Casa Hash Runner', 'HASH-EXT-' . $suffix, $hashCode, '510000000', 'available', $ventaName, $casaName, $curicoName),
));
$hashExpected = hash_file('sha256', $hashPath);
$expect(is_string($hashExpected), 'Unable to hash source-integrity fixture.');
$hashUuid = $batches->create($sourceKey, $hashExpected, MappingProfileCodec::encode($profile), 1, get_current_user_id());
$expect(is_string($hashUuid) && $hashUuid !== '', 'Unable to create source-integrity batch.');
$confirmBatch($batches, $hashUuid);
file_put_contents($hashPath, "\n#changed-after-confirmation\n", FILE_APPEND);
$hashRun = $runner->run($hashUuid, $hashPath, 5, 60.0);
$expect($hashRun->status() === BatchRunResult::STATUS_FAILED && $hashRun->reason() === 'source_hash_mismatch', 'Changed source was not rejected by SHA-256.');
$hashBatch = $batches->find($hashUuid);
$expect($hashBatch !== null && (int) $hashBatch['cursor_row'] === 0 && (int) $hashBatch['processed_rows'] === 0, 'Hash mismatch advanced batch progress.');
$expect($identity->findPropertyIdByCode($hashCode) === null, 'Hash mismatch created a property.');

// Row validation: an unknown required taxonomy must stop at the current row and
// remain retryable through FAILED -> PROCESSING after the source/data is fixed.
$badCode = 'BAD-' . $suffix;
$badPath = $writeCsv(array(
	$header,
	array('Casa Invalid Runner', 'BAD-EXT-' . $suffix, $badCode, '610000000', 'available', 'Operacion Inexistente ' . $suffix, $casaName, $curicoName),
));
$badHash = hash_file('sha256', $badPath);
$expect(is_string($badHash), 'Unable to hash invalid-row fixture.');
$badUuid = $batches->create($sourceKey, $badHash, MappingProfileCodec::encode($profile), 1, get_current_user_id());
$expect(is_string($badUuid) && $badUuid !== '', 'Unable to create invalid-row batch.');
$confirmBatch($batches, $badUuid);
$badRun = $runner->run($badUuid, $badPath, 5, 60.0);
$expect($badRun->status() === BatchRunResult::STATUS_FAILED && $badRun->reason() === 'row_validation_failed', 'Invalid taxonomy did not fail row validation.');
$expect(in_array('unknown_taxonomy_term', $badRun->rowCodes(), true), 'Invalid taxonomy failure did not expose stable error code.');
$badBatch = $batches->find($badUuid);
$expect($badBatch !== null && (int) $badBatch['cursor_row'] === 0 && (int) $badBatch['processed_rows'] === 0, 'Invalid row advanced batch progress.');
$expect($identity->findPropertyIdByCode($badCode) === null, 'Invalid row created a property.');

foreach (array_merge($createdIds, array($crashPropertyId)) as $propertyId) {
	wp_delete_post((int) $propertyId, true);
}
foreach (array($sourcePath, $crashPath, $hashPath, $badPath) as $path) {
	if (is_string($path) && is_file($path)) {
		unlink($path);
	}
}

echo "WLA Inmo batch runner integration tests passed.\n";
