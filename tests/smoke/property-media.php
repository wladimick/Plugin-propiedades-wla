<?php

declare(strict_types=1);

$GLOBALS['wla_media_post_types'] = array(
	11 => 'attachment',
	12 => 'attachment',
	13 => 'attachment',
	99 => 'post',
);
$GLOBALS['wla_media_image_ids'] = array(11, 12);

if (!function_exists('__')) {
	function __($text, $domain = 'default')
	{
		unset($domain);
		return $text;
	}
}
if (!function_exists('sanitize_text_field')) {
	function sanitize_text_field($value)
	{
		return trim(strip_tags((string) $value));
	}
}
if (!function_exists('sanitize_textarea_field')) {
	function sanitize_textarea_field($value)
	{
		return trim(strip_tags((string) $value));
	}
}
if (!function_exists('sanitize_key')) {
	function sanitize_key($value)
	{
		return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)) ?? '';
	}
}
if (!function_exists('esc_url_raw')) {
	function esc_url_raw($value, $protocols = null)
	{
		unset($protocols);
		$validated = filter_var((string) $value, FILTER_VALIDATE_URL);
		if (!is_string($validated)) {
			return '';
		}
		$scheme = strtolower((string) parse_url($validated, PHP_URL_SCHEME));
		return in_array($scheme, array('http', 'https'), true) ? $validated : '';
	}
}
if (!function_exists('get_post_type')) {
	function get_post_type($postId)
	{
		return $GLOBALS['wla_media_post_types'][(int) $postId] ?? false;
	}
}
if (!function_exists('wp_attachment_is_image')) {
	function wp_attachment_is_image($postId)
	{
		return in_array((int) $postId, $GLOBALS['wla_media_image_ids'], true);
	}
}

$root = dirname(__DIR__, 2) . '/plugin/wla-inmo/src/';
require_once $root . 'Properties/Sanitizer.php';
require_once $root . 'Properties/Capabilities.php';
require_once $root . 'Properties/PostType.php';
require_once $root . 'Properties/MetaSchema.php';
require_once $root . 'Properties/Validator.php';
require_once $root . 'Admin/PropertyMedia.php';

use WLA\Inmo\Admin\PropertyMedia;
use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\Sanitizer;

function wlaPropertyMediaExpect(bool $condition, string $message): void
{
	if ($condition) {
		return;
	}

	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
}

wlaPropertyMediaExpect(MetaSchema::metaKey('gallery_ids') !== null, 'Gallery IDs must remain canonical MetaSchema data.');
wlaPropertyMediaExpect(MetaSchema::metaKey('video_urls') !== null, 'Video URLs must remain canonical MetaSchema data.');

$gallery = PropertyMedia::normalizeGalleryInput('11, 12 11');
wlaPropertyMediaExpect($gallery === array('11', '12', '11'), 'Gallery input should preserve submitted order before canonical sanitization.');
wlaPropertyMediaExpect(Sanitizer::positiveIntegerArray($gallery) === array(11, 12), 'Canonical gallery sanitizer must remove duplicates without re-sorting.');

$videos = PropertyMedia::normalizeVideoInput("https://example.test/a\n\nhttps://example.test/b\r\n");
wlaPropertyMediaExpect($videos === array('https://example.test/a', 'https://example.test/b'), 'Video textarea must normalize to one non-empty value per line.');

$valid = PropertyMedia::validateValues(array('11', '12'), array('https://example.test/video'));
wlaPropertyMediaExpect($valid === array(), 'Existing image attachments and HTTPS videos must pass media validation.');

$invalidId = PropertyMedia::validateValues(array('not-an-id'), array());
wlaPropertyMediaExpect(($invalidId['gallery_ids'] ?? '') === 'invalid_attachment_id', 'Non-numeric gallery IDs must be rejected.');

$nonImage = PropertyMedia::validateValues(array('13'), array());
wlaPropertyMediaExpect(($nonImage['gallery_ids'] ?? '') === 'invalid_image_attachment', 'Non-image attachments must be rejected from the gallery.');

$notAttachment = PropertyMedia::validateValues(array('99'), array());
wlaPropertyMediaExpect(($notAttachment['gallery_ids'] ?? '') === 'invalid_image_attachment', 'Normal posts must never be accepted as gallery attachments.');

$invalidVideo = PropertyMedia::validateValues(array(), array('<iframe src="https://example.test/video"></iframe>'));
wlaPropertyMediaExpect(($invalidVideo['video_urls'] ?? '') === 'invalid_http_url', 'HTML/iframe video values must be rejected.');

$propertyScreen = (object) array('post_type' => 'wla_property');
$postScreen = (object) array('post_type' => 'post');
wlaPropertyMediaExpect(PropertyMedia::isPropertyEditorContext('post.php', $propertyScreen), 'Media assets should load on the property edit screen.');
wlaPropertyMediaExpect(PropertyMedia::isPropertyEditorContext('post-new.php', $propertyScreen), 'Media assets should load when creating a property.');
wlaPropertyMediaExpect(!PropertyMedia::isPropertyEditorContext('edit.php', $propertyScreen), 'Media assets must not load on the property list.');
wlaPropertyMediaExpect(!PropertyMedia::isPropertyEditorContext('post.php', $postScreen), 'Media assets must not load for normal posts.');

$moduleSource = file_get_contents(dirname(__DIR__, 2) . '/plugin/wla-inmo/src/Admin/PropertyMedia.php');
$scriptSource = file_get_contents(dirname(__DIR__, 2) . '/plugin/wla-inmo/assets/admin/property-media.js');
wlaPropertyMediaExpect(is_string($moduleSource) && strpos($moduleSource, 'wp_delete_attachment') === false, 'Removing a gallery item must never delete the attachment.');
wlaPropertyMediaExpect(is_string($moduleSource) && strpos($moduleSource, "current_user_can('edit_post', \$attachmentId)") !== false, 'ALT persistence must be protected by attachment-level capability.');
wlaPropertyMediaExpect(is_string($scriptSource) && strpos($scriptSource, 'window.wp.media') !== false, 'Gallery selection must use the native WordPress media library.');
wlaPropertyMediaExpect(is_string($scriptSource) && stripos($scriptSource, 'jquery') === false, 'Property media interactions must not introduce a jQuery dependency.');

echo "WLA Inmo property media smoke tests passed.\n";
