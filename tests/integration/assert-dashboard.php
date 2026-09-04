<?php

if (!defined('ABSPATH')) {
	exit(1);
}

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

if (!class_exists(WLA\Inmo\Dashboard\Snapshot::class)
	|| !class_exists(WLA\Inmo\Admin\DashboardPage::class)
	|| !class_exists(WLA\Inmo\Activity\Repository::class)
) {
	$fail('Operational dashboard classes are unavailable from the active plugin.');
}

$adminUser = get_user_by('login', 'admin');
if (!$adminUser instanceof WP_User) {
	$fail('Unable to resolve CI administrator for dashboard tests.');
}
wp_set_current_user($adminUser->ID);

$createTerm = static function (string $name, string $taxonomy) use ($fail): int {
	$term = term_exists($name, $taxonomy);
	if ($term === null) {
		$term = wp_insert_term($name, $taxonomy);
	}
	if (is_wp_error($term)) {
		$fail("Unable to create dashboard fixture term in {$taxonomy}.");
	}
	return is_array($term) ? (int) $term['term_id'] : (int) $term;
};

$ventaId = $createTerm('Venta Dashboard', 'wla_operation');
$arriendoId = $createTerm('Arriendo Dashboard', 'wla_operation');
$communeId = $createTerm('Curicó Dashboard', 'wla_commune');

$createProperty = static function (string $title, string $status) use ($fail): int {
	$postId = wp_insert_post(
		array(
			'post_type' => 'wla_property',
			'post_status' => $status,
			'post_title' => $title,
			'post_content' => str_repeat('Descripción de propiedad para la prueba del resumen operativo. ', 2),
		),
		true
	);
	if (is_wp_error($postId) || (int) $postId < 1) {
		$fail("Unable to create dashboard fixture {$title}.");
	}
	return (int) $postId;
};

$publishedId = $createProperty('Dashboard Publicada', 'publish');
$draftId = $createProperty('Dashboard Borrador', 'draft');
$pendingId = $createProperty('Dashboard Pendiente', 'pending');

wp_set_object_terms($publishedId, array($ventaId), 'wla_operation', false);
wp_set_object_terms($draftId, array($arriendoId), 'wla_operation', false);
wp_set_object_terms($pendingId, array($ventaId), 'wla_operation', false);
wp_set_object_terms($publishedId, array($communeId), 'wla_commune', false);

update_post_meta($publishedId, '_wla_inmo_property_code', 'DASH-PUB');
update_post_meta($publishedId, '_wla_inmo_status', 'available');
update_post_meta($publishedId, '_wla_inmo_featured', 1);
update_post_meta($publishedId, '_wla_inmo_price_clp', 150000000);
update_post_meta($publishedId, '_wla_inmo_built_area_m2', 120);
update_post_meta($publishedId, '_wla_inmo_last_verified_date', gmdate('Y-m-d'));
update_post_meta($publishedId, '_wla_inmo_private_address', 'PRIVATE-DASHBOARD-MARKER');
update_post_meta($publishedId, '_wla_inmo_internal_notes', 'INTERNAL-DASHBOARD-MARKER');

update_post_meta($draftId, '_wla_inmo_property_code', 'DASH-DRAFT');
update_post_meta($draftId, '_wla_inmo_status', 'reserved');
update_post_meta($pendingId, '_wla_inmo_property_code', 'DASH-PENDING');
update_post_meta($pendingId, '_wla_inmo_status', 'available');

foreach (array($publishedId, $draftId, $pendingId) as $propertyId) {
	if (!WLA\Inmo\Quality\Indexer::syncNow($propertyId)) {
		$fail('Unable to synchronize dashboard fixture into catalogue quality projection.');
	}
}

$withoutActivity = (new WLA\Inmo\Dashboard\Snapshot())->build(false);
$properties = is_array($withoutActivity['properties'] ?? null) ? $withoutActivity['properties'] : array();
$quality = is_array($withoutActivity['quality'] ?? null) ? $withoutActivity['quality'] : array();
$operations = is_array($withoutActivity['operations'] ?? null) ? $withoutActivity['operations'] : array();
$statuses = is_array($withoutActivity['commercial_statuses'] ?? null) ? $withoutActivity['commercial_statuses'] : array();

