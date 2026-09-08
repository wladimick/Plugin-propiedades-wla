<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Import\BatchRepository;
use WLA\Inmo\Import\BatchRunResult;
use WLA\Inmo\Import\BatchRunner;
use WLA\Inmo\Import\BatchStatus;
use WLA\Inmo\Import\IdentityRepository;
use WLA\Inmo\Import\MappingProfile;
use WLA\Inmo\Import\MappingProfileCodec;
use WLA\Inmo\Import\RollbackInspection;
use WLA\Inmo\Import\RollbackInspector;
use WLA\Inmo\Import\RollbackJournalRepository;
use WLA\Inmo\Import\RollbackJournalState;
use WLA\Inmo\Import\RollbackPreviewService;
use WLA\Inmo\Import\RollbackRestorer;
use WLA\Inmo\Import\RollbackRunResult;
use WLA\Inmo\Import\RollbackService;
use WLA\Inmo\Import\WordPressPropertyWriter;
use WLA\Inmo\Properties\MetaSchema;

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

$expect = static function (bool $condition, string $message) use ($fail): void {
	if (!$condition) {
		$fail($message);
	}
};

$files = array();
$writeCsv = static function (array $rows) use (&$files, $fail): string {
	$path = tempnam(sys_get_temp_dir(), 'wla-rb-checkpoint-');
	if (!is_string($path) || $path === '') {
		$fail('Unable to allocate rollback checkpoint fixture.');
	}
	$handle = fopen($path, 'wb');
	if ($handle === false) {
		$fail('Unable to open rollback checkpoint fixture.');
	}
	foreach ($rows as $row) {
		fputcsv($handle, $row);
	}
	fclose($handle);
	$files[] = $path;
	return $path;
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
			$fail("Unable to transition checkpoint fixture to {$status}.");
		}
	}
};

$runOne = static function (
	BatchRepository $batches,
	MappingProfile $profile,
	string $sourceKey,
	array $row
) use ($writeCsv, $confirmBatch, $expect): string {
	$path = $writeCsv(
		array(
			array('titulo', 'external_id', 'codigo', 'precio'),
			$row,
		)
	);
	$hash = hash_file('sha256', $path);
	$expect(is_string($hash), 'Unable to hash checkpoint fixture.');
	$uuid = $batches->create($sourceKey, $hash, MappingProfileCodec::encode($profile), 1, get_current_user_id());
	$expect(is_string($uuid) && $uuid !== '', 'Unable to create checkpoint fixture batch.');
	$confirmBatch($batches, $uuid);
	$result = (new BatchRunner())->run($uuid, $path, 10, 10.0);
	$expect($result->status() === BatchRunResult::STATUS_COMPLETED, 'Checkpoint fixture import did not complete.');
	return $uuid;
};

$begin = static function (string $uuid) use ($expect): void {
	$preview = (new RollbackPreviewService())->preview($uuid);
	$expect($preview->canConfirm(), 'Checkpoint fixture preview is not safe.');
	$result = (new RollbackService())->begin($uuid, $preview->revision(), $preview->hash());
	$expect($result->status() === RollbackRunResult::STARTED, 'Checkpoint fixture rollback did not start.');
};

$suffix = strtolower(substr(str_replace('-', '', wp_generate_uuid4()), 0, 10));
$sourceKey = 'rb_checkpoint_' . $suffix;
$profile = new MappingProfile(
	$sourceKey,
	array(
		'titulo'      => 'post.title',
		'external_id' => 'meta.external_id',
		'codigo'       => 'meta.property_code',
		'precio'       => 'meta.price_clp',
	),
	'Rollback Checkpoint CI'
);

$batches = new BatchRepository();
$journal = new RollbackJournalRepository();
$identity = new IdentityRepository();
$inspector = new RollbackInspector();
$restorer = new RollbackRestorer($inspector);
$priceKey = MetaSchema::metaKey('price_clp');
$expect(is_string($priceKey) && $priceKey !== '', 'Price meta key unavailable.');

