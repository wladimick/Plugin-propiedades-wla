<?php

if (!defined('ABSPATH')) {
	exit(1);
}

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

if (!class_exists(WLA\Inmo\Quality\Evaluator::class)
	|| !class_exists(WLA\Inmo\Quality\Repository::class)
	|| !class_exists(WLA\Inmo\Quality\Indexer::class)
	|| !class_exists(WLA\Inmo\Quality\Rebuilder::class)
) {
	$fail('Catalogue quality classes are unavailable from the active plugin.');
}

global $wpdb;
$qualityTable = WLA\Inmo\Quality\Schema::tableName($wpdb);
$qualityTableFound = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $qualityTable));
if ($qualityTableFound !== $qualityTable) {
	$fail('Catalogue quality table was not installed.');
}

if ((string) get_option(WLA\Inmo\Quality\Schema::DB_VERSION_OPTION, '0') !== WLA\Inmo\Quality\Schema::DB_VERSION) {
	$fail('Catalogue quality schema version was not installed.');
}

$qualityIndexes = $wpdb->get_col("SHOW INDEX FROM {$qualityTable}", 2);
foreach (array('score', 'complete_score', 'price_score', 'image_score') as $requiredIndex) {
	if (!is_array($qualityIndexes) || !in_array($requiredIndex, $qualityIndexes, true)) {
		$fail("Missing catalogue-quality index {$requiredIndex}.");
	}
}

$adminUser = get_user_by('login', 'admin');
if (!$adminUser instanceof WP_User) {
	$fail('Unable to resolve CI administrator for quality tests.');
}
wp_set_current_user($adminUser->ID);

$qualityId = wp_insert_post(
	array(
		'post_type' => 'wla_property',
		'post_status' => 'draft',
		'post_title' => 'CI Quality Property',
		'post_content' => str_repeat('Descripción completa para validar la calidad inmobiliaria de la propiedad. ', 3),
	),
	true
);
if (is_wp_error($qualityId) || (int) $qualityId < 1) {
	$fail('Unable to create catalogue-quality fixture property.');
}
$qualityId = (int) $qualityId;

$createTerm = static function (string $name, string $taxonomy) use ($fail): int {
	$term = term_exists($name, $taxonomy);
	if ($term === null) {
		$term = wp_insert_term($name, $taxonomy);
	}
	if (is_wp_error($term)) {
		$fail("Unable to create quality fixture term in {$taxonomy}.");
	}

	return is_array($term) ? (int) $term['term_id'] : (int) $term;
};

$operationId = $createTerm('Quality Venta ' . $qualityId, 'wla_operation');
$typeId = $createTerm('Quality Casa ' . $qualityId, 'wla_property_type');
$communeId = $createTerm('Quality Curicó ' . $qualityId, 'wla_commune');

wp_set_object_terms($qualityId, array($operationId), 'wla_operation', false);
wp_set_object_terms($qualityId, array($typeId), 'wla_property_type', false);
wp_set_object_terms($qualityId, array($communeId), 'wla_commune', false);

$privateMarker = 'PRIVATE-QUALITY-' . $qualityId;
update_post_meta($qualityId, '_wla_inmo_property_code', 'QUALITY-' . $qualityId);
update_post_meta($qualityId, '_wla_inmo_currency_primary', 'CLP');
update_post_meta($qualityId, '_wla_inmo_price_clp', 345000000);
update_post_meta($qualityId, '_wla_inmo_built_area_m2', 140);
update_post_meta($qualityId, '_wla_inmo_private_address', $privateMarker);
update_post_meta($qualityId, '_wla_inmo_last_verified_date', gmdate('Y-m-d'));

