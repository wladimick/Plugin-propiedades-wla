<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Import\OptionRollbackLock;

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

$expect = static function (bool $condition, string $message) use ($fail): void {
	if (!$condition) {
		$fail($message);
	}
};

$uuid = strtolower(wp_generate_uuid4());
$key = 'wla_inmo_rb_lock_' . sha1($uuid);
$lock = new OptionRollbackLock();

delete_option($key);
add_option(
	$key,
	array(
		'token'   => 'expired-owner',
		'expires' => time() - 60,
	),
	'',
	false
);

$token = $lock->acquire($uuid, 30);
$expect(is_string($token) && strlen($token) === 32, 'Expired lock could not be safely replaced.');
$current = get_option($key, null);
$expect(is_array($current) && hash_equals((string) ($current['token'] ?? ''), $token), 'Replacement lock token was not persisted.');

$lock->release($uuid, 'wrong-owner-token');
$currentAfterWrongRelease = get_option($key, null);
$expect(is_array($currentAfterWrongRelease) && hash_equals((string) ($currentAfterWrongRelease['token'] ?? ''), $token), 'Non-owner release removed the active rollback lock.');

$contender = $lock->acquire($uuid, 30);
$expect($contender === null, 'A second runner acquired an active rollback lock.');

$lock->release($uuid, $token);
$expect(get_option($key, null) === null, 'Owner release did not remove rollback lock.');

$nextToken = $lock->acquire($uuid, 30);
$expect(is_string($nextToken) && $nextToken !== $token, 'Lock could not be reacquired after owner release.');
$lock->release($uuid, $nextToken);
$expect(get_option($key, null) === null, 'Final rollback lock cleanup failed.');

echo "WLA Inmo rollback lock integration tests passed.\n";
