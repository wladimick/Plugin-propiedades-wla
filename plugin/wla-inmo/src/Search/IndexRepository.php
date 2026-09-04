<?php

namespace WLA\Inmo\Search;

final class IndexRepository
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

	/**
	 * Persist one complete projection row without destructive REPLACE semantics.
	 *
	 * A duplicate property_code belonging to another property is rejected so a
	 * malformed import/save can never evict the existing indexed property.
	 *
	 * @param array<string, mixed> $row Projection row.
	 */
	public function upsert(array $row): bool
	{
		if ($this->wpdb === null || !isset($row['property_id'])) {
			return false;
		}

		$propertyId = (int) $row['property_id'];
		if ($propertyId < 1 || !$this->isValidRow($row)) {
			return false;
		}

		$propertyCode = $row['property_code'] ?? null;
		if (is_string($propertyCode) && $propertyCode !== '') {
			$conflictId = $this->findPropertyIdByCode($propertyCode);

			if ($conflictId !== null && $conflictId !== $propertyId) {
				if (function_exists('do_action')) {
					do_action('wla_inmo_index_property_code_conflict', $propertyId, $propertyCode, $conflictId);
				}

				return false;
			}
		}

		$formats = $this->formatsForRow($row);
		$table = IndexSchema::tableName($this->wpdb);

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

		$inserted = $this->wpdb->insert($table, $row, $formats);

		return $inserted !== false;
	}

	/**
	 * Backwards-friendly alias for early internal callers.
	 *
	 * @param array<string, mixed> $row Projection row.
	 */
	public function replace(array $row): bool
	{
		return $this->upsert($row);
	}

	public function delete(int $propertyId): bool
	{
		if ($this->wpdb === null || $propertyId < 1) {
			return false;
		}

		$result = $this->wpdb->delete(
			IndexSchema::tableName($this->wpdb),
			array('property_id' => $propertyId),
			array('%d')
		);

		return $result !== false;
	}

	public function clear(): bool
	{
		if ($this->wpdb === null || !method_exists($this->wpdb, 'query')) {
			return false;
		}

		$table = IndexSchema::tableName($this->wpdb);
		$result = $this->wpdb->query("DELETE FROM {$table}");

		return $result !== false;
	}

	public function exists(int $propertyId): bool
	{
		if ($this->wpdb === null || $propertyId < 1 || !method_exists($this->wpdb, 'prepare')) {
			return false;
		}

		$table = IndexSchema::tableName($this->wpdb);
		$sql = $this->wpdb->prepare(
			"SELECT property_id FROM {$table} WHERE property_id = %d LIMIT 1",
			$propertyId
		);

		return (int) $this->wpdb->get_var($sql) === $propertyId;
	}

	public function findPropertyIdByCode(string $propertyCode): ?int
	{
		$propertyCode = trim($propertyCode);
		if ($this->wpdb === null || $propertyCode === '' || !method_exists($this->wpdb, 'prepare')) {
			return null;
		}

		$table = IndexSchema::tableName($this->wpdb);
		$sql = $this->wpdb->prepare(
			"SELECT property_id FROM {$table} WHERE property_code = %s LIMIT 1",
			$propertyCode
		);
		$value = $this->wpdb->get_var($sql);

		return $value === null ? null : (int) $value;
	}

	/**
	 * @return array<string, string>
	 */
	public static function formats(): array
	{
		return array(
			'property_id'    => '%d',
			'property_code'  => '%s',
			'external_id'    => '%s',
			'status'         => '%s',
			'operation_slug' => '%s',
			'type_slug'      => '%s',
			'region_slug'    => '%s',
			'commune_slug'   => '%s',
			'sector_slug'    => '%s',
			'price_clp'      => '%d',
			'price_uf'       => '%f',
			'price_usd'      => '%f',
			'bedrooms'       => '%d',
			'bathrooms'      => '%d',
			'parking'        => '%d',
			'land_area_m2'   => '%f',
			'built_area_m2'  => '%f',
			'latitude'       => '%f',
			'longitude'      => '%f',
			'featured'       => '%d',
			'updated_at'     => '%s',
		);
	}

	/**
	 * @param array<string, mixed> $row Projection row.
	 */
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

		foreach (array_keys($row) as $column) {
			if (!isset($known[$column])) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string, mixed> $row Projection row.
	 * @return array<int, string>
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
