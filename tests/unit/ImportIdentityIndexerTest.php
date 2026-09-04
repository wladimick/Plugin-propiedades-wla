<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\IdentityIndexer;
use WLA\Inmo\Import\IdentityMeta;
use WLA\Inmo\Properties\MetaSchema;

final class ImportIdentityIndexerTest extends TestCase
{
	public function testIdentityIndexerWatchesEveryStableIdentityMetaKey(): void
	{
		self::assertSame(
			array(
				IdentityMeta::SOURCE_KEY_META,
				MetaSchema::META_PREFIX . 'external_id',
				MetaSchema::META_PREFIX . 'property_code',
			),
			IdentityIndexer::identityMetaKeys()
		);
	}
}
