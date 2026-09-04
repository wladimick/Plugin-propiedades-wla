<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Search\IndexSchema;
use WLA\Inmo\Settings\Repository as SettingsRepository;
use WLA\Inmo\Taxonomies\Registry as TaxonomyRegistry;

final class PropertyList
{
	private const FILTER_OPERATION = 'wla_filter_operation';
	private const FILTER_TYPE = 'wla_filter_type';
	private const FILTER_REGION = 'wla_filter_region';
	private const FILTER_COMMUNE = 'wla_filter_commune';
	private const FILTER_SECTOR = 'wla_filter_sector';
	private const FILTER_STATUS = 'wla_filter_status';
	private const FILTER_FEATURED = 'wla_filter_featured';
	private const INDEX_ALIAS = 'wla_property_idx';

	public static function register(): void
	{
		add_filter('manage_' . PostType::POST_TYPE . '_posts_columns', array(self::class, 'columns'));
		add_action('manage_' . PostType::POST_TYPE . '_posts_custom_column', array(self::class, 'renderColumn'), 10, 2);
		add_filter('manage_edit-' . PostType::POST_TYPE . '_sortable_columns', array(self::class, 'sortableColumns'));
		add_action('restrict_manage_posts', array(self::class, 'renderFilters'), 10, 2);
		add_action('pre_get_posts', array(self::class, 'prepareQuery'));
		add_filter('posts_join', array(self::class, 'filterJoin'), 10, 2);
		add_filter('posts_where', array(self::class, 'filterWhere'), 10, 2);
		add_filter('posts_search', array(self::class, 'filterSearch'), 10, 2);
		add_filter('posts_orderby', array(self::class, 'filterOrderBy'), 10, 2);
	}

	/**
	 * @param array<string,string> $columns Existing list-table columns.
	 * @return array<string,string>
	 */
	public static function columns(array $columns): array
	{
		$result = array();

		if (isset($columns['cb'])) {
			$result['cb'] = $columns['cb'];
		}

		$result['wla_thumbnail'] = __('Foto', 'wla-inmo');
		$result['title'] = $columns['title'] ?? __('Propiedad', 'wla-inmo');
		$result['wla_code'] = __('Código', 'wla-inmo');
		$result['wla_operation'] = __('Operación', 'wla-inmo');
		$result['wla_type'] = __('Tipo', 'wla-inmo');
		$result['wla_location'] = __('Ubicación', 'wla-inmo');
		$result['wla_price'] = __('Precio', 'wla-inmo');
		$result['wla_status'] = __('Estado', 'wla-inmo');
		$result['wla_featured'] = __('Destacada', 'wla-inmo');
		$result['wla_updated'] = __('Actualización', 'wla-inmo');

		return $result;
	}

	/**
	 * @param array<string,string> $columns Sortable columns.
	 * @return array<string,string>
	 */
	public static function sortableColumns(array $columns): array
	{
		$columns['wla_code'] = 'wla_code';
		$columns['wla_updated'] = 'modified';

		return $columns;
	}

	public static function renderColumn(string $column, int $postId): void
	{
		switch ($column) {
			case 'wla_thumbnail':
				self::renderThumbnail($postId);
				break;
			case 'wla_code':
				echo esc_html(self::metaText($postId, 'property_code', '—'));
				break;
			case 'wla_operation':
				echo esc_html(self::termNames($postId, TaxonomyRegistry::OPERATION));
				break;
			case 'wla_type':
				echo esc_html(self::termNames($postId, TaxonomyRegistry::PROPERTY_TYPE));
				break;
			case 'wla_location':
				echo esc_html(self::locationLabel($postId));
				break;
			case 'wla_price':
				echo esc_html(self::priceLabel($postId));
				break;
			case 'wla_status':
				echo esc_html(self::statusLabel($postId));
				break;
			case 'wla_featured':
				echo esc_html(self::booleanMeta($postId, 'featured') ? __('Sí', 'wla-inmo') : '—');
				break;
			case 'wla_updated':
				$modified = get_post_modified_time(get_option('date_format') . ' ' . get_option('time_format'), false, $postId);
				echo esc_html(is_string($modified) && $modified !== '' ? $modified : '—');
				break;
		}
	}

