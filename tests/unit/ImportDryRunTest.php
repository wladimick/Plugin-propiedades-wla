<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\DryRunEngine;
use WLA\Inmo\Import\DryRunResult;
use WLA\Inmo\Import\DryRunSummary;
use WLA\Inmo\Import\IdentityResolver;
use WLA\Inmo\Import\MappingException;
use WLA\Inmo\Import\MappingProfile;
use WLA\Inmo\Import\RowMapper;
use WLA\Inmo\Import\TargetRegistry;

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		return $text;
	}
}

final class ImportDryRunTest extends TestCase
{
	public function testTargetRegistryAndProfileRejectUnknownAndDuplicateTargets(): void
	{
		self::assertTrue(TargetRegistry::isAllowed('post.title'));
		self::assertTrue(TargetRegistry::isAllowed('meta.price_clp'));
		self::assertTrue(TargetRegistry::isAllowed('taxonomy.feature'));
		self::assertTrue(TargetRegistry::isPrivate('meta.internal_notes'));

		$this->expectMappingException(
			'unknown_target',
			static fn (): MappingProfile => new MappingProfile('portal_a', array('codigo' => 'meta.not_real'))
		);

		$this->expectMappingException(
			'duplicate_target',
			static fn (): MappingProfile => new MappingProfile(
				'portal_a',
				array('codigo_a' => 'meta.property_code', 'codigo_b' => 'meta.property_code')
			)
		);

		$this->expectMappingException(
			'unsupported_target',
			static fn (): MappingProfile => new MappingProfile('portal_a', array('galeria' => 'meta.gallery_ids'))
		);
	}

	public function testRowMapperNormalizesValuesAndPreservesEmptyCells(): void
	{
		$profile = new MappingProfile(
			'portal_a',
			array(
				'titulo' => 'post.title',
				'precio' => 'meta.price_clp',
				'estado' => 'meta.status',
				'visible' => 'meta.featured',
				'fecha' => 'meta.availability_date',
				'lat' => 'meta.latitude',
				'caracteristicas' => 'taxonomy.feature',
			),
			'',
			MappingProfile::EMPTY_PRESERVE,
			array('caracteristicas' => '|')
		);

		$mapped = (new RowMapper($profile))->map(
			7,
			array(
				'titulo' => ' Casa Central ',
				'precio' => '390000000',
				'estado' => 'available',
				'visible' => 'sí',
				'fecha' => '',
				'lat' => '-34.9812',
				'caracteristicas' => 'Piscina | Terraza',
			)
		);

		self::assertFalse($mapped->hasErrors());
		self::assertSame('Casa Central', $mapped->values()['post.title']);
		self::assertSame(390000000, $mapped->values()['meta.price_clp']);
		self::assertSame('available', $mapped->values()['meta.status']);
		self::assertTrue($mapped->values()['meta.featured']);
		self::assertSame(-34.9812, $mapped->values()['meta.latitude']);
		self::assertSame(array('Piscina', 'Terraza'), $mapped->values()['taxonomy.feature']);
		self::assertContains('meta.availability_date', $mapped->preservedTargets());
	}

	public function testRowMapperRejectsInvalidTypedValues(): void
	{
		$profile = new MappingProfile(
			'portal_a',
			array(
				'precio' => 'meta.price_clp',
				'moneda' => 'meta.currency_primary',
				'estado' => 'meta.status',
				'lat' => 'meta.latitude',
				'fecha' => 'meta.availability_date',
			)
		);
		$mapped = (new RowMapper($profile))->map(
			2,
			array('precio' => '-1', 'moneda' => 'EUR', 'estado' => 'inventado', 'lat' => '91', 'fecha' => '2026-02-31')
		);

		$codes = array_column($mapped->errors(), 'code');
		self::assertContains('invalid_non_negative_integer', $codes);
		self::assertContains('invalid_currency', $codes);
		self::assertContains('invalid_status', $codes);
		self::assertContains('invalid_latitude', $codes);
		self::assertContains('invalid_date', $codes);
	}

