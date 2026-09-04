<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Quality\Repository as QualityRepository;
use WLA\Inmo\Quality\Schema as QualitySchema;

final class PropertyQualityList
{
	private const FILTER = 'wla_quality_filter';
	private const ALIAS = 'wla_quality_idx';

	/** @var array<int,array<string,mixed>> */
	private static array $rows = array();

	public static function register(): void
	{
		add_filter('manage_' . PostType::POST_TYPE . '_posts_columns', array(self::class, 'columns'), 20);
		add_action('manage_' . PostType::POST_TYPE . '_posts_custom_column', array(self::class, 'renderColumn'), 20, 2);
		add_action('restrict_manage_posts', array(self::class, 'renderFilter'), 20, 2);
		add_action('pre_get_posts', array(self::class, 'prepareQuery'), 20);
		add_filter('posts_join', array(self::class, 'filterJoin'), 20, 2);
		add_filter('posts_where', array(self::class, 'filterWhere'), 20, 2);
		add_filter('the_posts', array(self::class, 'primeRows'), 20, 2);
	}

	/**
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public static function columns(array $columns): array
	{
		$result = array();
		$inserted = false;

		foreach ($columns as $key => $label) {
			if ($key === 'wla_updated') {
				$result['wla_quality'] = __('Calidad', 'wla-inmo');
				$inserted = true;
			}
			$result[$key] = $label;
		}

		if (!$inserted) {
			$result['wla_quality'] = __('Calidad', 'wla-inmo');
		}

		return $result;
	}

	public static function renderColumn(string $column, int $postId): void
	{
		if ($column !== 'wla_quality') {
			return;
		}

		$row = self::$rows[$postId] ?? null;
		if (!is_array($row)) {
			echo '<span class="description">' . esc_html__('Pendiente', 'wla-inmo') . '</span>';
			return;
		}

		$score = max(0, min(100, (int) ($row['score'] ?? 0)));
		$total = max(0, (int) ($row['total_checks'] ?? 0));
		$passed = max(0, min($total, (int) ($row['passed_checks'] ?? 0)));
		$pending = max(0, $total - $passed);
		$isComplete = (int) ($row['is_complete'] ?? 0) === 1;

		echo '<strong>' . esc_html($score . '%') . '</strong><br>';
		if ($isComplete) {
			echo '<span class="description">' . esc_html__('Completa', 'wla-inmo') . '</span>';
			return;
		}

		printf(
			'<span class="description">%s</span>',
			esc_html(sprintf(_n('%d pendiente', '%d pendientes', $pending, 'wla-inmo'), $pending))
		);
	}

	public static function renderFilter(string $postType, string $which = 'top'): void
	{
		unset($which);

		if ($postType !== PostType::POST_TYPE || !current_user_can('edit_wla_properties')) {
			return;
		}

		$current = self::requestFilter();
		$options = array(
			'' => __('Toda la calidad', 'wla-inmo'),
			'incomplete' => __('Incompletas', 'wla-inmo'),
			'complete' => __('Completas', 'wla-inmo'),
			'no_price' => __('Sin precio', 'wla-inmo'),
			'no_image' => __('Sin imagen principal', 'wla-inmo'),
		);

		echo '<label class="screen-reader-text" for="wla-quality-filter">' . esc_html__('Filtrar por calidad', 'wla-inmo') . '</label>';
		echo '<select id="wla-quality-filter" name="' . esc_attr(self::FILTER) . '">';
		foreach ($options as $value => $label) {
			echo '<option value="' . esc_attr($value) . '"' . selected($current, $value, false) . '>' . esc_html($label) . '</option>';
		}
		echo '</select>';
	}

	public static function prepareQuery($query): void
	{
		if (!PropertyList::isPropertyAdminQuery($query)) {
			return;
		}

		$filter = self::requestFilter();
		if ($filter !== '') {
			$query->set('wla_inmo_quality_filter', $filter);
		}
	}

	public static function filterJoin(string $join, $query): string
	{
		if (!self::needsQualityJoin($query)) {
			return $join;
		}

		global $wpdb;
		if (!isset($wpdb)) {
			return $join;
		}

		$needle = ' ' . self::ALIAS . ' ';
		if (strpos($join, $needle) !== false) {
			return $join;
		}

		$table = QualitySchema::tableName($wpdb);

		return $join . " INNER JOIN {$table} " . self::ALIAS . " ON {$wpdb->posts}.ID = " . self::ALIAS . '.property_id';
	}

	public static function filterWhere(string $where, $query): string
	{
		if (!self::needsQualityJoin($query)) {
			return $where;
		}

		$filter = (string) $query->get('wla_inmo_quality_filter');
		$conditions = array(
			'incomplete' => self::ALIAS . '.is_complete = 0',
			'complete' => self::ALIAS . '.is_complete = 1',
			'no_price' => self::ALIAS . '.has_price = 0',
			'no_image' => self::ALIAS . '.has_image = 0',
		);

		return isset($conditions[$filter]) ? $where . ' AND ' . $conditions[$filter] : $where;
	}

	/**
	 * Prime quality rows for the current native list page in one query.
	 *
	 * @param array<int,mixed> $posts Query posts.
	 * @return array<int,mixed>
	 */
	public static function primeRows(array $posts, $query): array
	{
		if (!PropertyList::isPropertyAdminQuery($query)) {
			return $posts;
		}

		$ids = array();
		foreach ($posts as $post) {
			if (is_object($post) && isset($post->ID)) {
				$ids[] = (int) $post->ID;
			} elseif (is_numeric($post)) {
				$ids[] = (int) $post;
			}
		}

		self::$rows = (new QualityRepository())->findMany($ids);

		return $posts;
	}

	/**
	 * Test seam for verifying batch presentation without querying WordPress.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows keyed by property ID.
	 */
	public static function setRowsForTests(array $rows): void
	{
		self::$rows = $rows;
	}

	private static function needsQualityJoin($query): bool
	{
		return PropertyList::isPropertyAdminQuery($query)
			&& in_array((string) $query->get('wla_inmo_quality_filter'), array('incomplete', 'complete', 'no_price', 'no_image'), true);
	}

	private static function requestFilter(): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only filter, immediately normalized and whitelisted.
		$raw = isset($_GET[self::FILTER]) ? wp_unslash($_GET[self::FILTER]) : '';
		$value = is_scalar($raw) ? sanitize_key((string) $raw) : '';

		return in_array($value, array('incomplete', 'complete', 'no_price', 'no_image'), true) ? $value : '';
	}
}