$createImage = static function (string $name, string $alt) use ($fail): int {
	$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z8+UAAAAASUVORK5CYII=', true);
	if (!is_string($pngBytes)) {
		$fail('Unable to decode quality PNG fixture.');
	}

	$upload = wp_upload_bits($name, null, $pngBytes);
	if (!is_array($upload) || !empty($upload['error']) || empty($upload['file'])) {
		$fail('Unable to write quality image fixture.');
	}

	$attachmentId = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title' => $name,
			'post_status' => 'inherit',
		),
		$upload['file'],
		0,
		true
	);
	if (is_wp_error($attachmentId) || (int) $attachmentId < 1) {
		$fail('Unable to create quality image attachment.');
	}

	$attachmentId = (int) $attachmentId;
	update_post_meta($attachmentId, '_wp_attachment_image_alt', $alt);

	return $attachmentId;
};

$imageOneId = $createImage('wla-quality-main.png', 'Fachada principal');
$imageTwoId = $createImage('wla-quality-interior.png', 'Interior propiedad');
$imageThreeId = $createImage('wla-quality-patio.png', 'Patio propiedad');

set_post_thumbnail($qualityId, $imageOneId);
update_post_meta($qualityId, '_wla_inmo_gallery_ids', array($imageTwoId, $imageThreeId));

if (!WLA\Inmo\Quality\Indexer::syncNow($qualityId)) {
	$fail('Unable to synchronize draft property into administrative quality projection.');
}

$qualityRepository = new WLA\Inmo\Quality\Repository();
$qualityRow = $qualityRepository->find($qualityId);
if (!is_array($qualityRow)) {
	$fail('Draft property is missing from administrative quality projection.');
}
if ((int) ($qualityRow['score'] ?? -1) !== 100 || (int) ($qualityRow['is_complete'] ?? 0) !== 1) {
	$fail('Complete draft property did not reach deterministic 100% quality.');
}
if ((string) ($qualityRow['missing_codes'] ?? 'unexpected') !== '') {
	$fail('Complete quality row still contains missing checks.');
}
if (strpos(implode('|', array_map('strval', $qualityRow)), $privateMarker) !== false) {
	$fail('Private address leaked into administrative quality projection.');
}

$searchTable = WLA\Inmo\Search\IndexSchema::tableName($wpdb);
WLA\Inmo\Search\Indexer::syncNow($qualityId);
$publicIndexedDraft = (int) $wpdb->get_var(
	$wpdb->prepare("SELECT COUNT(*) FROM {$searchTable} WHERE property_id = %d", $qualityId)
);
if ($publicIndexedDraft !== 0) {
	$fail('Draft quality support weakened the published-only public search index contract.');
}

update_post_meta($imageTwoId, '_wp_attachment_image_alt', '');
if (!WLA\Inmo\Quality\Indexer::syncNow($qualityId)) {
	$fail('Unable to resynchronize quality after ALT change.');
}
$qualityRow = $qualityRepository->find($qualityId);
if (!is_array($qualityRow) || (int) ($qualityRow['score'] ?? 100) >= 100) {
	$fail('Missing ALT did not reduce catalogue quality.');
}
if (!str_contains((string) ($qualityRow['missing_codes'] ?? ''), 'image_alt')) {
	$fail('Missing ALT is not explained by a stable quality code.');
}

update_post_meta($imageTwoId, '_wp_attachment_image_alt', 'Interior restaurado');
if (!WLA\Inmo\Quality\Indexer::syncNow($qualityId)) {
	$fail('Unable to restore quality after ALT correction.');
}

$rebuilt = WLA\Inmo\Quality\Rebuilder::rebuildAll(50);
if ($rebuilt < 1) {
	$fail('Catalogue quality rebuild did not process existing properties.');
}
$qualityRow = $qualityRepository->find($qualityId);
if (!is_array($qualityRow) || (int) ($qualityRow['score'] ?? -1) !== 100) {
	$fail('Catalogue quality rebuild did not reproduce the deterministic result.');
}

$summary = $qualityRepository->summary();
if ((int) ($summary['total'] ?? 0) < 1) {
	$fail('Catalogue quality summary did not count rebuilt properties.');
}

if (!class_exists(WLA\Inmo\Admin\PropertyQualityList::class) || !class_exists(WLA\Inmo\Admin\QualityPage::class)) {
	$fail('Catalogue quality admin modules are unavailable.');
}

echo "WLA Inmo catalogue quality integration tests passed.\n";
