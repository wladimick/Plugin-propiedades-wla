<?php

use WLA\Inmo\Access\Capabilities;
use WLA\Inmo\Access\RoleMatrix;
use WLA\Inmo\Activity\EventTypes;
use WLA\Inmo\Activity\Repository;
use WLA\Inmo\Activity\Retention;
use WLA\Inmo\Activity\Schema;
use WLA\Inmo\Admin\ActivityPage;
use WLA\Inmo\Admin\PropertyActivity;
use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Settings\Repository as SettingsRepository;
use WLA\Inmo\Settings\Schema as SettingsSchema;

function wlaActivityIntegrationExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

global $wpdb;
$table = Schema::tableName($wpdb);
$tableExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
wlaActivityIntegrationExpect($tableExists === $table, 'Activity table was not installed.');
wlaActivityIntegrationExpect((string) get_option(Schema::DB_VERSION_OPTION, '') === Schema::DB_VERSION, 'Activity DB version was not stored.');

$admin = get_user_by('login', 'admin');
wlaActivityIntegrationExpect($admin instanceof WP_User, 'CI administrator was not found.');
wp_set_current_user((int) $admin->ID);
wlaActivityIntegrationExpect(current_user_can(Capabilities::VIEW_ACTIVITY), 'Administrator must be able to view activity.');

$editorId = wp_insert_user(array(
	'user_login' => 'activity-editor',
	'user_pass' => wp_generate_password(24, true, true),
	'user_email' => 'activity-editor@example.test',
	'role' => RoleMatrix::ROLE_EDITOR,
));
wlaActivityIntegrationExpect(!is_wp_error($editorId), 'Could not create property editor.');
$editor = get_user_by('id', (int) $editorId);
wlaActivityIntegrationExpect($editor instanceof WP_User && !$editor->has_cap(Capabilities::VIEW_ACTIVITY), 'Property editor must not inherit activity access.');

$propertyId = wp_insert_post(array(
	'post_type' => PostType::POST_TYPE,
	'post_status' => 'draft',
	'post_title' => 'Propiedad actividad CI',
	'post_content' => 'Ficha creada para validar la bitácora.',
), true);
wlaActivityIntegrationExpect(!is_wp_error($propertyId) && (int) $propertyId > 0, 'Could not create activity property.');
$propertyId = (int) $propertyId;

$priceKey = MetaSchema::metaKey('price_clp');
$statusKey = MetaSchema::metaKey('status');
$featuredKey = MetaSchema::metaKey('featured');
$currencyKey = MetaSchema::metaKey('currency_primary');
wlaActivityIntegrationExpect(is_string($priceKey) && is_string($statusKey) && is_string($featuredKey) && is_string($currencyKey), 'Canonical activity meta keys are missing.');

update_post_meta($propertyId, $currencyKey, 'CLP');
update_post_meta($propertyId, $priceKey, 100000000);
update_post_meta($propertyId, $priceKey, 95000000);
update_post_meta($propertyId, $statusKey, 'available');
update_post_meta($propertyId, $statusKey, 'reserved');
update_post_meta($propertyId, $featuredKey, 1);
wp_update_post(array('ID' => $propertyId, 'post_status' => 'publish'));

$settings = SettingsSchema::defaults();
$settings['business_email'] = 'activity-private@example.test';
$settings['business_phone'] = '+56 9 1111 2222';
$settings['whatsapp_number'] = '+56911112222';
$settings['property_base'] = 'propiedades-activity-ci';
update_option(SettingsSchema::OPTION_NAME, $settings, false);
SettingsRepository::resetCache();
do_action('wla_inmo_rewrite_rules_applied', 'propiedades-activity-ci');

$rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT event_type,object_type,object_id,actor_user_id,context FROM {$table} WHERE (object_id = %d OR object_type = %s) ORDER BY id ASC",
		$propertyId,
		'settings'
	),
	ARRAY_A
);
wlaActivityIntegrationExpect(is_array($rows) && count($rows) >= 8, 'Expected activity events were not persisted.');

$types = array_column($rows, 'event_type');
foreach (array(
	EventTypes::PROPERTY_CREATED,
	EventTypes::PROPERTY_PRICE_CHANGED,
	EventTypes::PROPERTY_COMMERCIAL_STATUS_CHANGED,
	EventTypes::PROPERTY_FEATURED_CHANGED,
	EventTypes::PROPERTY_WP_STATUS_CHANGED,
	EventTypes::SETTINGS_CHANGED,
	EventTypes::PROPERTY_BASE_CHANGED,
	EventTypes::REWRITE_RULES_APPLIED,
) as $requiredType) {
	wlaActivityIntegrationExpect(in_array($requiredType, $types, true), 'Missing activity event: ' . $requiredType);
}

