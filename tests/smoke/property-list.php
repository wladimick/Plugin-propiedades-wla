<?php

declare(strict_types=1);

$GLOBALS['wla_property_meta'] = array();

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
if (!function_exists('is_admin')) {
	function is_admin()
	{
		return true;
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
if (!function_exists('get_post_meta')) {
	function get_post_meta($postId, $key, $single = false)
	{
		unset($single);
		return $GLOBALS['wla_property_meta'][(int) $postId][(string) $key] ?? '';
	}
}
if (!function_exists('number_format_i18n')) {
	function number_format_i18n($number, $decimals = 0)
	{
		return number_format((float) $number, (int) $decimals, ',', '.');
	}
}

$root = dirname(__DIR__, 2) . '/plugin/wla-inmo/src/';
require_once $root . 'Properties/Sanitizer.php';
require_once $root . 'Properties/MetaSchema.php';
require_once $root . 'Properties/Capabilities.php';
require_once $root . 'Properties/PostType.php';
require_once $root . 'Taxonomies/Registry.php';
require_once $root . 'Search/IndexSchema.php';
require_once $root . 'Admin/PropertyList.php';

use WLA\Inmo\Admin\PropertyList;
use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;

final class WlaPropertyListSmokeQuery
{
	/** @var array<string,mixed> */
	private array $vars;

	/** @param array<string,mixed> $vars */
	public function __construct(array $vars)
	{
		$this->vars = $vars;
	}

	public function is_main_query(): bool
	{
		return true;
	}

	public function get(string $key)
	{
		return $this->vars[$key] ?? '';
	}

	public function set(string $key, $value): void
	{
		$this->vars[$key] = $value;
	}
}

final class WlaPropertyListSmokeDb
{
	public string $prefix = 'wp_';
	public string $posts = 'wp_posts';

	public function prepare(string $query, ...$values): string
	{
		foreach ($values as $value) {
			if (strpos($query, '%s') !== false) {
				$replacement = "'" . str_replace("'", "''", (string) $value) . "'";
				$query = preg_replace('/%s/', $replacement, $query, 1) ?? $query;
				continue;
			}

			if (strpos($query, '%d') !== false) {
				$query = preg_replace('/%d/', (string) (int) $value, $query, 1) ?? $query;
			}
		}

		return $query;
	}

	public function esc_like(string $value): string
	{
		return addcslashes($value, '_%\\');
	}
}

function wlaPropertyListExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$columns = PropertyList::columns(array('cb' => '<input>', 'title' => 'Title', 'date' => 'Date'));
wlaPropertyListExpect(array_keys($columns) === array('cb', 'wla_thumbnail', 'title', 'wla_code', 'wla_operation', 'wla_type', 'wla_location', 'wla_price', 'wla_status', 'wla_featured', 'wla_updated'), 'Professional property columns changed unexpectedly.');
wlaPropertyListExpect(!isset($columns['date']), 'Native date column should be replaced by modified date.');

$propertyId = 101;
$GLOBALS['wla_property_meta'][$propertyId] = array(
	MetaSchema::metaKey('currency_primary') => 'CLP',
	MetaSchema::metaKey('price_clp') => '390000000',
	MetaSchema::metaKey('price_on_request') => '0',
	MetaSchema::metaKey('hide_price') => '0',
	MetaSchema::metaKey('status') => 'available',
);
wlaPropertyListExpect(PropertyList::priceLabel($propertyId) === '$390.000.000', 'CLP price must use canonical price_clp and localized thousands.');
wlaPropertyListExpect(PropertyList::statusLabel($propertyId) === 'Disponible', 'Known commercial status must be humanized.');

$GLOBALS['wla_property_meta'][$propertyId][MetaSchema::metaKey('price_on_request')] = '1';
wlaPropertyListExpect(PropertyList::priceLabel($propertyId) === 'A consultar', 'Price-on-request must override numeric price.');
$GLOBALS['wla_property_meta'][$propertyId][MetaSchema::metaKey('hide_price')] = '1';
wlaPropertyListExpect(PropertyList::priceLabel($propertyId) === 'Precio oculto', 'Explicit hide_price must override price-on-request.');

$_GET = array(
	'wla_filter_operation' => 'Venta<script>',
	'wla_filter_region' => 'maule',
	'wla_filter_featured' => '1',
);
$query = new WlaPropertyListSmokeQuery(
	array(
		'post_type' => PostType::POST_TYPE,
		'orderby' => 'wla_code',
		'order' => 'DESC',
		's' => '001254',
	)
);
PropertyList::prepareQuery($query);
wlaPropertyListExpect($query->get('wla_inmo_operation_slug') === 'ventascript', 'Operation filter must be sanitize_key normalized.');
wlaPropertyListExpect($query->get('wla_inmo_region_slug') === 'maule', 'Region filter was not transferred to the scoped query.');
wlaPropertyListExpect($query->get('wla_inmo_featured') === 1, 'Featured filter must be strict boolean-like input.');
wlaPropertyListExpect($query->get('wla_inmo_orderby') === 'property_code', 'Code sorting marker missing.');
wlaPropertyListExpect($query->get('orderby') === 'none', 'Native unknown orderby must be neutralized.');

global $wpdb;
$wpdb = new WlaPropertyListSmokeDb();
$join = PropertyList::filterJoin('', $query);
wlaPropertyListExpect(str_contains($join, 'LEFT JOIN wp_wla_property_index wla_property_idx'), 'Property filters/search must join the indexed projection.');
wlaPropertyListExpect(str_contains($join, 'wp_posts.ID = wla_property_idx.property_id'), 'Index join must be scoped by property ID.');

$where = PropertyList::filterWhere(' WHERE 1=1', $query);
wlaPropertyListExpect(str_contains($where, "wla_property_idx.operation_slug = 'ventascript'"), 'Operation filter must be prepared into indexed WHERE.');
wlaPropertyListExpect(str_contains($where, "wla_property_idx.region_slug = 'maule'"), 'Region filter must be prepared into indexed WHERE.');
wlaPropertyListExpect(str_contains($where, 'wla_property_idx.featured = 1'), 'Featured filter must be integer constrained.');

$search = PropertyList::filterSearch(" AND ((wp_posts.post_title LIKE '%001254%'))", $query);
wlaPropertyListExpect(str_contains($search, 'wla_property_idx.property_code LIKE'), 'Admin search must include public property code.');
wlaPropertyListExpect(str_contains($search, 'wla_property_idx.external_id LIKE'), 'Admin search may include internal external_id without rendering it.');
wlaPropertyListExpect(str_starts_with($search, ' AND ('), 'Expanded search must preserve WHERE grouping.');

$orderby = PropertyList::filterOrderBy('', $query);
wlaPropertyListExpect($orderby === 'wla_property_idx.property_code DESC', 'Code sorting must use a whitelisted index column and order.');

$otherQuery = new WlaPropertyListSmokeQuery(array('post_type' => 'post', 's' => '001254'));
wlaPropertyListExpect(PropertyList::filterJoin('ORIGINAL', $otherQuery) === 'ORIGINAL', 'Property list filters must not affect other post types.');
wlaPropertyListExpect(PropertyList::filterWhere(' WHERE native=1', $otherQuery) === ' WHERE native=1', 'Property list WHERE must not affect other post types.');

$_GET = array();
echo "WLA Inmo professional property list smoke tests passed.\n";
