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

	public function testDuplicateScanUsesCanonicalIdentityNormalization(): void
	{
		$profile = new MappingProfile(
			'portal_a',
			array('titulo' => 'post.title', 'codigo' => 'meta.property_code')
		);
		$resolver = new IdentityResolver(
			static fn (string $sourceKey, string $externalId): array => array(),
			static fn (string $propertyCode): array => array()
		);
		$rows = array(
			array('row_number' => 2, 'data' => array('titulo' => 'A', 'codigo' => '  C-1  ')),
			array('row_number' => 3, 'data' => array('titulo' => 'B', 'codigo' => '<b>C-1</b>')),
		);
		$factory = static fn (): iterable => $rows;

		$results = iterator_to_array(
			(new DryRunEngine($profile, $resolver, static fn (string $taxonomy, string $value): array => array()))->results($factory),
			false
		);

		self::assertCount(2, $results);
		self::assertContains('duplicate_property_code_in_file', array_column($results[0]->errors(), 'code'));
		self::assertContains('duplicate_property_code_in_file', array_column($results[1]->errors(), 'code'));
	}

	public function testEmptyClearTaxonomyBypassesLookupAndRemainsClearIntent(): void
	{
		$profile = new MappingProfile(
			'portal_a',
			array('codigo' => 'meta.property_code', 'operacion' => 'taxonomy.operation'),
			'',
			MappingProfile::EMPTY_CLEAR
		);
		$resolver = new IdentityResolver(
			static fn (string $sourceKey, string $externalId): array => array(),
			static fn (string $propertyCode): array => $propertyCode === 'COD-20' ? array(20) : array()
		);
		$lookupCalls = 0;
		$taxonomyLookup = static function (string $taxonomy, string $value) use (&$lookupCalls): array {
			++$lookupCalls;
			return array();
		};
		$currentSnapshot = static fn (int $propertyId): array => $propertyId === 20
			? array(
				'meta.property_code' => 'COD-20',
				'taxonomy.operation' => array('id' => 5, 'slug' => 'venta'),
			)
			: array();
		$factory = static fn (): iterable => array(
			array('row_number' => 2, 'data' => array('codigo' => 'COD-20', 'operacion' => '')),
		);

		$result = iterator_to_array(
			(new DryRunEngine($profile, $resolver, $taxonomyLookup, $currentSnapshot))->results($factory),
			false
		)[0];

		self::assertSame(DryRunResult::STATUS_UPDATE, $result->status());
		self::assertSame(0, $lookupCalls);
		self::assertArrayHasKey('taxonomy.operation', $result->values());
		self::assertNull($result->values()['taxonomy.operation']);
		self::assertContains('taxonomy.operation', $result->changedTargets());
	}

	public function testMultiSourceFeatureIsNotPreservedWhenAnySourceHasValue(): void
	{
		$profile = new MappingProfile(
			'portal_a',
			array(
				'feature_a' => 'taxonomy.feature',
				'feature_b' => 'taxonomy.feature',
			)
		);
		$mapped = (new RowMapper($profile))->map(
			2,
			array('feature_a' => '', 'feature_b' => 'Piscina')
		);

		self::assertFalse($mapped->hasErrors());
		self::assertSame(array('Piscina'), $mapped->values()['taxonomy.feature']);
		self::assertNotContains('taxonomy.feature', $mapped->preservedTargets());
	}

	public function testTextareaMetadataKeepsLineBreaks(): void
	{
		self::assertSame('textarea', TargetRegistry::definition('meta.location_text')['validator']);
		self::assertSame('textarea', TargetRegistry::definition('meta.internal_notes')['validator']);

		$profile = new MappingProfile(
			'portal_a',
			array('ubicacion' => 'meta.location_text', 'nota' => 'meta.internal_notes')
		);
		$mapped = (new RowMapper($profile))->map(
			2,
			array('ubicacion' => "Línea 1\nLínea 2", 'nota' => "Privada 1\nPrivada 2")
		);

		self::assertSame("Línea 1\nLínea 2", $mapped->values()['meta.location_text']);
		self::assertSame("Privada 1\nPrivada 2", $mapped->values()['meta.internal_notes']);
	}
}
