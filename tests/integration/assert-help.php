<?php

if (!defined('ABSPATH')) {
	exit(1);
}

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

if (!class_exists(WLA\Inmo\Admin\HelpCenter::class) || !class_exists(WLA\Inmo\Admin\Onboarding::class)) {
	$fail('Help center modules are unavailable from the active release ZIP.');
}

$topics = WLA\Inmo\Admin\HelpCenter::topics();
if (count($topics) < 13) {
	$fail('Initial help topic catalogue is incomplete.');
}

$planned = array_filter(
	$topics,
	static fn(array $topic): bool => ($topic['status'] ?? '') === 'planned'
);
if (count($planned) < 4) {
	$fail('Future modules are not clearly marked as planned.');
}

$admin = get_user_by('login', 'admin');
if (!$admin instanceof WP_User) {
	$fail('Unable to resolve CI administrator.');
}
wp_set_current_user($admin->ID);

ob_start();
WLA\Inmo\Admin\HelpCenter::render();
$adminHtml = (string) ob_get_clean();

foreach (array('Crear propiedad', 'Revisar calidad', 'Abrir ajustes', 'Próximamente', 'Preguntas frecuentes', 'Conceptos frecuentes') as $needle) {
	if (!str_contains($adminHtml, $needle)) {
		$fail("Administrator help output is missing: {$needle}");
	}
}

update_user_meta($admin->ID, '_wla_inmo_onboarding_progress', array('business', 'quality'));
$adminProgress = WLA\Inmo\Admin\Onboarding::progress($admin->ID);
if ($adminProgress !== array('business', 'quality')) {
	$fail('Onboarding progress did not read valid per-user steps.');
}

$editorId = wp_create_user('wla-help-editor', wp_generate_password(24, true, true), 'wla-help-editor@example.test');
if (is_wp_error($editorId) || (int) $editorId < 1) {
	$fail('Unable to create help-center editor fixture.');
}
$editorId = (int) $editorId;
$editor = get_user_by('id', $editorId);
if (!$editor instanceof WP_User) {
	$fail('Unable to load editor fixture.');
}
$editor->set_role('wla_property_editor');

if (WLA\Inmo\Admin\Onboarding::progress($editorId) !== array()) {
	$fail('Onboarding progress leaked between users.');
}

wp_set_current_user($editorId);
ob_start();
WLA\Inmo\Admin\HelpCenter::render();
$editorHtml = (string) ob_get_clean();

if (!str_contains($editorHtml, 'Crear propiedad') || !str_contains($editorHtml, 'Revisar calidad')) {
	$fail('Property editor did not receive permitted help actions.');
}
if (str_contains($editorHtml, 'Abrir ajustes')) {
	$fail('Property editor received a restricted Settings action.');
}

update_user_meta($editorId, '_wla_inmo_onboarding_dismissed', '1');
if (!WLA\Inmo\Admin\Onboarding::isDismissed($editorId)) {
	$fail('Onboarding dismissed state is not stored per user.');
}
if (WLA\Inmo\Admin\Onboarding::isDismissed($admin->ID)) {
	$fail('Dismissed state leaked to the administrator fixture.');
}

wp_set_current_user($admin->ID);
$steps = WLA\Inmo\Admin\Onboarding::steps();
if (count($steps) !== 6) {
	$fail('Onboarding checklist contract changed unexpectedly.');
}

foreach ($steps as $step) {
	if (!isset($step['label'], $step['url'], $step['available'])) {
		$fail('Onboarding step shape is incomplete.');
	}
}

wp_delete_user($editorId);
delete_user_meta($admin->ID, '_wla_inmo_onboarding_progress');
delete_user_meta($admin->ID, '_wla_inmo_onboarding_dismissed');

echo "WLA Inmo help center integration passed.\n";
