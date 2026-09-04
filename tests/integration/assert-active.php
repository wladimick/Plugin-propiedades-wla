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

$propertyType = get_post_type_object('wla_property');
if (!is_object($propertyType) || $propertyType->show_in_menu !== 'wla-inmo') {
	$fail('wla_property must be nested under the WLA Inmo admin menu.');
}

if (!class_exists(WLA\Inmo\Admin\PropertyList::class)) {
	$fail('Professional property list module is unavailable.');
}

if (!class_exists(WLA\Inmo\Admin\PropertyEditor::class)) {
	$fail('Guided property editor module is unavailable.');
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

if ((string) get_option(WLA\Inmo\Search\IndexSchema::DB_VERSION_OPTION, '0') !== WLA\Inmo\Search\IndexSchema::DB_VERSION) {
	$fail('Property index schema version was not upgraded.');
}

$indexNames = $wpdb->get_col("SHOW INDEX FROM {$table}", 2);
foreach (array('region_slug', 'sector_slug', 'status_featured') as $requiredIndex) {
	if (!is_array($indexNames) || !in_array($requiredIndex, $indexNames, true)) {
		$fail("Missing admin-filter index {$requiredIndex}.");
	}
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
update_post_meta($propertyId, '_wla_inmo_currency_primary', 'CLP');
update_post_meta($propertyId, '_wla_inmo_price_clp', 123456789);
update_post_meta($propertyId, '_wla_inmo_status', 'available');

$priceLabel = WLA\Inmo\Admin\PropertyList::priceLabel($propertyId);
$priceDigits = preg_replace('/[^0-9]/', '', $priceLabel);
if (!str_starts_with($priceLabel, '$') || $priceDigits !== '123456789') {
	$fail('Professional list does not render canonical CLP price correctly.');
}

if (WLA\Inmo\Admin\PropertyList::statusLabel($propertyId) !== 'Disponible') {
	$fail('Professional list does not humanize property status correctly.');
}

if (!WLA\Inmo\Search\Indexer::syncNow($propertyId)) {
	$fail('Unable to synchronize synthetic property into search index.');
}

$indexedId = (int) $wpdb->get_var(
	$wpdb->prepare("SELECT property_id FROM {$table} WHERE property_id = %d", $propertyId)
);
if ($indexedId !== $propertyId) {
	$fail('Synthetic property was not indexed.');
}

$adminUser = get_user_by('login', 'admin');
if (!$adminUser instanceof WP_User) {
	$fail('Unable to resolve CI administrator for guided editor tests.');
}
wp_set_current_user($adminUser->ID);

$guidedId = wp_insert_post(
	array(
		'post_type'   => 'wla_property',
		'post_status' => 'draft',
		'post_title'  => 'CI Guided Property',
	),
	true
);
if (is_wp_error($guidedId) || (int) $guidedId < 1) {
	$fail('Unable to create guided-editor synthetic property.');
}
$guidedId = (int) $guidedId;

$operation = term_exists('CI Venta', 'wla_operation');
if ($operation === null) {
	$operation = wp_insert_term('CI Venta', 'wla_operation');
}
if (is_wp_error($operation)) {
	$fail('Unable to create synthetic operation term.');
}
$operationId = is_array($operation) ? (int) $operation['term_id'] : (int) $operation;

$_POST = array(
	'wla_inmo_property_editor_nonce' => wp_create_nonce('wla_inmo_save_property_editor'),
	'wla_inmo_fields' => array(
		'property_code' => 'GUIDED-' . $guidedId,
		'status' => 'available',
		'currency_primary' => 'CLP',
		'price_clp' => '222000000',
		'private_address' => 'Dirección privada CI 123',
		'featured' => '1',
		'indexable' => '1',
	),
	'wla_inmo_taxonomies' => array(
		'wla_operation' => (string) $operationId,
	),
);
WLA\Inmo\Admin\PropertyEditor::save($guidedId, get_post($guidedId), true);
$_POST = array();

if (get_post_meta($guidedId, '_wla_inmo_property_code', true) !== 'GUIDED-' . $guidedId) {
	$fail('Guided editor did not persist canonical property code.');
}
if ((int) get_post_meta($guidedId, '_wla_inmo_price_clp', true) !== 222000000) {
	$fail('Guided editor did not persist canonical CLP price.');
}
if (get_post_meta($guidedId, '_wla_inmo_private_address', true) !== 'Dirección privada CI 123') {
	$fail('Guided editor did not persist private address internally.');
}
$operationTerms = wp_get_object_terms($guidedId, 'wla_operation', array('fields' => 'ids'));
if (!is_array($operationTerms) || !in_array($operationId, array_map('intval', $operationTerms), true)) {
	$fail('Guided editor did not assign canonical operation taxonomy.');
}

$duplicateId = wp_insert_post(
	array(
		'post_type' => 'wla_property',
		'post_status' => 'draft',
		'post_title' => 'CI Duplicate Code Owner',
	),
	true
);
if (is_wp_error($duplicateId) || (int) $duplicateId < 1) {
	$fail('Unable to create duplicate-code fixture.');
}
$duplicateId = (int) $duplicateId;
update_post_meta($duplicateId, '_wla_inmo_property_code', 'DUPLICATE-CI');

$_POST = array(
	'wla_inmo_property_editor_nonce' => wp_create_nonce('wla_inmo_save_property_editor'),
	'wla_inmo_fields' => array(
		'property_code' => 'DUPLICATE-CI',
		'currency_primary' => 'CLP',
		'price_clp' => '999000000',
	),
);
WLA\Inmo\Admin\PropertyEditor::save($guidedId, get_post($guidedId), true);
$_POST = array();

if (get_post_meta($guidedId, '_wla_inmo_property_code', true) !== 'GUIDED-' . $guidedId) {
	$fail('Duplicate code validation allowed a partial property-code write.');
}
if ((int) get_post_meta($guidedId, '_wla_inmo_price_clp', true) !== 222000000) {
	$fail('Duplicate code validation allowed a partial price write.');
}

$_POST = array(
	'wla_inmo_property_editor_nonce' => 'invalid-nonce',
	'wla_inmo_fields' => array('price_clp' => '777000000'),
);
WLA\Inmo\Admin\PropertyEditor::save($guidedId, get_post($guidedId), true);
$_POST = array();
if ((int) get_post_meta($guidedId, '_wla_inmo_price_clp', true) !== 222000000) {
	$fail('Invalid nonce was able to mutate guided property fields.');
}

$subscriberId = wp_create_user('wla-ci-subscriber-' . $guidedId, wp_generate_password(24, true), 'wla-ci-' . $guidedId . '@example.test');
if (is_wp_error($subscriberId)) {
	$fail('Unable to create least-privilege CI user.');
}
$subscriber = get_user_by('id', (int) $subscriberId);
if (!$subscriber instanceof WP_User) {
	$fail('Unable to load least-privilege CI user.');
}
$subscriber->set_role('subscriber');
wp_set_current_user($subscriber->ID);

$_POST = array(
	'wla_inmo_property_editor_nonce' => wp_create_nonce('wla_inmo_save_property_editor'),
	'wla_inmo_fields' => array('price_clp' => '888000000'),
);
WLA\Inmo\Admin\PropertyEditor::save($guidedId, get_post($guidedId), true);
$_POST = array();
if ((int) get_post_meta($guidedId, '_wla_inmo_price_clp', true) !== 222000000) {
	$fail('User without edit_post capability mutated guided property fields.');
}
wp_set_current_user($adminUser->ID);

update_option('wla_inmo_ci_preservation_marker', 'keep-me', false);

echo $propertyId;
