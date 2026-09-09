<?php

declare(strict_types=1);

$GLOBALS['wla_frontend_child'] = sys_get_temp_dir() . '/wla-inmo-child-' . bin2hex(random_bytes(4));
$GLOBALS['wla_frontend_parent'] = sys_get_temp_dir() . '/wla-inmo-parent-' . bin2hex(random_bytes(4));
$GLOBALS['wla_frontend_filter_callbacks'] = array();
$GLOBALS['wla_frontend_hooks'] = array();
$GLOBALS['wla_frontend_styles'] = array();
$GLOBALS['wla_frontend_context'] = array(
	'archive' => false,
	'single'  => false,
	'admin'   => false,
	'ajax'    => false,
	'feed'    => false,
);

mkdir($GLOBALS['wla_frontend_child'] . '/wla-inmo', 0777, true);
mkdir($GLOBALS['wla_frontend_parent'] . '/wla-inmo', 0777, true);

if (!defined('WLA_INMO_DIR')) {
	define('WLA_INMO_DIR', dirname(__DIR__, 2) . '/plugin/wla-inmo/');
}
if (!defined('WLA_INMO_URL')) {
	define('WLA_INMO_URL', 'https://example.test/wp-content/plugins/wla-inmo/');
}
if (!defined('WLA_INMO_VERSION')) {
	define('WLA_INMO_VERSION', '0.1.0-alpha');
}

function apply_filters($tag, $value, ...$args)
{
	$callback = $GLOBALS['wla_frontend_filter_callbacks'][$tag] ?? null;
	if (is_callable($callback)) {
		return $callback($value, ...$args);
	}

	return $value;
}

function locate_template($template_names, $load = false, $load_once = true, $args = array())
{
	unset($load, $load_once, $args);
	foreach ((array) $template_names as $template_name) {
		foreach (array($GLOBALS['wla_frontend_child'], $GLOBALS['wla_frontend_parent']) as $root) {
			$path = $root . '/' . ltrim((string) $template_name, '/');
			if (is_file($path)) {
				return $path;
			}
		}
	}

	return '';
}

function get_stylesheet_directory()
{
	return $GLOBALS['wla_frontend_child'];
}

function get_template_directory()
{
	return $GLOBALS['wla_frontend_parent'];
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
{
	$GLOBALS['wla_frontend_hooks'][] = array('filter', $hook, $callback, $priority, $accepted_args);
	return true;
}

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
	$GLOBALS['wla_frontend_hooks'][] = array('action', $hook, $callback, $priority, $accepted_args);
	return true;
}

function is_post_type_archive($post_types = '')
{
	unset($post_types);
	return $GLOBALS['wla_frontend_context']['archive'];
}

function is_singular($post_types = '')
{
	unset($post_types);
	return $GLOBALS['wla_frontend_context']['single'];
}

function is_admin()
{
	return $GLOBALS['wla_frontend_context']['admin'];
}

function wp_doing_ajax()
{
	return $GLOBALS['wla_frontend_context']['ajax'];
}

function is_feed()
{
	return $GLOBALS['wla_frontend_context']['feed'];
}

function wp_enqueue_style($handle, $src = '', $deps = array(), $version = false, $media = 'all')
{
	$GLOBALS['wla_frontend_styles'][] = array($handle, $src, $deps, $version, $media);
	return true;
}

$root = dirname(__DIR__, 2) . '/plugin/wla-inmo/src/';
require_once $root . 'Properties/PostType.php';
require_once $root . 'Frontend/TemplateResolver.php';
require_once $root . 'Frontend/Assets.php';
require_once $root . 'Frontend/Bootstrap.php';

use WLA\Inmo\Frontend\Assets;
use WLA\Inmo\Frontend\Bootstrap;
use WLA\Inmo\Frontend\TemplateResolver;

function wlaFrontendExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

function wlaFrontendResetContext(): void
{
	$GLOBALS['wla_frontend_context'] = array(
		'archive' => false,
		'single'  => false,
		'admin'   => false,
		'ajax'    => false,
		'feed'    => false,
	);
}

$pluginArchive = realpath(WLA_INMO_DIR . 'templates/archive-property.php');
wlaFrontendExpect(is_string($pluginArchive), 'Plugin archive fallback is missing.');
wlaFrontendExpect(TemplateResolver::locate('archive-property.php') === $pluginArchive, 'Plugin archive fallback did not resolve.');
wlaFrontendExpect(TemplateResolver::locate('../archive-property.php') === null, 'Traversal template name was accepted.');
wlaFrontendExpect(TemplateResolver::locate('unknown.php') === null, 'Non-allowlisted template was accepted.');

$parentArchive = $GLOBALS['wla_frontend_parent'] . '/wla-inmo/archive-property.php';
$childArchive = $GLOBALS['wla_frontend_child'] . '/wla-inmo/archive-property.php';
file_put_contents($parentArchive, "<?php // parent override\n");
file_put_contents($childArchive, "<?php // child override\n");

