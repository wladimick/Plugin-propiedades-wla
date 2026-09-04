<?php

declare(strict_types=1);

if (!defined('WLA_INMO_DIR')) {
	define('WLA_INMO_DIR', dirname(__DIR__, 2) . '/plugin/wla-inmo/');
}

$GLOBALS['wla_settings_options'] = array(
	'wla_inmo_settings' => array(
		'country_code'     => 'CL',
		'currency_primary' => 'CLP',
		'area_unit'        => 'm2',
		'map_provider'     => 'osm',
		'property_base'    => 'casas-y-terrenos',
		'business_name'    => '',
	),
);
$GLOBALS['wla_registered_settings'] = array();
$GLOBALS['wla_filters_registered'] = array();
$GLOBALS['wla_filter_calls'] = array();
$GLOBALS['wla_locate_template_result'] = '';

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		unset($domain);
		return $text;
	}
}
if (!function_exists('_x')) {
	function _x($text, $context, $domain = 'default')
	{
		unset($context, $domain);
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
		$value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
		return trim($value, '-');
	}
}
if (!function_exists('get_option')) {
	function get_option($key, $default = false)
	{
		return $GLOBALS['wla_settings_options'][(string) $key] ?? $default;
	}
}
if (!function_exists('register_setting')) {
	function register_setting($group, $name, $args = array())
	{
		$GLOBALS['wla_registered_settings'][(string) $name] = array('group' => $group, 'args' => $args);
		return true;
	}
}
if (!function_exists('add_filter')) {
	function add_filter($hook, $callback, $priority = 10, $acceptedArgs = 1)
	{
		$GLOBALS['wla_filters_registered'][(string) $hook][] = array($callback, $priority, $acceptedArgs);
		return true;
	}
}
if (!function_exists('apply_filters')) {
	function apply_filters($hook, $value, ...$args)
	{
		$GLOBALS['wla_filter_calls'][] = array($hook, $value, $args);
		return $value;
	}
}
if (!function_exists('locate_template')) {
	function locate_template($candidates, $load = false, $loadOnce = true)
	{
		unset($load, $loadOnce);
		$GLOBALS['wla_last_template_candidates'] = $candidates;
		return $GLOBALS['wla_locate_template_result'];
	}
}
if (!function_exists('register_post_type')) {
	function register_post_type($postType, $args)
	{
		$GLOBALS['wla_settings_post_type'] = array($postType, $args);
		return true;
	}
}

require_once WLA_INMO_DIR . 'src/Access/Capabilities.php';
require_once WLA_INMO_DIR . 'src/Localization/ChilePreset.php';
require_once WLA_INMO_DIR . 'src/Settings/Schema.php';
require_once WLA_INMO_DIR . 'src/Settings/Repository.php';
require_once WLA_INMO_DIR . 'src/Settings/Registry.php';
require_once WLA_INMO_DIR . 'src/Properties/Capabilities.php';
require_once WLA_INMO_DIR . 'src/Properties/PostType.php';
require_once WLA_INMO_DIR . 'src/Frontend/TemplateResolver.php';

use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Frontend\TemplateResolver;
use WLA\Inmo\Localization\ChilePreset;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Settings\Registry;
use WLA\Inmo\Settings\Repository;
use WLA\Inmo\Settings\Schema;

function wlaSettingsExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$chile = ChilePreset::settings();
wlaSettingsExpect($chile['country_code'] === 'CL', 'Chile preset code must remain CL.');
wlaSettingsExpect($chile['currency_primary'] === 'CLP', 'Chile preset default currency must remain CLP.');
wlaSettingsExpect(!isset($chile['business_name']), 'Country preset must not contain site branding.');

$defaults = Schema::defaults();
wlaSettingsExpect($defaults['property_base'] === 'propiedades', 'Default property base must remain propiedades.');
wlaSettingsExpect($defaults['business_name'] === '', 'Core defaults must not force business branding.');

