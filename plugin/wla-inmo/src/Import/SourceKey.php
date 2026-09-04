<?php

namespace WLA\Inmo\Import;

use InvalidArgumentException;

final class SourceKey
{
	private const MAX_LENGTH = 64;

	private string $value;

	public function __construct(string $value)
	{
		$normalized = self::normalize($value);
		if (!self::isValid($normalized)) {
			throw new InvalidArgumentException('Invalid import source key.');
		}

		$this->value = $normalized;
	}

	public function value(): string
	{
		return $this->value;
	}

	public function __toString(): string
	{
		return $this->value;
	}

	public static function normalize(string $value): string
	{
		$value = trim($value);
		$value = strtr(
			$value,
			array(
				'á' => 'a',
				'é' => 'e',
				'í' => 'i',
				'ó' => 'o',
				'ú' => 'u',
				'ü' => 'u',
				'ñ' => 'n',
				'Á' => 'a',
				'É' => 'e',
				'Í' => 'i',
				'Ó' => 'o',
				'Ú' => 'u',
				'Ü' => 'u',
				'Ñ' => 'n',
			)
		);
		$value = strtolower($value);
		$value = preg_replace('/[^a-z0-9_-]+/', '_', $value) ?? '';
		$value = preg_replace('/_+/', '_', $value) ?? '';

		return trim($value, '_-');
	}

	public static function isValid(string $value): bool
	{
		$length = strlen($value);
		if ($length < 2 || $length > self::MAX_LENGTH) {
			return false;
		}

		return preg_match('/^[a-z0-9][a-z0-9_-]*[a-z0-9]$/', $value) === 1;
	}
}