// UPDATE recovery: simulate durable data restoration followed by a failed
// journal checkpoint. The next runner must detect current == before as NOOP,
// resync projections, commit the journal row and complete the batch.
$updateCode = 'RB-CP-UP-' . $suffix;
$updateExternal = 'RB-CP-UP-EXT-' . $suffix;
$propertyId = (new WordPressPropertyWriter())->create(
	array(
		'post.title'         => 'Checkpoint Antes',
		'meta.external_id'   => $updateExternal,
		'meta.property_code' => $updateCode,
		'meta.price_clp'     => 111000000,
	),
	$sourceKey
);
$expect($propertyId > 0, 'Unable to seed checkpoint update property.');
$updateUuid = $runOne(
	$batches,
	$profile,
	$sourceKey,
	array('Checkpoint Después', $updateExternal, $updateCode, '222000000')
);
$begin($updateUuid);
$updateJournal = $journal->findRow($updateUuid, 1);
$expect(is_array($updateJournal), 'Checkpoint update journal row missing.');
$expect((string) $updateJournal['rollback_status'] === RollbackJournalState::ROLLBACK_PENDING, 'Checkpoint update row is not pending.');

$restorer->restore($updateJournal);
$expect(get_the_title($propertyId) === 'Checkpoint Antes', 'Manual checkpoint simulation did not restore update title.');
$expect((int) get_post_meta($propertyId, $priceKey, true) === 111000000, 'Manual checkpoint simulation did not restore update price.');
$stillPending = $journal->findRow($updateUuid, 1);
$expect(is_array($stillPending) && (string) $stillPending['rollback_status'] === RollbackJournalState::ROLLBACK_PENDING, 'Manual checkpoint simulation unexpectedly committed journal.');
$inspection = $inspector->inspect($stillPending);
$expect($inspection->status() === RollbackInspection::NOOP, 'Already-restored update was not recognized as NOOP.');
$expect($inspection->reason() === 'rollback_update_already_restored', 'Already-restored update emitted wrong recovery reason.');

$recoveredUpdate = (new RollbackService())->run($updateUuid, 10, 10.0);
$expect($recoveredUpdate->status() === RollbackRunResult::ROLLED_BACK, 'Update checkpoint recovery did not finish rolled_back.');
$updateJournalAfter = $journal->findRow($updateUuid, 1);
$expect(is_array($updateJournalAfter) && (string) $updateJournalAfter['rollback_status'] === RollbackJournalState::ROLLBACK_ROLLED_BACK, 'Recovered update journal was not committed.');
$expect($identity->findPropertyIdByCode($updateCode) === $propertyId, 'Recovered update lost identity projection.');
$expect(get_the_title($propertyId) === 'Checkpoint Antes', 'Recovery mutated already-restored update again.');

// CREATE recovery: the same checkpoint gap after deletion is already naturally
// represented by an absent created property. Retry must clean projections and
// commit the pending journal without trying to delete the object again.
$createCode = 'RB-CP-CR-' . $suffix;
$createExternal = 'RB-CP-CR-EXT-' . $suffix;
$createUuid = $runOne(
	$batches,
	$profile,
	$sourceKey,
	array('Checkpoint Create', $createExternal, $createCode, '333000000')
);
$createdId = $identity->findPropertyIdByCode($createCode);
$expect(is_int($createdId) && $createdId > 0, 'Checkpoint create property missing.');
$begin($createUuid);
$createJournal = $journal->findRow($createUuid, 1);
$expect(is_array($createJournal), 'Checkpoint create journal row missing.');
$restorer->restore($createJournal);
$expect(get_post($createdId) === null, 'Manual checkpoint simulation did not delete created property.');
$createPending = $journal->findRow($createUuid, 1);
$expect(is_array($createPending) && (string) $createPending['rollback_status'] === RollbackJournalState::ROLLBACK_PENDING, 'Create checkpoint simulation unexpectedly committed journal.');
$createInspection = $inspector->inspect($createPending);
$expect($createInspection->status() === RollbackInspection::NOOP, 'Absent created property was not recognized as NOOP.');

$recoveredCreate = (new RollbackService())->run($createUuid, 10, 10.0);
$expect($recoveredCreate->status() === RollbackRunResult::ROLLED_BACK, 'Create checkpoint recovery did not finish rolled_back.');
$createJournalAfter = $journal->findRow($createUuid, 1);
$expect(is_array($createJournalAfter) && (string) $createJournalAfter['rollback_status'] === RollbackJournalState::ROLLBACK_ROLLED_BACK, 'Recovered create journal was not committed.');
$expect($identity->findPropertyIdByCode($createCode) === null, 'Recovered create identity projection survived.');

foreach ($files as $path) {
	if (is_string($path) && is_file($path)) {
		unlink($path);
	}
}

echo "WLA Inmo rollback checkpoint recovery integration tests passed.\n";