$sanitized = Schema::sanitize(array(
	'country_code'     => 'us',
	'currency_primary' => 'usd',
	'area_unit'        => 'ft2',
	'map_provider'     => 'google',
	'property_base'    => '  Casas Premium / Región  ',
	'business_name'    => '<b>Demo Realty</b>',
	'unknown_secret'   => 'must disappear',
));
wlaSettingsExpect($sanitized['country_code'] === 'US', 'Country code must normalize to two uppercase letters.');
wlaSettingsExpect($sanitized['currency_primary'] === 'USD', 'Currency must normalize to three uppercase letters.');
wlaSettingsExpect($sanitized['area_unit'] === 'ft2', 'Supported imperial area unit must remain usable.');
wlaSettingsExpect($sanitized['map_provider'] === 'google', 'Supported map adapter selection must remain usable.');
wlaSettingsExpect($sanitized['property_base'] === 'casas-premium-region', 'Property base must become a safe slug.');
wlaSettingsExpect($sanitized['business_name'] === 'Demo Realty', 'Business label must be sanitized.');
wlaSettingsExpect(!isset($sanitized['unknown_secret']), 'Unknown settings keys must be discarded.');

Repository::resetCache();
wlaSettingsExpect(Repository::get('property_base') === 'casas-y-terrenos', 'Repository must merge stored settings over defaults.');
wlaSettingsExpect(Repository::get('missing', 'fallback') === 'fallback', 'Repository must support explicit fallback.');

Registry::register();
$registered = $GLOBALS['wla_registered_settings'][Schema::OPTION_NAME] ?? null;
wlaSettingsExpect(is_array($registered), 'Settings option must be registered.');
wlaSettingsExpect($registered['group'] === Schema::OPTION_GROUP, 'Settings group mismatch.');
wlaSettingsExpect(($registered['args']['show_in_rest'] ?? null) === false, 'Raw settings must not be exposed through REST.');
wlaSettingsExpect(is_callable($registered['args']['sanitize_callback'] ?? null), 'Settings must have a sanitizer.');
wlaSettingsExpect(Registry::settingsCapability('manage_options') === AccessCapabilities::MANAGE_SETTINGS, 'Settings page must use WLA capability, not manage_options.');
wlaSettingsExpect(isset($GLOBALS['wla_filters_registered']['option_page_capability_wla_inmo']), 'Settings capability filter must be registered.');

$args = PostType::arguments();
wlaSettingsExpect($args['has_archive'] === 'casas-y-terrenos', 'CPT archive must use configured property base.');
wlaSettingsExpect(($args['rewrite']['slug'] ?? '') === 'casas-y-terrenos', 'Single rewrite must use configured property base.');

$GLOBALS['wla_locate_template_result'] = '/tmp/theme/wla-inmo/single-property.php';
$located = TemplateResolver::locate('single-property.php');
wlaSettingsExpect($located === '/tmp/theme/wla-inmo/single-property.php', 'Theme override must win over plugin fallback.');
wlaSettingsExpect(($GLOBALS['wla_last_template_candidates'][0] ?? '') === 'wla-inmo/single-property.php', 'Theme override contract must live under wla-inmo/.');
wlaSettingsExpect(TemplateResolver::locate('../wp-config.php') === null, 'Template resolver must reject parent traversal.');
wlaSettingsExpect(TemplateResolver::locate('single-property.html') === null, 'Template resolver must accept PHP templates only.');
wlaSettingsExpect(TemplateResolver::locate("parts/evil\0.php") === null, 'Template resolver must reject null bytes.');
wlaSettingsExpect(TemplateResolver::pluginFallbackPath('parts/card.php') === WLA_INMO_DIR . 'templates/parts/card.php', 'Plugin fallback path contract changed unexpectedly.');

echo "WLA Inmo settings and template contract smoke tests passed.\n";
