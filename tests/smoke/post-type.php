<?php

declare(strict_types=1);

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		return $text;
	}
}

if (!function_exists('_x')) {
	function _x($text, $context, $domain = 'default')
	{
		return $text;
	}
}

if (!function_exists('register_post_type')) {
	function register_post_type($postType, $args = array())
	{
		$GLOBALS['wla_inmo_smoke_registered_post_type'] = array($postType, $args);
		return (object) array('name' => $postType);
	}
}

require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Properties/Capabilities.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Properties/PostType.php';

use WLA\Inmo\Properties\Capabilities;
use WLA\Inmo\Properties\PostType;

function wlaPropertySmokeExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$args = PostType::arguments();
$caps = Capabilities::postTypeMap();

wlaPropertySmokeExpect(PostType::POST_TYPE === 'wla_property', 'Post type key must remain wla_property.');
wlaPropertySmokeExpect(PostType::ARCHIVE_SLUG === 'propiedades', 'Initial archive slug must be propiedades.');
wlaPropertySmokeExpect($args['public'] === true, 'Properties must be public.');
wlaPropertySmokeExpect($args['publicly_queryable'] === true, 'Properties must be publicly queryable.');
wlaPropertySmokeExpect($args['show_in_rest'] === true, 'Properties must support native WordPress REST/editor integration.');
wlaPropertySmokeExpect($args['rest_base'] === 'wla-properties', 'REST base must be stable and namespaced.');
wlaPropertySmokeExpect($args['has_archive'] === 'propiedades', 'Archive route must match the initial contract.');
wlaPropertySmokeExpect($args['rewrite']['slug'] === 'propiedades', 'Single property rewrite base must be propiedades.');
wlaPropertySmokeExpect($args['rewrite']['with_front'] === false, 'Property routes must not inherit the blog front prefix.');
wlaPropertySmokeExpect($args['delete_with_user'] === false, 'Deleting a user must not delete properties.');
wlaPropertySmokeExpect($args['map_meta_cap'] === true, 'Meta capabilities must be mapped by WordPress.');
wlaPropertySmokeExpect($args['capabilities'] === $caps, 'Post type must use the explicit WLA Inmo capability contract.');
wlaPropertySmokeExpect($args['supports'] === array('title', 'editor', 'excerpt', 'thumbnail', 'revisions'), 'Phase 1 supports contract changed unexpectedly.');
wlaPropertySmokeExpect($caps['edit_posts'] === 'edit_wla_properties', 'Generic post edit capability must not be used.');
wlaPropertySmokeExpect($caps['publish_posts'] === 'publish_wla_properties', 'Publishing must use WLA Inmo capability.');
wlaPropertySmokeExpect($caps['create_posts'] === 'edit_wla_properties', 'Creation must use the WLA Inmo edit collection capability.');
wlaPropertySmokeExpect(!in_array('edit_posts', Capabilities::all(), true), 'Generic WordPress post capability leaked into property contract.');

PostType::register();
$registered = $GLOBALS['wla_inmo_smoke_registered_post_type'] ?? null;

wlaPropertySmokeExpect(is_array($registered), 'PostType::register() must call register_post_type().');
wlaPropertySmokeExpect($registered[0] === PostType::POST_TYPE, 'Registered post type key is incorrect.');
wlaPropertySmokeExpect($registered[1]['capabilities'] === $caps, 'Registered capability mapping is incorrect.');

echo "WLA Inmo property post type smoke tests passed.\n";