	public function testDryRunClassifiesNewAndUpdateAndResolvesTaxonomiesReadOnly(): void
	{
		$profile = new MappingProfile(
			'portal_a',
			array(
				'titulo' => 'post.title',
				'external_id' => 'meta.external_id',
				'codigo' => 'meta.property_code',
				'precio' => 'meta.price_clp',
				'operacion' => 'taxonomy.operation',
				'features' => 'taxonomy.feature',
			),
			'',
			MappingProfile::EMPTY_PRESERVE,
			array('features' => '|')
		);

		$resolver = new IdentityResolver(
			static fn (string $sourceKey, string $externalId): array => $sourceKey === 'portal_a' && $externalId === 'EXT-2' ? array(22) : array(),
			static fn (string $propertyCode): array => $propertyCode === 'COD-2' ? array(22) : array()
		);
		$taxonomyLookup = static function (string $taxonomy, string $value): array {
			$known = array(
				'wla_operation:Venta' => array(array('id' => 5, 'slug' => 'venta', 'name' => 'Venta')),
				'wla_feature:Piscina' => array(array('id' => 8, 'slug' => 'piscina', 'name' => 'Piscina')),
				'wla_feature:Terraza' => array(array('id' => 9, 'slug' => 'terraza', 'name' => 'Terraza')),
			);

			return $known[$taxonomy . ':' . $value] ?? array();
		};
		$currentSnapshot = static fn (int $propertyId): array => $propertyId === 22
			? array('post.title' => 'Casa 2', 'meta.price_clp' => 300, 'taxonomy.operation' => array('id' => 5, 'slug' => 'venta'))
			: array();

		$rows = array(
			array('row_number' => 2, 'data' => array('titulo' => 'Casa 1', 'external_id' => 'EXT-1', 'codigo' => 'COD-1', 'precio' => '100', 'operacion' => 'Venta', 'features' => 'Piscina|Terraza')),
			array('row_number' => 3, 'data' => array('titulo' => 'Casa 2', 'external_id' => 'EXT-2', 'codigo' => 'COD-2', 'precio' => '350', 'operacion' => 'Venta', 'features' => 'Piscina')),
		);
		$factory = static fn (): iterable => $rows;

		$results = iterator_to_array((new DryRunEngine($profile, $resolver, $taxonomyLookup, $currentSnapshot))->results($factory), false);

		self::assertSame(DryRunResult::STATUS_NEW, $results[0]->status());
		self::assertNull($results[0]->propertyId());
		self::assertSame(DryRunResult::STATUS_UPDATE, $results[1]->status());
		self::assertSame(22, $results[1]->propertyId());
		self::assertContains('meta.price_clp', $results[1]->changedTargets());
		self::assertNotContains('post.title', $results[1]->changedTargets());
		self::assertSame(array('id' => 5, 'slug' => 'venta'), $results[0]->values()['taxonomy.operation']);
		self::assertCount(2, $results[0]->values()['taxonomy.feature']);
	}