if ((int) ($properties['total'] ?? 0) !== 3) {
	$fail('Dashboard total property count is incorrect.');
}
if ((int) ($properties['published'] ?? 0) !== 1 || (int) ($properties['draft'] ?? 0) !== 1 || (int) ($properties['pending'] ?? 0) !== 1) {
	$fail('Dashboard WordPress status distribution is incorrect.');
}
if ((int) ($withoutActivity['featured'] ?? 0) !== 1) {
	$fail('Dashboard featured count is incorrect.');
}
if ((int) ($operations['venta-dashboard']['count'] ?? 0) !== 2 || (int) ($operations['arriendo-dashboard']['count'] ?? 0) !== 1) {
	$fail('Dashboard operation distribution is incorrect.');
}
if ((int) ($statuses['available'] ?? 0) !== 2 || (int) ($statuses['reserved'] ?? 0) !== 1) {
	$fail('Dashboard commercial status distribution is incorrect.');
}
if ((int) ($quality['total'] ?? 0) !== 3 || (int) ($quality['incomplete'] ?? 0) < 1) {
	$fail('Dashboard catalogue quality summary is incorrect.');
}
if ((int) ($quality['no_price'] ?? 0) < 2 || (int) ($quality['no_image'] ?? 0) !== 3) {
	$fail('Dashboard exception counts are incorrect.');
}
if (!is_array($withoutActivity['attention'] ?? null) || count($withoutActivity['attention']) > 6) {
	$fail('Dashboard attention list is not bounded.');
}
if (($withoutActivity['activity'] ?? array()) !== array()) {
	$fail('Dashboard queried activity despite activity being disabled for the snapshot.');
}

$encoded = wp_json_encode($withoutActivity);
if (!is_string($encoded) || str_contains($encoded, 'PRIVATE-DASHBOARD-MARKER') || str_contains($encoded, 'INTERNAL-DASHBOARD-MARKER')) {
	$fail('Private property data leaked into the dashboard snapshot.');
}

$withActivity = (new WLA\Inmo\Dashboard\Snapshot())->build(true);
if (!is_array($withActivity['activity'] ?? null) || count($withActivity['activity']) < 1 || count($withActivity['activity']) > 6) {
	$fail('Dashboard recent activity is missing or unbounded.');
}

for ($i = 0; $i < 100; $i++) {
	$postId = wp_insert_post(
		array(
			'post_type' => 'wla_property',
			'post_status' => 'draft',
			'post_title' => 'Dashboard Scale ' . $i,
		),
		true
	);
	if (is_wp_error($postId)) {
		$fail('Unable to create dashboard scale fixture.');
	}
}

global $wpdb;
$beforeQueries = (int) $wpdb->num_queries;
$scaled = (new WLA\Inmo\Dashboard\Snapshot())->build(false);
$queryDelta = (int) $wpdb->num_queries - $beforeQueries;
if ($queryDelta > 5) {
	$fail("Dashboard snapshot exceeded bounded query budget: {$queryDelta} queries.");
}
if ((int) ($scaled['properties']['total'] ?? 0) !== 103) {
	$fail('Dashboard scaled property count is incorrect.');
}

if (!current_user_can(WLA\Inmo\Access\Capabilities::VIEW_DASHBOARD)) {
	$fail('Administrator lost dashboard capability.');
}

ob_start();
WLA\Inmo\Admin\DashboardPage::render();
$html = (string) ob_get_clean();
foreach (array('Necesita atención', 'Estado del catálogo', 'Actividad reciente', 'Accesos rápidos') as $expectedText) {
	if (!str_contains($html, $expectedText)) {
		$fail("Dashboard render is missing expected section: {$expectedText}.");
	}
}
if (str_contains($html, 'PRIVATE-DASHBOARD-MARKER') || str_contains($html, 'INTERNAL-DASHBOARD-MARKER')) {
	$fail('Private property data leaked into dashboard HTML.');
}

echo "WLA Inmo operational dashboard integration tests passed.\n";
