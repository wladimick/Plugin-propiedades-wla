<?php

if (!defined('ABSPATH')) {
	exit(1);
}

$propertyId = (int) getenv('WLA_TEST_PROPERTY_ID');
$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

if ($propertyId < 1 || get_post($propertyId) === null) {
	$fail('Synthetic property was removed.');
}

if (get_option('wla_inmo_ci_preservation_marker') !== 'keep-me') {
	$fail('Plugin setting/marker was removed.');
}

foreach (array('wla_inmo_manager', 'wla_property_editor', 'wla_lead_manager') as $role) {
	if (get_role($role) === null) {
		$fail("Role {$role} was removed during deactivate/uninstall.");
	}
}

global $wpdb;
$table = $wpdb->prefix . 'wla_property_index';
$tableFound = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
if ($tableFound !== $table) {
	$fail('Derived index table was removed unexpectedly.');
}

$indexedId = (int) $wpdb->get_var(
	$wpdb->prepare("SELECT property_id FROM {$table} WHERE property_id = %d", $propertyId)
);
if ($indexedId !== $propertyId) {
	$fail('Derived index row was removed unexpectedly.');
}

echo "WLA Inmo data preservation verified for property {$propertyId}.\n";
