<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Frontend\SearchFilters;

/** No WordPress bootstrap required for numeric/taxonomy allowlist normalization. */
final class FrontendSearchFiltersTest extends TestCase
{
	public function testEmptySearchHasNoAdditionalFilters(): void
	{
		self::assertSame(array(), SearchFilters::normalize(array()));
	}

	public function testOnlyAllowlistedNormalizedSlugsAreAccepted(): void
	{
		self::assertSame(
			array('operacion' => 'venta', 'comuna' => 'curico', 'tipo' => 'departamento'),
			SearchFilters::normalize(array(
				'operacion' => 'venta',
				'comuna' => 'curico',
				'tipo' => 'departamento',
				'unknown_filter' => 'private',
				'region' => 'MALICIOUS <>',
			))
		);
	}

	public function testArraysMalformedValuesAndInjectionAreRejected(): void
	{
		self::assertSame(
			array(),
			SearchFilters::normalize(array(
				'operacion' => array('venta', 'arriendo'),
				'tipo' => '../private',
				'moneda' => 'XSS',
				'precio_min' => '1 OR 1=1',
				'dormitorios' => '-5',
				'banos' => '1.5',
				'superficie_min' => '1e12',
				'admin_token' => 'keep',
			))
		);
	}

	public function testCurrencyAndPriceBoundsAreNormalized(): void
	{
		self::assertSame(
			array(
				'moneda' => 'UF',
				'precio_min' => '1200.50',
				'precio_max' => '2500',
				'dormitorios' => '2',
				'banos' => '1',
				'superficie_min' => '75.5',
			),
			SearchFilters::normalize(array(
				'moneda' => 'uf',
				'precio_min' => '2500',
				'precio_max' => '1200,50',
				'dormitorios' => '2',
				'banos' => '1',
				'superficie_min' => '75,5',
			))
		);
	}

	public function testPriceDefaultsToClpWhenCurrencyIsNotSupplied(): void
	{
		self::assertSame(
			array('precio_min' => '1000000', 'moneda' => 'CLP'),
			SearchFilters::normalize(array('precio_min' => '1000000'))
		);
	}
}
