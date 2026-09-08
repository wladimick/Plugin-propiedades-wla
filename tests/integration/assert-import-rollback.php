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
use WLA\Inmo\Import\RollbackJournalSchema;
use WLA\Inmo\Import\RollbackPreviewService;
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
	$path = tempnam(sys_get_temp_dir(), 'wla-rollback-');
	if (!is_string($path) || $path === '') {
		$fail('Unable to allocate rollback CSV fixture.');
	}
	$handle = fopen($path, 'wb');
	if ($handle === false) {
		$fail('Unable to open rollback CSV fixture.');
	}
	foreach ($rows as $row) {
		if (fputcsv($handle, $row) === false) {
			fclose($handle);
			$fail('Unable to write rollback CSV fixture.');
		}
	}
	fclose($handle);
	$files[] = $path;
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
			$fail("Unable to transition rollback fixture batch to {$status}.");
		}
	}
};

$runSingleRowBatch = static function (
	BatchRepository $batches,
	BatchRunner $runner,
	MappingProfile $profile,
	string $sourceKey,
	array $header,
	array $row
) use ($writeCsv, $confirmBatch, $expect): array {
	$path = $writeCsv(array($header, $row));
	$hash = hash_file('sha256', $path);
	$expect(is_string($hash), 'Unable to hash rollback fixture.');
	$uuid = $batches->create($sourceKey, $hash, MappingProfileCodec::encode($profile), 1, get_current_user_id());
	$expect(is_string($uuid) && $uuid !== '', 'Unable to create rollback fixture batch.');
	$confirmBatch($batches, $uuid);
	$result = $runner->run($uuid, $path, 10, 10.0);
	$expect($result->status() === BatchRunResult::STATUS_COMPLETED, 'Rollback fixture batch did not complete.');
	$batch = $batches->find($uuid);
	$expect($batch !== null && (string) $batch['status'] === BatchStatus::COMPLETED, 'Rollback fixture batch is not completed.');
	return array($uuid, $batch, $path);
};

$rollbackSafely = static function (string $uuid) use ($expect): void {
	$previewService = new RollbackPreviewService();
	$preview = $previewService->preview($uuid);
	$expect($preview->canConfirm(), 'Expected rollback preview to be safe.');
	$expect($preview->blocked() === 0 && $preview->errors() === 0, 'Safe rollback preview contains blocked/errors.');
	$service = new RollbackService();
	$started = $service->begin($uuid, $preview->revision(), $preview->hash());
	$expect($started->status() === RollbackRunResult::STARTED, 'Rollback could not enter processing state.');
	$result = $service->run($uuid, 25, 10.0);
	$expect($result->status() === RollbackRunResult::ROLLED_BACK, 'Rollback did not finish as rolled_back.');
};

$suffix = strtolower(substr(str_replace('-', '', wp_generate_uuid4()), 0, 10));
$sourceKey = 'rollback_ci_' . $suffix;
$header = array('titulo', 'external_id', 'codigo', 'precio');
$profile = new MappingProfile(
	$sourceKey,
	array(
		'titulo' => 'post.title',
		'external_id' => 'meta.external_id',
		'codigo' => 'meta.property_code',
		'precio' => 'meta.price_clp',
	),
	'Rollback CI'
);

$batches = new BatchRepository();
$runner = new BatchRunner();
$identity = new IdentityRepository();
$priceKey = MetaSchema::metaKey('price_clp');
$expect(is_string($priceKey) && $priceKey !== '', 'Price meta key is unavailable.');

global $wpdb;
$journalTable = RollbackJournalSchema::tableName($wpdb);
$journalExists = (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $journalTable));
$expect($journalExists === $journalTable, 'Rollback journal table was not created on activation.');
$columns = (array) $wpdb->get_col("SHOW COLUMNS FROM {$journalTable}", 0);
$expect(in_array('source_row', $columns, true), 'Rollback journal does not use portable source_row column.');
$expect(!in_array('row_number', $columns, true), 'Rollback journal still exposes reserved row_number physical column.');

