<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\DryRunEngine;
use WLA\Inmo\Import\DryRunResult;
use WLA\Inmo\Import\IdentityResolver;
use WLA\Inmo\Import\MappingProfile;

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		unset($domain);
		return $text;
	}
}

final class ImportDryRunIdentityRegressionTest extends TestCase
{
	public function testDryRunRejectsNewRowsWithoutIdempotentIdentity(): void
	{
		$profile = new MappingProfile('portal_a', array('titulo' => 'post.title'));
		$resolver = new IdentityResolver(
			static fn (string $sourceKey, string $externalId): array => array(),
			static fn (string $propertyCode): array => array()
		);
		$factory = static fn (): iterable => array(
			array('row_number' => 2, 'data' => array('titulo' => 'Casa sin identidad')),
		);

		$result = iterator_to_array(
			(new DryRunEngine($profile, $resolver, static fn (string $taxonomy, string $value): array => array()))->results($factory),
			false
		)[0];

		self::assertSame(DryRunResult::STATUS_ERROR, $result->status());
		self::assertContains('missing_identity', array_column($result->errors(), 'code'));
	}
}
