<?php

declare(strict_types=1);

$GLOBALS['wla_index_posts'] = array(
	101 => array('type' => 'wla_property', 'status' => 'publish', 'modified' => '2026-09-03 23:50:00'),
	102 => array('type' => 'wla_property', 'status' => 'publish', 'modified' => '2026-09-03 23:51:00'),
	103 => array('type' => 'wla_property', 'status' => 'draft', 'modified' => '2026-09-03 23:52:00'),
);
$GLOBALS['wla_index_meta'] = array(
	101 => array(
		'_wla_inmo_property_code' => 'COD-101',
		'_wla_inmo_external_id' => 'feed-101',
		'_wla_inmo_status' => 'disponible',
		'_wla_inmo_price_clp' => '390000000',
		'_wla_inmo_price_uf' => '9500.50',
		'_wla_inmo_price_usd' => '420000',
		'_wla_inmo_bedrooms' => '3',
		'_wla_inmo_bathrooms' => '2',
		'_wla_inmo_parking' => '1',
		'_wla_inmo_land_area_m2' => '1610.5',
		'_wla_inmo_built_area_m2' => '180',
		'_wla_inmo_latitude' => '-34.9828',
		'_wla_inmo_longitude' => '-71.2394',
		'_wla_inmo_featured' => '1',
	),
	102 => array(
		'_wla_inmo_property_code' => 'COD-101',
		'_wla_inmo_status' => 'disponible',
	),
);
$GLOBALS['wla_index_terms'] = array(
	101 => array(
		'wla_operation' => array('venta'),
		'wla_property_type' => array('casa'),
		'wla_region' => array('maule'),
		'wla_commune' => array('curico'),
		'wla_sector' => array('boldo'),
	),
);

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		return $text;
	}
}

if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value)
	{
		return trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $value)));
	}
}

if (!function_exists('sanitize_key')) {
	function sanitize_key($value)
	{
		return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)) ?? '';
	}
}

if (!function_exists('get_post_type')) {
	function get_post_type($postId)
	{
		return $GLOBALS['wla_index_posts'][(int) $postId]['type'] ?? null;
	}
}

if (!function_exists('get_post_status')) {
	function get_post_status($postId)
	{
		return $GLOBALS['wla_index_posts'][(int) $postId]['status'] ?? null;
	}
}

if (!function_exists('get_post_meta')) {
	function get_post_meta($postId, $key, $single = false)
	{
		unset($single);
		return $GLOBALS['wla_index_meta'][(int) $postId][$key] ?? '';
	}
}

if (!function_exists('wp_get_object_terms')) {
	function wp_get_object_terms($postId, $taxonomy, $args = array())
	{
		unset($args);
		return $GLOBALS['wla_index_terms'][(int) $postId][$taxonomy] ?? array();
	}
}

if (!function_exists('is_wp_error')) {
	function is_wp_error($value)
	{
		return false;
	}
}

if (!function_exists('get_post_modified_time')) {
	function get_post_modified_time($format, $gmt, $postId)
	{
		unset($format, $gmt);
		return $GLOBALS['wla_index_posts'][(int) $postId]['modified'] ?? '';
	}
}

if (!function_exists('do_action')) {
	function do_action($hook, ...$args)
	{
		$GLOBALS['wla_index_actions'][] = array($hook, $args);
	}
}

if (!function_exists('add_action')) {
	function add_action($hook, $callback, $priority = 10, $acceptedArgs = 1)
	{
		$GLOBALS['wla_index_hooks'][$hook][] = array($callback, $priority, $acceptedArgs);
		return true;
	}
}

if (!function_exists('get_posts')) {
	function get_posts($args)
	{
		$page = (int) ($args['paged'] ?? 1);
		$perPage = (int) ($args['posts_per_page'] ?? 100);
		$ids = array();
		foreach ($GLOBALS['wla_index_posts'] as $id => $post) {
			if ($post['type'] === 'wla_property' && $post['status'] === 'publish') {
				$ids[] = (int) $id;
			}
		}
		sort($ids);
		return array_slice($ids, ($page - 1) * $perPage, $perPage);
	}
}

