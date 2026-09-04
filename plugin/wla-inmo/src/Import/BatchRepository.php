<?php

namespace WLA\Inmo\Import;

final class BatchRepository
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
	 * Create a batch and return its UUID.
	 */
	public function create(
		string $sourceKey,
		string $sourceHash,
		string $profileJson,
		int $totalRows,
		int $createdBy = 0,
		?string $batchUuid = null
	): ?string {
		if ($this->wpdb === null || $totalRows < 0 || $createdBy < 0) {
			return null;
		}

		$sourceKey = SourceKey::normalize($sourceKey);
		$sourceHash = strtolower(trim($sourceHash));
		if (!SourceKey::isValid($sourceKey) || preg_match('/^[a-f0-9]{64}$/', $sourceHash) !== 1) {
			return null;
		}

		$decodedProfile = json_decode($profileJson, true);
		if (!is_array($decodedProfile)) {
			return null;
		}

		$batchUuid = $batchUuid === null ? self::uuid4() : strtolower(trim($batchUuid));
		if (!self::isUuid($batchUuid)) {
			return null;
		}

		$now = gmdate('Y-m-d H:i:s');
		$row = array(
			'batch_uuid'     => $batchUuid,
			'source_key'     => $sourceKey,
			'source_hash'    => $sourceHash,
			'status'         => BatchStatus::UPLOADED,
			'profile_json'   => $profileJson,
			'created_by'     => $createdBy,
			'total_rows'     => $totalRows,
			'cursor_row'     => 0,
			'processed_rows' => 0,
			'created_count'  => 0,
			'updated_count'  => 0,
			'skipped_count'  => 0,
			'warning_count'  => 0,
			'error_count'    => 0,
			'revision'       => 0,
			'created_at'     => $now,
			'updated_at'     => $now,
			'started_at'     => null,
			'completed_at'   => null,
		);

		$formats = array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s');
		$inserted = $this->wpdb->insert(BatchSchema::tableName($this->wpdb), $row, $formats);

		return $inserted === false ? null : $batchUuid;
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public function find(string $batchUuid): ?array
	{
		$batchUuid = strtolower(trim($batchUuid));
		if ($this->wpdb === null || !self::isUuid($batchUuid) || !method_exists($this->wpdb, 'prepare')) {
			return null;
		}

		$table = BatchSchema::tableName($this->wpdb);
		$sql = $this->wpdb->prepare("SELECT * FROM {$table} WHERE batch_uuid = %s LIMIT 1", $batchUuid);
		$row = $this->wpdb->get_row($sql, 'ARRAY_A');

		return is_array($row) ? self::normalizeRow($row) : null;
	}

	/**
	 * Perform an optimistic-lock state transition.
	 */
	public function transition(string $batchUuid, string $toStatus, int $expectedRevision): bool
	{
		$current = $this->find($batchUuid);
		if ($current === null || $expectedRevision < 0 || (int) $current['revision'] !== $expectedRevision) {
			return false;
		}

		$fromStatus = (string) $current['status'];
		if (!BatchStatus::isValid($toStatus) || !BatchStatus::canTransition($fromStatus, $toStatus)) {
			return false;
		}

		if ($toStatus === BatchStatus::COMPLETED && (int) $current['processed_rows'] !== (int) $current['total_rows']) {
			return false;
		}

		$now = gmdate('Y-m-d H:i:s');
		$data = array(
			'status'     => $toStatus,
			'revision'   => $expectedRevision + 1,
			'updated_at' => $now,
		);
		$formats = array('%s', '%d', '%s');

		if ($toStatus === BatchStatus::PROCESSING && empty($current['started_at'])) {
			$data['started_at'] = $now;
			$formats[] = '%s';
		}

		if ($toStatus === BatchStatus::COMPLETED) {
			$data['completed_at'] = $now;
			$formats[] = '%s';
		}

		$updated = $this->wpdb->update(
			BatchSchema::tableName($this->wpdb),
			$data,
			array(
				'batch_uuid' => $batchUuid,
				'status'     => $fromStatus,
				'revision'   => $expectedRevision,
			),
			$formats,
			array('%s', '%s', '%d')
		);

		return $updated === 1;
	}

	/**
	 * Persist monotonic progress. Callers must supply the full current counters,
	 * which prevents accidental counter resets when a worker resumes.
	 *
	 * @param array<string,int> $counters created, updated, skipped, warnings, errors.
	 */
	public function advanceProgress(
		string $batchUuid,
		int $expectedRevision,
		int $cursorRow,
		int $processedRows,
		array $counters
	): bool {
		$current = $this->find($batchUuid);
		if (
			$current === null
			|| (string) $current['status'] !== BatchStatus::PROCESSING
			|| (int) $current['revision'] !== $expectedRevision
		) {
			return false;
		}

		$normalizedCounters = self::normalizeCounters($counters);
		if ($normalizedCounters === null) {
			return false;
		}

		$totalRows = (int) $current['total_rows'];
		if (
			$cursorRow < (int) $current['cursor_row']
			|| $processedRows < (int) $current['processed_rows']
			|| $cursorRow > $totalRows
			|| $processedRows > $totalRows
		) {
			return false;
		}

		$oldCounters = array(
			'created'  => (int) $current['created_count'],
			'updated'  => (int) $current['updated_count'],
			'skipped'  => (int) $current['skipped_count'],
			'warnings' => (int) $current['warning_count'],
			'errors'   => (int) $current['error_count'],
		);
		foreach ($oldCounters as $key => $oldValue) {
			if ($normalizedCounters[$key] < $oldValue) {
				return false;
			}
		}

		$terminalRowCounts = $normalizedCounters['created'] + $normalizedCounters['updated'] + $normalizedCounters['skipped'] + $normalizedCounters['errors'];
		if ($terminalRowCounts > $processedRows) {
			return false;
		}

		$data = array(
			'cursor_row'     => $cursorRow,
			'processed_rows' => $processedRows,
			'created_count'  => $normalizedCounters['created'],
			'updated_count'  => $normalizedCounters['updated'],
			'skipped_count'  => $normalizedCounters['skipped'],
			'warning_count'  => $normalizedCounters['warnings'],
			'error_count'    => $normalizedCounters['errors'],
			'revision'       => $expectedRevision + 1,
			'updated_at'     => gmdate('Y-m-d H:i:s'),
		);

		$updated = $this->wpdb->update(
			BatchSchema::tableName($this->wpdb),
			$data,
			array(
				'batch_uuid' => strtolower(trim($batchUuid)),
				'status'     => BatchStatus::PROCESSING,
				'revision'   => $expectedRevision,
			),
			array('%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s'),
			array('%s', '%s', '%d')
		);

		return $updated === 1;
	}

	/**
	 * @param array<string,mixed> $row Database row.
	 * @return array<string,mixed>
	 */
	private static function normalizeRow(array $row): array
	{
		$integerFields = array(
			'id', 'created_by', 'total_rows', 'cursor_row', 'processed_rows',
			'created_count', 'updated_count', 'skipped_count', 'warning_count', 'error_count', 'revision',
		);
		foreach ($integerFields as $field) {
			if (array_key_exists($field, $row)) {
				$row[$field] = (int) $row[$field];
			}
		}

		return $row;
	}

	/**
	 * @param array<string,int> $counters Raw counters.
	 * @return array<string,int>|null
	 */
	private static function normalizeCounters(array $counters): ?array
	{
		$required = array('created', 'updated', 'skipped', 'warnings', 'errors');
		$normalized = array();
		foreach ($required as $key) {
			if (!array_key_exists($key, $counters) || !is_int($counters[$key]) || $counters[$key] < 0) {
				return null;
			}
			$normalized[$key] = $counters[$key];
		}

		return $normalized;
	}

	private static function isUuid(string $value): bool
	{
		return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $value) === 1;
	}

	private static function uuid4(): string
	{
		if (function_exists('wp_generate_uuid4')) {
			return strtolower((string) wp_generate_uuid4());
		}

		$bytes = random_bytes(16);
		$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
		$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
		$hex = bin2hex($bytes);

		return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
	}
}