// 1) CREATE -> preview safe -> rollback deletes the batch-created property and
// its identity/secondary projections.
$createCode = 'RB-CREATE-' . $suffix;
$createExternal = 'RB-EXT-CREATE-' . $suffix;
[$createUuid] = $runSingleRowBatch(
	$batches,
	$runner,
	$profile,
	$sourceKey,
	$header,
	array('Casa Rollback Create', $createExternal, $createCode, '150000000')
);
$createPropertyId = $identity->findPropertyIdByCode($createCode);
$expect(is_int($createPropertyId) && $createPropertyId > 0, 'Created rollback property identity is missing.');
$createPreview = (new RollbackPreviewService())->preview($createUuid);
$expect($createPreview->canConfirm(), 'Created-property rollback preview is not safe.');
$expect($createPreview->createdToDelete() === 1 && $createPreview->updatesToRestore() === 0, 'Created-property preview counts are wrong.');
$rollbackSafely($createUuid);
$expect(get_post($createPropertyId) === null, 'Batch-created property still exists after rollback.');
$expect($identity->findPropertyIdByCode($createCode) === null, 'Identity projection survived create rollback.');
$searchTable = $wpdb->prefix . 'wla_property_index';
$qualityTable = $wpdb->prefix . 'wla_property_quality';
$expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$searchTable} WHERE property_id = %d", $createPropertyId)) === 0, 'Search projection survived create rollback.');
$expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$qualityTable} WHERE property_id = %d", $createPropertyId)) === 0, 'Quality projection survived create rollback.');
$createBatchAfter = $batches->find($createUuid);
$expect($createBatchAfter !== null && (string) $createBatchAfter['status'] === BatchStatus::ROLLED_BACK, 'Create batch did not finish rolled_back.');
$replay = (new RollbackService())->run($createUuid, 25, 10.0);
$expect($replay->status() === RollbackRunResult::ALREADY_ROLLED_BACK, 'Repeated rollback was not a no-op.');

// 2) UPDATE -> edit outside imported scope -> rollback restores only imported
// targets and preserves the later outside-scope edit.
$updateCode = 'RB-UPDATE-' . $suffix;
$updateExternal = 'RB-EXT-UPDATE-' . $suffix;
$writer = new WordPressPropertyWriter();
$updatePropertyId = $writer->create(
	array(
		'post.title' => 'Casa Antes',
		'post.excerpt' => 'Extracto inicial',
		'meta.external_id' => $updateExternal,
		'meta.property_code' => $updateCode,
		'meta.price_clp' => 100000000,
	),
	$sourceKey
);
$expect($updatePropertyId > 0, 'Unable to seed update rollback property.');
[$updateUuid, $updateBatch] = $runSingleRowBatch(
	$batches,
	$runner,
	$profile,
	$sourceKey,
	$header,
	array('Casa Después Import', $updateExternal, $updateCode, '220000000')
);
$expect((int) $updateBatch['updated_count'] === 1 && (int) $updateBatch['created_count'] === 0, 'Update fixture was not classified as update.');
$expect(get_the_title($updatePropertyId) === 'Casa Después Import', 'Update fixture title was not imported.');
$expect((int) get_post_meta($updatePropertyId, $priceKey, true) === 220000000, 'Update fixture price was not imported.');
wp_update_post(array('ID' => $updatePropertyId, 'post_excerpt' => 'Edición manual fuera del scope'));
$outsidePreview = (new RollbackPreviewService())->preview($updateUuid);
$expect($outsidePreview->canConfirm(), 'Outside-scope edit incorrectly blocked rollback.');
$expect($outsidePreview->updatesToRestore() === 1, 'Update preview did not identify one update to restore.');
$rollbackSafely($updateUuid);
$expect(get_the_title($updatePropertyId) === 'Casa Antes', 'Rollback did not restore previous title.');
$expect((int) get_post_meta($updatePropertyId, $priceKey, true) === 100000000, 'Rollback did not restore previous price.');
$restoredPost = get_post($updatePropertyId);
$expect(is_object($restoredPost) && (string) $restoredPost->post_excerpt === 'Edición manual fuera del scope', 'Rollback overwrote an outside-scope manual edit.');
$expect($identity->findPropertyIdByCode($updateCode) === $updatePropertyId, 'Identity projection is incoherent after update rollback.');

