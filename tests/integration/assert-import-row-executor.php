<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Import\BatchCheckpoint;
use WLA\Inmo\Import\BatchRepository;
use WLA\Inmo\Import\BatchStatus;
use WLA\Inmo\Import\DryRunResult;
use WLA\Inmo\Import\IdentityMeta;
use WLA\Inmo\Import\IdentityRepository;
use WLA\Inmo\Import\RowExecutionResult;
use WLA\Inmo\Import\RowExecutor;
use WLA\Inmo\Import\WordPressPropertyWriter;

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

$termId = static function (string $name, string $taxonomy, string $slug) use ($fail): int {
	$existing = term_exists($slug, $taxonomy);
	if (is_array($existing)) {
		return (int) $existing['term_id'];
	}
	if (is_int($existing) || is_string($existing)) {
		return (int) $existing;
	}

	$created = wp_insert_term($name, $taxonomy, array('slug' => $slug));
	if (is_wp_error($created) || !is_array($created) || empty($created['term_id'])) {
		$fail("Unable to create {$taxonomy}:{$slug} fixture.");
	}

	return (int) $created['term_id'];
};

$ventaId = $termId('Venta Executor', 'wla_operation', 'venta-executor');
$arriendoId = $termId('Arriendo Executor', 'wla_operation', 'arriendo-executor');
$casaId = $termId('Casa Executor', 'wla_property_type', 'casa-executor');
$curicoId = $termId('Curicó Executor', 'wla_commune', 'curico-executor');

$sourceKey = 'executor_ci';
$externalId = 'EXEC-' . wp_generate_uuid4();
$propertyCode = 'EXEC-COD-' . substr(str_replace('-', '', wp_generate_uuid4()), 0, 12);

$identityRepository = new IdentityRepository();
$executor = new RowExecutor($identityRepository->resolver(), new WordPressPropertyWriter());

$createValues = array(
	'post.title' => 'Casa Executor CI',
	'post.content' => 'Descripción sintética para validar persistencia real.',
	'meta.external_id' => $externalId,
	'meta.property_code' => $propertyCode,
	'meta.currency_primary' => 'CLP',
	'meta.price_clp' => 390000000,
	'meta.status' => 'available',
	'meta.featured' => true,
	'taxonomy.operation' => array('id' => $ventaId, 'slug' => 'venta-executor'),
	'taxonomy.property_type' => array('id' => $casaId, 'slug' => 'casa-executor'),
	'taxonomy.commune' => array('id' => $curicoId, 'slug' => 'curico-executor'),
);

$createDryRun = new DryRunResult(
	2,
	DryRunResult::STATUS_NEW,
	null,
	$createValues,
	array(),
	array_keys($createValues),
	array(),
	array()
);

$created = $executor->execute($createDryRun, $sourceKey);
if ($created->status() !== RowExecutionResult::STATUS_CREATED || (int) $created->propertyId() < 1) {
	$fail('Validated NEW row was not created successfully.');
}

$propertyId = (int) $created->propertyId();
$post = get_post($propertyId);
if (!$post instanceof WP_Post || $post->post_type !== 'wla_property' || $post->post_status !== 'draft') {
	$fail('Executor did not create a draft wla_property.');
}
if ($post->post_title !== 'Casa Executor CI') {
	$fail('Canonical title was not persisted.');
}
if ((string) get_post_meta($propertyId, '_wla_inmo_external_id', true) !== $externalId) {
	$fail('External ID was not persisted.');
}
if ((string) get_post_meta($propertyId, IdentityMeta::SOURCE_KEY_META, true) !== $sourceKey) {
	$fail('External source key was not persisted with external ID.');
}
if ((string) get_post_meta($propertyId, '_wla_inmo_property_code', true) !== $propertyCode) {
	$fail('Property code was not persisted.');
}
if ((int) get_post_meta($propertyId, '_wla_inmo_price_clp', true) !== 390000000) {
	$fail('CLP price was not persisted.');
}

