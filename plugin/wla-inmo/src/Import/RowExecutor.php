<?php

namespace WLA\Inmo\Import;

use InvalidArgumentException;
use Throwable;

final class RowExecutor
{
	private IdentityResolver $identityResolver;
	private PropertyWriterInterface $writer;
	private ?RemoteMediaRowProcessorInterface $remoteMediaProcessor;

	public function __construct(
		IdentityResolver $identityResolver,
		PropertyWriterInterface $writer,
		?RemoteMediaRowProcessorInterface $remoteMediaProcessor = null
	) {
		$this->identityResolver = $identityResolver;
		$this->writer = $writer;
		$this->remoteMediaProcessor = $remoteMediaProcessor;
	}

	public function execute(DryRunResult $dryRun, string $sourceKey): RowExecutionResult
	{
		$rowNumber = $dryRun->rowNumber();
		$warnings = $dryRun->warnings();

		if ($dryRun->status() === DryRunResult::STATUS_ERROR || $dryRun->errors() !== array()) {
			return $this->error($rowNumber, 'dry_run_has_errors', 'dry_run', null, $warnings);
		}

		try {
			$sourceKey = (new SourceKey($sourceKey))->value();
		} catch (InvalidArgumentException) {
			return $this->error($rowNumber, 'invalid_source_key', 'identity', null, $warnings);
		}

		$values = $dryRun->values();
		$payloadError = $this->validatePayload($values, $dryRun->preservedTargets());
		if ($payloadError !== null) {
			return $this->error($rowNumber, $payloadError['code'], $payloadError['target'], null, $warnings);
		}

		$mediaValues = $this->extractMediaValues($values);
		if ($mediaValues !== array() && $this->remoteMediaProcessor === null) {
			return $this->error($rowNumber, 'media_processor_unavailable', 'media', null, $warnings);
		}

		$externalId = $this->identityValue($values, 'meta.external_id');
		$propertyCode = $this->identityValue($values, 'meta.property_code');
		if ($externalId === '' && $propertyCode === '') {
			return $this->error($rowNumber, 'missing_identity', 'identity', null, $warnings);
		}

		$resolution = $this->identityResolver->resolve(
			new IdentityCandidate($sourceKey, $externalId, $propertyCode)
		);

		if ($resolution->status() === IdentityResolution::CONFLICT) {
			return $this->error($rowNumber, $resolution->reason(), 'identity', null, $warnings);
		}

		$stateError = $this->validateDryRunState($dryRun, $resolution);
		if ($stateError !== null) {
			return $this->error($rowNumber, $stateError['code'], $stateError['target'], $resolution->propertyId(), $warnings);
		}

		if ($resolution->status() === IdentityResolution::NEW) {
			$title = $values[TargetRegistry::POST_TITLE] ?? null;
			if (!is_string($title) || trim($title) === '') {
				return $this->error($rowNumber, 'title_required_for_new', TargetRegistry::POST_TITLE, null, $warnings);
			}

			$this->beforeExecute($rowNumber, RowExecutionResult::STATUS_CREATED, null);

			try {
				$propertyId = $this->writer->create($values, $sourceKey);
			} catch (ExecutionException $exception) {
				return $this->error($rowNumber, $exception->reason(), $exception->target(), null, $warnings);
			} catch (Throwable) {
				return $this->error($rowNumber, 'unexpected_persistence_failure', 'persistence', null, $warnings);
			}

			if ($propertyId < 1) {
				return $this->error($rowNumber, 'invalid_created_property_id', 'persistence', null, $warnings);
			}

			$mediaError = $this->processRemoteMedia($propertyId, $mediaValues, $warnings);
			if ($mediaError !== null) {
				return $this->error($rowNumber, $mediaError['code'], $mediaError['target'], $propertyId, $warnings);
			}

			$result = RowExecutionResult::created($rowNumber, $propertyId, $resolution->reason(), $warnings);
			$this->afterExecute($result);
			return $result;
		}

		$propertyId = $resolution->propertyId();
		if ($propertyId === null || $propertyId < 1) {
			return $this->error($rowNumber, 'resolved_property_missing', 'identity', null, $warnings);
		}

		$this->beforeExecute($rowNumber, RowExecutionResult::STATUS_UPDATED, $propertyId);

		try {
			$this->writer->update($propertyId, $values, $sourceKey);
		} catch (ExecutionException $exception) {
			return $this->error($rowNumber, $exception->reason(), $exception->target(), $propertyId, $warnings);
		} catch (Throwable) {
			return $this->error($rowNumber, 'unexpected_persistence_failure', 'persistence', $propertyId, $warnings);
		}

		$mediaError = $this->processRemoteMedia($propertyId, $mediaValues, $warnings);
		if ($mediaError !== null) {
			return $this->error($rowNumber, $mediaError['code'], $mediaError['target'], $propertyId, $warnings);
		}

		$result = RowExecutionResult::updated($rowNumber, $propertyId, $resolution->reason(), $warnings);
		$this->afterExecute($result);
		return $result;
	}

