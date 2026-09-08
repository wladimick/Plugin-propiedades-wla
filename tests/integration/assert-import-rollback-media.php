<?php

if (!defined('ABSPATH')) {
	exit(1);
}

use WLA\Inmo\Import\RollbackJournalState;
use WLA\Inmo\Import\RollbackRestorer;
use WLA\Inmo\Import\RollbackSnapshotCodec;
use WLA\Inmo\Import\RollbackSnapshotter;
use WLA\Inmo\Import\TargetRegistry;
use WLA\Inmo\Properties\MetaSchema;

$fail = static function (string $message): void {
	fwrite(STDERR, "FAIL: {$message}\n");
	exit(1);
};

$expect = static function (bool $condition, string $message) use ($fail): void {
	if (!$condition) {
		$fail($message);
	}
};

$makeImageAttachment = static function (string $name) use ($fail): int {
	// 1x1 transparent PNG; sufficient for WordPress attachment/thumbnail APIs.
	$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
	if (!is_string($png)) {
		$fail('Unable to decode media fixture PNG.');
	}
	$upload = wp_upload_bits($name . '.png', null, $png);
	if (!empty($upload['error']) || empty($upload['file']) || empty($upload['url'])) {
		$fail('Unable to create uploaded media fixture.');
	}
	$attachmentId = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => $name,
			'post_status'    => 'inherit',
			'guid'            => (string) $upload['url'],
		),
		(string) $upload['file'],
		0,
		true
	);
	if (is_wp_error($attachmentId) || (int) $attachmentId < 1) {
		$fail('Unable to insert media fixture attachment.');
	}
	$attachmentId = (int) $attachmentId;
	update_attached_file($attachmentId, (string) $upload['file']);
	$uploads = wp_upload_dir();
	$relative = ltrim(str_replace(trailingslashit((string) $uploads['basedir']), '', (string) $upload['file']), '/');
	wp_update_attachment_metadata(
		$attachmentId,
		array(
			'width'  => 1,
			'height' => 1,
			'file'   => $relative,
			'sizes'  => array(),
		)
	);
	return $attachmentId;
};

$propertyId = wp_insert_post(
	array(
		'post_type'   => 'wla_property',
		'post_status' => 'draft',
		'post_title'  => 'Rollback Media Fixture',
	),
	true
);
if (is_wp_error($propertyId) || (int) $propertyId < 1) {
	$fail('Unable to create media rollback property fixture.');
}
$propertyId = (int) $propertyId;

$oldGalleryA = $makeImageAttachment('wla-rb-old-a-' . $propertyId);
$oldGalleryB = $makeImageAttachment('wla-rb-old-b-' . $propertyId);
$oldFeatured = $makeImageAttachment('wla-rb-old-featured-' . $propertyId);
$newGallery = $makeImageAttachment('wla-rb-new-gallery-' . $propertyId);
$newFeatured = $makeImageAttachment('wla-rb-new-featured-' . $propertyId);
$attachmentIds = array($oldGalleryA, $oldGalleryB, $oldFeatured, $newGallery, $newFeatured);

$galleryKey = MetaSchema::metaKey('gallery_ids');
$expect(is_string($galleryKey) && $galleryKey !== '', 'Gallery meta key is unavailable.');
update_post_meta($propertyId, $galleryKey, array($oldGalleryA, $oldGalleryB));
$expect((bool) set_post_thumbnail($propertyId, $oldFeatured), 'Unable to seed previous featured image.');

$targets = array(TargetRegistry::MEDIA_GALLERY_URLS, TargetRegistry::MEDIA_FEATURED_IMAGE_URL);
$snapshotter = new RollbackSnapshotter();
$before = $snapshotter->captureScope($propertyId, $targets);

update_post_meta($propertyId, $galleryKey, array($newGallery));
$expect((bool) set_post_thumbnail($propertyId, $newFeatured), 'Unable to seed imported featured image.');
$after = $snapshotter->captureScope($propertyId, $targets);

$journalRow = array(
	'property_id'         => $propertyId,
	'original_action'     => RollbackJournalState::ACTION_UPDATED,
	'before_json'         => RollbackSnapshotCodec::encode($before),
	'after_json'          => RollbackSnapshotCodec::encode($after),
	'after_hash'          => RollbackSnapshotCodec::hash($after),
	'created_object_hash' => '',
	'journal_state'       => RollbackJournalState::READY,
	'rollback_status'     => RollbackJournalState::ROLLBACK_PENDING,
);

(new RollbackRestorer())->restore($journalRow);

$galleryAfter = get_post_meta($propertyId, $galleryKey, true);
$expect(is_array($galleryAfter) && array_values(array_map('intval', $galleryAfter)) === array($oldGalleryA, $oldGalleryB), 'Rollback did not restore previous gallery IDs.');
$expect((int) get_post_thumbnail_id($propertyId) === $oldFeatured, 'Rollback did not restore previous featured attachment.');

foreach ($attachmentIds as $attachmentId) {
	$expect(get_post($attachmentId) instanceof WP_Post, "Rollback deleted attachment {$attachmentId}.");
	$expect(get_post_type($attachmentId) === 'attachment', "Rollback changed attachment {$attachmentId} type.");
}

$restored = $snapshotter->captureScope($propertyId, $targets);
$expect(RollbackSnapshotCodec::equals($restored, $before), 'Restored media scope does not match the previous snapshot.');

echo "WLA Inmo rollback media integration tests passed.\n";
