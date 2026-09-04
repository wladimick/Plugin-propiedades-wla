<?php

declare(strict_types=1);

$GLOBALS['wla_asset_styles'] = array();
$GLOBALS['wla_asset_scripts'] = array();
$GLOBALS['wla_asset_media_calls'] = 0;
$GLOBALS['wla_asset_screen'] = null;

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
if (!function_exists('wp_enqueue_style')) {
	function wp_enqueue_style($handle, $src = '', $deps = array(), $version = false)
	{
		$GLOBALS['wla_asset_styles'][] = (string) $handle;
		return true;
	}
}
if (!function_exists('wp_enqueue_script')) {
	function wp_enqueue_script($handle, $src = '', $deps = array(), $version = false, $inFooter = false)
	{
		unset($src, $deps, $version, $inFooter);
		$GLOBALS['wla_asset_scripts'][] = (string) $handle;
		return true;
	}
}
if (!function_exists('wp_enqueue_media')) {
	function wp_enqueue_media()
	{
		$GLOBALS['wla_asset_media_calls']++;
	}
}
if (!function_exists('get_current_screen')) {
	function get_current_screen()
	{
		return $GLOBALS['wla_asset_screen'];
	}
}
if (!defined('WLA_INMO_URL')) {
	define('WLA_INMO_URL', 'https://example.test/wp-content/plugins/wla-inmo/');
}
if (!defined('WLA_INMO_VERSION')) {
	define('WLA_INMO_VERSION', '0.1.0-alpha');
}

$root = dirname(__DIR__, 2) . '/plugin/wla-inmo/src/';
require_once $root . 'Access/Capabilities.php';
require_once $root . 'Properties/Capabilities.php';
require_once $root . 'Properties/PostType.php';
require_once $root . 'Taxonomies/Capabilities.php';
require_once $root . 'Admin/ScreenRegistry.php';
require_once $root . 'Admin/PageRenderer.php';
require_once $root . 'Admin/Menu.php';
require_once $root . 'Admin/PropertyMedia.php';
require_once $root . 'Admin/Assets.php';

use WLA\Inmo\Admin\Assets;
use WLA\Inmo\Properties\PostType;

function wlaAssetsExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

function wlaAssetsReset($screen = null): void
{
	$GLOBALS['wla_asset_styles'] = array();
	$GLOBALS['wla_asset_scripts'] = array();
	$GLOBALS['wla_asset_media_calls'] = 0;
	$GLOBALS['wla_asset_screen'] = $screen;
}

function wlaAssetsAssert(array $styles, array $scripts, int $mediaCalls, string $context): void
{
	wlaAssetsExpect($GLOBALS['wla_asset_styles'] === $styles, $context . ': unexpected styles ' . json_encode($GLOBALS['wla_asset_styles']));
	wlaAssetsExpect($GLOBALS['wla_asset_scripts'] === $scripts, $context . ': unexpected scripts ' . json_encode($GLOBALS['wla_asset_scripts']));
	wlaAssetsExpect($GLOBALS['wla_asset_media_calls'] === $mediaCalls, $context . ': unexpected Media Library enqueue count.');
}

$normalScreen = (object) array('post_type' => 'post');
$propertyScreen = (object) array('post_type' => PostType::POST_TYPE);

wlaAssetsReset($normalScreen);
Assets::enqueue('edit.php');
wlaAssetsAssert(array(), array(), 0, 'Unrelated WordPress list');

wlaAssetsReset($normalScreen);
Assets::enqueue('post.php');
wlaAssetsAssert(array(), array(), 0, 'Unrelated WordPress editor');

wlaAssetsReset(null);
Assets::enqueue('toplevel_page_wla-inmo');
wlaAssetsAssert(array('wla-inmo-admin', 'wla-inmo-dashboard'), array(), 0, 'Dashboard');

wlaAssetsReset(null);
Assets::enqueue('wla-inmo_page_wla-inmo-help');
wlaAssetsAssert(array('wla-inmo-admin', 'wla-inmo-help-center'), array('wla-inmo-help-center'), 0, 'Help Center');

wlaAssetsReset(null);
Assets::enqueue('wla-inmo_page_wla-inmo-settings');
wlaAssetsAssert(array('wla-inmo-admin', 'wla-inmo-settings'), array(), 0, 'Settings');

wlaAssetsReset(null);
Assets::enqueue('wla-inmo_page_wla-inmo-activity');
wlaAssetsAssert(array('wla-inmo-admin', 'wla-inmo-activity'), array(), 0, 'Activity');

wlaAssetsReset($propertyScreen);
Assets::enqueue('edit.php');
wlaAssetsAssert(array('wla-inmo-admin'), array(), 0, 'Property list');

wlaAssetsReset($propertyScreen);
Assets::enqueue('post.php');
wlaAssetsAssert(
	array('wla-inmo-admin', 'wla-inmo-activity', 'wla-inmo-property-media'),
	array('wla-inmo-property-media'),
	1,
	'Property editor'
);

wlaAssetsExpect(Assets::isDashboardContext('wla-inmo_page_wla-inmo-help') === false, 'Help must never be detected as Dashboard.');
wlaAssetsExpect(Assets::isHelpContext('wla-inmo_page_wla-inmo-settings') === false, 'Settings must never be detected as Help.');
wlaAssetsExpect(Assets::isSettingsContext('wla-inmo_page_wla-inmo-activity') === false, 'Activity must never be detected as Settings.');
wlaAssetsExpect(Assets::isActivityContext('toplevel_page_wla-inmo') === false, 'Dashboard must never be detected as Activity.');

echo "WLA Inmo conditional admin asset smoke tests passed.\n";
