<?php

declare(strict_types=1);

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		return $text;
	}
}

if (!function_exists('register_taxonomy')) {
	function register_taxonomy($taxonomy, $objectType, $args = array())
	{
		$GLOBALS['wla_inmo_smoke_taxonomies'][$taxonomy] = array(
			'object_type' => $objectType,
			'args'        => $args,
		);

		return (object) array('name' => $taxonomy);
	}
}

require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Properties/PostType.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Taxonomies/Capabilities.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Taxonomies/Registry.php';

use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Taxonomies\Capabilities;
use WLA\Inmo\Taxonomies\Registry;

function wlaTaxonomySmokeExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$expectedKeys = array(
	'wla_operation',
	'wla_property_type',
	'wla_region',
	'wla_commune',
	'wla_sector',
);

wlaTaxonomySmokeExpect(Registry::keys() === $expectedKeys, 'Base taxonomy keys changed unexpectedly.');
wlaTaxonomySmokeExpect(!in_array('product_cat', Registry::keys(), true), 'WooCommerce product_cat must never be part of WLA Inmo taxonomy contract.');

$definitions = Registry::definitions();
$capabilities = Capabilities::map();

wlaTaxonomySmokeExpect(count($definitions) === 5, 'Exactly five base taxonomies are expected in Phase 1.3.');
wlaTaxonomySmokeExpect($definitions[Registry::PROPERTY_TYPE]['hierarchical'] === true, 'Property type must be hierarchical.');
wlaTaxonomySmokeExpect($definitions[Registry::OPERATION]['hierarchical'] === false, 'Operation must be flat.');
wlaTaxonomySmokeExpect($definitions[Registry::REGION]['hierarchical'] === false, 'Region must be flat.');
wlaTaxonomySmokeExpect($definitions[Registry::COMMUNE]['hierarchical'] === false, 'Commune must be flat.');
wlaTaxonomySmokeExpect($definitions[Registry::SECTOR]['hierarchical'] === false, 'Sector must be flat.');

foreach ($definitions as $taxonomy => $definition) {
	$args = Registry::arguments($taxonomy, $definition);

	wlaTaxonomySmokeExpect($args['public'] === true, "$taxonomy must be public.");
	wlaTaxonomySmokeExpect($args['publicly_queryable'] === true, "$taxonomy must be publicly queryable.");
	wlaTaxonomySmokeExpect($args['show_in_rest'] === true, "$taxonomy must be available through native WordPress REST.");
	wlaTaxonomySmokeExpect($args['show_admin_column'] === true, "$taxonomy must support an admin column.");
	wlaTaxonomySmokeExpect($args['capabilities'] === $capabilities, "$taxonomy must use WLA Inmo taxonomy capabilities.");
	wlaTaxonomySmokeExpect($args['rewrite']['with_front'] === false, "$taxonomy rewrite must not inherit blog front.");
	wlaTaxonomySmokeExpect($args['hierarchical'] === (bool) $definition['hierarchical'], "$taxonomy hierarchy mismatch.");
	wlaTaxonomySmokeExpect($args['rewrite']['hierarchical'] === (bool) $definition['hierarchical'], "$taxonomy rewrite hierarchy mismatch.");
}

wlaTaxonomySmokeExpect($definitions[Registry::OPERATION]['rewrite'] === 'operacion', 'Operation rewrite slug changed unexpectedly.');
wlaTaxonomySmokeExpect($definitions[Registry::PROPERTY_TYPE]['rewrite'] === 'tipo', 'Property type rewrite slug changed unexpectedly.');
wlaTaxonomySmokeExpect($definitions[Registry::REGION]['rewrite'] === 'region', 'Region rewrite slug changed unexpectedly.');
wlaTaxonomySmokeExpect($definitions[Registry::COMMUNE]['rewrite'] === 'comuna', 'Commune rewrite slug changed unexpectedly.');
wlaTaxonomySmokeExpect($definitions[Registry::SECTOR]['rewrite'] === 'sector', 'Sector rewrite slug changed unexpectedly.');

wlaTaxonomySmokeExpect(!in_array('manage_categories', Capabilities::all(), true), 'Generic category capability leaked into WLA taxonomy contract.');
wlaTaxonomySmokeExpect(Capabilities::ASSIGN_TERMS === 'assign_wla_property_terms', 'Assign capability contract changed unexpectedly.');

Registry::register();
$registered = $GLOBALS['wla_inmo_smoke_taxonomies'] ?? array();

wlaTaxonomySmokeExpect(count($registered) === 5, 'Registry::register() must register all five taxonomies.');

foreach ($expectedKeys as $taxonomy) {
	wlaTaxonomySmokeExpect(isset($registered[$taxonomy]), "$taxonomy was not registered.");
	wlaTaxonomySmokeExpect($registered[$taxonomy]['object_type'] === array(PostType::POST_TYPE), "$taxonomy must only attach to wla_property.");
}

echo "WLA Inmo taxonomy smoke tests passed.\n";
