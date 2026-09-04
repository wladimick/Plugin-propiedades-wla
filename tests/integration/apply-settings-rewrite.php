<?php

if (!defined('ABSPATH')) {
	exit(1);
}

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

$admin = get_user_by('login', 'admin');
if (!$admin instanceof WP_User) {
	$fail('Unable to resolve CI administrator.');
}
wp_set_current_user($admin->ID);

if (WLA\Inmo\Settings\RewriteManager::pendingBase() !== 'casas-ci') {
	$fail('Controlled rewrite test requires casas-ci pending state.');
}

$_POST = array(
	'wla_inmo_settings_action' => 'wla_inmo_apply_rewrite_rules',
	'wla_inmo_rewrite_nonce'   => wp_create_nonce('wla_inmo_apply_rewrite_rules'),
);

WLA\Inmo\Settings\RewriteManager::handleApplyRequest();
