<?php

declare(strict_types=1);

$GLOBALS['wla_admin_caps'] = array();
$GLOBALS['wla_admin_menu'] = array();
$GLOBALS['wla_admin_submenu'] = array();
$GLOBALS['wla_admin_styles'] = array();

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
if (!function_exists('current_user_can')) {
	function current_user_can($capability, ...$args)
	{
		unset($args);
		return in_array((string) $capability, $GLOBALS['wla_admin_caps'], true);
	}
}
if (!function_exists('add_menu_page')) {
	function add_menu_page($pageTitle, $menuTitle, $capability, $menuSlug, $callback = '', $icon = '', $position = null)
	{
		$GLOBALS['wla_admin_menu'][] = compact('pageTitle', 'menuTitle', 'capability', 'menuSlug', 'callback', 'icon', 'position');
		return 'toplevel_page_' . $menuSlug;
	}
}
if (!function_exists('add_submenu_page')) {
	function add_submenu_page($parentSlug, $pageTitle, $menuTitle, $capability, $menuSlug, $callback = '')
	{
		$GLOBALS['wla_admin_submenu'][] = compact('parentSlug', 'pageTitle', 'menuTitle', 'capability', 'menuSlug', 'callback');
		return $parentSlug . '_page_' . str_replace(array('?', '=', '&'), '-', (string) $menuSlug);
	}
}
if (!function_exists('wp_unslash')) {
	function wp_unslash($value)
	{
		return $value;
	}
}
if (!function_exists('sanitize_key')) {
	function sanitize_key($value)
	{
		return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)) ?? '';
	}
}
if (!function_exists('esc_html__')) {
	function esc_html__($text, $domain = 'default')
	{
		unset($domain);
		return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
	}
}
if (!function_exists('esc_attr__')) {
	function esc_attr__($text, $domain = 'default')
	{
		unset($domain);
		return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
	}
}
if (!function_exists('esc_html')) {
	function esc_html($text)
	{
		return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
	}
}
if (!function_exists('esc_url')) {
	function esc_url($url)
	{
		return (string) $url;
	}
}
if (!function_exists('admin_url')) {
	function admin_url($path = '')
	{
		return 'https://example.test/wp-admin/' . ltrim((string) $path, '/');
	}
}
if (!function_exists('wp_die')) {
	function wp_die($message = '', $title = '', $args = array())
	{
		$response = is_array($args) && isset($args['response']) ? (int) $args['response'] : 500;
		throw new RuntimeException($response . ':' . strip_tags((string) $title) . ':' . strip_tags((string) $message));
	}
}
if (!function_exists('wp_enqueue_style')) {
	function wp_enqueue_style($handle, $src = '', $deps = array(), $version = false)
	{
		$GLOBALS['wla_admin_styles'][] = compact('handle', 'src', 'deps', 'version');
		return true;
	}
}
if (!function_exists('get_current_screen')) {
	function get_current_screen()
	{
		return $GLOBALS['wla_current_screen'] ?? null;
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
require_once $root . 'Admin/Assets.php';
require_once $root . 'Admin/ContextHelp.php';

use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Admin\Assets;
use WLA\Inmo\Admin\ContextHelp;
use WLA\Inmo\Admin\Menu;
use WLA\Inmo\Admin\ScreenRegistry;
use WLA\Inmo\Properties\Capabilities as PropertyCapabilities;
use WLA\Inmo\Properties\PostType;

function wlaAdminExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$definitions = ScreenRegistry::definitions();
$slugs = array_column($definitions, 'slug');

wlaAdminExpect(count($definitions) === 16, 'The documented Phase 2 admin shell must expose 16 registered sections/native links.');
wlaAdminExpect(count($slugs) === count(array_unique($slugs)), 'Admin screen slugs must be unique.');
wlaAdminExpect($definitions['dashboard']['capability'] === AccessCapabilities::VIEW_DASHBOARD, 'Dashboard capability changed unexpectedly.');
wlaAdminExpect($definitions['settings']['capability'] === AccessCapabilities::MANAGE_SETTINGS, 'Settings must use WLA settings capability.');
wlaAdminExpect($definitions['tools']['capability'] === AccessCapabilities::MANAGE_TOOLS, 'Tools must remain separately restricted.');
wlaAdminExpect($definitions['properties']['kind'] === 'native', 'Property list must remain a WordPress-owned native screen.');
wlaAdminExpect($definitions['new_property']['kind'] === 'native', 'New property must remain a WordPress-owned native screen.');
wlaAdminExpect($definitions['properties']['slug'] === 'edit.php?post_type=wla_property', 'Property native URL contract changed unexpectedly.');
wlaAdminExpect($definitions['new_property']['slug'] === 'post-new.php?post_type=wla_property', 'New property native URL contract changed unexpectedly.');
wlaAdminExpect($definitions['properties']['capability'] === PropertyCapabilities::EDIT_POSTS, 'Property link must use WLA property capability.');
wlaAdminExpect(PostType::arguments()['show_in_menu'] === ScreenRegistry::ROOT_SLUG, 'wla_property must be nested under WLA Inmo instead of creating a second top-level menu.');

$GLOBALS['wla_admin_caps'] = array(
	AccessCapabilities::VIEW_DASHBOARD,
	PropertyCapabilities::EDIT_POSTS,
	'upload_files',
);
$GLOBALS['wla_admin_menu'] = array();
$GLOBALS['wla_admin_submenu'] = array();
Menu::resetForTests();
Menu::register();

wlaAdminExpect(count($GLOBALS['wla_admin_menu']) === 1, 'Editor-capable user should receive one WLA Inmo root menu.');
$visibleSlugs = array_column($GLOBALS['wla_admin_submenu'], 'menuSlug');
wlaAdminExpect(!in_array('edit.php?post_type=wla_property', $visibleSlugs, true), 'WLA Menu must not duplicate the native property list submenu registered by WordPress.');
wlaAdminExpect(!in_array('post-new.php?post_type=wla_property', $visibleSlugs, true), 'WLA Menu must not duplicate the native new-property submenu registered by WordPress.');
wlaAdminExpect(in_array('wla-inmo-help', $visibleSlugs, true), 'Editor must see Help.');
wlaAdminExpect(in_array('wla-inmo-media', $visibleSlugs, true), 'Editor with upload_files must see Multimedia.');
wlaAdminExpect(!in_array('wla-inmo-settings', $visibleSlugs, true), 'Editor must not see Settings.');
wlaAdminExpect(!in_array('wla-inmo-tools', $visibleSlugs, true), 'Editor must not see Tools.');
wlaAdminExpect(!in_array('wla-inmo-import-export', $visibleSlugs, true), 'Editor must not see Import/Export.');

$_GET['page'] = 'wla-inmo-settings';
$denied = false;
try {
	Menu::renderCurrentPage();
} catch (RuntimeException $exception) {
	$denied = str_starts_with($exception->getMessage(), '403:');
}
wlaAdminExpect($denied, 'Direct URL access without the declared capability must be rejected with 403.');
unset($_GET['page']);

$nativeScreen = (object) array('post_type' => 'post');
$propertyScreen = (object) array('post_type' => PostType::POST_TYPE);
wlaAdminExpect(Assets::isWlaContext('edit.php', $nativeScreen) === false, 'WLA admin assets must not load on unrelated WordPress screens.');
wlaAdminExpect(Assets::isWlaContext('edit.php', $propertyScreen) === true, 'Property screens must load WLA admin assets.');
Assets::enqueue('toplevel_page_wla-inmo');
wlaAdminExpect(count($GLOBALS['wla_admin_styles']) === 1, 'WLA admin stylesheet should enqueue once on a registered WLA screen.');
wlaAdminExpect($GLOBALS['wla_admin_styles'][0]['handle'] === 'wla-inmo-admin', 'Admin stylesheet handle must remain namespaced.');

$helpScreen = new class {
	public string $post_type = 'wla_property';
	public array $tabs = array();
	public function add_help_tab(array $tab): void
	{
		$this->tabs[] = $tab;
	}
};
ContextHelp::add($helpScreen);
wlaAdminExpect(count($helpScreen->tabs) === 1, 'Property screens must expose contextual WLA Inmo help.');
wlaAdminExpect(($helpScreen->tabs[0]['id'] ?? '') === 'wla-inmo-context-help', 'Context help tab ID changed unexpectedly.');

echo "WLA Inmo admin shell smoke tests passed.\n";