	public static function renderFilters(string $postType, string $which = 'top'): void
	{
		unset($which);

		if ($postType !== PostType::POST_TYPE || !current_user_can('edit_wla_properties')) {
			return;
		}

		self::taxonomyDropdown(TaxonomyRegistry::OPERATION, self::FILTER_OPERATION, __('Todas las operaciones', 'wla-inmo'));
		self::taxonomyDropdown(TaxonomyRegistry::PROPERTY_TYPE, self::FILTER_TYPE, __('Todos los tipos', 'wla-inmo'));
		self::taxonomyDropdown(TaxonomyRegistry::REGION, self::FILTER_REGION, __('Todas las regiones', 'wla-inmo'));
		self::taxonomyDropdown(TaxonomyRegistry::COMMUNE, self::FILTER_COMMUNE, __('Todas las comunas', 'wla-inmo'));
		self::taxonomyDropdown(TaxonomyRegistry::SECTOR, self::FILTER_SECTOR, __('Todos los sectores', 'wla-inmo'));
		self::statusDropdown();
		self::featuredDropdown();
	}

	public static function prepareQuery($query): void
	{
		if (!self::isPropertyAdminQuery($query)) {
			return;
		}

		$map = array(
			self::FILTER_OPERATION => 'operation_slug',
			self::FILTER_TYPE      => 'type_slug',
			self::FILTER_REGION    => 'region_slug',
			self::FILTER_COMMUNE   => 'commune_slug',
			self::FILTER_SECTOR    => 'sector_slug',
			self::FILTER_STATUS    => 'status',
		);

		foreach ($map as $requestKey => $queryKey) {
			$value = self::requestKey($requestKey);
			if ($value !== '') {
				$query->set('wla_inmo_' . $queryKey, $value);
			}
		}

		$featured = self::requestFeatured();
		if ($featured !== null) {
			$query->set('wla_inmo_featured', $featured);
		}

		$orderby = (string) $query->get('orderby');
		if ($orderby === 'wla_code') {
			$query->set('wla_inmo_orderby', 'property_code');
			$query->set('orderby', 'none');
		}
	}

	public static function filterJoin(string $join, $query): string
	{
		if (!self::needsIndex($query)) {
			return $join;
		}

		global $wpdb;
		if (!isset($wpdb)) {
			return $join;
		}

		$needle = ' ' . self::INDEX_ALIAS . ' ';
		if (strpos($join, $needle) !== false) {
			return $join;
		}

		$table = IndexSchema::tableName($wpdb);
		$join .= " LEFT JOIN {$table} " . self::INDEX_ALIAS . " ON {$wpdb->posts}.ID = " . self::INDEX_ALIAS . '.property_id';

		return $join;
	}

	public static function filterWhere(string $where, $query): string
	{
		if (!self::isPropertyAdminQuery($query)) {
			return $where;
		}

		global $wpdb;
		if (!isset($wpdb) || !method_exists($wpdb, 'prepare')) {
			return $where;
		}

		$columns = array(
			'operation_slug',
			'type_slug',
			'region_slug',
			'commune_slug',
			'sector_slug',
			'status',
		);

		foreach ($columns as $column) {
			$value = (string) $query->get('wla_inmo_' . $column);
			if ($value === '') {
				continue;
			}

			$where .= $wpdb->prepare(' AND ' . self::INDEX_ALIAS . ".{$column} = %s", $value);
		}

		$featured = $query->get('wla_inmo_featured');
		if ($featured === 0 || $featured === 1 || $featured === '0' || $featured === '1') {
			$where .= $wpdb->prepare(' AND ' . self::INDEX_ALIAS . '.featured = %d', (int) $featured);
		}

		return $where;
	}

	public static function filterSearch(string $search, $query): string
	{
		if (!self::isPropertyAdminQuery($query)) {
			return $search;
		}

		$term = trim((string) $query->get('s'));
		if ($term === '') {
			return $search;
		}

		global $wpdb;
		if (!isset($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'esc_like')) {
			return $search;
		}

		$like = '%' . $wpdb->esc_like($term) . '%';
		$indexSearch = $wpdb->prepare(
			'(' . self::INDEX_ALIAS . '.property_code LIKE %s OR ' . self::INDEX_ALIAS . '.external_id LIKE %s)',
			$like,
			$like
		);

		$native = preg_replace('/^\s*AND\s+/i', '', $search) ?? $search;
		$native = trim($native);

		if ($native === '') {
			return ' AND ' . $indexSearch;
		}

		return ' AND ((' . $native . ') OR ' . $indexSearch . ')';
	}

	public static function filterOrderBy(string $orderby, $query): string
	{
		if (!self::isPropertyAdminQuery($query) || $query->get('wla_inmo_orderby') !== 'property_code') {
			return $orderby;
		}

		$order = strtoupper((string) $query->get('order')) === 'DESC' ? 'DESC' : 'ASC';

		return self::INDEX_ALIAS . ".property_code {$order}";
	}