	/**
	 * @param array<string,mixed> $values Canonical dry-run values.
	 * @param array<int,string>    $preservedTargets Preserved targets.
	 * @return array{code:string,target:string}|null
	 */
	private function validatePayload(array $values, array $preservedTargets): ?array
	{
		foreach ($preservedTargets as $target) {
			if (!TargetRegistry::isAllowed($target)) {
				return array('code' => 'unknown_preserved_target', 'target' => $target);
			}
			if (array_key_exists($target, $values)) {
				return array('code' => 'inconsistent_preserved_target', 'target' => $target);
			}
		}

		foreach ($values as $target => $value) {
			$definition = TargetRegistry::definition((string) $target);
			if ($definition === null) {
				return array('code' => 'unknown_target', 'target' => (string) $target);
			}

			$kind = (string) ($definition['kind'] ?? '');
			if ($kind === 'media') {
				if (!empty($definition['multiple']) && !is_array($value)) {
					return array('code' => 'invalid_media_payload', 'target' => (string) $target);
				}
				if (empty($definition['multiple']) && $value !== null && !is_string($value)) {
					return array('code' => 'invalid_media_payload', 'target' => (string) $target);
				}
				continue;
			}

			if ($kind !== 'taxonomy') {
				continue;
			}

			if ($value === null || $value === array()) {
				continue;
			}

			$items = !empty($definition['multiple']) ? $value : array($value);
			if (!is_array($items) || $items === array()) {
				return array('code' => 'unresolved_taxonomy_value', 'target' => (string) $target);
			}

			foreach ($items as $item) {
				if (!is_array($item) || (int) ($item['id'] ?? 0) < 1 || trim((string) ($item['slug'] ?? '')) === '') {
					return array('code' => 'unresolved_taxonomy_value', 'target' => (string) $target);
				}
			}
		}

		return null;
	}

	/**
	 * Remove import-only media targets so the canonical property writer never sees remote URLs.
	 *
	 * @param array<string,mixed> $values Values mutated to persistence-only targets.
	 * @return array<string,mixed>
	 */
	private function extractMediaValues(array &$values): array
	{
		$mediaValues = array();
		foreach (array_keys($values) as $target) {
			$definition = TargetRegistry::definition((string) $target);
			if ($definition === null || ($definition['kind'] ?? '') !== 'media') {
				continue;
			}
			$mediaValues[(string) $target] = $values[$target];
			unset($values[$target]);
		}
		return $mediaValues;
	}

	/**
	 * @param array<string,mixed>                         $mediaValues Import-only media values.
	 * @param array<int,array{code:string,target:string}> $warnings Existing warnings, mutated with media warnings.
	 * @return array{code:string,target:string}|null
	 */
	private function processRemoteMedia(int $propertyId, array $mediaValues, array &$warnings): ?array
	{
		if ($mediaValues === array()) {
			return null;
		}
		if ($this->remoteMediaProcessor === null) {
			return array('code' => 'media_processor_unavailable', 'target' => 'media');
		}

		try {
			$warnings = array_values(array_merge($warnings, $this->remoteMediaProcessor->process($propertyId, $mediaValues)));
		} catch (RemoteMediaException $exception) {
			return array('code' => $exception->reason(), 'target' => 'media');
		} catch (Throwable) {
			return array('code' => 'media_processing_failed', 'target' => 'media');
		}

		return null;
	}

	/** @return array{code:string,target:string}|null */
	private function validateDryRunState(DryRunResult $dryRun, IdentityResolution $resolution): ?array
	{
		if ($dryRun->status() === DryRunResult::STATUS_NEW) {
			if ($dryRun->propertyId() !== null) {
				return array('code' => 'invalid_dry_run_state', 'target' => 'dry_run');
			}
			return null;
		}

		if ($dryRun->status() !== DryRunResult::STATUS_UPDATE) {
			return array('code' => 'invalid_dry_run_state', 'target' => 'dry_run');
		}

		$expectedPropertyId = $dryRun->propertyId();
		if ($expectedPropertyId === null || $expectedPropertyId < 1) {
			return array('code' => 'dry_run_property_missing', 'target' => 'dry_run');
		}
		if ($resolution->status() === IdentityResolution::NEW) {
			return array('code' => 'identity_missing_since_dry_run', 'target' => 'identity');
		}
		if ($resolution->propertyId() !== $expectedPropertyId) {
			return array('code' => 'identity_changed_since_dry_run', 'target' => 'identity');
		}
		return null;
	}

	/** @param array<string,mixed> $values Canonical values. */
	private function identityValue(array $values, string $target): string
	{
		if (!array_key_exists($target, $values) || $values[$target] === null) {
			return '';
		}
		$value = $values[$target];
		if (!is_scalar($value)) {
			return '';
		}
		return trim((string) $value);
	}

	/** @param array<int,array{code:string,target:string}> $warnings Warnings inherited from dry-run. */
	private function error(int $rowNumber, string $code, string $target, ?int $propertyId, array $warnings): RowExecutionResult
	{
		$result = RowExecutionResult::error($rowNumber, $code, $target, $propertyId, $warnings);
		$this->afterExecute($result);
		return $result;
	}

	private function beforeExecute(int $rowNumber, string $action, ?int $propertyId): void
	{
		if (function_exists('do_action')) {
			do_action('wla_inmo_import_before_row_execute', $rowNumber, $action, $propertyId);
		}
	}

	private function afterExecute(RowExecutionResult $result): void
	{
		if (function_exists('do_action')) {
			do_action('wla_inmo_import_after_row_execute', $result);
		}
	}
}
