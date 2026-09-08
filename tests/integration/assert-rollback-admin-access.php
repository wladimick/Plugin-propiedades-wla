<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Access\RoleMatrix;
use WLA\Inmo\Admin\RollbackAdmin;
use WLA\Inmo\Import\BatchRepository;

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

$expect = static function (bool $condition, string $message) use ($fail): void {
	if (!$condition) {
		$fail($message);
	}
};

$ownerId = wp_insert_user(
	array(
		'user_login' => 'rollback-owner-' . wp_generate_password(8, false, false),
		'user_email' => 'rollback-owner-' . wp_generate_password(8, false, false) . '@example.test',
		'user_pass'  => wp_generate_password(24, true, true),
		'role'       => RoleMatrix::ROLE_MANAGER,
	)
);
$delegateId = wp_insert_user(
	array(
		'user_login' => 'rollback-delegate-' . wp_generate_password(8, false, false),
		'user_email' => 'rollback-delegate-' . wp_generate_password(8, false, false) . '@example.test',
		'user_pass'  => wp_generate_password(24, true, true),
		'role'       => RoleMatrix::ROLE_MANAGER,
	)
);
if (is_wp_error($ownerId) || is_wp_error($delegateId) || (int) $ownerId < 1 || (int) $delegateId < 1) {
	$fail('Unable to create rollback access fixtures.');
}
$ownerId = (int) $ownerId;
$delegateId = (int) $delegateId;

$delegate = get_user_by('id', $delegateId);
if (!$delegate instanceof WP_User) {
	$fail('Unable to resolve rollback delegate fixture.');
}
$delegate->add_cap(AccessCapabilities::ROLLBACK_IMPORTS, true);
$expect($delegate->has_cap(AccessCapabilities::ROLLBACK_IMPORTS), 'Delegate fixture is missing explicit rollback capability.');
$expect(!$delegate->has_cap(AccessCapabilities::MANAGE_TOOLS), 'Delegate fixture unexpectedly has manage-tools bypass.');

$hash = str_repeat('a', 64);
$uuid = (new BatchRepository())->create(
	'rollback_access',
	$hash,
	'{"version":1}',
	0,
	$ownerId
);
$expect(is_string($uuid) && $uuid !== '', 'Unable to create access-control batch fixture.');

$reflection = new ReflectionClass(RollbackAdmin::class);
$canAccess = $reflection->getMethod('canAccessBatch');
$accessibleBatch = $reflection->getMethod('accessibleBatch');
$batch = (new BatchRepository())->find($uuid);
$expect(is_array($batch), 'Unable to reload access-control batch fixture.');

wp_set_current_user($delegateId);
$expect(current_user_can(AccessCapabilities::ROLLBACK_IMPORTS), 'Delegate lost explicit rollback capability.');
$expect(!$canAccess->invoke(null, $batch), 'Rollback IDOR policy allowed a delegated user to access another users batch.');
$expect($accessibleBatch->invoke(null, $uuid) === null, 'Rollback accessibleBatch exposed another users batch.');

wp_set_current_user($ownerId);
$expect($canAccess->invoke(null, $batch), 'Batch owner cannot access own rollback context.');
$expect(is_array($accessibleBatch->invoke(null, $uuid)), 'Batch owner cannot resolve own rollback batch.');

$admin = get_user_by('login', 'admin');
if (!$admin instanceof WP_User) {
	$fail('Unable to resolve CI administrator.');
}
wp_set_current_user($admin->ID);
$expect(current_user_can(AccessCapabilities::MANAGE_TOOLS), 'Administrator is missing manage-tools bypass.');
$expect($canAccess->invoke(null, $batch), 'Administrator cannot inspect rollback batch across owners.');

$constants = $reflection->getConstants();
$previewNonce = (string) ($constants['NONCE_PREVIEW'] ?? '');
$confirmNonce = (string) ($constants['NONCE_CONFIRM'] ?? '');
$runNonce = (string) ($constants['NONCE_RUN'] ?? '');
$expect($previewNonce !== '' && $confirmNonce !== '' && $runNonce !== '', 'Rollback nonce contracts are missing.');
$expect(count(array_unique(array($previewNonce, $confirmNonce, $runNonce))) === 3, 'Rollback destructive actions do not use distinct nonce actions.');
$expect(wp_verify_nonce(wp_create_nonce($previewNonce), $previewNonce) !== false, 'Preview nonce contract is invalid.');
$expect(wp_verify_nonce(wp_create_nonce($confirmNonce), $confirmNonce) !== false, 'Confirm nonce contract is invalid.');
$expect(wp_verify_nonce(wp_create_nonce($runNonce), $runNonce) !== false, 'Run nonce contract is invalid.');
$expect(wp_verify_nonce('definitely-invalid', $confirmNonce) === false, 'Invalid rollback confirmation nonce unexpectedly verified.');

echo "WLA Inmo rollback admin access integration tests passed.\n";
