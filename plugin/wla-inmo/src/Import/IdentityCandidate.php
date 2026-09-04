<?php

namespace WLA\Inmo\Import;

final class IdentityCandidate
{
	private string $sourceKey;

	private string $externalId;

	private string $propertyCode;

	public function __construct(string $sourceKey, string $externalId = '', string $propertyCode = '')
	{
		$this->sourceKey = $sourceKey === '' ? '' : (new SourceKey($sourceKey))->value();
		$this->externalId = trim($externalId);
		$this->propertyCode = trim($propertyCode);
	}

	public function sourceKey(): string
	{
		return $this->sourceKey;
	}

	public function externalId(): string
	{
		return $this->externalId;
	}

	public function propertyCode(): string
	{
		return $this->propertyCode;
	}

	public function hasExternalIdentity(): bool
	{
		return $this->sourceKey !== '' && $this->externalId !== '';
	}

	public function hasIncompleteExternalIdentity(): bool
	{
		return $this->externalId !== '' && $this->sourceKey === '';
	}

	public function hasPropertyCode(): bool
	{
		return $this->propertyCode !== '';
	}
}
