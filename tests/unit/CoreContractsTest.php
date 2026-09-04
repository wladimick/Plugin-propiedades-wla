<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Core\Requirements;
use WLA\Inmo\Properties\Capabilities as PropertyCapabilities;
use WLA\Inmo\Taxonomies\Capabilities as TaxonomyCapabilities;

final class CoreContractsTest extends TestCase
{
	public function testMinimumPlatformContract(): void
	{
		self::assertFalse(Requirements::supportsPhp('8.0.30'));
		self::assertTrue(Requirements::supportsPhp('8.1.0'));
		self::assertFalse(Requirements::supportsWordPress('6.5.9'));
		self::assertTrue(Requirements::supportsWordPress('6.6.0'));
		self::assertSame(array(), Requirements::failures('8.3.0', '7.0.0'));
		self::assertCount(2, Requirements::failures('8.0.0', '6.5.0'));
	}

	public function testPropertyMetaCapabilitiesAreNotPrimitive(): void
	{
		self::assertSame(array(), array_values(array_intersect(PropertyCapabilities::meta(), PropertyCapabilities::primitive())));
		self::assertContains(PropertyCapabilities::EDIT_POSTS, PropertyCapabilities::primitive());
		self::assertNotContains(PropertyCapabilities::EDIT_POST, PropertyCapabilities::primitive());
	}

	public function testCapabilityContractsRemainNamespaced(): void
	{
		$capabilities = array_merge(
			PropertyCapabilities::all(),
			TaxonomyCapabilities::all(),
			AccessCapabilities::all()
		);

		self::assertSame($capabilities, array_values(array_unique($capabilities)));
		self::assertNotContains('manage_options', $capabilities);
		self::assertNotContains('edit_posts', $capabilities);

		foreach ($capabilities as $capability) {
			self::assertStringContainsString('wla_', $capability);
		}
	}
}
