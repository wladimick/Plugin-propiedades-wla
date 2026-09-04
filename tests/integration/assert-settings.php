<?php

if (!defined('ABSPATH')) {
	exit(1);
}

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

foreach (array(
	WLA\Inmo\Admin\SettingsPage::class,
	WLA\Inmo\Settings\RewriteManager::class,
	WLA\Inmo\Settings\Schema::class,
) as $class) {
	if (!class_exists($class)) {
		$fail("Missing settings class {$class}.");
	}
}

$admin = get_user_by('login', 'admin');
if (!$admin instanceof WP_User) {
	$fail('Unable to resolve CI administrator.');
}
wp_set_current_user($admin->ID);

if (!current_user_can(WLA\Inmo\Access\Capabilities::MANAGE_SETTINGS)) {
	$fail('Administrator is missing WLA settings capability.');
}

$current = WLA\Inmo\Settings\Repository::all();
if (($current['lead_retention_months'] ?? '') !== '24' || ($current['activity_retention_months'] ?? '') !== '12') {
	$fail('Decision-backed privacy defaults are unavailable.');
}

$rulesBefore = get_option('rewrite_rules', array());
$rulesHashBefore = md5(serialize($rulesBefore));

$incoming = array_merge(
	$current,
	array(
		'property_base' => 'Casas CI',
		'business_name' => 'Inmobiliaria CI',
		'business_email' => 'ventas-ci@example.test',
		'business_phone' => '+56 (9) 5555-1234',
		'whatsapp_number' => '+56 9 5555 1234',
		'business_address' => 'Av. Integración 123',
		'lead_retention_months' => '36',
		'activity_retention_months' => '18',
		'unknown_secret' => 'must-not-persist',
	)
);
$sanitized = WLA\Inmo\Settings\Schema::sanitize($incoming);
update_option(WLA\Inmo\Settings\Schema::OPTION_NAME, $sanitized, false);
WLA\Inmo\Settings\Repository::resetCache();
$stored = WLA\Inmo\Settings\Repository::all();

if (($stored['property_base'] ?? '') !== 'casas-ci') {
	$fail('Property base was not sanitized and persisted.');
}
if (($stored['business_email'] ?? '') !== 'ventas-ci@example.test') {
	$fail('Business email was not persisted.');
}
if (($stored['whatsapp_number'] ?? '') !== '+56955551234') {
	$fail('WhatsApp was not normalized.');
}
if (($stored['lead_retention_months'] ?? '') !== '36' || ($stored['activity_retention_months'] ?? '') !== '18') {
	$fail('Privacy retention settings were not persisted.');
}
if (array_key_exists('unknown_secret', $stored)) {
	$fail('Unknown settings key leaked into canonical option.');
}

if (WLA\Inmo\Settings\RewriteManager::pendingBase() !== 'casas-ci') {
	$fail('Changing property_base did not mark controlled rewrites as pending.');
}

$rulesAfterSave = get_option('rewrite_rules', array());
if (md5(serialize($rulesAfterSave)) !== $rulesHashBefore) {
	$fail('Saving settings flushed rewrite rules in the same request.');
}

$_GET['tab'] = 'contact';
ob_start();
WLA\Inmo\Admin\SettingsPage::render();
$settingsHtml = (string) ob_get_clean();
$_GET = array();

foreach (array('Datos de contacto', 'ventas-ci@example.test', '+56955551234') as $needle) {
	if (!str_contains($settingsHtml, $needle)) {
		$fail("Settings UI render is missing {$needle}.");
	}
}

$editorId = wp_create_user('wla-settings-editor', wp_generate_password(24, true, true), 'settings-editor@example.test');
if (is_wp_error($editorId) || (int) $editorId < 1) {
	$fail('Unable to create settings permission fixture.');
}
$editor = get_user_by('id', (int) $editorId);
if (!$editor instanceof WP_User) {
	$fail('Unable to load settings permission fixture.');
}
$editor->set_role('wla_property_editor');
wp_set_current_user((int) $editorId);
if (current_user_can(WLA\Inmo\Access\Capabilities::MANAGE_SETTINGS)) {
	$fail('Property editor unexpectedly received settings capability.');
}
wp_set_current_user($admin->ID);
wp_delete_user((int) $editorId);

echo "WLA Inmo settings integration prepared controlled rewrite.\n";
