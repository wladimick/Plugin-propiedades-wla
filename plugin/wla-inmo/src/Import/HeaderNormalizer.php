<?php

namespace WLA\Inmo\Import;

final class HeaderNormalizer
{
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
		$value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
		$value = preg_replace('/_+/', '_', $value) ?? '';

		return trim($value, '_');
	}
}