require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Properties/PostType.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Properties/Sanitizer.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Properties/MetaSchema.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Taxonomies/Capabilities.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Taxonomies/Registry.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Search/IndexSchema.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Search/Projection.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Search/IndexRepository.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Search/Indexer.php';
require_once dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Search/Rebuilder.php';

use WLA\Inmo\Search\IndexRepository;
use WLA\Inmo\Search\Indexer;
use WLA\Inmo\Search\IndexSchema;
use WLA\Inmo\Search\Projection;
use WLA\Inmo\Search\Rebuilder;

function wlaIndexExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

final class WlaIndexFakeDb
{
	public string $prefix = 'wp_';
	public array $rows = array();
	public array $queries = array();

	public function get_charset_collate(): string
	{
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	public function prepare($query, ...$args): string
	{
		return serialize(array($query, $args));
	}

	public function get_var($prepared)
	{
		[$query, $args] = unserialize((string) $prepared, array('allowed_classes' => false));

		if (str_contains($query, 'property_code = %s')) {
			$code = (string) ($args[0] ?? '');
			foreach ($this->rows as $id => $row) {
				if (($row['property_code'] ?? null) === $code) {
					return $id;
				}
			}
			return null;
		}

		if (str_contains($query, 'property_id = %d')) {
			$id = (int) ($args[0] ?? 0);
			return isset($this->rows[$id]) ? $id : null;
		}

		return null;
	}

	public function update($table, $data, $where, $formats = null, $whereFormats = null)
	{
		unset($table, $formats, $whereFormats);
		$id = (int) ($where['property_id'] ?? 0);
		if (!isset($this->rows[$id])) {
			return 0;
		}
		$this->rows[$id] = $data;
		return 1;
	}

	public function insert($table, $data, $formats = null)
	{
		unset($table, $formats);
		$id = (int) ($data['property_id'] ?? 0);
		if ($id < 1 || isset($this->rows[$id])) {
			return false;
		}
		$this->rows[$id] = $data;
		return 1;
	}

	public function delete($table, $where, $whereFormats = null)
	{
		unset($table, $whereFormats);
		$id = (int) ($where['property_id'] ?? 0);
		if (isset($this->rows[$id])) {
			unset($this->rows[$id]);
			return 1;
		}
		return 0;
	}

	public function query($query)
	{
		$this->queries[] = $query;
		if (str_starts_with((string) $query, 'DELETE FROM ')) {
			$count = count($this->rows);
			$this->rows = array();
			return $count;
		}
		return 0;
	}
}

$db = new WlaIndexFakeDb();
$sql = IndexSchema::sql($db);
wlaIndexExpect(IndexSchema::tableName($db) === 'wp_wla_property_index', 'Index table name must use the WordPress prefix.');
wlaIndexExpect(str_contains($sql, 'PRIMARY KEY  (property_id)'), 'Index table must use property_id as primary key.');
wlaIndexExpect(str_contains($sql, 'UNIQUE KEY property_code (property_code)'), 'Property code uniqueness must be enforced when present.');
wlaIndexExpect(str_contains($sql, 'KEY operation_status (operation_slug,status)'), 'Operation/status compound index is required.');
wlaIndexExpect(!str_contains(strtoupper($sql), 'FOREIGN KEY'), 'Derived index must not introduce fragile foreign keys.');

$row = Projection::fromProperty(101);
wlaIndexExpect(is_array($row), 'Published property must generate a projection.');
wlaIndexExpect($row['property_id'] === 101, 'Projection property ID mismatch.');
wlaIndexExpect($row['property_code'] === 'COD-101', 'Canonical property code must project.');
wlaIndexExpect($row['operation_slug'] === 'venta', 'Operation taxonomy must project.');
wlaIndexExpect($row['type_slug'] === 'casa', 'Property type taxonomy must project.');
wlaIndexExpect($row['commune_slug'] === 'curico', 'Commune taxonomy must project.');
wlaIndexExpect($row['price_clp'] === 390000000, 'CLP price must normalize to integer.');
wlaIndexExpect($row['price_uf'] === 9500.5, 'UF price must normalize to number.');
wlaIndexExpect($row['featured'] === 1, 'Featured flag must normalize to 1.');
wlaIndexExpect($row['updated_at'] === '2026-09-03 23:50:00', 'Modified timestamp must project deterministically.');
wlaIndexExpect(Projection::fromProperty(103) === null, 'Draft property must not belong to public search index.');
wlaIndexExpect(Projection::fromProperty(999) === null, 'Unknown post must not project.');

$repository = new IndexRepository($db);
wlaIndexExpect($repository->upsert($row) === true, 'First projection insert must succeed.');
wlaIndexExpect(isset($db->rows[101]), 'Inserted property must exist in fake index.');
wlaIndexExpect($repository->exists(101) === true, 'Repository exists() must find indexed property.');
wlaIndexExpect($repository->findPropertyIdByCode('COD-101') === 101, 'Repository must resolve property code owner.');

$duplicate = $row;
$duplicate['property_id'] = 102;
wlaIndexExpect($repository->upsert($duplicate) === false, 'Duplicate property code must be rejected, not REPLACEd.');
wlaIndexExpect(isset($db->rows[101]) && !isset($db->rows[102]), 'Duplicate code must never evict original index row.');
wlaIndexExpect(($GLOBALS['wla_index_actions'][0][0] ?? '') === 'wla_inmo_index_property_code_conflict', 'Duplicate code conflict must emit an audit hook.');

$reordered = array_reverse($row, true);
wlaIndexExpect($repository->upsert($reordered) === true, 'Repository must accept complete projection independent of key order.');
wlaIndexExpect($repository->delete(101) === true, 'Deleting an indexed row must succeed.');
wlaIndexExpect($repository->delete(101) === true, 'Deleting an already absent derived row must remain idempotent.');

$repository->upsert($row);
wlaIndexExpect($repository->clear() === true && $db->rows === array(), 'Derived index reset must clear only index rows.');

Indexer::setRepository($repository);
Indexer::register();
wlaIndexExpect(isset($GLOBALS['wla_index_hooks']['shutdown']), 'Indexer must register one shutdown flush hook.');
wlaIndexExpect(isset($GLOBALS['wla_index_hooks']['updated_post_meta']), 'Indexer must listen for canonical meta changes.');
wlaIndexExpect(isset($GLOBALS['wla_index_hooks']['set_object_terms']), 'Indexer must listen for taxonomy changes.');

// deleted_post_meta passes an array of IDs; this must never trigger a PHP type error.
Indexer::onMetaChanged(array(77, 78), 101, '_wla_inmo_price_clp', null);
Indexer::flush();
wlaIndexExpect(isset($db->rows[101]), 'Canonical meta change must reindex property at most once per flush.');

$GLOBALS['wla_index_posts'][101]['status'] = 'draft';
Indexer::mark(101);
Indexer::flush();
wlaIndexExpect(!isset($db->rows[101]), 'Unpublishing a property must remove it from the public index.');
$GLOBALS['wla_index_posts'][101]['status'] = 'publish';

$result = Rebuilder::batch(1, 1);
wlaIndexExpect($result['processed'] === 1 && $result['done'] === false && $result['next_page'] === 2, 'Rebuild batch must be resumable.');
$result = Rebuilder::batch(2, 1);
wlaIndexExpect($result['processed'] === 1 && $result['done'] === false && $result['next_page'] === 3, 'Second rebuild page must remain deterministic.');
$result = Rebuilder::batch(3, 1);
wlaIndexExpect($result['processed'] === 0 && $result['done'] === true && $result['next_page'] === null, 'Empty rebuild page must mark rebuild complete.');

echo "WLA Inmo search index smoke tests passed.\n";
