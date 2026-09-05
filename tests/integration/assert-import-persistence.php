<?php

if (!defined('ABSPATH')) {
	exit(1);
}

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

global $wpdb;

$identityTable = WLA\Inmo\Import\IdentitySchema::tableName($wpdb);
$batchTable = WLA\Inmo\Import\BatchSchema::tableName($wpdb);

foreach (array($identityTable, $batchTable) as $table) {
	$found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
	if ($found !== $table) {
		$fail("Missing import persistence table {$table}.");
	}
}

if ((string) get_option(WLA\Inmo\Import\IdentitySchema::DB_VERSION_OPTION, '0') !== WLA\Inmo\Import\IdentitySchema::DB_VERSION) {
	$fail('Identity schema version is not installed.');
}
if ((string) get_option(WLA\Inmo\Import\BatchSchema::DB_VERSION_OPTION, '0') !== WLA\Inmo\Import\BatchSchema::DB_VERSION) {
	$fail('Batch schema version is not installed.');
}

$batchColumns = $wpdb->get_col("SHOW COLUMNS FROM {$batchTable}");
if (!in_array('cursor_offset', $batchColumns, true)) {
	$fail('Import batch byte cursor column is not installed.');
}

$registeredMeta = get_registered_meta_keys('post', 'wla_property');
if (!isset($registeredMeta[WLA\Inmo\Import\IdentityMeta::SOURCE_KEY_META])) {
	$fail('External source key metadata is not registered.');
}
if (!empty($registeredMeta[WLA\Inmo\Import\IdentityMeta::SOURCE_KEY_META]['show_in_rest'])) {
	$fail('External source key must remain private from REST.');
}

$propertyId = wp_insert_post(
	array(
		'post_type'   => 'wla_property',
		'post_status' => 'draft',
		'post_title'  => 'CI Import Identity Property',
	),
	true
);
if (is_wp_error($propertyId) || (int) $propertyId < 1) {
	$fail('Unable to create import identity fixture.');
}
$propertyId = (int) $propertyId;

$repository = new WLA\Inmo\Import\IdentityRepository();
$code = 'IMPORT-CI-' . $propertyId;

update_post_meta($propertyId, '_wla_inmo_property_code', $code);
if ($repository->findPropertyIdByCode($code) !== null) {
	$fail('Direct meta write was indexed before the deferred write sequence completed.');
}
WLA\Inmo\Import\IdentityIndexer::flushDirty();
if ($repository->findPropertyIdByCode($code) !== $propertyId) {
	$fail('Deferred property-code identity synchronization failed.');
}

update_post_meta($propertyId, WLA\Inmo\Import\IdentityMeta::SOURCE_KEY_META, 'Portal Á');
update_post_meta($propertyId, '_wla_inmo_external_id', 'EXT-' . $propertyId);
if ($repository->findPropertyIdByExternalIdentity('portal_a', 'EXT-' . $propertyId) !== null) {
	$fail('Transient external identity became queryable before deferred synchronization.');
}
WLA\Inmo\Import\IdentityIndexer::flushDirty();
if ($repository->findPropertyIdByExternalIdentity('portal_a', 'EXT-' . $propertyId) !== $propertyId) {
	$fail('External identity pair did not synchronize after direct meta writes.');
}

$resolution = $repository->resolver()->resolve(
	new WLA\Inmo\Import\IdentityCandidate('Portal Á', 'EXT-' . $propertyId, $code)
);
if ($resolution->status() !== WLA\Inmo\Import\IdentityResolution::MATCH || $resolution->propertyId() !== $propertyId) {
	$fail('Indexed resolver did not match the persisted external identity.');
}

update_post_meta($propertyId, '_wla_inmo_external_id', 'EXT-UPDATED-' . $propertyId);
WLA\Inmo\Import\IdentityIndexer::flushDirty();
if ($repository->findPropertyIdByExternalIdentity('portal_a', 'EXT-' . $propertyId) !== null) {
	$fail('Obsolete external identity remained queryable after update.');
}
if ($repository->findPropertyIdByExternalIdentity('portal_a', 'EXT-UPDATED-' . $propertyId) !== $propertyId) {
	$fail('Updated external identity was not projected.');
}

