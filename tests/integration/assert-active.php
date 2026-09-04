<?php

if (!defined('ABSPATH')) {
	exit(1);
}

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

if (!is_plugin_active('wla-inmo/wla-inmo.php')) {
	$fail('WLA Inmo must be active.');
}

if (!post_type_exists('wla_property')) {
	$fail('wla_property CPT is not registered.');
}

foreach (array('wla_operation', 'wla_property_type', 'wla_region', 'wla_commune', 'wla_sector') as $taxonomy) {
	if (!taxonomy_exists($taxonomy)) {
		$fail("Missing taxonomy {$taxonomy}.");
	}
}

$meta = get_registered_meta_keys('post', 'wla_property');
if (!is_array($meta) || count($meta) < 37) {
	$fail('Canonical property meta schema is incomplete.');
}

foreach (array('wla_inmo_manager', 'wla_property_editor', 'wla_lead_manager') as $role) {
	if (get_role($role) === null) {
		$fail("Missing role {$role}.");
	}
}

$administrator = get_role('administrator');
if ($administrator === null || !$administrator->has_cap('manage_wla_inmo_tools')) {
	$fail('Administrator did not receive WLA capabilities.');
}

$manager = get_role('wla_inmo_manager');
if ($manager === null || $manager->has_cap('manage_options') || $manager->has_cap('manage_wla_inmo_tools')) {
	$fail('Manager least-privilege contract is invalid.');
}

$settings = WLA\Inmo\Settings\Repository::all();
if (($settings['country_code'] ?? '') !== 'CL' || ($settings['property_base'] ?? '') === '') {
	$fail('Settings defaults are unavailable.');
}

global $wpdb;
$table = $wpdb->prefix . 'wla_property_index';
$tableFound = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
if ($tableFound !== $table) {
	$fail('Property index table was not installed.');
}

$propertyId = wp_insert_post(
	array(
		'post_type'   => 'wla_property',
		'post_status' => 'publish',
		'post_title'  => 'CI Synthetic Property',
	),
	true
);

if (is_wp_error($propertyId) || (int) $propertyId < 1) {
	$fail('Unable to create synthetic property.');
}

$propertyId = (int) $propertyId;
update_post_meta($propertyId, '_wla_inmo_property_code', 'CI-' . $propertyId);
update_post_meta($propertyId, '_wla_inmo_price_clp', 123456789);

if (!WLA\Inmo\Search\Indexer::syncNow($propertyId)) {
	$fail('Unable to synchronize synthetic property into search index.');
}

$indexedId = (int) $wpdb->get_var(
	$wpdb->prepare("SELECT property_id FROM {$table} WHERE property_id = %d", $propertyId)
);
if ($indexedId !== $propertyId) {
	$fail('Synthetic property was not indexed.');
}

update_option('wla_inmo_ci_preservation_marker', 'keep-me', false);

echo $propertyId;
