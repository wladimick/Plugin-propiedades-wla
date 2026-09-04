<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\BatchRepository;
use WLA\Inmo\Import\BatchSchema;
use WLA\Inmo\Import\BatchStatus;
use WLA\Inmo\Import\IdentityMeta;
use WLA\Inmo\Import\IdentityRepository;
use WLA\Inmo\Import\IdentityResolution;
use WLA\Inmo\Import\IdentitySchema;

final class ImportPersistenceTest extends TestCase
{
	public function testSourceKeyMetadataUsesCanonicalNormalizer(): void
	{
		self::assertSame('portal_a', IdentityMeta::sanitize(' Portal Á '));
		self::assertSame('proveedor_sur_2026', IdentityMeta::sanitize('Proveedor Sur_2026'));
		self::assertSame('', IdentityMeta::sanitize('x'));
		self::assertSame('', IdentityMeta::sanitize(array('invalid')));
	}

	public function testIdentitySchemaHasDatabaseLevelUniqueness(): void
	{
		$database = new PersistenceFakeDatabase();
		$sql = IdentitySchema::sql($database);

		self::assertStringContainsString('UNIQUE KEY property_code (property_code)', $sql);
		self::assertStringContainsString('UNIQUE KEY external_identity (source_key,external_id)', $sql);
	}

	public function testIdentityRepositoryRejectsCrossPropertyCollisions(): void
	{
		$database = new PersistenceFakeDatabase();
		$repository = new IdentityRepository($database);
		$first = array(
			'property_id'   => 10,
			'source_key'    => 'portal_a',
			'external_id'   => 'EXT-10',
			'property_code' => 'COD-10',
			'updated_at'    => '2026-09-04 12:00:00',
		);

		self::assertTrue($repository->upsert($first));
		self::assertSame(10, $repository->findPropertyIdByExternalIdentity('Portal A', 'EXT-10'));
		self::assertSame(10, $repository->findPropertyIdByCode('COD-10'));

		$resolution = $repository->resolver()->resolve(
			new WLA\Inmo\Import\IdentityCandidate('portal_a', 'EXT-10', 'COD-10')
		);
		self::assertSame(IdentityResolution::MATCH, $resolution->status());
		self::assertSame(10, $resolution->propertyId());

		$externalConflict = $first;
		$externalConflict['property_id'] = 11;
		$externalConflict['property_code'] = 'COD-11';
		self::assertFalse($repository->upsert($externalConflict));

		$codeConflict = $first;
		$codeConflict['property_id'] = 12;
		$codeConflict['source_key'] = 'portal_b';
		$codeConflict['external_id'] = 'EXT-12';
		self::assertFalse($repository->upsert($codeConflict));

		$incompletePair = $first;
		$incompletePair['property_id'] = 13;
		$incompletePair['source_key'] = null;
		self::assertFalse($repository->upsert($incompletePair));
	}

	public function testBatchRepositoryPersistsMonotonicResumableProgress(): void
	{
		$database = new PersistenceFakeDatabase();
		$repository = new BatchRepository($database);
		$uuid = '11111111-1111-4111-8111-111111111111';
		$hash = str_repeat('a', 64);

		self::assertSame($uuid, $repository->create('Portal A', $hash, '{"version":1}', 3, 7, $uuid));
		$batch = $repository->find($uuid);
		self::assertNotNull($batch);
		self::assertSame(BatchStatus::UPLOADED, $batch['status']);
		self::assertSame(0, $batch['revision']);

		self::assertFalse($repository->transition($uuid, BatchStatus::PROCESSING, 0));
		self::assertTrue($repository->transition($uuid, BatchStatus::MAPPED, 0));
		self::assertTrue($repository->transition($uuid, BatchStatus::VALIDATED, 1));
		self::assertTrue($repository->transition($uuid, BatchStatus::DRY_RUN_READY, 2));
		self::assertTrue($repository->transition($uuid, BatchStatus::CONFIRMED, 3));
		self::assertTrue($repository->transition($uuid, BatchStatus::PROCESSING, 4));

		self::assertTrue(
			$repository->advanceProgress(
				$uuid,
				5,
				2,
				2,
				array('created' => 1, 'updated' => 1, 'skipped' => 0, 'warnings' => 1, 'errors' => 0)
			)
		);
		self::assertFalse(
			$repository->advanceProgress(
				$uuid,
				5,
				3,
				3,
				array('created' => 2, 'updated' => 1, 'skipped' => 0, 'warnings' => 1, 'errors' => 0)
			)
		);
		self::assertFalse($repository->transition($uuid, BatchStatus::COMPLETED, 6));
		self::assertTrue($repository->transition($uuid, BatchStatus::PAUSED, 6));
		self::assertTrue($repository->transition($uuid, BatchStatus::PROCESSING, 7));
		self::assertTrue(
			$repository->advanceProgress(
				$uuid,
				8,
				3,
				3,
				array('created' => 2, 'updated' => 1, 'skipped' => 0, 'warnings' => 1, 'errors' => 0)
			)
		);
		self::assertTrue($repository->transition($uuid, BatchStatus::COMPLETED, 9));

		$completed = $repository->find($uuid);
		self::assertNotNull($completed);
		self::assertSame(BatchStatus::COMPLETED, $completed['status']);
		self::assertSame(3, $completed['cursor_row']);
		self::assertSame(3, $completed['processed_rows']);
		self::assertSame(2, $completed['created_count']);
		self::assertSame(1, $completed['updated_count']);
		self::assertNotEmpty($completed['started_at']);
		self::assertNotEmpty($completed['completed_at']);
	}