// Removing the complete external pair must keep property_code resolution.
delete_post_meta($propertyId, WLA\Inmo\Import\IdentityMeta::SOURCE_KEY_META);
delete_post_meta($propertyId, '_wla_inmo_external_id');
WLA\Inmo\Import\IdentityIndexer::flushDirty();
if ($repository->findPropertyIdByCode($code) !== $propertyId) {
	$fail('Removing the external pair incorrectly removed property-code identity.');
}

$batchRepository = new WLA\Inmo\Import\BatchRepository();
$batchUuid = '22222222-2222-4222-8222-222222222222';
$createdBatch = $batchRepository->create(
	'Portal Á',
	str_repeat('b', 64),
	'{"version":1,"mapping":{"codigo":"meta.property_code"}}',
	2,
	get_current_user_id(),
	$batchUuid
);
if ($createdBatch !== $batchUuid) {
	$fail('Unable to create persistent import batch.');
}

$transitions = array(
	WLA\Inmo\Import\BatchStatus::MAPPED,
	WLA\Inmo\Import\BatchStatus::VALIDATED,
	WLA\Inmo\Import\BatchStatus::DRY_RUN_READY,
	WLA\Inmo\Import\BatchStatus::CONFIRMED,
	WLA\Inmo\Import\BatchStatus::PROCESSING,
);
$revision = 0;
foreach ($transitions as $status) {
	if (!$batchRepository->transition($batchUuid, $status, $revision)) {
		$fail("Unable to transition import batch to {$status}.");
	}
	++$revision;
}

if (
	!$batchRepository->advanceProgress(
		$batchUuid,
		$revision,
		1,
		1,
		array('created' => 1, 'updated' => 0, 'skipped' => 0, 'warnings' => 0, 'errors' => 0),
		100
	)
) {
	$fail('Unable to persist first resumable batch checkpoint.');
}
++$revision;

$firstCheckpoint = $batchRepository->find($batchUuid);
if (!is_array($firstCheckpoint) || (int) $firstCheckpoint['cursor_offset'] !== 100) {
	$fail('First resumable byte checkpoint was not persisted.');
}

if (
	$batchRepository->advanceProgress(
		$batchUuid,
		$revision - 1,
		2,
		2,
		array('created' => 2, 'updated' => 0, 'skipped' => 0, 'warnings' => 0, 'errors' => 0),
		200
	)
) {
	$fail('Stale optimistic-lock revision was accepted.');
}

if (
	$batchRepository->advanceProgress(
		$batchUuid,
		$revision,
		2,
		2,
		array('created' => 2, 'updated' => 0, 'skipped' => 0, 'warnings' => 0, 'errors' => 0),
		99
	)
) {
	$fail('Regressive byte checkpoint was accepted.');
}

if ($batchRepository->transition($batchUuid, WLA\Inmo\Import\BatchStatus::COMPLETED, $revision)) {
	$fail('Batch completed before every row was processed.');
}

if (
	!$batchRepository->advanceProgress(
		$batchUuid,
		$revision,
		2,
		2,
		array('created' => 2, 'updated' => 0, 'skipped' => 0, 'warnings' => 0, 'errors' => 0),
		200
	)
) {
	$fail('Unable to persist final resumable batch checkpoint.');
}
++$revision;

if (!$batchRepository->transition($batchUuid, WLA\Inmo\Import\BatchStatus::COMPLETED, $revision)) {
	$fail('Unable to complete fully processed batch.');
}

$completed = $batchRepository->find($batchUuid);
if (
	!is_array($completed)
	|| $completed['status'] !== WLA\Inmo\Import\BatchStatus::COMPLETED
	|| (int) $completed['processed_rows'] !== 2
	|| (int) $completed['cursor_offset'] !== 200
) {
	$fail('Completed batch state is inconsistent.');
}

wp_delete_post($propertyId, true);
if ($repository->findPropertyIdByCode($code) !== null) {
	$fail('Permanent property deletion left stale identity projection data.');
}

echo "WLA Inmo import persistence integration tests passed.\n";