	public static function priceLabel(int $postId): string
	{
		if (self::booleanMeta($postId, 'hide_price')) {
			return __('Precio oculto', 'wla-inmo');
		}

		if (self::booleanMeta($postId, 'price_on_request')) {
			return __('A consultar', 'wla-inmo');
		}

		$currency = strtoupper(self::metaText($postId, 'currency_primary', ''));
		if ($currency === '') {
			$currency = strtoupper((string) SettingsRepository::get('currency_primary', 'CLP'));
		}

		$field = array(
			'CLP' => 'price_clp',
			'UF'  => 'price_uf',
			'USD' => 'price_usd',
		)[$currency] ?? '';

		if ($field === '') {
			return '—';
		}

		$value = self::metaRaw($postId, $field);
		if ($value === '' || $value === null || !is_numeric($value)) {
			return '—';
		}

		$number = (float) $value;
		switch ($currency) {
			case 'CLP':
				return '$' . number_format_i18n($number, 0);
			case 'UF':
				return 'UF ' . number_format_i18n($number, 2);
			case 'USD':
				return 'US$ ' . number_format_i18n($number, 2);
			default:
				return '—';
		}
	}

	public static function statusLabel(int $postId): string
	{
		$status = self::metaText($postId, 'status', '');
		if ($status === '') {
			return '—';
		}

		$known = array(
			'available'   => __('Disponible', 'wla-inmo'),
			'disponible'  => __('Disponible', 'wla-inmo'),
			'reserved'    => __('Reservada', 'wla-inmo'),
			'reservada'   => __('Reservada', 'wla-inmo'),
			'sold'        => __('Vendida', 'wla-inmo'),
			'vendida'     => __('Vendida', 'wla-inmo'),
			'rented'      => __('Arrendada', 'wla-inmo'),
			'arrendada'   => __('Arrendada', 'wla-inmo'),
			'unavailable' => __('No disponible', 'wla-inmo'),
		);

		if (isset($known[$status])) {
			return $known[$status];
		}

		return ucwords(str_replace(array('-', '_'), ' ', $status));
	}

	public static function isPropertyAdminQuery($query): bool
	{
		if (!is_admin() || !is_object($query) || !method_exists($query, 'is_main_query') || !$query->is_main_query()) {
			return false;
		}

		$postType = $query->get('post_type');

		return $postType === PostType::POST_TYPE;
	}

	private static function needsIndex($query): bool
	{
		if (!self::isPropertyAdminQuery($query)) {
			return false;
		}

		if (trim((string) $query->get('s')) !== '' || $query->get('wla_inmo_orderby') === 'property_code') {
			return true;
		}

		foreach (array('operation_slug', 'type_slug', 'region_slug', 'commune_slug', 'sector_slug', 'status', 'featured') as $key) {
			$value = $query->get('wla_inmo_' . $key);
			if ($value !== '' && $value !== null) {
				return true;
			}
		}

		return false;
	}

	private static function renderThumbnail(int $postId): void
	{
		if (!has_post_thumbnail($postId)) {
			echo '<span class="wla-inmo-property-list__no-image" aria-label="' . esc_attr__('Sin imagen', 'wla-inmo') . '">—</span>';
			return;
		}

		$image = get_the_post_thumbnail(
			$postId,
			array(72, 72),
			array(
				'class'   => 'wla-inmo-property-list__thumb',
				'loading' => 'lazy',
			)
		);
		echo wp_kses_post($image);
	}

	private static function locationLabel(int $postId): string
	{
		$parts = array();
		foreach (array(TaxonomyRegistry::REGION, TaxonomyRegistry::COMMUNE, TaxonomyRegistry::SECTOR) as $taxonomy) {
			$value = self::termNames($postId, $taxonomy);
			if ($value !== '—') {
				$parts[] = $value;
			}
		}

		return $parts === array() ? '—' : implode(' · ', $parts);
	}

	private static function termNames(int $postId, string $taxonomy): string
	{
		$terms = get_the_terms($postId, $taxonomy);
		if (is_wp_error($terms) || !is_array($terms) || $terms === array()) {
			return '—';
		}

		$names = array();
		foreach ($terms as $term) {
			if (is_object($term) && isset($term->name) && is_string($term->name) && $term->name !== '') {
				$names[] = $term->name;
			}
		}

		return $names === array() ? '—' : implode(', ', $names);
	}

