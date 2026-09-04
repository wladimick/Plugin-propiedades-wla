<?php

namespace WLA\Inmo\Quality;

final class Repository
{
	private $wpdb;

	public function __construct($database = null)
	{
		if ($database === null) {
			global $wpdb;
			$database = $wpdb ?? null;
		}

		$this->wpdb = $database;
	}

	/** @param array<string,mixed> $row */
	public function upsert(array $row): bool
	{
		if ($this->wpdb === null || !$this->isValidRow($row)) {
			return false;
		}

		$propertyId = (int) $row['property_id'];
		if ($propertyId < 1) {
			return false;
		}

		$table = Schema::tableName($this->wpdb);
		$formats = $this->formatsForRow($row);
		$updated = $this->wpdb->update(
			$table,
			$row,
			array('property_id' => $propertyId),
			$formats,
			array('%d')
		);

		if ($updated === false) {
			return false;
		}

		if ($updated > 0 || $this->exists($propertyId)) {
			return true;
		}

		return $this->wpdb->insert($table, $row, $formats) !== false;
	}

	public function delete(int $propertyId): bool
	{
		if ($this->wpdb === null || $propertyId < 1) {
			return false;
		}

		return $this->wpdb->delete(
			Schema::tableName($this->wpdb),
			array('property_id' => $propertyId),
			array('%d')
		) !== false;
	}

	public function clear(): bool
	{
		if ($this->wpdb === null || !method_exists($this->wpdb, 'query')) {
			return false;
		}

		$table = Schema::tableName($this->wpdb);

		return $this->wpdb->query("DELETE FROM {$table}") !== false;
	}

	public function exists(int $propertyId): bool
	{
		if ($this->wpdb === null || $propertyId < 1 || !method_exists($this->wpdb, 'prepare')) {
			return false;
		}

		$table = Schema::tableName($this->wpdb);
		$sql = $this->wpdb->prepare("SELECT property_id FROM {$table} WHERE property_id = %d LIMIT 1", $propertyId);

		return (int) $this->wpdb->get_var($sql) === $propertyId;
	}

	/** @return array<string,mixed>|null */
	public function find(int $propertyId): ?array
	{
		if ($this->wpdb === null || $propertyId < 1 || !method_exists($this->wpdb, 'prepare')) {
			return null;
		}

		$table = Schema::tableName($this->wpdb);
		$sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE property_id = %d LIMIT 1", $propertyId);
		$row = $this->wpdb->get_row($sql, ARRAY_A);

		return is_array($row) ? $row : null;
	}

	/** @return array<string,int> */
	public function summary(): array
	{
		$empty = array('total' => 0, 'complete' => 0, 'incomplete' => 0, 'no_price' => 0, 'no_image' => 0);
		if ($this->wpdb === null) {
			return $empty;
		}

		$table = Schema::tableName($this->wpdb);
		$row = $this->wpdb->get_row(
			"SELECT COUNT(*) AS total,
SUM(CASE WHEN is_complete = 1 THEN 1 ELSE 0 END) AS complete,
SUM(CASE WHEN is_complete = 0 THEN 1 ELSE 0 END) AS incomplete,
SUM(CASE WHEN has_price = 0 THEN 1 ELSE 0 END) AS no_price,
SUM(CASE WHEN has_image = 0 THEN 1 ELSE 0 END) AS no_image
FROM {$table}",
			ARRAY_A
		);

		if (!is_array($row)) {
			return $empty;
		}

		return array(
			'total' => (int) ($row['total'] ?? 0),
			'complete' => (int) ($row['complete'] ?? 0),
			'incomplete' => (int) ($row['incomplete'] ?? 0),
			'no_price' => (int) ($row['no_price'] ?? 0),
			'no_image' => (int) ($row['no_image'] ?? 0),
		);
	}

	/** @return array<int,array<string,mixed>> */
	public function lowestScores(int $limit = 20): array
	{
		if ($this->wpdb === null || !method_exists($this->wpdb, 'prepare')) {
			return array();
		}

		$limit = max(1, min(100, $limit));
		$table = Schema::tableName($this->wpdb);
		$sql = $this->wpdb->prepare(
			"SELECT property_id, score, passed_checks, total_checks, missing_codes, updated_at
FROM {$table}
WHERE is_complete = 0
ORDER BY score ASC, updated_at ASC, property_id ASC
LIMIT %d",
			$limit
		);
		$rows = $this->wpdb->get_results($sql, ARRAY_A);

		return is_array($rows) ? $rows : array();
	}

	/** @return array<string,string> */
	public static function formats(): array
	{
		return array(
			'property_id' => '%d',
			'score' => '%d',
			'passed_checks' => '%d',
			'total_checks' => '%d',
			'is_complete' => '%d',
			'has_price' => '%d',
			'has_image' => '%d',
			'missing_codes' => '%s',
			'updated_at' => '%s',
		);
	}

	/** @param array<string,mixed> $row */
	private function isValidRow(array $row): bool
	{
		$known = self::formats();
		if (count($row) !== count($known)) {
			return false;
		}

		foreach (array_keys($known) as $column) {
			if (!array_key_exists($column, $row)) {
				return false;
			}
		}

		return count(array_diff(array_keys($row), array_keys($known))) === 0;
	}

	/** @param array<string,mixed> $row
	 * @return array<int,string>
	 */
	private function formatsForRow(array $row): array
	{
		$known = self::formats();
		$formats = array();

		foreach (array_keys($row) as $column) {
			$formats[] = $known[$column];
		}

		return $formats;
	}
}
