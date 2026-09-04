<?php

namespace WLA\Inmo\Import;

use Generator;

final class DryRunEngine
{
	private MappingProfile $profile;
	private IdentityResolver $identityResolver;

	/** @var callable(string,string):array<int,array<string,mixed>> */
	private $taxonomyLookup;

	/** @var callable(int):array<string,mixed>|null */
	private $currentSnapshot;

	/**
	 * @param callable(string,string):array<int,array<string,mixed>> $taxonomyLookup Read-only taxonomy lookup.
	 * @param callable(int):array<string,mixed>|null                 $currentSnapshot Read-only current canonical values lookup.
	 */
	public function __construct(
		MappingProfile $profile,
		IdentityResolver $identityResolver,
		callable $taxonomyLookup,
		?callable $currentSnapshot = null
	) {
		$this->profile = $profile;
		$this->identityResolver = $identityResolver;
		$this->taxonomyLookup = $taxonomyLookup;
		$this->currentSnapshot = $currentSnapshot;
	}

	/**
	 * Run a deterministic two-pass dry-run. The factory must return a fresh iterable each time.
	 *
	 * The first pass stores only identity fingerprints/row numbers so every row participating
	 * in an intra-file duplicate can be marked without retaining complete source payloads.
	 *
	 * @param callable():iterable<int,array{row_number:int,data:array<string,mixed>}> $rowFactory Fresh row iterable factory.
	 * @return Generator<int,DryRunResult>
	 */
	public function results(callable $rowFactory): Generator
	{
		$duplicates = $this->scanDuplicates($rowFactory());
		$mapper = new RowMapper($this->profile);
		$position = 0;

		foreach ($rowFactory() as $row) {
			++$position;
			$rowNumber = (int) $row['row_number'];
			$mapped = $mapper->map($rowNumber, $row['data']);
			$values = $mapped->values();
			$errors = $mapped->errors();
			$warnings = array();
			$preservedTargets = $mapped->preservedTargets();

			if (isset($duplicates['external'][$rowNumber])) {
				$errors[] = array('code' => 'duplicate_external_identity_in_file', 'target' => 'meta.external_id');
			}
			if (isset($duplicates['code'][$rowNumber])) {
				$errors[] = array('code' => 'duplicate_property_code_in_file', 'target' => 'meta.property_code');
			}

			$this->resolveTaxonomies($values, $warnings, $errors, $preservedTargets);

			$propertyId = null;
			$status = DryRunResult::STATUS_ERROR;
			$changedTargets = array();

			if ($errors === array()) {
				$externalId = isset($values['meta.external_id']) && is_scalar($values['meta.external_id'])
					? trim((string) $values['meta.external_id'])
					: '';
				$propertyCode = isset($values['meta.property_code']) && is_scalar($values['meta.property_code'])
					? trim((string) $values['meta.property_code'])
					: '';

				$resolution = $this->identityResolver->resolve(
					new IdentityCandidate($this->profile->sourceKey(), $externalId, $propertyCode)
				);

				if ($resolution->status() === IdentityResolution::CONFLICT) {
					$errors[] = array('code' => (string) $resolution->reason(), 'target' => 'identity');
				} elseif ($resolution->status() === IdentityResolution::MATCH) {
					$status = DryRunResult::STATUS_UPDATE;
					$propertyId = $resolution->propertyId();
					if ($propertyId !== null) {
						$changedTargets = $this->changedTargets($propertyId, $values, $preservedTargets);
					}
				} else {
					$status = DryRunResult::STATUS_NEW;
					$title = $values[TargetRegistry::POST_TITLE] ?? '';
					if (!is_string($title) || trim($title) === '') {
						$errors[] = array('code' => 'title_required_for_new', 'target' => TargetRegistry::POST_TITLE);
						$status = DryRunResult::STATUS_ERROR;
					}
				}
			}

			if ($errors !== array()) {
				$status = DryRunResult::STATUS_ERROR;
			}

			yield $position => new DryRunResult(
				$rowNumber,
				$status,
				$propertyId,
				$values,
				$preservedTargets,
				$changedTargets,
				$warnings,
				$errors
			);
		}
	}

