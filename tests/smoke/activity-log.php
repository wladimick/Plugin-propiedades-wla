<?php

declare(strict_types=1);

if (!defined('WLA_INMO_DIR')) {
	define('WLA_INMO_DIR', dirname(__DIR__, 2) . '/plugin/wla-inmo/');
}

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		unset($domain);
		return $text;
	}
}
if (!function_exists('sanitize_key')) {
	function sanitize_key($value)
	{
		$value = strtolower((string) $value);
		return preg_replace('/[^a-z0-9_\-]/', '', $value) ?? '';
	}
}
if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value)
	{
		return trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $value)));
	}
}
if (!function_exists('absint')) {
	function absint($value)
	{
		return abs((int) $value);
	}
}
if (!function_exists('wp_json_encode')) {
	function wp_json_encode($value)
	{
		return json_encode($value);
	}
}
if (!function_exists('get_current_user_id')) {
	function get_current_user_id()
	{
		return 42;
	}
}
if (!function_exists('do_action')) {
	function do_action($hook, ...$args)
	{
		$GLOBALS['wla_activity_actions'][] = array($hook, $args);
	}
}

final class WlaActivityWpdbStub
{
	public string $prefix = 'wp_';
	public int $insert_id = 0;
	public array $last_data = array();

	public function insert($table, $data, $formats)
	{
		unset($formats);
		$this->last_data = array('table' => $table, 'data' => $data);
		$this->insert_id++;
		return 1;
	}

	public function get_charset_collate(): string
	{
		return 'DEFAULT CHARACTER SET utf8mb4';
	}
}

$GLOBALS['wpdb'] = new WlaActivityWpdbStub();
$GLOBALS['wla_activity_actions'] = array();

require_once WLA_INMO_DIR . 'src/Activity/Schema.php';
require_once WLA_INMO_DIR . 'src/Activity/EventTypes.php';
require_once WLA_INMO_DIR . 'src/Activity/Repository.php';
require_once WLA_INMO_DIR . 'src/Activity/Recorder.php';

use WLA\Inmo\Activity\EventTypes;
use WLA\Inmo\Activity\Recorder;
use WLA\Inmo\Activity\Schema;

function wlaActivityExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

$sql = Schema::sql($GLOBALS['wpdb']);
wlaActivityExpect(str_contains($sql, 'wp_wla_inmo_activity'), 'Activity table name contract changed.');
wlaActivityExpect(str_contains($sql, 'KEY object_timeline'), 'Object timeline index is required.');
wlaActivityExpect(str_contains($sql, 'KEY event_timeline'), 'Event timeline index is required.');
wlaActivityExpect(str_contains($sql, 'KEY created_at'), 'Retention/query index is required.');

$context = EventTypes::sanitizeContext(
	EventTypes::PROPERTY_PRICE_CHANGED,
	array(
		'field' => 'price_clp',
		'old' => '100000000',
		'new' => '95000000',
		'currency' => 'CLP',
		'private_address' => 'Nunca guardar esto',
		'token' => 'secret',
	)
);
wlaActivityExpect(($context['field'] ?? '') === 'price_clp', 'Allowed price field must survive sanitization.');
wlaActivityExpect(!isset($context['private_address']), 'Private address must be discarded from activity context.');
wlaActivityExpect(!isset($context['token']), 'Unknown context keys must be discarded.');

$id = Recorder::record(
	EventTypes::PROPERTY_PRICE_CHANGED,
	'wla_property',
	123,
	array(
		'field' => 'price_clp',
		'old' => '100000000',
		'new' => '95000000',
		'currency' => 'CLP',
		'business_email' => 'private@example.test',
	)
);
wlaActivityExpect($id === 1, 'Valid dotted activity event must be persisted.');
$stored = $GLOBALS['wpdb']->last_data['data'] ?? array();
wlaActivityExpect(($stored['event_type'] ?? '') === EventTypes::PROPERTY_PRICE_CHANGED, 'Dotted event identifier must remain intact.');
wlaActivityExpect(($stored['actor_user_id'] ?? 0) === 42, 'Current actor should be resolved when not supplied explicitly.');
wlaActivityExpect(!str_contains((string) ($stored['context'] ?? ''), 'private@example.test'), 'Unexpected contact values must never enter stored context.');

wlaActivityExpect(Recorder::record('unknown.event', 'settings', null, array('token' => 'x')) === false, 'Unknown event types must be rejected.');

$settings = EventTypes::sanitizeContext(
	EventTypes::SETTINGS_CHANGED,
	array(
		'keys' => array('business_email', 'property_base', 'property_base', '<script>x</script>'),
		'business_email' => 'private@example.test',
	)
);
wlaActivityExpect(($settings['keys'] ?? array()) === array('business_email', 'property_base', 'scriptxscript'), 'Settings activity should retain only normalized changed key names.');
wlaActivityExpect(!isset($settings['business_email']), 'Settings activity must not store setting values.');

$activitySource = file_get_contents(WLA_INMO_DIR . 'src/Activity/Observer.php');
wlaActivityExpect(is_string($activitySource) && !str_contains($activitySource, 'private_address'), 'Observer must not reference private address data.');
wlaActivityExpect(is_string($activitySource) && !str_contains($activitySource, 'internal_notes'), 'Observer must not reference internal notes.');
wlaActivityExpect(is_string($activitySource) && !str_contains($activitySource, '$_POST'), 'Observer must not persist request payloads.');

$retentionSource = file_get_contents(WLA_INMO_DIR . 'src/Activity/Retention.php');
wlaActivityExpect(is_string($retentionSource) && str_contains($retentionSource, 'BATCH_SIZE = 500'), 'Retention must remain batched.');
wlaActivityExpect(is_string($retentionSource) && str_contains($retentionSource, "'daily'"), 'Retention scheduling must remain at most daily.');

echo "WLA Inmo activity log smoke tests passed.\n";