$operationIds = wp_get_object_terms($propertyId, 'wla_operation', array('fields' => 'ids'));
if (is_wp_error($operationIds) || !in_array($ventaId, array_map('intval', (array) $operationIds), true)) {
	$fail('Resolved operation taxonomy was not persisted.');
}

// The exact same previously-NEW dry-run must become UPDATE after a retry. This
// simulates a crash after persistence but before the batch checkpoint.
$retried = $executor->execute($createDryRun, $sourceKey);
if ($retried->status() !== RowExecutionResult::STATUS_UPDATED || (int) $retried->propertyId() !== $propertyId) {
	$fail('Retry of an already-created row did not resolve idempotently to UPDATE.');
}

$duplicates = get_posts(
	array(
		'post_type' => 'wla_property',
		'post_status' => 'any',
		'posts_per_page' => -1,
		'fields' => 'ids',
		'meta_key' => '_wla_inmo_property_code',
		'meta_value' => $propertyCode,
		'no_found_rows' => true,
	)
);
if (!is_array($duplicates) || count(array_map('intval', $duplicates)) !== 1 || (int) $duplicates[0] !== $propertyId) {
	$fail('Retry created a duplicate property.');
}

$updateValues = array(
	'post.title' => 'Casa Executor CI Actualizada',
	'meta.external_id' => $externalId,
	'meta.property_code' => $propertyCode,
	'meta.currency_primary' => 'CLP',
	'meta.price_clp' => 425000000,
	'meta.status' => 'reserved',
	'taxonomy.operation' => array('id' => $arriendoId, 'slug' => 'arriendo-executor'),
	'taxonomy.property_type' => array('id' => $casaId, 'slug' => 'casa-executor'),
	'taxonomy.commune' => array('id' => $curicoId, 'slug' => 'curico-executor'),
);
$updateDryRun = new DryRunResult(
	3,
	DryRunResult::STATUS_UPDATE,
	$propertyId,
	$updateValues,
	array(),
	array('post.title', 'meta.price_clp', 'meta.status', 'taxonomy.operation'),
	array(),
	array()
);

$updated = $executor->execute($updateDryRun, $sourceKey);
if ($updated->status() !== RowExecutionResult::STATUS_UPDATED || (int) $updated->propertyId() !== $propertyId) {
	$fail('Matched row was not updated.');
}
if (get_the_title($propertyId) !== 'Casa Executor CI Actualizada') {
	$fail('Updated title was not persisted.');
}
if ((int) get_post_meta($propertyId, '_wla_inmo_price_clp', true) !== 425000000) {
	$fail('Updated price was not persisted.');
}
if ((string) get_post_meta($propertyId, '_wla_inmo_status', true) !== 'reserved') {
	$fail('Updated status was not persisted.');
}
$operationIds = wp_get_object_terms($propertyId, 'wla_operation', array('fields' => 'ids'));
if (is_wp_error($operationIds) || array_map('intval', (array) $operationIds) !== array($arriendoId)) {
	$fail('Updated operation taxonomy did not replace the previous term.');
}

if ($identityRepository->findPropertyIdByExternalIdentity($sourceKey, $externalId) !== $propertyId) {
	$fail('Identity projection was not synchronized after execution.');
}
if ($identityRepository->findPropertyIdByCode($propertyCode) !== $propertyId) {
	$fail('Property-code identity projection was not synchronized.');
}

global $wpdb;
$searchTable = $wpdb->prefix . 'wla_property_index';
$indexedId = (int) $wpdb->get_var(
	$wpdb->prepare("SELECT property_id FROM {$searchTable} WHERE property_id = %d LIMIT 1", $propertyId)
);
if ($indexedId !== 0) {
	$fail('Draft property leaked into the public/search projection.');
}
$qualityTable = $wpdb->prefix . 'wla_property_quality';
$qualityId = (int) $wpdb->get_var(
	$wpdb->prepare("SELECT property_id FROM {$qualityTable} WHERE property_id = %d LIMIT 1", $propertyId)
);
if ($qualityId !== $propertyId) {
	$fail('Catalogue-quality projection was not synchronized after execution.');
}

