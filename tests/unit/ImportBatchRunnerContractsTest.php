<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use WLA\Inmo\Import\BatchRunResult;
use WLA\Inmo\Import\MappingException;
use WLA\Inmo\Import\MappingProfile;
use WLA\Inmo\Import\MappingProfileCodec;

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		return $text;
	}
}

final class ImportBatchRunnerContractsTest extends TestCase
{
	public function testMappingProfileCodecRoundTripsCanonicalProfile(): void
	{
		$profile = new MappingProfile(
			'portal_martinez',
			array(
				'titulo' => 'post.title',
				'codigo' => 'meta.property_code',
				'caracteristicas' => 'taxonomy.feature',
			),
			'Portal Martínez',
			MappingProfile::EMPTY_PRESERVE,
			array('caracteristicas' => '|')
		);

		$json = MappingProfileCodec::encode($profile);
		$decoded = MappingProfileCodec::decode($json);

		self::assertSame(MappingProfile::CONTRACT_VERSION, $decoded->version());
		self::assertSame('portal_martinez', $decoded->sourceKey());
		self::assertSame('Portal Martínez', $decoded->name());
		self::assertSame(MappingProfile::EMPTY_PRESERVE, $decoded->emptyPolicy());
		self::assertSame($profile->mapping(), $decoded->mapping());
		self::assertSame(array('caracteristicas' => '|'), $decoded->separators());
	}

	public function testMappingProfileCodecRejectsInvalidSnapshots(): void
	{
		try {
			MappingProfileCodec::decode('{bad json');
			self::fail('Invalid profile JSON should fail.');
		} catch (MappingException $exception) {
			self::assertSame('invalid_profile_json', $exception->reason());
		}

		try {
			MappingProfileCodec::decode('{"version":1,"source_key":12,"mapping":{}}');
			self::fail('Invalid profile field types should fail.');
		} catch (MappingException $exception) {
			self::assertSame('invalid_profile_snapshot', $exception->reason());
		}

		try {
			MappingProfileCodec::decode('{"version":1,"source_key":"!","name":"Bad","empty_policy":"preserve","mapping":{"titulo":"post.title"},"separators":{}}');
			self::fail('Invalid persisted source key should fail as a mapping error.');
		} catch (MappingException $exception) {
			self::assertSame('invalid_profile_source_key', $exception->reason());
		}
	}

	public function testBatchRunResultContainsOnlyOperationalCodes(): void
	{
		$result = new BatchRunResult(
			'11111111-1111-4111-8111-111111111111',
			BatchRunResult::STATUS_FAILED,
			2,
			8,
			14,
			'row_validation_failed',
			12,
			array('invalid_status', 'invalid_status', '', 'unknown_taxonomy_term')
		);

		self::assertFalse($result->isSuccessful());
		self::assertSame(2, $result->processedThisRun());
		self::assertSame(8, $result->cursorRow());
		self::assertSame(14, $result->revision());
		self::assertSame(array('invalid_status', 'unknown_taxonomy_term'), $result->rowCodes());
		self::assertSame('row_validation_failed', $result->toArray()['reason']);
		self::assertArrayNotHasKey('values', $result->toArray());
	}
}
