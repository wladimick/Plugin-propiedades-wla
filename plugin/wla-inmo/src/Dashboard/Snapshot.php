<?php

namespace WLA\Inmo\Dashboard;

use WLA\Inmo\Activity\Repository as ActivityRepository;
use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Quality\Schema as QualitySchema;
use WLA\Inmo\Taxonomies\Registry as TaxonomyRegistry;

final class Snapshot
{
	private const ACTIVE_STATUSES = array('publish', 'draft', 'pending', 'private', 'future');
	private const ATTENTION_LIMIT = 6;
	private const ACTIVITY_LIMIT = 6;

	private $wpdb;

	public function __construct($database = null)
	{
		if ($database === null) {
			global $wpdb;
			$database = $wpdb ?? null;
		}

		$this->wpdb = $database;
	}

	/**
	 * Build a bounded, read-only dashboard snapshot.
	 *
	 * No private property fields or contact values are selected here.
	 * Activity is optional so capability checks can happen before the query.
	 *
	 * @return array<string,mixed>
	 */
	public function build(bool $includeActivity = false): array
	{
		if ($this->wpdb === null) {
			return self::emptySnapshot();
		}

		$postCounts = $this->postCounts();
		$meta = $this->metaDistributions();
		$quality = $this->qualitySummary();

		return array(
			'properties' => $postCounts,
			'operations' => $this->operationDistribution(),
			'commercial_statuses' => $meta['commercial_statuses'],
			'featured' => $meta['featured'],
			'quality' => $quality,
			'attention' => $this->attentionRows(self::ATTENTION_LIMIT),
			'activity' => $includeActivity ? ActivityRepository::recent(self::ACTIVITY_LIMIT) : array(),
		);
	}

	/** @return array<string,mixed> */
	public static function emptySnapshot(): array
	{
		return array(
			'properties' => array(
				'total' => 0,
				'published' => 0,
				'draft' => 0,
				'pending' => 0,
				'private' => 0,
				'future' => 0,
				'recently_updated' => 0,
			),
			'operations' => array(),
			'commercial_statuses' => array(),
			'featured' => 0,
			'quality' => array(
				'total' => 0,
				'average_score' => 0,
				'complete' => 0,
				'incomplete' => 0,
				'no_price' => 0,
				'no_image' => 0,
				'no_location' => 0,
				'not_verified' => 0,
			),
			'attention' => array(),
			'activity' => array(),
		);
	}

	/** @return array<string,int> */
	private function postCounts(): array
	{
		$counts = self::emptySnapshot()['properties'];
		$cutoff = gmdate('Y-m-d H:i:s', strtotime('-7 days'));
		$statuses = self::ACTIVE_STATUSES;
		$placeholders = implode(',', array_fill(0, count($statuses), '%s'));
		$sql = $this->wpdb->prepare(
			"SELECT post_status, COUNT(*) AS total,
			SUM(CASE WHEN post_modified_gmt >= %s THEN 1 ELSE 0 END) AS recent
			FROM {$this->wpdb->posts}
			WHERE post_type = %s AND post_status IN ({$placeholders})
			GROUP BY post_status",
			...array_merge(array($cutoff, PostType::POST_TYPE), $statuses)
		);
		$rows = $this->wpdb->get_results($sql, ARRAY_A);

		if (!is_array($rows)) {
			return $counts;
		}

		foreach ($rows as $row) {
			$status = isset($row['post_status']) ? sanitize_key((string) $row['post_status']) : '';
			$total = (int) ($row['total'] ?? 0);
			$target = $status === 'publish' ? 'published' : $status;
			if (array_key_exists($target, $counts)) {
				$counts[$target] = $total;
			}
			$counts['total'] += $total;
			$counts['recently_updated'] += (int) ($row['recent'] ?? 0);
		}

		return $counts;
	}

