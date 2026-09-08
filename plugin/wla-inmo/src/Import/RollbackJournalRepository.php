<?php

namespace WLA\Inmo\Import;

final class RollbackJournalRepository
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

	public function prepareIntent(
		string $batchUuid,
		int $rowNumber,
		string $action,
		string $targetsJson,
		?string $beforeJson
	): bool {
		if (
			$this->wpdb === null
			|| !self::isUuid($batchUuid)
			|| $rowNumber < 1
			|| !RollbackJournalState::isAction($action)
			|| !$this->validJson($targetsJson)
			|| ($beforeJson !== null && !$this->validJson($beforeJson))
		) {
			return false;
		}

		$existing = $this->findRow($batchUuid, $rowNumber);
		if ($existing !== null) {
			return $this->intentMatches($existing, $action, $targetsJson, $beforeJson);
		}

		$now = gmdate('Y-m-d H:i:s');
		$inserted = $this->wpdb->insert(
			RollbackJournalSchema::tableName($this->wpdb),
			array(
				'batch_uuid'          => strtolower($batchUuid),
				'source_row'          => $rowNumber,
				'property_id'         => 0,
				'original_action'     => $action,
				'targets_json'        => $targetsJson,
				'before_json'         => $beforeJson,
				'after_json'          => null,
				'after_hash'          => '',
				'created_object_hash' => '',
				'journal_state'       => RollbackJournalState::PREPARED,
				'rollback_status'     => RollbackJournalState::ROLLBACK_PENDING,
				'rollback_reason'     => '',
				'created_at'          => $now,
				'updated_at'          => $now,
				'rolled_back_at'      => null,
			),
			array('%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
		);

		if ($inserted !== false) {
			return true;
		}

		$existing = $this->findRow($batchUuid, $rowNumber);
		return $existing !== null && $this->intentMatches($existing, $action, $targetsJson, $beforeJson);
	}

	public function finalize(
		string $batchUuid,
		int $rowNumber,
		int $propertyId,
		string $afterJson,
		string $afterHash,
		string $createdObjectHash = ''
	): bool {
		if (
			$this->wpdb === null
			|| !self::isUuid($batchUuid)
			|| $rowNumber < 1
			|| $propertyId < 1
			|| !$this->validJson($afterJson)
			|| !self::isHash($afterHash)
			|| ($createdObjectHash !== '' && !self::isHash($createdObjectHash))
		) {
			return false;
		}

		$current = $this->findRow($batchUuid, $rowNumber);
		if ($current === null || (string) $current['rollback_status'] !== RollbackJournalState::ROLLBACK_PENDING) {
			return false;
		}
		if ((string) $current['journal_state'] === RollbackJournalState::READY) {
			return $this->isFinalizedAs($batchUuid, $rowNumber, $propertyId, $afterHash, $createdObjectHash);
		}
		if ((string) $current['journal_state'] !== RollbackJournalState::PREPARED) {
			return false;
		}

		$updated = $this->wpdb->update(
			RollbackJournalSchema::tableName($this->wpdb),
			array(
				'property_id'         => $propertyId,
				'after_json'          => $afterJson,
				'after_hash'          => strtolower($afterHash),
				'created_object_hash' => strtolower($createdObjectHash),
				'journal_state'       => RollbackJournalState::READY,
				'updated_at'          => gmdate('Y-m-d H:i:s'),
			),
			array(
				'batch_uuid'      => strtolower($batchUuid),
				'source_row'      => $rowNumber,
				'journal_state'   => RollbackJournalState::PREPARED,
				'rollback_status' => RollbackJournalState::ROLLBACK_PENDING,
			),
			array('%d', '%s', '%s', '%s', '%s', '%s'),
			array('%s', '%d', '%s', '%s')
		);

		return $updated === 1 || ($updated === 0 && $this->isFinalizedAs($batchUuid, $rowNumber, $propertyId, $afterHash, $createdObjectHash));
	}

	/** @return array<string,mixed>|null */
	public function findRow(string $batchUuid, int $rowNumber): ?array
	{
		if ($this->wpdb === null || !self::isUuid($batchUuid) || $rowNumber < 1) {
			return null;
		}

		$table = RollbackJournalSchema::tableName($this->wpdb);
		$sql = $this->wpdb->prepare(
			"SELECT * FROM {$table} WHERE batch_uuid = %s AND source_row = %d LIMIT 1",
			strtolower($batchUuid),
			$rowNumber
		);
		$row = $this->wpdb->get_row($sql, 'ARRAY_A');

		return is_array($row) ? self::normalizeRow($row) : null;
	}

	/** @return array<int,array<string,mixed>> */
	public function page(string $batchUuid, int $limit = 50, int $offset = 0): array
	{
		return $this->queryPage($batchUuid, $limit, $offset, null, 'ASC');
	}

	/** @return array<int,array<string,mixed>> */
	public function pendingPageDescending(string $batchUuid, int $limit = 25): array
	{
		return $this->queryPage(
			$batchUuid,
			$limit,
			0,
			RollbackJournalState::ROLLBACK_PENDING,
			'DESC'
		);
	}

	public function countReady(string $batchUuid): int
	{
		return $this->countWhere($batchUuid, 'journal_state', RollbackJournalState::READY);
	}

	public function countRollbackStatus(string $batchUuid, string $status): int
	{
		if (!RollbackJournalState::isRollbackStatus($status)) {
			return 0;
		}

		return $this->countWhere($batchUuid, 'rollback_status', $status);
	}

	public function markRollback(string $batchUuid, int $rowNumber, string $status, string $reason = ''): bool
	{
		if (
			$this->wpdb === null
			|| !self::isUuid($batchUuid)
			|| $rowNumber < 1
			|| !RollbackJournalState::isRollbackStatus($status)
			|| strlen($reason) > 64
		) {
			return false;
		}

		$current = $this->findRow($batchUuid, $rowNumber);
		if ($current === null) {
			return false;
		}
		if ((string) $current['rollback_status'] === $status && (string) $current['rollback_reason'] === $reason) {
			return true;
		}
		if ((string) $current['rollback_status'] !== RollbackJournalState::ROLLBACK_PENDING) {
			return false;
		}

		$data = array(
			'rollback_status' => $status,
			'rollback_reason' => $reason,
			'updated_at'      => gmdate('Y-m-d H:i:s'),
		);
		$formats = array('%s', '%s', '%s');
		if ($status === RollbackJournalState::ROLLBACK_ROLLED_BACK) {
			$data['rolled_back_at'] = gmdate('Y-m-d H:i:s');
			$formats[] = '%s';
		}

		$updated = $this->wpdb->update(
			RollbackJournalSchema::tableName($this->wpdb),
			$data,
			array(
				'batch_uuid'      => strtolower($batchUuid),
				'source_row'      => $rowNumber,
				'rollback_status' => RollbackJournalState::ROLLBACK_PENDING,
			),
			$formats,
			array('%s', '%d', '%s')
		);

		return $updated === 1;
	}

	/** @return array<int,array<string,mixed>> */
	private function queryPage(
		string $batchUuid,
		int $limit,
		int $offset,
		?string $rollbackStatus,
		string $direction
	): array {
		if (
			$this->wpdb === null
			|| !self::isUuid($batchUuid)
			|| $limit < 1
			|| $limit > 250
			|| $offset < 0
			|| !in_array($direction, array('ASC', 'DESC'), true)
		) {
			return array();
		}

		$table = RollbackJournalSchema::tableName($this->wpdb);
		if ($rollbackStatus === null) {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$table} WHERE batch_uuid = %s ORDER BY source_row {$direction} LIMIT %d OFFSET %d",
				strtolower($batchUuid),
				$limit,
				$offset
			);
		} else {
			$sql = $this->wpdb->prepare(
				"SELECT * FROM {$table} WHERE batch_uuid = %s AND rollback_status = %s ORDER BY source_row {$direction} LIMIT %d OFFSET %d",
				strtolower($batchUuid),
				$rollbackStatus,
				$limit,
				$offset
			);
		}

		$rows = $this->wpdb->get_results($sql, 'ARRAY_A');
		if (!is_array($rows)) {
			return array();
		}

		return array_values(array_map(array(self::class, 'normalizeRow'), $rows));
	}

	private function countWhere(string $batchUuid, string $column, string $value): int
	{
		if (
			$this->wpdb === null
			|| !self::isUuid($batchUuid)
			|| !in_array($column, array('journal_state', 'rollback_status'), true)
		) {
			return 0;
		}

		$table = RollbackJournalSchema::tableName($this->wpdb);
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE batch_uuid = %s AND {$column} = %s",
			strtolower($batchUuid),
			$value
		);

		return max(0, (int) $this->wpdb->get_var($sql));
	}

	/** @param array<string,mixed> $row */
	private function intentMatches(array $row, string $action, string $targetsJson, ?string $beforeJson): bool
	{
		return (string) ($row['original_action'] ?? '') === $action
			&& (string) ($row['targets_json'] ?? '') === $targetsJson
			&& ($row['before_json'] ?? null) === $beforeJson
			&& (string) ($row['journal_state'] ?? '') === RollbackJournalState::PREPARED
			&& (string) ($row['rollback_status'] ?? '') === RollbackJournalState::ROLLBACK_PENDING;
	}

	private function isFinalizedAs(string $batchUuid, int $rowNumber, int $propertyId, string $afterHash, string $createdObjectHash): bool
	{
		$row = $this->findRow($batchUuid, $rowNumber);
		return $row !== null
			&& (string) $row['journal_state'] === RollbackJournalState::READY
			&& (string) $row['rollback_status'] === RollbackJournalState::ROLLBACK_PENDING
			&& (int) $row['property_id'] === $propertyId
			&& hash_equals((string) $row['after_hash'], strtolower($afterHash))
			&& (string) $row['created_object_hash'] === strtolower($createdObjectHash);
	}

	private function validJson(string $json): bool
	{
		json_decode($json, true);
		return json_last_error() === JSON_ERROR_NONE;
	}

	private static function isUuid(string $value): bool
	{
		return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', trim($value)) === 1;
	}

	private static function isHash(string $value): bool
	{
		return preg_match('/^[a-f0-9]{64}$/', strtolower(trim($value))) === 1;
	}

	/**
	 * Normalize the physical source_row column to the public row_number key used
	 * by the import domain, so SQL portability stays isolated in this repository.
	 *
	 * @param array<string,mixed> $row Database row.
	 * @return array<string,mixed>
	 */
	private static function normalizeRow(array $row): array
	{
		$row['row_number'] = (int) ($row['source_row'] ?? 0);
		unset($row['source_row']);

		foreach (array('id', 'property_id') as $field) {
			$row[$field] = (int) ($row[$field] ?? 0);
		}
		foreach (array('batch_uuid', 'original_action', 'targets_json', 'before_json', 'after_json', 'after_hash', 'created_object_hash', 'journal_state', 'rollback_status', 'rollback_reason', 'created_at', 'updated_at', 'rolled_back_at') as $field) {
			if ($field === 'before_json' || $field === 'after_json' || $field === 'rolled_back_at') {
				$row[$field] = isset($row[$field]) ? (string) $row[$field] : null;
			} else {
				$row[$field] = (string) ($row[$field] ?? '');
			}
		}
		return $row;
	}
}