$serializedRows = wp_json_encode($rows);
wlaActivityIntegrationExpect(is_string($serializedRows), 'Could not encode activity rows.');
wlaActivityIntegrationExpect(!str_contains($serializedRows, 'activity-private@example.test'), 'Activity log leaked business email value.');
wlaActivityIntegrationExpect(!str_contains($serializedRows, '+56 9 1111 2222'), 'Activity log leaked phone value.');
wlaActivityIntegrationExpect(!str_contains($serializedRows, '+56911112222'), 'Activity log leaked WhatsApp value.');

$priceEvents = array_values(array_filter($rows, static fn(array $row): bool => $row['event_type'] === EventTypes::PROPERTY_PRICE_CHANGED));
wlaActivityIntegrationExpect(count($priceEvents) === 2, 'Price add/update should create exactly two relevant events.');
$lastPriceContext = json_decode((string) end($priceEvents)['context'], true);
wlaActivityIntegrationExpect(is_array($lastPriceContext) && (string) ($lastPriceContext['old'] ?? '') === '100000000', 'Price history must preserve the previous value.');
wlaActivityIntegrationExpect((string) ($lastPriceContext['new'] ?? '') === '95000000', 'Price history must preserve the new value.');

$countBeforeNoop = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE object_id = %d", $propertyId));
update_post_meta($propertyId, $priceKey, 95000000);
$countAfterNoop = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE object_id = %d", $propertyId));
wlaActivityIntegrationExpect($countBeforeNoop === $countAfterNoop, 'Unchanged meta must not create duplicate activity.');

$page = Repository::paginate(array('object_id' => $propertyId), 1, 5);
wlaActivityIntegrationExpect($page['items'] !== array() && count($page['items']) <= 5, 'Activity repository pagination failed.');
wlaActivityIntegrationExpect((int) $page['total'] >= count($page['items']), 'Activity pagination total is inconsistent.');

ob_start();
ActivityPage::render();
$activityHtml = (string) ob_get_clean();
wlaActivityIntegrationExpect(str_contains($activityHtml, 'Propiedad creada') || str_contains($activityHtml, 'Precio actualizado'), 'Activity screen did not render event labels.');
wlaActivityIntegrationExpect(!str_contains($activityHtml, 'activity-private@example.test'), 'Activity screen leaked a private setting value.');

ob_start();
PropertyActivity::render(get_post($propertyId));
$propertyHistoryHtml = (string) ob_get_clean();
wlaActivityIntegrationExpect(str_contains($propertyHistoryHtml, 'historial') || str_contains($propertyHistoryHtml, 'Historial'), 'Property editor history did not render.');
wlaActivityIntegrationExpect(str_contains($propertyHistoryHtml, 'Precio actualizado'), 'Property history did not include relevant property events.');

$oldDate = gmdate('Y-m-d H:i:s', strtotime('-18 months'));
$wpdb->insert(
	$table,
	array(
		'event_type' => EventTypes::PROPERTY_CREATED,
		'object_type' => PostType::POST_TYPE,
		'object_id' => $propertyId,
		'actor_user_id' => (int) $admin->ID,
		'summary' => EventTypes::PROPERTY_CREATED,
		'context' => '{}',
		'created_at' => $oldDate,
	),
	array('%s', '%s', '%d', '%d', '%s', '%s', '%s')
);
$oldEventId = (int) $wpdb->insert_id;
wlaActivityIntegrationExpect($oldEventId > 0, 'Could not insert old retention fixture.');

$settings = SettingsRepository::all();
$settings['activity_retention_months'] = '12';
update_option(SettingsSchema::OPTION_NAME, $settings, false);
SettingsRepository::resetCache();
$deleted = Retention::cleanup();
wlaActivityIntegrationExpect($deleted >= 1, 'Retention cleanup did not delete the expired fixture.');
wlaActivityIntegrationExpect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE id = %d", $oldEventId)) === 0, 'Expired activity row still exists after cleanup.');

wlaActivityIntegrationExpect(wp_next_scheduled(Retention::CRON_HOOK) !== false, 'Daily activity retention schedule is missing.');

echo "WLA Inmo activity integration tests passed.\n";
