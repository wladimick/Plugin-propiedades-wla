<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$plugin = $root . '/plugin/wla-inmo/';

$expect = static function (bool $condition, string $message): void {
	if ($condition) {
		return;
	}
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

$files = array(
	$plugin . 'src/Dashboard/Snapshot.php',
	$plugin . 'src/Admin/DashboardPage.php',
	$plugin . 'assets/admin/dashboard.css',
);

foreach ($files as $file) {
	$expect(is_file($file), 'Missing dashboard release file: ' . basename($file));
}

$snapshot = file_get_contents($plugin . 'src/Dashboard/Snapshot.php');
$page = file_get_contents($plugin . 'src/Admin/DashboardPage.php');
$renderer = file_get_contents($plugin . 'src/Admin/PageRenderer.php');
$assets = file_get_contents($plugin . 'src/Admin/Assets.php');

$expect(is_string($snapshot) && str_contains($snapshot, 'final class Snapshot'), 'Dashboard snapshot class is missing.');
$expect(str_contains($snapshot, "private const ACTIVE_STATUSES = array('publish', 'draft', 'pending', 'private', 'future')"), 'Dashboard must count administrative post statuses explicitly.');
$expect(str_contains($snapshot, "ActivityRepository::recent"), 'Dashboard activity must use the bounded recent activity query.');
$expect(!str_contains($snapshot, 'private_address'), 'Dashboard snapshot must not reference private addresses.');
$expect(!str_contains($snapshot, 'internal_notes'), 'Dashboard snapshot must not reference internal notes.');
$expect(!str_contains($snapshot, 'business_email'), 'Dashboard snapshot must not reference contact settings.');
$expect(!str_contains($snapshot, 'Search\\Index'), 'Dashboard administrative counts must not depend on the public search index.');

$expect(is_string($page) && str_contains($page, 'final class DashboardPage'), 'Dashboard admin page is missing.');
$expect(str_contains($page, 'VIEW_DASHBOARD'), 'Dashboard access must require its dedicated capability.');
$expect(str_contains($page, 'VIEW_ACTIVITY'), 'Recent activity must be capability-gated.');
$expect(str_contains($page, 'MANAGE_SETTINGS'), 'Settings action must be capability-gated.');
$expect(!str_contains($page, 'Chart.js'), 'Dashboard must not depend on Chart.js.');
$expect(!str_contains($page, '<script'), 'Dashboard renderer must not emit inline scripts.');

$expect(is_string($renderer) && str_contains($renderer, 'DashboardPage::render();'), 'Root admin screen must delegate to DashboardPage.');
$expect(is_string($assets) && str_contains($assets, 'assets/admin/dashboard.css'), 'Dashboard stylesheet must be registered.');
$expect(str_contains($assets, 'isDashboardContext'), 'Dashboard stylesheet must be scoped to the summary screen.');

echo "WLA Inmo operational dashboard smoke tests passed.\n";
