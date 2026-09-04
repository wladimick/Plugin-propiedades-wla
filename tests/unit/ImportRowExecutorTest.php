<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\DryRunResult;
use WLA\Inmo\Import\ExecutionException;
use WLA\Inmo\Import\IdentityResolver;
use WLA\Inmo\Import\PropertyWriterInterface;
use WLA\Inmo\Import\RowExecutionResult;
use WLA\Inmo\Import\RowExecutor;

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		unset($domain);
		return $text;
	}
}

final class ImportRowExecutorTest extends TestCase
{
	public function testCreatesNewPropertyFromValidatedDryRun(): void
	{
		$writer = new RowExecutorFakeWriter();
		$executor = new RowExecutor($this->resolver(), $writer);
		$result = $executor->execute(
			$this->dryRun(
				DryRunResult::STATUS_NEW,
				null,
				array(
					'post.title' => 'Casa Central',
					'meta.external_id' => 'EXT-1',
					'meta.property_code' => 'COD-1',
					'meta.price_clp' => 390000000,
				)
			),
			'portal_a'
		);

		self::assertSame(RowExecutionResult::STATUS_CREATED, $result->status());
		self::assertSame(101, $result->propertyId());
		self::assertCount(1, $writer->creates);
		self::assertSame('portal_a', $writer->creates[0]['source_key']);
		self::assertSame(390000000, $writer->creates[0]['values']['meta.price_clp']);
	}

	public function testUpdatesExactMatchedProperty(): void
	{
		$writer = new RowExecutorFakeWriter();
		$executor = new RowExecutor(
			$this->resolver(array('portal_a|EXT-2' => array(22)), array('COD-2' => array(22))),
			$writer
		);

		$result = $executor->execute(
			$this->dryRun(
				DryRunResult::STATUS_UPDATE,
				22,
				array('meta.external_id' => 'EXT-2', 'meta.property_code' => 'COD-2', 'meta.price_clp' => 350)
			),
			'portal_a'
		);

		self::assertSame(RowExecutionResult::STATUS_UPDATED, $result->status());
		self::assertSame(22, $result->propertyId());
		self::assertSame('external_identity', $result->identityReason());
		self::assertCount(1, $writer->updates);
		self::assertSame(22, $writer->updates[0]['property_id']);
	}

	public function testRetryOfPreviouslyNewRowBecomesUpdateInsteadOfDuplicateCreate(): void
	{
		$writer = new RowExecutorFakeWriter();
		$executor = new RowExecutor(
			$this->resolver(array('portal_a|EXT-3' => array(33)), array('COD-3' => array(33))),
			$writer
		);

		$result = $executor->execute(
			$this->dryRun(
				DryRunResult::STATUS_NEW,
				null,
				array('post.title' => 'Retry', 'meta.external_id' => 'EXT-3', 'meta.property_code' => 'COD-3')
			),
			'portal_a'
		);

		self::assertSame(RowExecutionResult::STATUS_UPDATED, $result->status());
		self::assertSame(33, $result->propertyId());
		self::assertCount(0, $writer->creates);
		self::assertCount(1, $writer->updates);
	}

	public function testRejectsUpdateWhenIdentityDisappearedSinceDryRun(): void
	{
		$writer = new RowExecutorFakeWriter();
		$result = (new RowExecutor($this->resolver(), $writer))->execute(
			$this->dryRun(
				DryRunResult::STATUS_UPDATE,
				44,
				array('meta.property_code' => 'COD-44', 'meta.price_clp' => 100)
			),
			'portal_a'
		);

		self::assertSame(RowExecutionResult::STATUS_ERROR, $result->status());
		self::assertSame('identity_missing_since_dry_run', $result->errors()[0]['code']);
		self::assertCount(0, $writer->updates);
	}

	public function testRejectsUpdateWhenIdentityNowTargetsAnotherProperty(): void
	{
		$writer = new RowExecutorFakeWriter();
		$executor = new RowExecutor($this->resolver(array(), array('COD-55' => array(56))), $writer);
		$result = $executor->execute(
			$this->dryRun(DryRunResult::STATUS_UPDATE, 55, array('meta.property_code' => 'COD-55')),
			'portal_a'
		);

		self::assertSame(RowExecutionResult::STATUS_ERROR, $result->status());
		self::assertSame('identity_changed_since_dry_run', $result->errors()[0]['code']);
		self::assertCount(0, $writer->updates);
	}

	public function testRejectsConflictingExternalAndPropertyCodeIdentities(): void
	{
		$writer = new RowExecutorFakeWriter();
		$executor = new RowExecutor(
			$this->resolver(array('portal_a|EXT-6' => array(60)), array('COD-6' => array(61))),
			$writer
		);
		$result = $executor->execute(
			$this->dryRun(DryRunResult::STATUS_NEW, null, array(
				'post.title' => 'Conflicto',
				'meta.external_id' => 'EXT-6',
				'meta.property_code' => 'COD-6',
			)),
			'portal_a'
		);

		self::assertSame(RowExecutionResult::STATUS_ERROR, $result->status());
		self::assertSame('identity_disagreement', $result->errors()[0]['code']);
		self::assertCount(0, $writer->creates);
	}