// A stale UPDATE may never retarget a different property silently.
$otherId = wp_insert_post(
	array(
		'post_type' => 'wla_property',
		'post_status' => 'draft',
		'post_title' => 'Executor Identity Conflict Fixture',
	),
	true
);
if (is_wp_error($otherId) || (int) $otherId < 1) {
	$fail('Unable to create stale-identity fixture.');
}
$otherId = (int) $otherId;
$otherCode = 'EXEC-OTHER-' . substr(str_replace('-', '', wp_generate_uuid4()), 0, 10);
update_post_meta($otherId, '_wla_inmo_property_code', $otherCode);
WLA\Inmo\Import\IdentityIndexer::sync($otherId);

$staleDryRun = new DryRunResult(
	4,
	DryRunResult::STATUS_UPDATE,
	$propertyId,
	array('meta.property_code' => $otherCode, 'meta.price_clp' => 999),
	array(),
	array('meta.property_code', 'meta.price_clp'),
	array(),
	array()
);
$stale = $executor->execute($staleDryRun, $sourceKey);
if ($stale->status() !== RowExecutionResult::STATUS_ERROR || ($stale->errors()[0]['code'] ?? '') !== 'identity_changed_since_dry_run') {
	$fail('Stale dry-run was allowed to retarget another property.');
}
if ((int) get_post_meta($propertyId, '_wla_inmo_price_clp', true) !== 425000000) {
	$fail('Rejected stale dry-run mutated the original property.');
}

// Batch checkpoint: only successful rows move the optimistic cursor.
$batches = new BatchRepository();
$batchUuid = $batches->create(
	$sourceKey,
	hash('sha256', 'executor-ci-batch'),
	'{"version":1,"test":"executor"}',
	2,
	get_current_user_id()
);
if (!is_string($batchUuid) || $batchUuid === '') {
	$fail('Unable to create executor checkpoint batch.');
}
if (!$batches->transition($batchUuid, BatchStatus::MAPPED, 0)
	|| !$batches->transition($batchUuid, BatchStatus::VALIDATED, 1)
	|| !$batches->transition($batchUuid, BatchStatus::DRY_RUN_READY, 2)
	|| !$batches->transition($batchUuid, BatchStatus::CONFIRMED, 3)
	|| !$batches->transition($batchUuid, BatchStatus::PROCESSING, 4)) {
	$fail('Unable to prepare processing batch for checkpoint test.');
}

$checkpoint = new BatchCheckpoint($batches);
if (!$checkpoint->confirm($batchUuid, 5, $created)) {
	$fail('Successful created row did not checkpoint.');
}
$afterCreate = $batches->find($batchUuid);
if ($afterCreate === null || (int) $afterCreate['processed_rows'] !== 1 || (int) $afterCreate['created_count'] !== 1 || (int) $afterCreate['revision'] !== 6) {
	$fail('Created-row checkpoint counters are inconsistent.');
}

$errorResult = RowExecutionResult::error(99, 'synthetic_failure', 'execution');
if ($checkpoint->confirm($batchUuid, 6, $errorResult)) {
	$fail('Error row incorrectly advanced the batch checkpoint.');
}
$afterError = $batches->find($batchUuid);
if ($afterError === null || (int) $afterError['processed_rows'] !== 1 || (int) $afterError['revision'] !== 6) {
	$fail('Error row mutated batch progress.');
}

if (!$checkpoint->confirm($batchUuid, 6, $updated)) {
	$fail('Successful updated row did not checkpoint.');
}
$afterUpdate = $batches->find($batchUuid);
if ($afterUpdate === null || (int) $afterUpdate['processed_rows'] !== 2 || (int) $afterUpdate['updated_count'] !== 1 || (int) $afterUpdate['revision'] !== 7) {
	$fail('Updated-row checkpoint counters are inconsistent.');
}
if (!$batches->transition($batchUuid, BatchStatus::COMPLETED, 7)) {
	$fail('Fully checkpointed batch could not complete.');
}

wp_delete_post($otherId, true);
wp_delete_post($propertyId, true);

echo "WLA Inmo row executor integration tests passed.\n";
