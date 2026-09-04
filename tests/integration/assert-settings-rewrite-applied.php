<?php

if (!defined('ABSPATH')) {
	exit(1);
}

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

if (WLA\Inmo\Settings\RewriteManager::isPending()) {
	$fail('Rewrite pending state was not cleared after controlled apply.');
}

$settings = WLA\Inmo\Settings\Repository::all();
if (($settings['property_base'] ?? '') !== 'casas-ci') {
	$fail('Canonical property base changed unexpectedly after rewrite apply.');
}

$postType = get_post_type_object('wla_property');
if (!$postType instanceof WP_Post_Type || ($postType->rewrite['slug'] ?? '') !== 'casas-ci') {
	$fail('CPT did not register with the new property base on the follow-up request.');
}

$rewriteRules = get_option('rewrite_rules', array());
$ruleKeys = is_array($rewriteRules) ? implode("\n", array_keys($rewriteRules)) : '';
if (!str_contains($ruleKeys, 'casas-ci')) {
	$fail('Flushed rewrite rules do not contain the new property base.');
}

echo "WLA Inmo controlled settings rewrite applied successfully.\n";