// 3) A later edit inside the imported scope must block rollback and preserve the
// human edit. Preview itself is read-only and leaves the batch completed.
[$blockedUuid, $blockedBatch] = $runSingleRowBatch(
	$batches,
	$runner,
	$profile,
	$sourceKey,
	$header,
	array('Casa Import Segundo', $updateExternal, $updateCode, '330000000')
);
$expect((int) $blockedBatch['updated_count'] === 1, 'Blocked fixture was not an update.');
update_post_meta($updatePropertyId, $priceKey, 999000000);
$blockedPreview = (new RollbackPreviewService())->preview($blockedUuid);
$expect(!$blockedPreview->canConfirm() && $blockedPreview->blocked() === 1, 'Touched-target manual edit did not block preview.');
$blockedStart = (new RollbackService())->begin($blockedUuid, $blockedPreview->revision(), $blockedPreview->hash());
$expect($blockedStart->status() === RollbackRunResult::BLOCKED, 'Unsafe preview was allowed to start rollback.');
$expect((int) get_post_meta($updatePropertyId, $priceKey, true) === 999000000, 'Blocked rollback overwrote manual touched-target edit.');
$blockedAfter = $batches->find($blockedUuid);
$expect($blockedAfter !== null && (string) $blockedAfter['status'] === BatchStatus::COMPLETED, 'Read-only blocked preview mutated batch status.');

// 4) A property created by a batch becomes undeletable when any later third-
// party metadata appears, because create rollback uses the conservative full
// object fingerprint.
$createChangedCode = 'RB-CREATE-CHANGED-' . $suffix;
$createChangedExternal = 'RB-EXT-CHANGED-' . $suffix;
[$createChangedUuid] = $runSingleRowBatch(
	$batches,
	$runner,
	$profile,
	$sourceKey,
	$header,
	array('Casa Create Changed', $createChangedExternal, $createChangedCode, '175000000')
);
$createChangedId = $identity->findPropertyIdByCode($createChangedCode);
$expect(is_int($createChangedId) && $createChangedId > 0, 'Changed-create fixture property is missing.');
update_post_meta($createChangedId, '_third_party_after_import', 'must-survive');
$createChangedPreview = (new RollbackPreviewService())->preview($createChangedUuid);
$expect(!$createChangedPreview->canConfirm() && $createChangedPreview->blocked() === 1, 'Post-import third-party meta did not block create deletion.');
$expect(get_post($createChangedId) !== null, 'Preview deleted a modified batch-created property.');
$expect((string) get_post_meta($createChangedId, '_third_party_after_import', true) === 'must-survive', 'Preview changed third-party metadata.');

// 5) Fresh-preview binding: state changed after a safe preview must invalidate
// confirmation before any batch transition or mutation.
$staleCode = 'RB-STALE-' . $suffix;
$staleExternal = 'RB-EXT-STALE-' . $suffix;
[$staleUuid] = $runSingleRowBatch(
	$batches,
	$runner,
	$profile,
	$sourceKey,
	$header,
	array('Casa Stale Preview', $staleExternal, $staleCode, '185000000')
);
$staleId = $identity->findPropertyIdByCode($staleCode);
$expect(is_int($staleId) && $staleId > 0, 'Stale-preview property is missing.');
$stalePreview = (new RollbackPreviewService())->preview($staleUuid);
$expect($stalePreview->canConfirm(), 'Stale-preview fixture did not begin safe.');
update_post_meta($staleId, '_third_party_after_preview', 'changed');
$staleStart = (new RollbackService())->begin($staleUuid, $stalePreview->revision(), $stalePreview->hash());
$expect($staleStart->status() === RollbackRunResult::CONFLICT && $staleStart->reason() === 'rollback_preview_stale', 'Changed state did not invalidate preview hash.');
$staleAfter = $batches->find($staleUuid);
$expect($staleAfter !== null && (string) $staleAfter['status'] === BatchStatus::COMPLETED, 'Stale preview changed batch status.');
$expect(get_post($staleId) !== null, 'Stale preview confirmation deleted property.');

foreach ($files as $path) {
	if (is_string($path) && is_file($path)) {
		unlink($path);
	}
}

echo "WLA Inmo safe rollback integration tests passed.\n";
