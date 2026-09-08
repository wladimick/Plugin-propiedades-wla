<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Access\RoleMatrix;
use WLA\Inmo\Import\BatchStatus;
use WLA\Inmo\Import\RollbackException;
use WLA\Inmo\Import\RollbackJournalSchema;
use WLA\Inmo\Import\RollbackPreview;
use WLA\Inmo\Import\RollbackSnapshotCodec;

final class ImportRollbackFoundationTest extends TestCase
{
	public function testSnapshotCodecIsDeterministicForAssociativeKeyOrder(): void
	{
		$left = array(
			'values' => array(
				'meta.price_clp' => array('value' => 1000, 'exists' => true),
				'post.title' => 'Casa',
			),
			'targets' => array('meta.price_clp', 'post.title'),
			'property_id' => 10,
		);
		$right = array(
			'property_id' => 10,
			'targets' => array('meta.price_clp', 'post.title'),
			'values' => array(
				'post.title' => 'Casa',
				'meta.price_clp' => array('exists' => true, 'value' => 1000),
			),
		);

		self::assertSame(RollbackSnapshotCodec::encode($left), RollbackSnapshotCodec::encode($right));
		self::assertSame(RollbackSnapshotCodec::hash($left), RollbackSnapshotCodec::hash($right));
		self::assertTrue(RollbackSnapshotCodec::equals($left, $right));
	}

	public function testSnapshotCodecDistinguishesMissingMetaFromExistingEmptyMeta(): void
	{
		$missing = array('meta' => array('exists' => false, 'value' => ''));
		$empty = array('meta' => array('exists' => true, 'value' => ''));

		self::assertNotSame(RollbackSnapshotCodec::hash($missing), RollbackSnapshotCodec::hash($empty));
		self::assertFalse(RollbackSnapshotCodec::equals($missing, $empty));
	}

	public function testSnapshotCodecRejectsMalformedJson(): void
	{
		$this->expectException(RollbackException::class);
		RollbackSnapshotCodec::decode('{bad json');
	}

	public function testRollbackJournalSchemaIsScopedAndUniquePerBatchRow(): void
	{
		$database = new RollbackFoundationFakeDatabase();
		$sql = RollbackJournalSchema::sql($database);

		self::assertSame('1', RollbackJournalSchema::DB_VERSION);
		self::assertStringContainsString('batch_uuid char(36) NOT NULL', $sql);
		self::assertStringContainsString('before_json longtext NULL', $sql);
		self::assertStringContainsString('after_hash char(64)', $sql);
		self::assertStringContainsString('created_object_hash char(64)', $sql);
		self::assertStringContainsString('UNIQUE KEY batch_row (batch_uuid,row_number)', $sql);
	}

	public function testRollbackProcessingIsResumableButTerminalStatesRemainTerminal(): void
	{
		self::assertTrue(BatchStatus::canTransition(BatchStatus::COMPLETED, BatchStatus::ROLLBACK_PROCESSING));
		self::assertTrue(BatchStatus::canTransition(BatchStatus::ROLLBACK_PROCESSING, BatchStatus::ROLLED_BACK));
		self::assertTrue(BatchStatus::canTransition(BatchStatus::ROLLBACK_PROCESSING, BatchStatus::ROLLBACK_BLOCKED));
		self::assertFalse(BatchStatus::canTransition(BatchStatus::ROLLED_BACK, BatchStatus::ROLLBACK_PROCESSING));
		self::assertContains(BatchStatus::ROLLED_BACK, BatchStatus::terminal(), true);
		self::assertContains(BatchStatus::ROLLBACK_BLOCKED, BatchStatus::terminal(), true);
	}

	public function testRollbackCapabilityIsAdministratorOnlyByDefault(): void
	{
		self::assertContains(AccessCapabilities::ROLLBACK_IMPORTS, RoleMatrix::administratorCapabilities(), true);
		self::assertNotContains(AccessCapabilities::ROLLBACK_IMPORTS, RoleMatrix::managerCapabilities(), true);
	}

	public function testPreviewCanConfirmOnlyWithoutBlockedOrErrors(): void
	{
		$hash = str_repeat('a', 64);
		$safe = new RollbackPreview('batch', 1, 2, 0, 0, 0, 1, 1, $hash);
		$blocked = new RollbackPreview('batch', 1, 1, 0, 1, 0, 1, 0, $hash);
		$error = new RollbackPreview('batch', 1, 1, 0, 0, 1, 1, 0, $hash);

		self::assertTrue($safe->canConfirm());
		self::assertFalse($blocked->canConfirm());
		self::assertFalse($error->canConfirm());
	}
}

final class RollbackFoundationFakeDatabase
{
	public string $prefix = 'wp_';

	public function get_charset_collate(): string
	{
		return 'DEFAULT CHARACTER SET utf8mb4';
	}
}