	public function testDryRunMarksAllDuplicateRowsAndUnknownTermsWithoutWriting(): void
	{
		$profile = new MappingProfile(
			'portal_a',
			array(
				'titulo' => 'post.title',
				'external_id' => 'meta.external_id',
				'codigo' => 'meta.property_code',
				'operacion' => 'taxonomy.operation',
				'features' => 'taxonomy.feature',
			),
			'',
			MappingProfile::EMPTY_PRESERVE,
			array('features' => '|')
		);
		$lookupCalls = 0;
		$resolver = new IdentityResolver(
			static fn (string $sourceKey, string $externalId): array => array(),
			static fn (string $propertyCode): array => array()
		);
		$taxonomyLookup = static function (string $taxonomy, string $value) use (&$lookupCalls): array {
			++$lookupCalls;
			if ($taxonomy === 'wla_feature') {
				return array();
			}

			return $value === 'Venta' ? array(array('id' => 5, 'slug' => 'venta')) : array();
		};
		$rows = array(
			array('row_number' => 2, 'data' => array('titulo' => 'A', 'external_id' => 'E-1', 'codigo' => 'C-1', 'operacion' => 'Venta', 'features' => 'Desconocida')),
			array('row_number' => 3, 'data' => array('titulo' => 'B', 'external_id' => 'E-1', 'codigo' => 'C-2', 'operacion' => 'Venta', 'features' => 'Desconocida')),
			array('row_number' => 4, 'data' => array('titulo' => 'C', 'external_id' => 'E-3', 'codigo' => 'C-2', 'operacion' => 'Venta', 'features' => 'Desconocida')),
		);
		$factory = static fn (): iterable => $rows;

		$results = iterator_to_array((new DryRunEngine($profile, $resolver, $taxonomyLookup))->results($factory), false);

		self::assertSame(DryRunResult::STATUS_ERROR, $results[0]->status());
		self::assertSame(DryRunResult::STATUS_ERROR, $results[1]->status());
		self::assertSame(DryRunResult::STATUS_ERROR, $results[2]->status());
		self::assertContains('duplicate_external_identity_in_file', array_column($results[0]->errors(), 'code'));
		self::assertContains('duplicate_external_identity_in_file', array_column($results[1]->errors(), 'code'));
		self::assertContains('duplicate_property_code_in_file', array_column($results[1]->errors(), 'code'));
		self::assertContains('duplicate_property_code_in_file', array_column($results[2]->errors(), 'code'));
		self::assertContains('unknown_feature_term', array_column($results[0]->warnings(), 'code'));
		self::assertGreaterThan(0, $lookupCalls);
	}

	public function testNewRowRequiresTitleAndPublicSerializationHidesPrivateFields(): void
	{
		$profile = new MappingProfile(
			'portal_a',
			array('titulo' => 'post.title', 'codigo' => 'meta.property_code', 'nota' => 'meta.internal_notes')
		);
		$resolver = new IdentityResolver(
			static fn (string $sourceKey, string $externalId): array => array(),
			static fn (string $propertyCode): array => array()
		);
		$factory = static fn (): iterable => array(
			array('row_number' => 2, 'data' => array('titulo' => '', 'codigo' => 'C-1', 'nota' => 'privado')),
		);
		$result = iterator_to_array((new DryRunEngine($profile, $resolver, static fn (string $taxonomy, string $value): array => array()))->results($factory), false)[0];

		self::assertSame(DryRunResult::STATUS_ERROR, $result->status());
		self::assertContains('title_required_for_new', array_column($result->errors(), 'code'));
		self::assertArrayNotHasKey('meta.internal_notes', $result->toArray(false)['values']);
		self::assertArrayHasKey('meta.internal_notes', $result->toArray(true)['values']);
	}

	public function testFiveThousandRowsCanBeSummarizedWithoutRetainingResults(): void
	{
		$profile = new MappingProfile('synthetic_feed', array('titulo' => 'post.title', 'codigo' => 'meta.property_code'));
		$resolver = new IdentityResolver(
			static fn (string $sourceKey, string $externalId): array => array(),
			static fn (string $propertyCode): array => array()
		);
		$factoryCalls = 0;
		$factory = static function () use (&$factoryCalls): iterable {
			++$factoryCalls;
			for ($index = 1; $index <= 5000; ++$index) {
				yield $index => array(
					'row_number' => $index + 1,
					'data' => array('titulo' => 'Propiedad ' . $index, 'codigo' => 'SYN-' . $index),
				);
			}
		};

		$summary = new DryRunSummary();
		foreach ((new DryRunEngine($profile, $resolver, static fn (string $taxonomy, string $value): array => array()))->results($factory) as $result) {
			$summary->consume($result);
		}

		self::assertSame(2, $factoryCalls);
		self::assertSame(array('new' => 5000, 'update' => 0, 'warnings' => 0, 'errors' => 0, 'skipped' => 0), $summary->toArray());
	}

	private function expectMappingException(string $reason, callable $callback): void
	{
		try {
			$callback();
			self::fail('Expected MappingException.');
		} catch (MappingException $exception) {
			self::assertSame($reason, $exception->reason());
		}
	}
}
