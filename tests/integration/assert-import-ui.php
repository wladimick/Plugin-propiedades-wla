<?php

use WLA\Inmo\Access\Capabilities;
use WLA\Inmo\Admin\ImportExportPage;
use WLA\Inmo\Admin\ScreenRegistry;
use WLA\Inmo\Import\BatchHistoryRepository;
use WLA\Inmo\Import\BatchRepository;
use WLA\Inmo\Import\BatchStatus;
use WLA\Inmo\Import\MappingProfile;
use WLA\Inmo\Import\MappingProfileCodec;

if (!defined('ABSPATH')) {
	exit(1);
}

function wlaImportUiIntegrationAssert(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$admin = get_user_by('login', 'admin');
wlaImportUiIntegrationAssert($admin instanceof WP_User, 'CI administrator is missing.');
wp_set_current_user((int) $admin->ID);

wlaImportUiIntegrationAssert(current_user_can(Capabilities::IMPORT_PROPERTIES), 'Administrator lacks import_wla_properties.');

$screen = ScreenRegistry::findBySlug('wla-inmo-import-export');
wlaImportUiIntegrationAssert(is_array($screen), 'Import/export screen is not registered.');
wlaImportUiIntegrationAssert(($screen['capability'] ?? '') === Capabilities::IMPORT_PROPERTIES, 'Import/export screen uses the wrong capability.');
wlaImportUiIntegrationAssert(str_contains((string) ($screen['description'] ?? ''), 'CSV'), 'Import/export screen still exposes a placeholder description.');

wlaImportUiIntegrationAssert(
	has_action('admin_post_wla_inmo_import_upload', array(ImportExportPage::class, 'handleUpload')) !== false,
	'Upload admin-post handler is not registered.'
);
wlaImportUiIntegrationAssert(
	has_action('admin_post_wla_inmo_import_map', array(ImportExportPage::class, 'handleMap')) !== false,
	'Mapping admin-post handler is not registered.'
);
wlaImportUiIntegrationAssert(
	has_action('admin_post_wla_inmo_import_confirm', array(ImportExportPage::class, 'handleConfirm')) !== false,
	'Confirmation admin-post handler is not registered.'
);
wlaImportUiIntegrationAssert(
	has_action('admin_post_wla_inmo_import_run', array(ImportExportPage::class, 'handleRun')) !== false,
	'Runner admin-post handler is not registered.'
);
wlaImportUiIntegrationAssert(
	has_action('admin_post_wla_inmo_import_cancel', array(ImportExportPage::class, 'handleCancel')) !== false,
	'Cancel admin-post handler is not registered.'
);

$profile = new MappingProfile(
	'integration_ui',
	array(
		'codigo' => 'meta.property_code',
		'titulo' => 'post.title',
	),
	'Integración UI',
	MappingProfile::EMPTY_PRESERVE
);
$profileJson = MappingProfileCodec::encode($profile);
$repository = new BatchRepository();
$created = array();

for ($index = 1; $index <= 5; ++$index) {
	$uuid = strtolower((string) wp_generate_uuid4());
	$batch = $repository->create(
		'integration_ui',
		hash('sha256', 'source-' . $index),
		$profileJson,
		$index * 10,
		(int) $admin->ID,
		$uuid
	);
	wlaImportUiIntegrationAssert(is_array($batch), 'Could not create batch ' . $index . '.');

	if ($index <= 3) {
		wlaImportUiIntegrationAssert($repository->transition($uuid, BatchStatus::MAPPED, 0), 'Could not move batch to mapped.');
		wlaImportUiIntegrationAssert($repository->transition($uuid, BatchStatus::VALIDATED, 1), 'Could not move batch to validated.');
		wlaImportUiIntegrationAssert($repository->transition($uuid, BatchStatus::DRY_RUN_READY, 2), 'Could not move batch to dry_run_ready.');
		wlaImportUiIntegrationAssert($repository->transition($uuid, BatchStatus::CONFIRMED, 3), 'Could not move batch to confirmed.');
	}

	$created[] = $uuid;
}

$history = new BatchHistoryRepository();
wlaImportUiIntegrationAssert($history->count((int) $admin->ID) === 5, 'History count is not scoped to the batch creator.');

$page = $history->recent(2, 0, (int) $admin->ID);
wlaImportUiIntegrationAssert(count($page) === 2, 'History page is not bounded by limit.');
wlaImportUiIntegrationAssert(($page[0]['batch_uuid'] ?? '') === $created[4], 'History is not ordered newest first.');
wlaImportUiIntegrationAssert(!array_key_exists('profile_json', $page[0]), 'History leaked profile_json.');
wlaImportUiIntegrationAssert(!array_key_exists('source_hash', $page[0]), 'History leaked source_hash.');

$secondPage = $history->recent(2, 2, (int) $admin->ID);
wlaImportUiIntegrationAssert(count($secondPage) === 2, 'History offset pagination is not stable.');
wlaImportUiIntegrationAssert(($secondPage[0]['batch_uuid'] ?? '') === $created[2], 'History offset returned the wrong batch.');

$confirmed = $history->recent(20, 0, (int) $admin->ID, BatchStatus::CONFIRMED);
wlaImportUiIntegrationAssert(count($confirmed) === 3, 'Status filter did not return the confirmed batches.');
wlaImportUiIntegrationAssert($history->count((int) $admin->ID, BatchStatus::CONFIRMED) === 3, 'Status count does not match filtered history.');
wlaImportUiIntegrationAssert($history->recent(20, 0, (int) $admin->ID, 'not-a-status') === array(), 'Invalid status filter was not rejected.');

$other = wp_create_user('import-ui-other', wp_generate_password(32, true, true), 'import-ui-other@example.test');
wlaImportUiIntegrationAssert(!is_wp_error($other), 'Could not create secondary user.');
wlaImportUiIntegrationAssert($history->count((int) $other) === 0, 'Creator-scoped history leaks another user batch.');

echo "WLA Inmo import UI integration tests passed.\n";
