<?php

namespace WLA\Inmo\Import;

final class IdentityResolver
{
	/** @var callable(string,string):array<int,int> */
	private $findByExternalIdentity;

	/** @var callable(string):array<int,int> */
	private $findByPropertyCode;

	/**
	 * @param callable(string,string):array<int,int> $findByExternalIdentity Read-only external identity lookup.
	 * @param callable(string):array<int,int>        $findByPropertyCode Read-only property-code lookup.
	 */
	public function __construct(callable $findByExternalIdentity, callable $findByPropertyCode)
	{
		$this->findByExternalIdentity = $findByExternalIdentity;
		$this->findByPropertyCode = $findByPropertyCode;
	}

	public function resolve(IdentityCandidate $candidate): IdentityResolution
	{
		if ($candidate->hasIncompleteExternalIdentity()) {
			return IdentityResolution::conflict('external_id_without_source_key');
		}

		$externalMatches = array();
		if ($candidate->hasExternalIdentity()) {
			$externalMatches = self::normalizeIds(
				($this->findByExternalIdentity)(
					$candidate->sourceKey(),
					$candidate->externalId()
				)
			);
		}

		$codeMatches = array();
		if ($candidate->hasPropertyCode()) {
			$codeMatches = self::normalizeIds(
				($this->findByPropertyCode)($candidate->propertyCode())
			);
		}

		if (count($externalMatches) > 1) {
			return IdentityResolution::conflict('external_identity_not_unique');
		}

		if (count($codeMatches) > 1) {
			return IdentityResolution::conflict('property_code_not_unique');
		}

		$externalId = $externalMatches[0] ?? null;
		$codeId = $codeMatches[0] ?? null;

		if ($externalId !== null && $codeId !== null && $externalId !== $codeId) {
			return IdentityResolution::conflict('identity_disagreement');
		}

		if ($externalId !== null) {
			return IdentityResolution::match($externalId, 'external_identity');
		}

		if ($codeId !== null) {
			return IdentityResolution::match($codeId, 'property_code');
		}

		return IdentityResolution::newProperty();
	}

	/**
	 * @param array<int,mixed> $ids Raw IDs.
	 * @return array<int,int>
	 */
	private static function normalizeIds(array $ids): array
	{
		$normalized = array();
		foreach ($ids as $id) {
			$id = (int) $id;
			if ($id > 0) {
				$normalized[] = $id;
			}
		}

		$normalized = array_values(array_unique($normalized));
		sort($normalized, SORT_NUMERIC);

		return $normalized;
	}
}
