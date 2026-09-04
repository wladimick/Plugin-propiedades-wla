<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\DryRunEngine;
use WLA\Inmo\Import\DryRunResult;
use WLA\Inmo\Import\HeaderNormalizer;
use WLA\Inmo\Import\IdentityResolver;
use WLA\Inmo\Import\MappingProfile;
use WLA\Inmo\Import\RowMapper;
use WLA\Inmo\Import\TargetRegistry;

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		return $text;
	}
}

final class ImportDryRunRegressionTest extends TestCase
{
	public function testMappingProfileUsesSameSpanishHeaderContractAsCsvReader(): void
	{
		self::assertSame('codigo_propiedad', HeaderNormalizer::normalize('CÓDIGO Propiedad'));
		self::assertSame('nandu_area_util', HeaderNormalizer::normalize('Ñandú Área Útil'));

		$profile = new MappingProfile(
			'portal_chile',
			array(
				'CÓDIGO Propiedad' => 'meta.property_code',
				'Ñandú Área Útil' => 'meta.usable_area_m2',
			)
		);

		self::assertSame(
			array(
				'codigo_propiedad' => 'meta.property_code',
				'nandu_area_util' => 'meta.usable_area_m2',
			),
			$profile->mapping()
		);

		$mapped = (new RowMapper($profile))->map(
			2,
			array('codigo_propiedad' => 'COD-Ñ-1', 'nandu_area_util' => '85.5')
		);
		self::assertFalse($mapped->hasErrors());
		self::assertSame('COD-Ñ-1', $mapped->values()['meta.property_code']);
		self::assertSame(85.5, $mapped->values()['meta.usable_area_m2']);
	}

	public function testGalleryAttachmentIdsAreNotCanonicalImportTargets(): void
	{
		self::assertFalse(TargetRegistry::isAllowed('meta.gallery_ids'));
		self::assertTrue(TargetRegistry::isAllowed('meta.video_urls'));
	}

	public function testUnknownFeaturePreservesWholeAssignmentInsteadOfProducingPartialUpdate(): void
	{
		$profile = new MappingProfile(
			'portal_a',
			array(
				'codigo' => 'meta.property_code',
				'features' => 'taxonomy.feature',
			),
			'',
			MappingProfile::EMPTY_PRESERVE,
			array('features' => '|')
		);
		$resolver = new IdentityResolver(
			static fn (string $sourceKey, string $externalId): array => array(),
			static fn (string $propertyCode): array => $propertyCode === 'COD-10' ? array(10) : array()
		);
		$taxonomyLookup = static function (string $taxonomy, string $value): array {
			if ($taxonomy === 'wla_feature' && $value === 'Piscina') {
				return array(array('id' => 8, 'slug' => 'piscina'));
			}

			return array();
		};
		$currentSnapshot = static fn (int $propertyId): array => $propertyId === 10
			? array(
				'meta.property_code' => 'COD-10',
				'taxonomy.feature' => array(array('id' => 99, 'slug' => 'jardin')),
			)
			: array();
		$rows = array(
			array(
				'row_number' => 2,
				'data' => array('codigo' => 'COD-10', 'features' => 'Piscina|Característica Nueva'),
			),
		);
		$factory = static fn (): iterable => $rows;

		$result = iterator_to_array(
			(new DryRunEngine($profile, $resolver, $taxonomyLookup, $currentSnapshot))->results($factory),
			false
		)[0];

		self::assertSame(DryRunResult::STATUS_UPDATE, $result->status());
		self::assertSame(10, $result->propertyId());
		self::assertContains('unknown_feature_term', array_column($result->warnings(), 'code'));
		self::assertContains('taxonomy.feature', $result->preservedTargets());
		self::assertArrayNotHasKey('taxonomy.feature', $result->values());
		self::assertNotContains('taxonomy.feature', $result->changedTargets());
	}
}