	/**
	 * @param iterable<int,array{row_number:int,data:array<string,mixed>}> $rows First-pass rows.
	 * @return array{external:array<int,bool>,code:array<int,bool>}
	 */
	private function scanDuplicates(iterable $rows): array
	{
		$externalSeen = array();
		$codeSeen = array();
		$duplicateExternal = array();
		$duplicateCode = array();

		foreach ($rows as $row) {
			$rowNumber = (int) $row['row_number'];
			$data = $row['data'];
			$externalId = $this->identitySourceValue($data, 'meta.external_id');
			$propertyCode = $this->identitySourceValue($data, 'meta.property_code');

			if ($externalId !== '') {
				$key = $this->profile->sourceKey() . "\0" . $externalId;
				if (isset($externalSeen[$key])) {
					$duplicateExternal[(int) $externalSeen[$key]] = true;
					$duplicateExternal[$rowNumber] = true;
				} else {
					$externalSeen[$key] = $rowNumber;
				}
			}

			if ($propertyCode !== '') {
				if (isset($codeSeen[$propertyCode])) {
					$duplicateCode[(int) $codeSeen[$propertyCode]] = true;
					$duplicateCode[$rowNumber] = true;
				} else {
					$codeSeen[$propertyCode] = $rowNumber;
				}
			}
		}

		return array('external' => $duplicateExternal, 'code' => $duplicateCode);
	}

	/**
	 * Resolve an identity source through the exact same canonical value normalizer used by RowMapper.
	 *
	 * @param array<string,mixed> $sourceRow Source data.
	 */
	private function identitySourceValue(array $sourceRow, string $target): string
	{
		$headers = $this->profile->sourceHeadersForTarget($target);
		if ($headers === array()) {
			return '';
		}

		$definition = TargetRegistry::definition($target);
		if ($definition === null) {
			return '';
		}

		$raw = $sourceRow[$headers[0]] ?? '';
		if ($raw === null || (is_string($raw) && trim($raw) === '')) {
			return '';
		}

		$normalized = ValueNormalizer::normalize($raw, $definition, $this->profile->separatorFor($headers[0]));
		if (!$normalized->isValid() || !is_scalar($normalized->value())) {
			return '';
		}

		return trim((string) $normalized->value());
	}

	/**
	 * @param array<string,mixed>                         $values Normalized values, mutated to resolved term references.
	 * @param array<int,array{code:string,target:string}> $warnings Warnings.
	 * @param array<int,array{code:string,target:string}> $errors Errors.
	 * @param array<int,string>                           $preservedTargets Targets intentionally preserved.
	 */
	private function resolveTaxonomies(array &$values, array &$warnings, array &$errors, array &$preservedTargets): void
	{
		foreach ($values as $target => $value) {
			$definition = TargetRegistry::definition($target);
			if ($definition === null || ($definition['kind'] ?? '') !== 'taxonomy') {
				continue;
			}

			// EMPTY_CLEAR is an explicit canonical intent. It must never be looked up as a term.
			if ($value === null || (is_array($value) && $value === array())) {
				continue;
			}

			$taxonomy = (string) $definition['taxonomy'];
			$items = is_array($value) ? $value : array($value);
			$resolved = array();
			$preserveWholeTarget = false;

			foreach ($items as $item) {
				$matches = ($this->taxonomyLookup)($taxonomy, (string) $item);
				if (count($matches) === 0) {
					if ($target === 'taxonomy.feature') {
						$warnings[] = array('code' => 'unknown_feature_term', 'target' => $target);
						$preserveWholeTarget = true;
					} else {
						$errors[] = array('code' => 'unknown_taxonomy_term', 'target' => $target);
					}
					continue;
				}

				if (count($matches) > 1) {
					$errors[] = array('code' => 'ambiguous_taxonomy_term', 'target' => $target);
					continue;
				}

				$match = $matches[0];
				$id = isset($match['id']) ? (int) $match['id'] : 0;
				$slug = isset($match['slug']) ? trim((string) $match['slug']) : '';
				if ($id < 1 || $slug === '') {
					$errors[] = array('code' => 'invalid_taxonomy_lookup_result', 'target' => $target);
					continue;
				}

				$resolved[] = array('id' => $id, 'slug' => $slug);
			}

			if ($preserveWholeTarget) {
				unset($values[$target]);
				$preservedTargets[] = $target;
				continue;
			}

			$values[$target] = !empty($definition['multiple']) ? $resolved : ($resolved[0] ?? null);
		}

		$preservedTargets = array_values(array_unique($preservedTargets));
	}

	/**
	 * @param array<string,mixed> $values Normalized new values.
	 * @param array<int,string>    $preservedTargets Preserved targets.
	 * @return array<int,string>
	 */
	private function changedTargets(int $propertyId, array $values, array $preservedTargets): array
	{
		if ($this->currentSnapshot === null) {
			return array();
		}

		$current = ($this->currentSnapshot)($propertyId);
		$changed = array();
		foreach ($values as $target => $value) {
			if (in_array($target, $preservedTargets, true)) {
				continue;
			}

			if (!array_key_exists($target, $current) || $current[$target] !== $value) {
				$changed[] = $target;
			}
		}

		return $changed;
	}
}
