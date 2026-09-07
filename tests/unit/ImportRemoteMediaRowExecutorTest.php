<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\DryRunResult;
use WLA\Inmo\Import\IdentityResolver;
use WLA\Inmo\Import\PropertyWriterInterface;
use WLA\Inmo\Import\RemoteMediaException;
use WLA\Inmo\Import\RemoteMediaRowProcessorInterface;
use WLA\Inmo\Import\RowExecutionResult;
use WLA\Inmo\Import\RowExecutor;
use WLA\Inmo\Import\TargetRegistry;

final class ImportRemoteMediaRowExecutorTest extends TestCase
{
	public function testMediaTargetsAreRemovedFromWriterAndProcessedAfterCreate(): void
	{
		$events = new MediaRowExecutorEventLog();
		$writer = new MediaRowExecutorFakeWriter($events);
		$processor = new MediaRowExecutorFakeProcessor($events);
		$executor = new RowExecutor($this->resolver($writer), $writer, $processor);

		$result = $executor->execute($this->dryRun(array(
			'post.title' => 'Casa con imágenes',
			'meta.property_code' => 'MEDIA-1',
			TargetRegistry::MEDIA_GALLERY_URLS => array('https://cdn.example.com/a.jpg'),
			TargetRegistry::MEDIA_FEATURED_IMAGE_URL => 'https://cdn.example.com/a.jpg',
		)), 'portal_media');

		self::assertSame(RowExecutionResult::STATUS_CREATED, $result->status());
		self::assertSame(array('create', 'media'), $events->events);
		self::assertArrayNotHasKey(TargetRegistry::MEDIA_GALLERY_URLS, $writer->lastValues);
		self::assertArrayNotHasKey(TargetRegistry::MEDIA_FEATURED_IMAGE_URL, $writer->lastValues);
		self::assertSame(77, $processor->lastPropertyId);
		self::assertSame(
			array('https://cdn.example.com/a.jpg'),
			$processor->lastValues[TargetRegistry::MEDIA_GALLERY_URLS]
		);
	}

	public function testPermanentMediaWarningKeepsExecutionSuccessful(): void
	{
		$writer = new MediaRowExecutorFakeWriter(new MediaRowExecutorEventLog());
		$processor = new MediaRowExecutorFakeProcessor(new MediaRowExecutorEventLog());
		$processor->warnings = array(array('code' => 'media_mime_not_allowed', 'target' => TargetRegistry::MEDIA_GALLERY_URLS));
		$executor = new RowExecutor($this->resolver($writer), $writer, $processor);

		$result = $executor->execute($this->dryRun(array(
			'post.title' => 'Casa warning',
			'meta.property_code' => 'MEDIA-2',
			TargetRegistry::MEDIA_GALLERY_URLS => array('https://cdn.example.com/bad.svg'),
		)), 'portal_media');

		self::assertSame(RowExecutionResult::STATUS_CREATED, $result->status());
		self::assertSame($processor->warnings, $result->warnings());
		self::assertSame(array(), $result->errors());
	}

	public function testTransientMediaFailureRetryUpdatesExistingPropertyInsteadOfCreatingDuplicate(): void
	{
		$events = new MediaRowExecutorEventLog();
		$writer = new MediaRowExecutorFakeWriter($events);
		$processor = new MediaRowExecutorFakeProcessor($events);
		$processor->transientFailuresRemaining = 1;
		$executor = new RowExecutor($this->resolver($writer), $writer, $processor);
		$dryRun = $this->dryRun(array(
			'post.title' => 'Casa retry',
			'meta.property_code' => 'MEDIA-3',
			TargetRegistry::MEDIA_FEATURED_IMAGE_URL => 'https://cdn.example.com/flaky.jpg',
		));

		$first = $executor->execute($dryRun, 'portal_media');
		self::assertSame(RowExecutionResult::STATUS_ERROR, $first->status());
		self::assertSame('media_http_failed', $first->errors()[0]['code']);
		self::assertSame(77, $first->propertyId());
		self::assertSame(1, $writer->createCount);
		self::assertSame(0, $writer->updateCount);

		$second = $executor->execute($dryRun, 'portal_media');
		self::assertSame(RowExecutionResult::STATUS_UPDATED, $second->status());
		self::assertSame(77, $second->propertyId());
		self::assertSame(1, $writer->createCount, 'Retry must not create a duplicate property.');
		self::assertSame(1, $writer->updateCount);
		self::assertSame(2, $processor->calls);
	}

	private function resolver(MediaRowExecutorFakeWriter $writer): IdentityResolver
	{
		return new IdentityResolver(
			static fn (string $sourceKey, string $externalId): array => array(),
			static fn (string $propertyCode): array => $writer->createCount > 0 && $propertyCode !== '' ? array(77) : array()
		);
	}

	/** @param array<string,mixed> $values */
	private function dryRun(array $values): DryRunResult
	{
		return new DryRunResult(
			2,
			DryRunResult::STATUS_NEW,
			null,
			$values,
			array(),
			array_keys($values),
			array(),
			array()
		);
	}
}

final class MediaRowExecutorEventLog
{
	/** @var array<int,string> */
	public array $events = array();
}

final class MediaRowExecutorFakeWriter implements PropertyWriterInterface
{
	public int $createCount = 0;
	public int $updateCount = 0;
	/** @var array<string,mixed> */
	public array $lastValues = array();

	public function __construct(private MediaRowExecutorEventLog $events)
	{
	}

	public function create(array $values, string $sourceKey): int
	{
		unset($sourceKey);
		++$this->createCount;
		$this->lastValues = $values;
		$this->events->events[] = 'create';
		return 77;
	}

	public function update(int $propertyId, array $values, string $sourceKey): void
	{
		unset($sourceKey);
		self::assertPropertyId($propertyId);
		++$this->updateCount;
		$this->lastValues = $values;
		$this->events->events[] = 'update';
	}

	private static function assertPropertyId(int $propertyId): void
	{
		if ($propertyId !== 77) {
			throw new RuntimeException('Unexpected synthetic property ID.');
		}
	}
}

final class MediaRowExecutorFakeProcessor implements RemoteMediaRowProcessorInterface
{
	public int $calls = 0;
	public int $transientFailuresRemaining = 0;
	public ?int $lastPropertyId = null;
	/** @var array<string,mixed> */
	public array $lastValues = array();
	/** @var array<int,array{code:string,target:string}> */
	public array $warnings = array();

	public function __construct(private MediaRowExecutorEventLog $events)
	{
	}

	public function process(int $propertyId, array $mediaValues): array
	{
		++$this->calls;
		$this->lastPropertyId = $propertyId;
		$this->lastValues = $mediaValues;
		$this->events->events[] = 'media';

		if ($this->transientFailuresRemaining > 0) {
			--$this->transientFailuresRemaining;
			throw new RemoteMediaException('media_http_failed', 'Synthetic transient media failure.');
		}

		return $this->warnings;
	}
}
