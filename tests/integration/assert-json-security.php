<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Admin\JsonImportExportPage;

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};
$expect = static function (bool $condition, string $message) use ($fail): void {
	if (!$condition) {
		$fail($message);
	}
};

final class WlaJsonSecurityDie extends RuntimeException
{
	public int $response;

	public function __construct(int $response)
	{
		parent::__construct('Intercepted wp_die response ' . $response);
		$this->response = $response;
	}
}

$dieHandlerFilter = static function (): callable {
	return static function ($message, $title = '', $args = array()): void {
		$response = is_array($args) ? (int) ($args['response'] ?? 500) : 500;
		throw new WlaJsonSecurityDie($response);
	};
};
add_filter('wp_die_handler', $dieHandlerFilter);

$admin = get_user_by('login', 'admin');
$expect($admin instanceof WP_User, 'JSON security fixture cannot find admin user.');

$suffix = substr(str_replace('-', '', wp_generate_uuid4()), 0, 8);
$username = 'json_sec_' . strtolower($suffix);
$userId = wp_create_user($username, wp_generate_password(24, true, true), $username . '@example.test');
$expect(!is_wp_error($userId) && (int) $userId > 0, 'Unable to create low-privilege JSON security user.');
$subscriber = get_user_by('id', (int) $userId);
$expect($subscriber instanceof WP_User, 'Unable to load low-privilege JSON security user.');
$subscriber->set_role('subscriber');

wp_set_current_user((int) $userId);
$capabilityDenied = false;
try {
	JsonImportExportPage::render();
} catch (WlaJsonSecurityDie $exception) {
	$capabilityDenied = $exception->response === 403;
}
$expect($capabilityDenied, 'JSON page did not deny a user without import capability with HTTP 403.');

wp_set_current_user((int) $admin->ID);
$_POST = array();
$_REQUEST = array();
$nonceDenied = false;
try {
	JsonImportExportPage::handleUpload();
} catch (WlaJsonSecurityDie $exception) {
	$nonceDenied = $exception->response === 403;
}
$expect($nonceDenied, 'JSON upload mutation accepted a request without a valid nonce.');

$_POST = array();
$_REQUEST = array();
$exportNonceDenied = false;
try {
	JsonImportExportPage::handleExport();
} catch (WlaJsonSecurityDie $exception) {
	$exportNonceDenied = $exception->response === 403;
}
$expect($exportNonceDenied, 'JSON export action accepted a request without a valid nonce.');

wp_delete_user((int) $userId);
remove_filter('wp_die_handler', $dieHandlerFilter);
wp_set_current_user((int) $admin->ID);

echo "WLA Inmo JSON runtime security negatives passed.\n";
