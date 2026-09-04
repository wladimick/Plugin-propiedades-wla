<?php

namespace WLA\Inmo\Activity;

final class Repository
{
	/** @return int|false */
	public static function insert(array $event)
	{
		global $wpdb;
		if (!isset($wpdb)) {
			return false;
		}

		$inserted = $wpdb->insert(
			Schema::tableName($wpdb),
			array(
				'event_type' => (string) ($event['event_type'] ?? ''),
				'object_type' => (string) ($event['object_type'] ?? ''),
				'object_id' => $event['object_id'] ?? null,
				'actor_user_id' => $event['actor_user_id'] ?? null,
				'summary' => (string) ($event['summary'] ?? ''),
				'context' => isset($event['context']) ? wp_json_encode($event['context']) : null,
				'created_at' => (string) ($event['created_at'] ?? gmdate('Y-m-d H:i:s')),
			),
			array('%s', '%s', '%d', '%d', '%s', '%s', '%s')
		);

		return $inserted === false ? false : (int) $wpdb->insert_id;
	}

	/**
	 * @return array{items:array<int,object>,total:int,pages:int,page:int,per_page:int}
	 */
	public static function paginate(array $filters = array(), int $page = 1, int $perPage = 30): array
	{
		global $wpdb;
		$page = max(1, $page);
		$perPage = max(1, min(100, $perPage));
		$offset = ($page - 1) * $perPage;

		$where = array('1=1');
		$args = array();

		$eventType = isset($filters['event_type']) ? sanitize_key((string) $filters['event_type']) : '';
		if ($eventType !== '') {
			$where[] = 'event_type = %s';
			$args[] = $eventType;
		}

		$objectId = isset($filters['object_id']) ? absint($filters['object_id']) : 0;
		if ($objectId > 0) {
			$where[] = 'object_id = %d';
			$args[] = $objectId;
		}

		$actorId = isset($filters['actor_user_id']) ? absint($filters['actor_user_id']) : 0;
		if ($actorId > 0) {
			$where[] = 'actor_user_id = %d';
			$args[] = $actorId;
		}

		$from = self::dateFilter($filters['from'] ?? null, false);
		if ($from !== null) {
			$where[] = 'created_at >= %s';
			$args[] = $from;
		}

		$to = self::dateFilter($filters['to'] ?? null, true);
		if ($to !== null) {
			$where[] = 'created_at <= %s';
			$args[] = $to;
		}

		$table = Schema::tableName($wpdb);
		$whereSql = implode(' AND ', $where);
		$countSql = "SELECT COUNT(*) FROM {$table} WHERE {$whereSql}";
		$listSql = "SELECT id,event_type,object_type,object_id,actor_user_id,summary,context,created_at FROM {$table} WHERE {$whereSql} ORDER BY created_at DESC,id DESC LIMIT %d OFFSET %d";

		$countQuery = $args === array() ? $countSql : $wpdb->prepare($countSql, $args);
		$total = (int) $wpdb->get_var($countQuery);

		$listArgs = array_merge($args, array($perPage, $offset));
		$listQuery = $wpdb->prepare($listSql, $listArgs);
		$items = $wpdb->get_results($listQuery);
		$items = is_array($items) ? array_map(array(self::class, 'hydrate'), $items) : array();

		return array(
			'items' => $items,
			'total' => $total,
			'pages' => max(1, (int) ceil($total / $perPage)),
			'page' => $page,
			'per_page' => $perPage,
		);
	}

	/**
	 * Load only the latest events without the COUNT query required by pagination.
	 * Intended for compact dashboard widgets.
	 *
	 * @return array<int,object>
	 */
	public static function recent(int $limit = 6): array
	{
		global $wpdb;
		if (!isset($wpdb)) {
			return array();
		}

		$limit = max(1, min(20, $limit));
		$sql = $wpdb->prepare(
			'SELECT id,event_type,object_type,object_id,actor_user_id,summary,context,created_at FROM ' . Schema::tableName($wpdb) . ' ORDER BY created_at DESC,id DESC LIMIT %d',
			$limit
		);
		$items = $wpdb->get_results($sql);

		return is_array($items) ? array_map(array(self::class, 'hydrate'), $items) : array();
	}

	/** @return array<int,object> */
	public static function forObject(string $objectType, int $objectId, int $limit = 10): array
	{
		global $wpdb;
		$objectType = sanitize_key($objectType);
		$objectId = absint($objectId);
		$limit = max(1, min(50, $limit));
		if ($objectType === '' || $objectId < 1) {
			return array();
		}

		$sql = $wpdb->prepare(
			'SELECT id,event_type,object_type,object_id,actor_user_id,summary,context,created_at FROM ' . Schema::tableName($wpdb) . ' WHERE object_type = %s AND object_id = %d ORDER BY created_at DESC,id DESC LIMIT %d',
			$objectType,
			$objectId,
			$limit
		);
		$items = $wpdb->get_results($sql);

		return is_array($items) ? array_map(array(self::class, 'hydrate'), $items) : array();
	}

	public static function deleteOlderThan(string $cutoff, int $limit = 500): int
	{
		global $wpdb;
		$limit = max(1, min(2000, $limit));
		$sql = $wpdb->prepare(
			'DELETE FROM ' . Schema::tableName($wpdb) . ' WHERE created_at < %s ORDER BY created_at ASC,id ASC LIMIT %d',
			$cutoff,
			$limit
		);
		$result = $wpdb->query($sql);

		return $result === false ? 0 : (int) $result;
	}

	private static function hydrate($row): object
	{
		if (isset($row->context) && is_string($row->context) && $row->context !== '') {
			$decoded = json_decode($row->context, true);
			$row->context = is_array($decoded) ? $decoded : array();
		} else {
			$row->context = array();
		}

		return $row;
	}

	private static function dateFilter($value, bool $endOfDay): ?string
	{
		$value = is_scalar($value) ? trim((string) $value) : '';
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
			return null;
		}

		return $value . ($endOfDay ? ' 23:59:59' : ' 00:00:00');
	}
}
