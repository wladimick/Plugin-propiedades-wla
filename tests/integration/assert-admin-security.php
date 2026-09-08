<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Access\RoleManager;
use WLA\Inmo\Access\RoleMatrix;
use WLA\Inmo\Admin\PropertyEditor;
use WLA\Inmo\Properties\Capabilities as PropertyCapabilities;

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

$expect = static function (bool $condition, string $message) use ($fail): void {
	if (!$condition) {
		$fail($message);
	}
};

$admin = get_user_by('login', 'admin');
if (!$admin instanceof WP_User) {
	$fail('Unable to resolve CI administrator.');
}

$roles = array(
	'administrator' => get_role('administrator'),
	'manager' => get_role(RoleMatrix::ROLE_MANAGER),
	'editor' => get_role(RoleMatrix::ROLE_EDITOR),
	'lead_manager' => get_role(RoleMatrix::ROLE_LEAD_MANAGER),
);

foreach ($roles as $name => $role) {
	$expect($role instanceof WP_Role, "Missing expected WLA role: {$name}.");
}

foreach (RoleMatrix::managedCapabilities() as $capability) {
	$expect($roles['administrator']->has_cap($capability), "Administrator is missing managed capability {$capability}.");
}

$expect($roles['administrator']->has_cap(AccessCapabilities::ROLLBACK_IMPORTS), 'Administrator must receive destructive rollback capability.');
$expect($roles['manager']->has_cap(PropertyCapabilities::EDIT_POSTS), 'Inmo manager must edit properties.');
$expect($roles['manager']->has_cap(AccessCapabilities::MANAGE_SETTINGS), 'Inmo manager must access WLA settings.');
$expect($roles['manager']->has_cap(AccessCapabilities::VIEW_ACTIVITY), 'Inmo manager must view activity.');
$expect(!$roles['manager']->has_cap(AccessCapabilities::MANAGE_TOOLS), 'Inmo manager must not receive technical tools capability.');
$expect(!$roles['manager']->has_cap(AccessCapabilities::ROLLBACK_IMPORTS), 'Inmo manager must not receive destructive rollback capability by default.');

$expect($roles['editor']->has_cap(PropertyCapabilities::EDIT_POSTS), 'Property editor must edit own properties.');
$expect($roles['editor']->has_cap(PropertyCapabilities::PUBLISH_POSTS), 'Property editor must publish own properties.');
$expect(!$roles['editor']->has_cap(PropertyCapabilities::EDIT_OTHERS_POSTS), 'Property editor must not edit other authors properties.');
$expect(!$roles['editor']->has_cap(AccessCapabilities::MANAGE_SETTINGS), 'Property editor must not manage settings.');
$expect(!$roles['editor']->has_cap(AccessCapabilities::VIEW_ACTIVITY), 'Property editor must not receive activity access by default.');
$expect(!$roles['editor']->has_cap(AccessCapabilities::ROLLBACK_IMPORTS), 'Property editor must not receive destructive rollback capability.');

$expect($roles['lead_manager']->has_cap(AccessCapabilities::VIEW_LEADS), 'Lead manager must view leads.');
$expect($roles['lead_manager']->has_cap(AccessCapabilities::MANAGE_LEADS), 'Lead manager must manage leads.');
$expect(!$roles['lead_manager']->has_cap(PropertyCapabilities::EDIT_POSTS), 'Lead manager must not edit properties.');
$expect(!$roles['lead_manager']->has_cap(AccessCapabilities::MANAGE_SETTINGS), 'Lead manager must not manage settings.');
$expect(!$roles['lead_manager']->has_cap(AccessCapabilities::ROLLBACK_IMPORTS), 'Lead manager must not receive destructive rollback capability.');