	private static function taxonomyDropdown(string $taxonomy, string $name, string $label): void
	{
		$selected = self::requestKey($name);

		wp_dropdown_categories(
			array(
				'taxonomy'        => $taxonomy,
				'name'            => $name,
				'id'              => $name,
				'show_option_all' => $label,
				'hide_empty'      => false,
				'hierarchical'    => is_taxonomy_hierarchical($taxonomy),
				'value_field'     => 'slug',
				'show_count'      => false,
				'orderby'         => 'name',
				'selected'        => $selected,
			)
		);
	}

	private static function statusDropdown(): void
	{
		$current = self::requestKey(self::FILTER_STATUS);
		$statuses = self::distinctStatuses();
		if ($current !== '' && !in_array($current, $statuses, true)) {
			$statuses[] = $current;
		}

		echo '<select name="' . esc_attr(self::FILTER_STATUS) . '" id="' . esc_attr(self::FILTER_STATUS) . '">';
		echo '<option value="">' . esc_html__('Todos los estados', 'wla-inmo') . '</option>';
		foreach ($statuses as $status) {
			echo '<option value="' . esc_attr($status) . '"' . selected($current, $status, false) . '>' . esc_html(self::humanizeStatus($status)) . '</option>';
		}
		echo '</select>';
	}

	private static function featuredDropdown(): void
	{
		$current = self::requestFeatured();
		$value = $current === null ? '' : (string) $current;

		echo '<select name="' . esc_attr(self::FILTER_FEATURED) . '" id="' . esc_attr(self::FILTER_FEATURED) . '">';
		echo '<option value="">' . esc_html__('Todas: destacadas y normales', 'wla-inmo') . '</option>';
		echo '<option value="1"' . selected($value, '1', false) . '>' . esc_html__('Solo destacadas', 'wla-inmo') . '</option>';
		echo '<option value="0"' . selected($value, '0', false) . '>' . esc_html__('No destacadas', 'wla-inmo') . '</option>';
		echo '</select>';
	}

	/** @return array<int,string> */
	private static function distinctStatuses(): array
	{
		global $wpdb;
		if (!isset($wpdb)) {
			return array();
		}

		$table = IndexSchema::tableName($wpdb);
		$values = $wpdb->get_col("SELECT DISTINCT status FROM {$table} WHERE status IS NOT NULL AND status <> '' ORDER BY status ASC");
		if (!is_array($values)) {
			return array();
		}

		$statuses = array();
		foreach ($values as $value) {
			$status = sanitize_key((string) $value);
			if ($status !== '') {
				$statuses[] = $status;
			}
		}

		return array_values(array_unique($statuses));
	}

	private static function humanizeStatus(string $status): string
	{
		$known = array(
			'available'   => __('Disponible', 'wla-inmo'),
			'disponible'  => __('Disponible', 'wla-inmo'),
			'reserved'    => __('Reservada', 'wla-inmo'),
			'reservada'   => __('Reservada', 'wla-inmo'),
			'sold'        => __('Vendida', 'wla-inmo'),
			'vendida'     => __('Vendida', 'wla-inmo'),
			'rented'      => __('Arrendada', 'wla-inmo'),
			'arrendada'   => __('Arrendada', 'wla-inmo'),
			'unavailable' => __('No disponible', 'wla-inmo'),
		);

		return $known[$status] ?? ucwords(str_replace(array('-', '_'), ' ', $status));
	}

	private static function requestKey(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter state.
		$value = isset($_GET[$key]) ? wp_unslash($_GET[$key]) : '';

		return sanitize_key(is_scalar($value) ? (string) $value : '');
	}

	private static function requestFeatured(): ?int
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list-table filter state.
		$value = isset($_GET[self::FILTER_FEATURED]) ? wp_unslash($_GET[self::FILTER_FEATURED]) : '';
		$value = is_scalar($value) ? (string) $value : '';

		if ($value !== '0' && $value !== '1') {
			return null;
		}

		return (int) $value;
	}

	private static function metaRaw(int $postId, string $field)
	{
		$key = MetaSchema::metaKey($field);

		return $key === null ? null : get_post_meta($postId, $key, true);
	}

	private static function metaText(int $postId, string $field, string $fallback): string
	{
		$value = self::metaRaw($postId, $field);
		if (!is_scalar($value)) {
			return $fallback;
		}

		$value = trim((string) $value);

		return $value === '' ? $fallback : $value;
	}

	private static function booleanMeta(int $postId, string $field): bool
	{
		$value = self::metaRaw($postId, $field);

		return $value === true || $value === 1 || $value === '1' || $value === 'yes' || $value === 'true';
	}
}
