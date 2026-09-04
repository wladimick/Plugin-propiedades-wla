<?php

namespace WLA\Inmo\Import;

final class IdentityRepository
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
	 * Persist one complete projection row. The database unique keys are the
	 * final protection against concurrent identity collisions.
	 *
	 * @param array<string,mixed> $row Projection row.
	 */
	public function upsert(array $row): bool
	{
		if ($this->wpdb === null || !$this->isValidRow($row)) {
			return false;
		}

		$propertyId = (int) $row['property_id'];
		if ($propertyId < 1 || !$this->identityPairIsComplete($row)) {
			return false;
		}

		$propertyCode = $row['property_code'];
		if (is_string($propertyCode) && $propertyCode !== '') {
			$conflict = $this->findPropertyIdByCode($propertyCode);
			if ($conflict !== null && $conflict !== $propertyId) {
				return false;
			}
		}

		$sourceKey = $row['source_key'];
		$externalId = $row['external_id'];
		if (is_string($sourceKey) && $sourceKey !== '' && is_string($externalId) && $externalId !== '') {
			$conflict = $this->findPropertyIdByExternalIdentity($sourceKey, $externalId);
			if ($conflict !== null && $conflict !== $propertyId) {
				return false;
			}
		}

		$table = IdentitySchema::tableName($this->wpdb);
		$formats = array('%d', '%s', '%s', '%s', '%s');
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
			IdentitySchema::tableName($this->wpdb),
			array('property_id' => $propertyId),
			array('%d')
		) !== false;
	}

	public function exists(int $propertyId): bool
	{
		if ($this->wpdb === null || $propertyId < 1 || !method_exists($this->wpdb, 'prepare')) {
			return false;
		}

		$table = IdentitySchema::tableName($this->wpdb);
		$sql = $this->wpdb->prepare("SELECT property_id FROM {$table} WHERE property_id = %d LIMIT 1", $propertyId);

		return (int) $this->wpdb->get_var($sql) === $propertyId;
	}

	public function findPropertyIdByCode(string $propertyCode): ?int
	{
		$propertyCode = trim($propertyCode);
		if ($this->wpdb === null || $propertyCode === '' || !method_exists($this->wpdb, 'prepare')) {
			return null;
		}

		$table = IdentitySchema::tableName($this->wpdb);
		$sql = $this->wpdb->prepare("SELECT property_id FROM {$table} WHERE property_code = %s LIMIT 1", $propertyCode);
		$value = $this->wpdb->get_var($sql);

		return $value === null ? null : (int) $value;
	}

	public function findPropertyIdByExternalIdentity(string $sourceKey, string $externalId): ?int
	{
		$sourceKey = SourceKey::normalize($sourceKey);
		$externalId = trim($externalId);
		if (
			$this->wpdb === null
			|| !SourceKey::isValid($sourceKey)
			|| $externalId === ''
			|| !method_exists($this->wpdb, 'prepare')
		) {
			return null;
		}

		$table = IdentitySchema::tableName($this->wpdb);
		$sql = $this->wpdb->prepare(
			"SELECT property_id FROM {$table} WHERE source_key = %s AND external_id = %s LIMIT 1",
			$sourceKey,
			$externalId
		);
		$value = $this->wpdb->get_var($sql);

		return $value === null ? null : (int) $value;
	}

	/**
	 * @return IdentityResolver Resolver backed by the indexed projection.
	 */
	public function resolver(): IdentityResolver
	{
		return new IdentityResolver(
			fn (string $sourceKey, string $externalId): array => $this->asArray($this->findPropertyIdByExternalIdentity($sourceKey, $externalId)),
			fn (string $propertyCode): array => $this->asArray($this->findPropertyIdByCode($propertyCode))
		);
	}

	/**
	 * @param array<string,mixed> $row Projection row.
	 */
	private function isValidRow(array $row): bool
	{
		$required = array('property_id', 'source_key', 'external_id', 'property_code', 'updated_at');

		return array_keys($row) === $required;
	}

	/**
	 * @param array<string,mixed> $row Projection row.
	 */
	private function identityPairIsComplete(array $row): bool
	{
		$sourceKey = $row['source_key'];
		$externalId = $row['external_id'];
		$hasSource = is_string($sourceKey) && $sourceKey !== '';
		$hasExternal = is_string($externalId) && $externalId !== '';

		return $hasSource === $hasExternal;
	}

	/**
	 * @return array<int,int>
	 */
	private function asArray(?int $propertyId): array
	{
		return $propertyId === null ? array() : array($propertyId);
	}
}