	public function testBatchSchemaContainsResumeAndOptimisticLockFields(): void
	{
		$database = new PersistenceFakeDatabase();
		$sql = BatchSchema::sql($database);

		self::assertStringContainsString('cursor_row int(10) unsigned', $sql);
		self::assertStringContainsString('processed_rows int(10) unsigned', $sql);
		self::assertStringContainsString('revision bigint(20) unsigned', $sql);
		self::assertStringContainsString('UNIQUE KEY batch_uuid (batch_uuid)', $sql);
	}
}

final class PersistenceFakeDatabase
{
	public string $prefix = 'wp_';

	/** @var array<string,array<int,array<string,mixed>>> */
	private array $tables = array();

	private int $nextId = 1;

	public function get_charset_collate(): string
	{
		return 'DEFAULT CHARACTER SET utf8mb4';
	}

	/**
	 * @param array<string,mixed> $row
	 * @param array<int,string>    $formats
	 */
	public function insert(string $table, array $row, array $formats = array()): int|false
	{
		unset($formats);
		$rows = $this->tables[$table] ?? array();

		if (str_ends_with($table, IdentitySchema::TABLE_SUFFIX)) {
			foreach ($rows as $existing) {
				if ((int) $existing['property_id'] === (int) $row['property_id']) {
					return false;
				}
				if (!empty($row['property_code']) && $existing['property_code'] === $row['property_code']) {
					return false;
				}
				if (
					!empty($row['source_key'])
					&& $existing['source_key'] === $row['source_key']
					&& $existing['external_id'] === $row['external_id']
				) {
					return false;
				}
			}
		}

		if (str_ends_with($table, BatchSchema::TABLE_SUFFIX)) {
			foreach ($rows as $existing) {
				if ($existing['batch_uuid'] === $row['batch_uuid']) {
					return false;
				}
			}
			$row['id'] = $this->nextId++;
		}

		$rows[] = $row;
		$this->tables[$table] = $rows;

		return 1;
	}

	/**
	 * @param array<string,mixed> $data
	 * @param array<string,mixed> $where
	 * @param array<int,string>    $formats
	 * @param array<int,string>    $whereFormats
	 */
	public function update(string $table, array $data, array $where, array $formats = array(), array $whereFormats = array()): int|false
	{
		unset($formats, $whereFormats);
		$rows = $this->tables[$table] ?? array();
		foreach ($rows as $index => $row) {
			if (!$this->matches($row, $where)) {
				continue;
			}
			$rows[$index] = array_merge($row, $data);
			$this->tables[$table] = $rows;
			return 1;
		}

		return 0;
	}

	/**
	 * @param array<string,mixed> $where
	 * @param array<int,string>    $whereFormats
	 */
	public function delete(string $table, array $where, array $whereFormats = array()): int|false
	{
		unset($whereFormats);
		$rows = $this->tables[$table] ?? array();
		foreach ($rows as $index => $row) {
			if (!$this->matches($row, $where)) {
				continue;
			}
			unset($rows[$index]);
			$this->tables[$table] = array_values($rows);
			return 1;
		}

		return 0;
	}

	/**
	 * @return array{sql:string,args:array<int,mixed>}
	 */
	public function prepare(string $sql, mixed ...$args): array
	{
		return array('sql' => $sql, 'args' => $args);
	}

	/**
	 * @param array{sql:string,args:array<int,mixed>} $prepared
	 * @return array<string,mixed>|null
	 */
	public function get_row(array $prepared, string $format = 'ARRAY_A'): ?array
	{
		unset($format);
		$rows = $this->tables[BatchSchema::tableName($this)] ?? array();
		$uuid = (string) ($prepared['args'][0] ?? '');
		foreach ($rows as $row) {
			if ((string) $row['batch_uuid'] === $uuid) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * @param array{sql:string,args:array<int,mixed>} $prepared
	 */
	public function get_var(array $prepared): mixed
	{
		$rows = $this->tables[IdentitySchema::tableName($this)] ?? array();
		$sql = $prepared['sql'];
		$args = $prepared['args'];
		foreach ($rows as $row) {
			if (str_contains($sql, 'WHERE property_id =') && (int) $row['property_id'] === (int) ($args[0] ?? 0)) {
				return $row['property_id'];
			}
			if (str_contains($sql, 'WHERE property_code =') && (string) $row['property_code'] === (string) ($args[0] ?? '')) {
				return $row['property_id'];
			}
			if (
				str_contains($sql, 'WHERE source_key =')
				&& (string) $row['source_key'] === (string) ($args[0] ?? '')
				&& (string) $row['external_id'] === (string) ($args[1] ?? '')
			) {
				return $row['property_id'];
			}
		}

		return null;
	}

	/**
	 * @param array<string,mixed> $row
	 * @param array<string,mixed> $where
	 */
	private function matches(array $row, array $where): bool
	{
		foreach ($where as $key => $value) {
			if (!array_key_exists($key, $row) || (string) $row[$key] !== (string) $value) {
				return false;
			}
		}

		return true;
	}
}