	/** @return array{featured:int,commercial_statuses:array<string,int>} */
	private function metaDistributions(): array
	{
		$featuredKey = MetaSchema::metaKey('featured');
		$statusKey = MetaSchema::metaKey('status');
		if (!is_string($featuredKey) || !is_string($statusKey)) {
			return array('featured' => 0, 'commercial_statuses' => array());
		}

		$statuses = self::ACTIVE_STATUSES;
		$placeholders = implode(',', array_fill(0, count($statuses), '%s'));
		$args = array_merge(array(PostType::POST_TYPE), $statuses, array($featuredKey, $statusKey));
		$sql = $this->wpdb->prepare(
			"SELECT pm.meta_key, pm.meta_value, COUNT(DISTINCT p.ID) AS total
			FROM {$this->wpdb->posts} p
			INNER JOIN {$this->wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE p.post_type = %s
			AND p.post_status IN ({$placeholders})
			AND pm.meta_key IN (%s,%s)
			GROUP BY pm.meta_key, pm.meta_value",
			...$args
		);
		$rows = $this->wpdb->get_results($sql, ARRAY_A);
		$result = array('featured' => 0, 'commercial_statuses' => array());

		if (!is_array($rows)) {
			return $result;
		}

		foreach ($rows as $row) {
			$key = (string) ($row['meta_key'] ?? '');
			$value = sanitize_key((string) ($row['meta_value'] ?? ''));
			$total = (int) ($row['total'] ?? 0);
			if ($key === $featuredKey && in_array($value, array('1', 'yes', 'true'), true)) {
				$result['featured'] += $total;
			} elseif ($key === $statusKey && $value !== '') {
				$result['commercial_statuses'][$value] = $total;
			}
		}

		arsort($result['commercial_statuses']);
		return $result;
	}

	/** @return array<string,array{name:string,count:int}> */
	private function operationDistribution(): array
	{
		$statuses = self::ACTIVE_STATUSES;
		$placeholders = implode(',', array_fill(0, count($statuses), '%s'));
		$args = array_merge(array(PostType::POST_TYPE), $statuses, array(TaxonomyRegistry::OPERATION));
		$sql = $this->wpdb->prepare(
			"SELECT t.slug, t.name, COUNT(DISTINCT p.ID) AS total
			FROM {$this->wpdb->posts} p
			INNER JOIN {$this->wpdb->term_relationships} tr ON tr.object_id = p.ID
			INNER JOIN {$this->wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			INNER JOIN {$this->wpdb->terms} t ON t.term_id = tt.term_id
			WHERE p.post_type = %s
			AND p.post_status IN ({$placeholders})
			AND tt.taxonomy = %s
			GROUP BY t.term_id, t.slug, t.name
			ORDER BY total DESC, t.name ASC",
			...$args
		);
		$rows = $this->wpdb->get_results($sql, ARRAY_A);
		$result = array();

		if (!is_array($rows)) {
			return $result;
		}

		foreach ($rows as $row) {
			$slug = sanitize_key((string) ($row['slug'] ?? ''));
			if ($slug === '') {
				continue;
			}
			$result[$slug] = array(
				'name' => sanitize_text_field((string) ($row['name'] ?? $slug)),
				'count' => (int) ($row['total'] ?? 0),
			);
		}

		return $result;
	}

	/** @return array<string,int> */
	private function qualitySummary(): array
	{
		$empty = self::emptySnapshot()['quality'];
		$table = QualitySchema::tableName($this->wpdb);
		$statuses = self::ACTIVE_STATUSES;
		$placeholders = implode(',', array_fill(0, count($statuses), '%s'));
		$args = array_merge(array(PostType::POST_TYPE), $statuses);
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) AS total,
			ROUND(COALESCE(AVG(q.score),0)) AS average_score,
			SUM(CASE WHEN q.is_complete = 1 THEN 1 ELSE 0 END) AS complete,
			SUM(CASE WHEN q.is_complete = 0 THEN 1 ELSE 0 END) AS incomplete,
			SUM(CASE WHEN q.has_price = 0 THEN 1 ELSE 0 END) AS no_price,
			SUM(CASE WHEN q.has_image = 0 THEN 1 ELSE 0 END) AS no_image,
			SUM(CASE WHEN FIND_IN_SET('location', q.missing_codes) > 0 THEN 1 ELSE 0 END) AS no_location,
			SUM(CASE WHEN FIND_IN_SET('last_verified', q.missing_codes) > 0 THEN 1 ELSE 0 END) AS not_verified
			FROM {$table} q
			INNER JOIN {$this->wpdb->posts} p ON p.ID = q.property_id
			WHERE p.post_type = %s AND p.post_status IN ({$placeholders})",
			...$args
		);
		$row = $this->wpdb->get_row($sql, ARRAY_A);

		if (!is_array($row)) {
			return $empty;
		}

		foreach (array_keys($empty) as $key) {
			$empty[$key] = (int) ($row[$key] ?? 0);
		}
		return $empty;
	}

	/** @return array<int,array<string,mixed>> */
	private function attentionRows(int $limit): array
	{
		$limit = max(1, min(20, $limit));
		$table = QualitySchema::tableName($this->wpdb);
		$statuses = self::ACTIVE_STATUSES;
		$placeholders = implode(',', array_fill(0, count($statuses), '%s'));
		$args = array_merge(array(PostType::POST_TYPE), $statuses, array($limit));
		$sql = $this->wpdb->prepare(
			"SELECT q.property_id, q.score, q.missing_codes, q.has_price, q.has_image, q.updated_at,
			p.post_title, p.post_status
			FROM {$table} q
			INNER JOIN {$this->wpdb->posts} p ON p.ID = q.property_id
			WHERE p.post_type = %s AND p.post_status IN ({$placeholders}) AND q.is_complete = 0
			ORDER BY q.score ASC, q.updated_at ASC, q.property_id ASC
			LIMIT %d",
			...$args
		);
		$rows = $this->wpdb->get_results($sql, ARRAY_A);

		if (!is_array($rows)) {
			return array();
		}

		return array_map(
			static function (array $row): array {
				return array(
					'property_id' => (int) ($row['property_id'] ?? 0),
					'score' => (int) ($row['score'] ?? 0),
					'missing_codes' => sanitize_text_field((string) ($row['missing_codes'] ?? '')),
					'has_price' => (int) ($row['has_price'] ?? 0),
					'has_image' => (int) ($row['has_image'] ?? 0),
					'updated_at' => sanitize_text_field((string) ($row['updated_at'] ?? '')),
					'post_title' => sanitize_text_field((string) ($row['post_title'] ?? '')),
					'post_status' => sanitize_key((string) ($row['post_status'] ?? '')),
				);
			},
			$rows
		);
	}
}
