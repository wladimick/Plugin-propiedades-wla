<?php

namespace WLA\Inmo\Import;

final class OptionRollbackLock implements RollbackLockInterface
{
	public function acquire(string $batchUuid, int $ttlSeconds = 30): ?string
	{
		$ttlSeconds = max(10, min(120, $ttlSeconds));
		$key = self::key($batchUuid);
		$token = bin2hex(random_bytes(16));
		$value = array(
			'token'   => $token,
			'expires' => time() + $ttlSeconds,
		);

		if (add_option($key, $value, '', false)) {
			return $token;
		}

		$current = get_option($key, null);
		if (!is_array($current) || (int) ($current['expires'] ?? 0) >= time()) {
			return null;
		}

		delete_option($key);

		return add_option($key, $value, '', false) ? $token : null;
	}

	public function release(string $batchUuid, string $token): void
	{
		if ($token === '') {
			return;
		}

		$key = self::key($batchUuid);
		$current = get_option($key, null);
		if (!is_array($current) || !hash_equals((string) ($current['token'] ?? ''), $token)) {
			return;
		}

		delete_option($key);
	}

	private static function key(string $batchUuid): string
	{
		return 'wla_inmo_rb_lock_' . sha1(strtolower(trim($batchUuid)));
	}
}
