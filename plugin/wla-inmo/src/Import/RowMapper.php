<?php

namespace WLA\Inmo\Import;

final class RowMapper
{
	private MappingProfile $profile;

	public function __construct(MappingProfile $profile)
	{
		$this->profile = $profile;
	}

	/**
	 * @param array<string,mixed> $sourceRow Normalized CSV source row.
	 */
	public function map(int $rowNumber, array $sourceRow): MappedRow
	{
		$values = array();
		$preserved = array();
		$errors = array();

		foreach ($this->profile->mapping() as $sourceHeader => $target) {
			$rawValue = array_key_exists($sourceHeader, $sourceRow) ? $sourceRow[$sourceHeader] : '';
			$isEmpty = self::isEmpty($rawValue);

			if ($isEmpty && $this->profile->emptyPolicy() === MappingProfile::EMPTY_PRESERVE) {
				$preserved[] = $target;
				continue;
			}

			$definition = TargetRegistry::definition($target);
			if ($definition === null) {
				$errors[] = array('code' => 'unknown_target', 'target' => $target);
				continue;
			}

			if ($isEmpty && $this->profile->emptyPolicy() === MappingProfile::EMPTY_CLEAR) {
				$values[$target] = TargetRegistry::isMultiple($target) ? array() : null;
				continue;
			}

			$normalized = ValueNormalizer::normalize($rawValue, $definition, $this->profile->separatorFor($sourceHeader));
			if (!$normalized->isValid()) {
				$errors[] = array(
					'code'   => (string) $normalized->errorCode(),
					'target' => $target,
				);
				continue;
			}

			$value = $normalized->value();
			if (TargetRegistry::isMultiple($target) && isset($values[$target])) {
				$existing = is_array($values[$target]) ? $values[$target] : array($values[$target]);
				$incoming = is_array($value) ? $value : array($value);
				$values[$target] = array_values(array_unique(array_merge($existing, $incoming), SORT_REGULAR));
			} else {
				$values[$target] = $value;
			}
		}

		return new MappedRow($rowNumber, $values, $preserved, $errors);
	}

	private static function isEmpty($value): bool
	{
		if ($value === null) {
			return true;
		}

		if (is_string($value)) {
			return trim($value) === '';
		}

		return is_array($value) && $value === array();
	}
}
