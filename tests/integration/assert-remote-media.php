<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Import\RemoteMediaDownloadedFile;
use WLA\Inmo\Import\RemoteMediaLibrary;
use WLA\Inmo\Import\WordPressJsonExportSource;
use WLA\Inmo\Import\WordPressRemoteMediaAttachmentStore;
use WLA\Inmo\Import\WordPressRemoteMediaPropertyStore;
use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;

$fail = static function (string $message): void {
	fwrite(STDERR, "REMOTE MEDIA INTEGRATION FAILURE: {$message}\n");
	exit(1);
};

$assert = static function (bool $condition, string $message) use ($fail): void {
	if (!$condition) {
		$fail($message);
	}
};

$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WlZ+kcAAAAASUVORK5CYII=', true);
$assert(is_string($pngBytes), 'PNG fixture could not be decoded.');

$propertyId = wp_insert_post(array(
	'post_type' => PostType::POST_TYPE,
	'post_status' => 'publish',
	'post_title' => 'Remote media integration property',
), true);
$assert(!is_wp_error($propertyId) && (int) $propertyId > 0, 'Synthetic property could not be created.');
$propertyId = (int) $propertyId;

$makeDownloadedFile = static function (string $sourceUrl) use ($pngBytes, $assert): RemoteMediaDownloadedFile {
	$path = wp_tempnam('wla-remote-media-integration');
	$assert(is_string($path) && $path !== '', 'Temporary PNG path could not be created.');
	$written = file_put_contents($path, $pngBytes);
	$assert($written === strlen($pngBytes), 'Temporary PNG could not be written.');
	chmod($path, 0600);
	$hash = hash_file('sha256', $path);
	$assert(is_string($hash), 'Temporary PNG hash could not be generated.');

	return new RemoteMediaDownloadedFile(
		$sourceUrl,
		$path,
		'image/png',
		'png',
		strlen($pngBytes),
		$hash,
		1,
		1
	);
};

$sourceUrl = 'https://images.example.test/property.png';
$library = new RemoteMediaLibrary(new WordPressRemoteMediaAttachmentStore());
$attachmentId = $library->ingest($makeDownloadedFile($sourceUrl), $propertyId);
$assert($attachmentId > 0, 'First attachment was not created.');
$assert(get_post_type($attachmentId) === 'attachment', 'Created media object is not an attachment.');

$duplicateId = $library->ingest($makeDownloadedFile($sourceUrl), $propertyId);
$assert($duplicateId === $attachmentId, 'Same SHA-256 did not reuse the existing WLA attachment.');

$attachmentStore = new WordPressRemoteMediaAttachmentStore();
$shaMeta = (string) get_post_meta($attachmentId, $attachmentStore::HASH_META, true);
$urlHashMeta = (string) get_post_meta($attachmentId, $attachmentStore::SOURCE_URL_HASH_META, true);
$assert($shaMeta !== '' && preg_match('/^[a-f0-9]{64}$/', $shaMeta) === 1, 'Attachment SHA metadata is invalid.');
$assert($urlHashMeta === hash('sha256', $sourceUrl), 'Source URL hash metadata is invalid.');
$allAttachmentMeta = serialize(get_post_meta($attachmentId));
$assert(strpos($allAttachmentMeta, $sourceUrl) === false, 'Raw remote source URL leaked into attachment metadata.');

$propertyStore = new WordPressRemoteMediaPropertyStore();
$propertyStore->setGallery($propertyId, array($attachmentId, $attachmentId));
$propertyStore->setFeaturedImage($propertyId, $attachmentId);

$definitions = MetaSchema::definitions();
$galleryKey = (string) $definitions['gallery_ids']['meta_key'];
$galleryIds = get_post_meta($propertyId, $galleryKey, true);
$assert($galleryIds === array($attachmentId), 'Canonical gallery IDs were not deduplicated/persisted.');
$assert((int) get_post_thumbnail_id($propertyId) === $attachmentId, 'Featured image was not persisted.');

$attachmentUrl = wp_get_attachment_url($attachmentId);
$assert(is_string($attachmentUrl) && $attachmentUrl !== '', 'Attachment public URL is unavailable.');
$exported = (new WordPressJsonExportSource(array('publish')))->page(1, 20);
$match = null;
foreach ($exported as $property) {
	if (($property['post']['title'] ?? '') === 'Remote media integration property') {
		$match = $property;
		break;
	}
}
$assert(is_array($match), 'Property was not present in WLA JSON export source.');
$assert(($match['media']['gallery_urls'] ?? array()) === array($attachmentUrl), 'JSON export did not emit canonical gallery URL.');
$assert(($match['media']['featured_image_url'] ?? '') === $attachmentUrl, 'JSON export did not emit canonical featured image URL.');
$assert(strpos(json_encode($match), $sourceUrl) === false, 'JSON export leaked original remote source URL.');

wp_delete_post($propertyId, true);
wp_delete_attachment($attachmentId, true);

echo "Remote media WordPress integration assertions passed.\n";