	public function testRejectsRowsWithoutStableIdentity(): void
	{
		$writer = new RowExecutorFakeWriter();
		$result = (new RowExecutor($this->resolver(), $writer))->execute(
			$this->dryRun(DryRunResult::STATUS_NEW, null, array('post.title' => 'Sin identidad')),
			'portal_a'
		);

		self::assertSame(RowExecutionResult::STATUS_ERROR, $result->status());
		self::assertSame('missing_identity', $result->errors()[0]['code']);
	}

	public function testRejectsDryRunErrorsAndUnknownTargetsBeforeWriting(): void
	{
		$writer = new RowExecutorFakeWriter();
		$executor = new RowExecutor($this->resolver(), $writer);

		$dryRunError = new DryRunResult(
			7,
			DryRunResult::STATUS_ERROR,
			null,
			array('post.title' => 'A'),
			array(),
			array(),
			array(),
			array(array('code' => 'invalid_value', 'target' => 'meta.price_clp'))
		);
		$result = $executor->execute($dryRunError, 'portal_a');
		self::assertSame('dry_run_has_errors', $result->errors()[0]['code']);

		$unknown = $executor->execute(
			$this->dryRun(DryRunResult::STATUS_NEW, null, array(
				'post.title' => 'A',
				'meta.property_code' => 'COD-7',
				'meta.not_real' => 'x',
			)),
			'portal_a'
		);
		self::assertSame('unknown_target', $unknown->errors()[0]['code']);
		self::assertCount(0, $writer->creates);
	}

	public function testRejectsUnresolvedTaxonomyPayload(): void
	{
		$writer = new RowExecutorFakeWriter();
		$result = (new RowExecutor($this->resolver(), $writer))->execute(
			$this->dryRun(DryRunResult::STATUS_NEW, null, array(
				'post.title' => 'Casa',
				'meta.property_code' => 'COD-8',
				'taxonomy.operation' => 'Venta',
			)),
			'portal_a'
		);

		self::assertSame(RowExecutionResult::STATUS_ERROR, $result->status());
		self::assertSame('unresolved_taxonomy_value', $result->errors()[0]['code']);
	}

	public function testReturnsStablePersistenceErrorWithoutCheckpointSemantics(): void
	{
		$writer = new RowExecutorFakeWriter();
		$writer->createException = new ExecutionException('rollback_failed', 'persistence');
		$result = (new RowExecutor($this->resolver(), $writer))->execute(
			$this->dryRun(DryRunResult::STATUS_NEW, null, array(
				'post.title' => 'Casa',
				'meta.property_code' => 'COD-9',
			)),
			'portal_a'
		);

		self::assertSame(RowExecutionResult::STATUS_ERROR, $result->status());
		self::assertSame('rollback_failed', $result->errors()[0]['code']);
		self::assertFalse($result->isSuccessful());
	}

	/**
	 * @param array<string,array<int,int>> $externalMatches
	 * @param array<string,array<int,int>> $codeMatches
	 */
	private function resolver(array $externalMatches = array(), array $codeMatches = array()): IdentityResolver
	{
		return new IdentityResolver(
			static fn (string $sourceKey, string $externalId): array => $externalMatches[$sourceKey . '|' . $externalId] ?? array(),
			static fn (string $propertyCode): array => $codeMatches[$propertyCode] ?? array()
		);
	}

	/**
	 * @param array<string,mixed> $values
	 */
	private function dryRun(string $status, ?int $propertyId, array $values): DryRunResult
	{
		return new DryRunResult(2, $status, $propertyId, $values, array(), array_keys($values), array(), array());
	}
}

final class RowExecutorFakeWriter implements PropertyWriterInterface
{
	/** @var array<int,array{values:array<string,mixed>,source_key:string}> */
	public array $creates = array();

	/** @var array<int,array{property_id:int,values:array<string,mixed>,source_key:string}> */
	public array $updates = array();

	public ?ExecutionException $createException = null;
	public ?ExecutionException $updateException = null;
	public int $nextPropertyId = 101;

	public function create(array $values, string $sourceKey): int
	{
		if ($this->createException !== null) {
			throw $this->createException;
		}

		$this->creates[] = array('values' => $values, 'source_key' => $sourceKey);
		return $this->nextPropertyId;
	}

	public function update(int $propertyId, array $values, string $sourceKey): void
	{
		if ($this->updateException !== null) {
			throw $this->updateException;
		}

		$this->updates[] = array(
			'property_id' => $propertyId,
			'values' => $values,
			'source_key' => $sourceKey,
		);
	}
}
