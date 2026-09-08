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

		if (!$this->deleteIfCurrent($key, $current)) {
			return null;
		}

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

		$this->deleteIfCurrent($key, $current);
	}

	/**
	 * Compare-and-delete avoids deleting a newer lock if an expired owner races
	 * with a replacement between reading and releasing the option.
	 *
	 * @param array<string,mixed> $expected Exact option value previously read.
	 */
	private function deleteIfCurrent(string $key, array $expected): bool
	{
		global $wpdb;
		if (!isset($wpdb) || !isset($wpdb->options)) {
			return false;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- WordPress Options API has no compare-and-delete primitive; exact option_value match is required for lock safety and cache is invalidated below.
		$deleted = $wpdb->delete(
			$wpdb->options,
			array(
				'option_name'  => $key,
				'option_value' => maybe_serialize($expected),
			),
			array('%s', '%s')
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		if ($deleted !== 1) {
			return false;
		}

		wp_cache_delete($key, 'options');
		return true;
	}

	private static function key(string $batchUuid): string
	{
		return 'wla_inmo_rb_lock_' . sha1(strtolower(trim($batchUuid)));
	}
}
