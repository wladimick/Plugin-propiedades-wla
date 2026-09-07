<?php

namespace WLA\Inmo\Import;

final class BatchHistoryRepository
{
	private mixed $wpdb;

	public function __construct(mixed $database = null)
	{
		if ($database === null) {
			global $wpdb;
			$database = $wpdb ?? null;
		}

		$this->wpdb = $database;
	}

	/**
	 * Return a bounded page of batch metadata without source payloads or file paths.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function recent(int $limit = 20, int $offset = 0, ?int $createdBy = null, ?string $status = null): array
	{
		if ($this->wpdb === null) {
			return array();
		}

		$limit = max(1, min(100, $limit));
		$offset = max(0, $offset);
		$where = array('1=1');
		$args = array();

		if ($createdBy !== null) {
			$createdBy = absint($createdBy);
			if ($createdBy < 1) {
				return array();
			}
			$where[] = 'created_by = %d';
			$args[] = $createdBy;
		}

		if ($status !== null && $status !== '') {
			$status = sanitize_key($status);
			if (!BatchStatus::isValid($status)) {
				return array();
			}
			$where[] = 'status = %s';
			$args[] = $status;
		}

		$table = BatchSchema::tableName($this->wpdb);
		$sql = "SELECT batch_uuid, source_key, status, created_by, total_rows, cursor_row, processed_rows, created_count, updated_count, skipped_count, warning_count, error_count, revision, created_at, updated_at, started_at, completed_at FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$args[] = $limit;
		$args[] = $offset;
		$query = $this->wpdb->prepare($sql, $args);
		$rows = $this->wpdb->get_results($query, 'ARRAY_A');

		if (!is_array($rows)) {
			return array();
		}

		return array_map(array(self::class, 'normalizeRow'), $rows);
	}

	public function count(?int $createdBy = null, ?string $status = null): int
	{
		if ($this->wpdb === null) {
			return 0;
		}

		$where = array('1=1');
		$args = array();

		if ($createdBy !== null) {
			$createdBy = absint($createdBy);
			if ($createdBy < 1) {
				return 0;
			}
			$where[] = 'created_by = %d';
			$args[] = $createdBy;
		}

		if ($status !== null && $status !== '') {
			$status = sanitize_key($status);
			if (!BatchStatus::isValid($status)) {
				return 0;
			}
			$where[] = 'status = %s';
			$args[] = $status;
		}

		$table = BatchSchema::tableName($this->wpdb);
		$sql = "SELECT COUNT(*) FROM {$table} WHERE " . implode(' AND ', $where);
		if ($args !== array()) {
			$sql = $this->wpdb->prepare($sql, $args);
		}

		return max(0, (int) $this->wpdb->get_var($sql));
	}

	/**
	 * @param array<string,mixed> $row Database row.
	 * @return array<string,mixed>
	 */
	private static function normalizeRow(array $row): array
	{
		foreach (
			array(
				'created_by', 'total_rows', 'cursor_row', 'processed_rows', 'created_count', 'updated_count',
				'skipped_count', 'warning_count', 'error_count', 'revision',
			) as $field
		) {
			if (array_key_exists($field, $row)) {
				$row[$field] = (int) $row[$field];
			}
		}

		return $row;
	}
}
