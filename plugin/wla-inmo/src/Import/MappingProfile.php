<?php

namespace WLA\Inmo\Import;

final class MappingProfile
{
	public const CONTRACT_VERSION = 1;
	public const EMPTY_PRESERVE = 'preserve';
	public const EMPTY_CLEAR = 'clear';

	private int $version;
	private SourceKey $sourceKey;
	private string $name;

	/** @var array<string,string> */
	private array $mapping;

	/** @var array<string,string> */
	private array $separators;

	private string $emptyPolicy;

	/**
	 * @param array<string,string> $mapping Source header => canonical target.
	 * @param array<string,string> $separators Source header => separator for multi-value targets.
	 */
	public function __construct(
		string $sourceKey,
		array $mapping,
		string $name = '',
		string $emptyPolicy = self::EMPTY_PRESERVE,
		array $separators = array(),
		int $version = self::CONTRACT_VERSION
	) {
		if ($version !== self::CONTRACT_VERSION) {
			throw new MappingException('unsupported_profile_version', 'Unsupported mapping profile version.');
		}

		if (!in_array($emptyPolicy, array(self::EMPTY_PRESERVE, self::EMPTY_CLEAR), true)) {
			throw new MappingException('invalid_empty_policy', 'Invalid empty-value policy.');
		}

		if ($mapping === array()) {
			throw new MappingException('empty_mapping', 'Mapping profile must include at least one column.');
		}

		$this->version = $version;
		$this->sourceKey = new SourceKey($sourceKey);
		$this->name = trim($name);
		$this->emptyPolicy = $emptyPolicy;
		$this->mapping = array();
		$this->separators = array();

		$targetSources = array();
		foreach ($mapping as $header => $target) {
			$header = HeaderNormalizer::normalize((string) $header);
			$target = trim((string) $target);

			if ($header === '') {
				throw new MappingException('invalid_source_header', 'Mapping contains an invalid source header.');
			}

			if ($target === 'meta.gallery_ids') {
				throw new MappingException('unsupported_target', 'Gallery attachment IDs are not importable in Phase 3.2.');
			}

			if (!TargetRegistry::isAllowed($target)) {
				throw new MappingException('unknown_target', 'Mapping contains an unknown canonical target.');
			}

			if (isset($this->mapping[$header])) {
				throw new MappingException('duplicate_source_header', 'Source header appears more than once after normalization.');
			}

			if (isset($targetSources[$target]) && !TargetRegistry::isMultiple($target)) {
				throw new MappingException('duplicate_target', 'Multiple source columns map to a single-value target.');
			}

			$this->mapping[$header] = $target;
			$targetSources[$target][] = $header;
		}

		foreach ($separators as $header => $separator) {
			$header = HeaderNormalizer::normalize((string) $header);
			$separator = (string) $separator;

			if (!isset($this->mapping[$header])) {
				throw new MappingException('separator_without_mapping', 'Separator references an unmapped source header.');
			}

			if (!TargetRegistry::isMultiple($this->mapping[$header])) {
				throw new MappingException('separator_for_single_target', 'Separator can only be configured for multi-value targets.');
			}

			if ($separator === '' || strlen($separator) > 8) {
				throw new MappingException('invalid_separator', 'Multi-value separator must contain between one and eight bytes.');
			}

			$this->separators[$header] = $separator;
		}
	}

	public function version(): int
	{
		return $this->version;
	}

	public function sourceKey(): string
	{
		return $this->sourceKey->value();
	}

	public function name(): string
	{
		return $this->name;
	}

	public function emptyPolicy(): string
	{
		return $this->emptyPolicy;
	}

	/**
	 * @return array<string,string>
	 */
	public function mapping(): array
	{
		return $this->mapping;
	}

	/**
	 * @return array<string,string>
	 */
	public function separators(): array
	{
		return $this->separators;
	}

	public function separatorFor(string $header): ?string
	{
		$header = HeaderNormalizer::normalize($header);

		return $this->separators[$header] ?? null;
	}

	/**
	 * @return array<int,string>
	 */
	public function sourceHeadersForTarget(string $target): array
	{
		$headers = array();
		foreach ($this->mapping as $header => $mappedTarget) {
			if ($mappedTarget === $target) {
				$headers[] = $header;
			}
		}

		return $headers;
	}
}
