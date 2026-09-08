<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Import\BatchRepository;
use WLA\Inmo\Import\BatchRunResult;
use WLA\Inmo\Import\BatchRunner;
use WLA\Inmo\Import\BatchStatus;
use WLA\Inmo\Import\CsvReader;
use WLA\Inmo\Import\DryRunEngine;
use WLA\Inmo\Import\DryRunResult;
use WLA\Inmo\Import\IdentityRepository;
use WLA\Inmo\Import\MappingProfile;
use WLA\Inmo\Import\MappingProfileCodec;
use WLA\Inmo\Import\RollbackJournalRecorder;
use WLA\Inmo\Import\RollbackJournalRepository;
use WLA\Inmo\Import\RollbackJournalState;
use WLA\Inmo\Import\RollbackPreviewService;
use WLA\Inmo\Import\RollbackRunResult;
use WLA\Inmo\Import\RollbackService;
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

$confirmBatch = static function (BatchRepository $batches, string $uuid) use ($fail): void {
	foreach (
		array(
			array(BatchStatus::MAPPED, 0),
			array(BatchStatus::VALIDATED, 1),
			array(BatchStatus::DRY_RUN_READY, 2),
			array(BatchStatus::CONFIRMED, 3),
		) as [$status, $revision]
	) {
		if (!$batches->transition($uuid, $status, $revision)) {
			$fail("Unable to transition crash fixture batch to {$status}.");
		}
	}
};

$suffix = strtolower(substr(str_replace('-', '', wp_generate_uuid4()), 0, 10));
$sourceKey = 'rollback_crash_' . $suffix;
$code = 'RB-CRASH-' . $suffix;
$externalId = 'RB-CRASH-EXT-' . $suffix;
$profile = new MappingProfile(
	$sourceKey,
	array(
		'titulo'      => 'post.title',
		'external_id' => 'meta.external_id',
		'codigo'       => 'meta.property_code',
		'precio'       => 'meta.price_clp',
	),
	'Rollback Crash CI'
);

$path = tempnam(sys_get_temp_dir(), 'wla-rb-crash-');
if (!is_string($path) || $path === '') {
	$fail('Unable to allocate crash CSV fixture.');
}
$handle = fopen($path, 'wb');
if ($handle === false) {
	$fail('Unable to open crash CSV fixture.');
}
fputcsv($handle, array('titulo', 'external_id', 'codigo', 'precio'));
fputcsv($handle, array('Casa Crash Rollback', $externalId, $code, '480000000'));
fclose($handle);
$hash = hash_file('sha256', $path);
$expect(is_string($hash), 'Unable to hash crash fixture.');

$batches = new BatchRepository();
$uuid = $batches->create($sourceKey, $hash, MappingProfileCodec::encode($profile), 1, get_current_user_id());
$expect(is_string($uuid) && $uuid !== '', 'Unable to create crash rollback batch.');
$confirmBatch($batches, $uuid);

$reader = new CsvReader();
$sourceRow = null;
foreach ($reader->rows($path) as $row) {
	$sourceRow = $row;
	break;
}
$expect(is_array($sourceRow), 'Unable to read crash source row.');

$identity = new IdentityRepository();
$factory = static fn (): iterable => array(1 => $sourceRow);
$dry = iterator_to_array(
	(new DryRunEngine($profile, $identity->resolver(), array(WordPressTaxonomyLookup::class, 'lookup')))->results($factory),
	false
);
$expect(count($dry) === 1 && $dry[0] instanceof DryRunResult, 'Crash dry-run did not produce one row.');
$expect($dry[0]->status() === DryRunResult::STATUS_NEW, 'Crash source was not originally classified NEW.');

$journal = new RollbackJournalRepository();
$recorder = new RollbackJournalRecorder($journal);
$expect($recorder->prepare($uuid, $dry[0]), 'Unable to persist pre-mutation rollback intent.');
$prepared = $journal->findRow($uuid, $dry[0]->rowNumber());
$expect($prepared !== null, 'Prepared crash journal row is missing.');
$expect((string) $prepared['original_action'] === RollbackJournalState::ACTION_CREATED, 'Prepared crash intent did not retain CREATED action.');
$expect((string) $prepared['journal_state'] === RollbackJournalState::PREPARED, 'Crash journal was not left prepared before simulated crash.');

// Simulate the process dying after WordPress persistence but before journal
// finalization/checkpoint. The batch cursor intentionally remains at zero.
$manual = (new RowExecutor($identity->resolver(), new WordPressPropertyWriter()))->execute($dry[0], $sourceKey);
$expect($manual->status() === RowExecutionResult::STATUS_CREATED, 'Crash simulation did not create the property.');
$propertyId = (int) $manual->propertyId();
$expect($propertyId > 0, 'Crash simulation did not expose created property ID.');
$batchBeforeRetry = $batches->find($uuid);
$expect($batchBeforeRetry !== null && (int) $batchBeforeRetry['cursor_row'] === 0, 'Simulated crash unexpectedly advanced batch cursor.');

// Retry through the normal runner. Identity now resolves UPDATE, but the
// journal must retain original_action=created and finalize against that intent.
$retry = (new BatchRunner())->run($uuid, $path, 10, 10.0);
$expect($retry->status() === BatchRunResult::STATUS_COMPLETED, 'Crash retry did not complete batch.');
$batchAfterRetry = $batches->find($uuid);
$expect($batchAfterRetry !== null && (int) $batchAfterRetry['updated_count'] === 1, 'Crash retry did not execute as UPDATE.');
$expect($batchAfterRetry !== null && (int) $batchAfterRetry['created_count'] === 0, 'Crash retry incorrectly counted a second CREATE.');
$expect($identity->findPropertyIdByCode($code) === $propertyId, 'Crash retry changed property identity or created a duplicate.');

$ready = $journal->findRow($uuid, 1);
$expect($ready !== null && (string) $ready['journal_state'] === RollbackJournalState::READY, 'Crash retry did not finalize rollback journal.');
$expect((string) $ready['original_action'] === RollbackJournalState::ACTION_CREATED, 'Crash retry overwrote original CREATED rollback intent.');
$expect((int) $ready['property_id'] === $propertyId, 'Crash retry journal points to the wrong property.');
$expect((string) $ready['created_object_hash'] !== '', 'Crash retry did not finalize conservative create fingerprint.');

$preview = (new RollbackPreviewService())->preview($uuid);
$expect($preview->canConfirm(), 'Crash-recovered batch is not rollback-safe.');
$expect($preview->createdToDelete() === 1 && $preview->updatesToRestore() === 0, 'Crash-recovered rollback lost original CREATE semantics.');

$service = new RollbackService();
$start = $service->begin($uuid, $preview->revision(), $preview->hash());
$expect($start->status() === RollbackRunResult::STARTED, 'Crash-recovered rollback could not start.');
$rolledBack = $service->run($uuid, 10, 10.0);
$expect($rolledBack->status() === RollbackRunResult::ROLLED_BACK, 'Crash-recovered rollback did not complete.');
$expect(get_post($propertyId) === null, 'Crash-recovered originally-created property was not deleted by rollback.');
$expect($identity->findPropertyIdByCode($code) === null, 'Crash-recovered property identity survived rollback.');

if (is_file($path)) {
	unlink($path);
}

echo "WLA Inmo rollback crash recovery integration tests passed.\n";
