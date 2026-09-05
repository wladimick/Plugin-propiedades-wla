<?php

namespace WLA\Inmo\Import;

use JsonException;

final class MappingProfileCodec
{
	public static function encode(MappingProfile $profile): string
	{
		try {
			return json_encode(
				array(
					'version'      => $profile->version(),
					'source_key'   => $profile->sourceKey(),
					'name'         => $profile->name(),
					'empty_policy' => $profile->emptyPolicy(),
					'mapping'      => $profile->mapping(),
					'separators'   => $profile->separators(),
				),
				JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			);
		} catch (JsonException) {
			throw new MappingException('profile_encode_failed', 'Mapping profile could not be encoded.');
		}
	}

	public static function decode(string $json): MappingProfile
	{
		try {
			$payload = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
		} catch (JsonException) {
			throw new MappingException('invalid_profile_json', 'Mapping profile JSON is invalid.');
		}

		if (!is_array($payload)) {
			throw new MappingException('invalid_profile_snapshot', 'Mapping profile snapshot must be an object.');
		}

		$version = $payload['version'] ?? null;
		$sourceKey = $payload['source_key'] ?? null;
		$name = $payload['name'] ?? '';
		$emptyPolicy = $payload['empty_policy'] ?? MappingProfile::EMPTY_PRESERVE;
		$mapping = $payload['mapping'] ?? null;
		$separators = $payload['separators'] ?? array();

		if (!is_int($version)
			|| !is_string($sourceKey)
			|| !is_string($name)
			|| !is_string($emptyPolicy)
			|| !is_array($mapping)
			|| !is_array($separators)) {
			throw new MappingException('invalid_profile_snapshot', 'Mapping profile snapshot has invalid field types.');
		}

		$normalizedMapping = self::stringMap($mapping);
		$normalizedSeparators = self::stringMap($separators);

		try {
			return new MappingProfile(
				$sourceKey,
				$normalizedMapping,
				$name,
				$emptyPolicy,
				$normalizedSeparators,
				$version
			);
		} catch (MappingException $exception) {
			throw $exception;
		} catch (\InvalidArgumentException) {
			throw new MappingException('invalid_profile_source_key', 'Mapping profile source key is invalid.');
		}
	}

	/**
	 * @param array<mixed> $values Raw decoded object.
	 * @return array<string,string>
	 */
	private static function stringMap(array $values): array
	{
		$normalized = array();
		foreach ($values as $key => $value) {
			if (!is_string($key) || !is_string($value)) {
				throw new MappingException('invalid_profile_snapshot', 'Mapping profile maps must contain string pairs.');
			}

			$normalized[$key] = $value;
		}

		return $normalized;
	}
}
