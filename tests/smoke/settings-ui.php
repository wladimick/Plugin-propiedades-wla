<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$plugin = $root . '/plugin/wla-inmo/';

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		unset($domain);
		return $text;
	}
}
if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value)
	{
		return trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $value)));
	}
}
if (!function_exists('sanitize_title')) {
	function sanitize_title($value)
	{
		$value = strtolower(trim((string) $value));
		$value = strtr($value, array('á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n'));
		$value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
		return trim($value, '-');
	}
}
if (!function_exists('sanitize_email')) {
	function sanitize_email($value)
	{
		$value = trim((string) $value);
		return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
	}
}
if (!function_exists('apply_filters')) {
	function apply_filters($hook, $value)
	{
		unset($hook);
		return $value;
	}
}

require_once $plugin . 'src/Localization/ChilePreset.php';
require_once $plugin . 'src/Settings/Schema.php';
require_once $plugin . 'src/Admin/SettingsPage.php';

use WLA\Inmo\Admin\SettingsPage;
use WLA\Inmo\Settings\Schema;

function wlaSettingsUiExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$defaults = Schema::defaults();
wlaSettingsUiExpect(($defaults['business_email'] ?? null) === '', 'Business email must default empty.');
wlaSettingsUiExpect(($defaults['business_phone'] ?? null) === '', 'Business phone must default empty.');
wlaSettingsUiExpect(($defaults['whatsapp_number'] ?? null) === '', 'WhatsApp must default empty.');
wlaSettingsUiExpect(($defaults['lead_retention_months'] ?? null) === '24', 'Lead retention must default to D45 24 months.');
wlaSettingsUiExpect(($defaults['activity_retention_months'] ?? null) === '12', 'Activity retention must default to D57 12 months.');

$sanitized = Schema::sanitize(array(
	'country_code' => 'cl',
	'currency_primary' => 'clp',
	'area_unit' => 'm2',
	'map_provider' => 'osm',
	'property_base' => ' Casas / Premium ',
	'business_name' => '<b>Demo Inmobiliaria</b>',
	'business_email' => 'ventas@example.com',
	'business_phone' => '+56 (9) 1234-5678 ext<script>',
	'whatsapp_number' => '+56 9 1234 5678',
	'business_address' => '<strong>Av. Demo 123</strong>',
	'lead_retention_months' => '999',
	'activity_retention_months' => '0',
	'unknown_secret' => 'discard-me',
));

wlaSettingsUiExpect($sanitized['property_base'] === 'casas-premium', 'Property base must remain a safe slug.');
wlaSettingsUiExpect($sanitized['business_name'] === 'Demo Inmobiliaria', 'Business name must be sanitized.');
wlaSettingsUiExpect($sanitized['business_email'] === 'ventas@example.com', 'Valid business email must survive sanitization.');
wlaSettingsUiExpect(!str_contains($sanitized['business_phone'], '<'), 'Phone must strip unsafe characters.');
wlaSettingsUiExpect($sanitized['whatsapp_number'] === '+56912345678', 'WhatsApp must normalize to compact international digits.');
wlaSettingsUiExpect($sanitized['business_address'] === 'Av. Demo 123', 'Business address must be plain text.');
wlaSettingsUiExpect($sanitized['lead_retention_months'] === '120', 'Lead retention must be capped at 120 months.');
wlaSettingsUiExpect($sanitized['activity_retention_months'] === '1', 'Activity retention must have a one-month minimum.');
wlaSettingsUiExpect(!isset($sanitized['unknown_secret']), 'Unknown settings keys must be discarded.');

$tabs = SettingsPage::tabs();
wlaSettingsUiExpect(count($tabs) === 8, 'Settings UI must expose the eight approved tabs.');
wlaSettingsUiExpect(SettingsPage::fieldsForTab('contact') === array('business_email', 'business_phone', 'whatsapp_number', 'business_address'), 'Contact tab allowlist changed unexpectedly.');
wlaSettingsUiExpect(SettingsPage::fieldsForTab('seo') === array(), 'SEO tab must not invent writable Phase 6 settings.');
wlaSettingsUiExpect(SettingsPage::fieldsForTab('performance') === array(), 'Performance tab must remain informational until real controls exist.');

$settingsPage = file_get_contents($plugin . 'src/Admin/SettingsPage.php');
$rewriteManager = file_get_contents($plugin . 'src/Settings/RewriteManager.php');
$schemaSource = file_get_contents($plugin . 'src/Settings/Schema.php');

wlaSettingsUiExpect(is_string($settingsPage) && str_contains($settingsPage, 'wp_verify_nonce'), 'Settings writes must verify nonce.');
wlaSettingsUiExpect(str_contains((string) $settingsPage, 'Capabilities::MANAGE_SETTINGS'), 'Settings UI must enforce WLA settings capability.');
wlaSettingsUiExpect(!str_contains((string) $settingsPage, 'flush_rewrite_rules('), 'Settings save request must not flush rewrites directly.');
wlaSettingsUiExpect(!str_contains((string) $schemaSource, 'flush_rewrite_rules('), 'Settings sanitizer must never flush rewrites.');
wlaSettingsUiExpect(is_string($rewriteManager) && substr_count($rewriteManager, 'flush_rewrite_rules(false)') === 1, 'Rewrite flush must exist exactly once in the controlled manager.');
wlaSettingsUiExpect(str_contains((string) $rewriteManager, 'wp_verify_nonce') && str_contains((string) $rewriteManager, 'Capabilities::MANAGE_SETTINGS'), 'Controlled rewrite operation must enforce nonce and capability.');
wlaSettingsUiExpect(!preg_match('/wp_remote_|curl_|XMLHttpRequest|axios/i', (string) $settingsPage . (string) $rewriteManager), 'Settings UI must not make remote requests.');

echo "WLA Inmo settings UI smoke tests passed.\n";