wlaFrontendExpect(TemplateResolver::locate('archive-property.php') === realpath($childArchive), 'Child theme override must have first precedence.');
unlink($childArchive);
wlaFrontendExpect(TemplateResolver::locate('archive-property.php') === realpath($parentArchive), 'Parent theme override must precede plugin fallback.');
unlink($parentArchive);
wlaFrontendExpect(TemplateResolver::locate('archive-property.php') === $pluginArchive, 'Plugin fallback must be used after theme overrides disappear.');

$outside = sys_get_temp_dir() . '/wla-inmo-outside-' . bin2hex(random_bytes(4)) . '.php';
file_put_contents($outside, "<?php // outside root\n");
$GLOBALS['wla_frontend_filter_callbacks']['wla_inmo_template_path'] = static fn ($path) => $outside;
wlaFrontendExpect(TemplateResolver::locate('archive-property.php') === null, 'Filtered path escaped approved template roots.');
unset($GLOBALS['wla_frontend_filter_callbacks']['wla_inmo_template_path']);

$GLOBALS['wla_frontend_filter_callbacks']['wla_inmo_template_candidates'] = static fn () => array('../outside.php');
wlaFrontendExpect(TemplateResolver::locate('archive-property.php') === $pluginArchive, 'Unsafe filtered candidate must not escape to the filesystem.');
unset($GLOBALS['wla_frontend_filter_callbacks']['wla_inmo_template_candidates']);
unlink($outside);

Bootstrap::register();
$registeredHooks = array_map(static fn ($row) => $row[1], $GLOBALS['wla_frontend_hooks']);
wlaFrontendExpect(in_array('template_include', $registeredHooks, true), 'Frontend template_include hook was not registered.');
wlaFrontendExpect(in_array('wp_enqueue_scripts', $registeredHooks, true), 'Frontend asset hook was not registered.');

wlaFrontendResetContext();
$currentTemplate = '/theme/index.php';
wlaFrontendExpect(Bootstrap::filterTemplate($currentTemplate) === $currentTemplate, 'Unrelated request template was intercepted.');

$GLOBALS['wla_frontend_context']['archive'] = true;
wlaFrontendExpect(Bootstrap::filterTemplate($currentTemplate) === $pluginArchive, 'Property archive did not use WLA fallback.');

$GLOBALS['wla_frontend_context']['feed'] = true;
wlaFrontendExpect(Bootstrap::filterTemplate($currentTemplate) === $currentTemplate, 'Feed request was intercepted.');

wlaFrontendResetContext();
$GLOBALS['wla_frontend_styles'] = array();
Assets::enqueue();
wlaFrontendExpect($GLOBALS['wla_frontend_styles'] === array(), 'Frontend assets loaded on unrelated request.');

$GLOBALS['wla_frontend_context']['single'] = true;
Assets::enqueue();
wlaFrontendExpect(count($GLOBALS['wla_frontend_styles']) === 1, 'Frontend stylesheet was not loaded on WLA single.');
wlaFrontendExpect($GLOBALS['wla_frontend_styles'][0][0] === Assets::STYLE_HANDLE, 'Unexpected frontend stylesheet handle.');
wlaFrontendExpect($GLOBALS['wla_frontend_styles'][0][2] === array(), 'Frontend stylesheet must not introduce dependencies.');

$css = file_get_contents(WLA_INMO_DIR . 'assets/css/frontend.css');
wlaFrontendExpect(is_string($css) && $css !== '', 'Frontend CSS is missing.');
wlaFrontendExpect(!preg_match('/(^|})\s*(html|body|h[1-6]|a|button|input|select|textarea|\*)\b/im', $css), 'Frontend CSS contains an unscoped global selector.');
wlaFrontendExpect(stripos($css, 'jquery') === false, 'Frontend CSS references jQuery.');
wlaFrontendExpect(!is_file(WLA_INMO_DIR . 'assets/js/frontend.js'), 'PR 4.1 must not ship an empty frontend JS file.');

foreach (array('archive-property.php', 'single-property.php') as $templateFile) {
	$source = file_get_contents(WLA_INMO_DIR . 'templates/' . $templateFile);
	wlaFrontendExpect(is_string($source), "Unable to read {$templateFile}.");
	wlaFrontendExpect(!preg_match('/private_address|internal_notes|external_id|get_post_meta/i', $source), "Private or arbitrary meta access found in {$templateFile}.");
}

@rmdir($GLOBALS['wla_frontend_child'] . '/wla-inmo');
@rmdir($GLOBALS['wla_frontend_child']);
@rmdir($GLOBALS['wla_frontend_parent'] . '/wla-inmo');
@rmdir($GLOBALS['wla_frontend_parent']);

echo "WLA Inmo frontend foundation smoke tests passed.\n";