// Simulate an existing installation at the previous role schema version. The
// upgrade must re-grant the new Administrator-only capability without leaking
// it into plugin-owned roles.
$roles['administrator']->remove_cap(AccessCapabilities::ROLLBACK_IMPORTS);
update_option(RoleManager::VERSION_OPTION, '1', false);
RoleManager::maybeUpgrade();
$roles['administrator'] = get_role('administrator');
$roles['manager'] = get_role(RoleMatrix::ROLE_MANAGER);
$expect($roles['administrator'] instanceof WP_Role && $roles['administrator']->has_cap(AccessCapabilities::ROLLBACK_IMPORTS), 'Role upgrade did not grant rollback capability to existing Administrator.');
$expect($roles['manager'] instanceof WP_Role && !$roles['manager']->has_cap(AccessCapabilities::ROLLBACK_IMPORTS), 'Role upgrade leaked rollback capability to Inmo manager.');
$expect((string) get_option(RoleManager::VERSION_OPTION, '0') === RoleManager::VERSION, 'Role upgrade version was not persisted.');

wp_set_current_user($admin->ID);

$postId = wp_insert_post(
	array(
		'post_type' => 'wla_property',
		'post_status' => 'draft',
		'post_title' => 'Admin Security Fixture',
		'post_author' => $admin->ID,
	),
	true
);
if (is_wp_error($postId) || (int) $postId < 1) {
	$fail('Unable to create property security fixture.');
}
$postId = (int) $postId;
update_post_meta($postId, '_wla_inmo_price_clp', 100000000);

$_POST = array(
	'wla_inmo_fields' => array('price_clp' => '110000000'),
);
PropertyEditor::save($postId, get_post($postId), true);
$expect((int) get_post_meta($postId, '_wla_inmo_price_clp', true) === 100000000, 'Property editor accepted a write without nonce.');

$_POST = array(
	'wla_inmo_property_editor_nonce' => 'invalid-nonce',
	'wla_inmo_fields' => array('price_clp' => '120000000'),
);
PropertyEditor::save($postId, get_post($postId), true);
$expect((int) get_post_meta($postId, '_wla_inmo_price_clp', true) === 100000000, 'Property editor accepted a write with an invalid nonce.');

$_POST = array(
	'wla_inmo_property_editor_nonce' => wp_create_nonce('wla_inmo_save_property_editor'),
	'wla_inmo_fields' => array('price_clp' => '130000000'),
);
PropertyEditor::save($postId, get_post($postId), true);
$expect((int) get_post_meta($postId, '_wla_inmo_price_clp', true) === 130000000, 'Valid authorized property write did not persist.');

$editorUserId = wp_insert_user(
	array(
		'user_login' => 'security-editor',
		'user_email' => 'security-editor@example.test',
		'user_pass' => wp_generate_password(24, true, true),
		'role' => RoleMatrix::ROLE_EDITOR,
	)
);
if (is_wp_error($editorUserId) || (int) $editorUserId < 1) {
	$fail('Unable to create restricted editor security fixture.');
}
wp_set_current_user((int) $editorUserId);

$_POST = array(
	'wla_inmo_property_editor_nonce' => wp_create_nonce('wla_inmo_save_property_editor'),
	'wla_inmo_fields' => array('price_clp' => '140000000'),
);
PropertyEditor::save($postId, get_post($postId), true);
$expect((int) get_post_meta($postId, '_wla_inmo_price_clp', true) === 130000000, 'Restricted editor changed another authors property.');

wp_set_current_user($admin->ID);

$duplicateId = wp_insert_post(
	array(
		'post_type' => 'wla_property',
		'post_status' => 'draft',
		'post_title' => 'Duplicate Code Fixture',
		'post_author' => $admin->ID,
	),
	true
);
if (is_wp_error($duplicateId) || (int) $duplicateId < 1) {
	$fail('Unable to create duplicate-code fixture.');
}
update_post_meta((int) $duplicateId, '_wla_inmo_property_code', 'QA-DUPLICATE');

$errors = PropertyEditor::validateSubmission(
	array('property_code' => 'QA-DUPLICATE'),
	array(),
	$postId
);
$expect(($errors['property_code'] ?? '') === 'duplicate_property_code', 'Duplicate property code was not rejected.');

$errors = PropertyEditor::validateSubmission(
	array('currency_primary' => 'INVALID'),
	array(),
	$postId
);
$expect(($errors['currency_primary'] ?? '') === 'unsupported_currency', 'Invalid currency was not rejected.');

$_POST = array();

echo "WLA Inmo administration security integration tests passed.\n";
