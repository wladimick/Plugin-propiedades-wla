<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Frontend\TemplateResolver;

if (!defined('WLA_INMO_DIR')) {
	define('WLA_INMO_DIR', dirname(__DIR__, 2) . '/plugin/wla-inmo/');
}

final class FrontendTemplateResolverTest extends TestCase
{
	public function testOnlyPhaseFourFoundationTemplatesAreAllowlisted(): void
	{
		self::assertTrue(TemplateResolver::isSupportedTemplate('archive-property.php'));
		self::assertTrue(TemplateResolver::isSupportedTemplate('single-property.php'));
		self::assertFalse(TemplateResolver::isSupportedTemplate('parts/property-card.php'));
		self::assertFalse(TemplateResolver::isSupportedTemplate('page.php'));
	}

	public function testUnsafeTemplateNamesAreRejected(): void
	{
		self::assertFalse(TemplateResolver::isSupportedTemplate('../archive-property.php'));
		self::assertFalse(TemplateResolver::isSupportedTemplate('..\\archive-property.php'));
		self::assertFalse(TemplateResolver::isSupportedTemplate("archive-property.php\0evil"));
		self::assertFalse(TemplateResolver::isSupportedTemplate('/etc/passwd'));
	}

	public function testPluginFallbackPathIsDeterministicAndFailClosed(): void
	{
		self::assertSame(
			WLA_INMO_DIR . 'templates/archive-property.php',
			TemplateResolver::pluginFallbackPath('archive-property.php')
		);
		self::assertSame('', TemplateResolver::pluginFallbackPath('../archive-property.php'));
		self::assertSame('', TemplateResolver::pluginFallbackPath('unknown.php'));
	}
}
