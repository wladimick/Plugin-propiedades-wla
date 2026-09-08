<?php

namespace WLA\Inmo\Import;

use JsonException;

final class RollbackSnapshotCodec
{
	public static function encode(array $snapshot): string
	{
		try {
			return json_encode(
				self::normalize($snapshot),
				JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
			);
		} catch (JsonException $exception) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Throwable chaining only; no output occurs here.
			throw new RollbackException('rollback_snapshot_encode_failed', 'Rollback snapshot could not be encoded.', $exception);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/** @return array<string,mixed> */
	public static function decode(string $json): array
	{
		try {
			$decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
		} catch (JsonException $exception) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Throwable chaining only; no output occurs here.
			throw new RollbackException('rollback_snapshot_decode_failed', 'Rollback snapshot could not be decoded.', $exception);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if (!is_array($decoded)) {
			throw new RollbackException('rollback_snapshot_invalid', 'Rollback snapshot root must be an object.');
		}

		return self::normalize($decoded);
	}

	public static function hash(array $snapshot): string
	{
		return hash('sha256', self::encode($snapshot));
	}

	public static function hashJson(string $json): string
	{
		return self::hash(self::decode($json));
	}

	public static function equals(array $left, array $right): bool
	{
		return hash_equals(self::hash($left), self::hash($right));
	}

	private static function normalize(mixed $value): mixed
	{
		if (!is_array($value)) {
			return $value;
		}

		if (array_is_list($value)) {
			return array_map(array(self::class, 'normalize'), $value);
		}

		$normalized = array();
		$keys = array_map('strval', array_keys($value));
		sort($keys, SORT_STRING);
		foreach ($keys as $key) {
			$normalized[$key] = self::normalize($value[$key]);
		}

		return $normalized;
	}
}
