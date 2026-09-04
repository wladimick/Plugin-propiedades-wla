<?php

namespace WLA\Inmo\Quality;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Taxonomies\Registry as TaxonomyRegistry;

final class Indexer
{
	/** @var array<int,true> */
	private static array $dirty = array();

	private static ?Repository $repository = null;

	public static function register(): void
	{
		add_action('save_post_' . PostType::POST_TYPE, array(self::class, 'onSave'), 40, 3);
		add_action('transition_post_status', array(self::class, 'onStatusTransition'), 40, 3);
		add_action('set_object_terms', array(self::class, 'onTermsChanged'), 40, 6);
		add_action('added_post_meta', array(self::class, 'onMetaChanged'), 40, 4);
		add_action('updated_post_meta', array(self::class, 'onMetaChanged'), 40, 4);
		add_action('deleted_post_meta', array(self::class, 'onMetaChanged'), 40, 4);
		add_action('before_delete_post', array(self::class, 'onBeforeDelete'), 40, 2);
		add_action('shutdown', array(self::class, 'flush'), 40);
	}

	public static function onSave(int $postId, $post, bool $update): void
	{
		unset($update);

		if (is_object($post) && isset($post->post_type) && $post->post_type === PostType::POST_TYPE) {
			self::mark($postId);
		}
	}

	public static function onStatusTransition(string $newStatus, string $oldStatus, $post): void
	{
		unset($newStatus, $oldStatus);

		if (is_object($post) && isset($post->ID, $post->post_type) && $post->post_type === PostType::POST_TYPE) {
			self::mark((int) $post->ID);
		}
	}

	/**
	 * @param mixed $terms Submitted terms.
	 * @param mixed $ttIds Term taxonomy IDs.
	 * @param mixed $oldTtIds Previous term taxonomy IDs.
	 */
	public static function onTermsChanged(int $objectId, $terms, $ttIds, string $taxonomy, bool $append, $oldTtIds): void
	{
		unset($terms, $ttIds, $append, $oldTtIds);

		if (in_array($taxonomy, TaxonomyRegistry::keys(), true) && get_post_type($objectId) === PostType::POST_TYPE) {
			self::mark($objectId);
		}
	}

	/**
	 * @param mixed $metaId Meta ID or IDs.
	 * @param mixed $metaValue Meta value.
	 */
	public static function onMetaChanged($metaId, int $objectId, string $metaKey, $metaValue): void
	{
		unset($metaId, $metaValue);

		$postType = get_post_type($objectId);
		if ($postType === PostType::POST_TYPE && (strpos($metaKey, MetaSchema::META_PREFIX) === 0 || $metaKey === '_thumbnail_id')) {
			self::mark($objectId);
			return;
		}

		if ($postType === 'attachment' && $metaKey === '_wp_attachment_image_alt') {
			self::markPropertiesUsingAttachment($objectId);
		}
	}

	public static function onBeforeDelete(int $postId, $post): void
	{
		if (!is_object($post) || !isset($post->post_type)) {
			return;
		}

		if ($post->post_type === PostType::POST_TYPE) {
			unset(self::$dirty[$postId]);
			self::repository()->delete($postId);
			return;
		}

		if ($post->post_type === 'attachment') {
			self::markPropertiesUsingAttachment($postId);
		}
	}

	public static function mark(int $postId): void
	{
		if ($postId > 0) {
			self::$dirty[$postId] = true;
		}
	}

	public static function syncNow(int $postId): bool
	{
		if ($postId < 1) {
			return false;
		}

		$row = Evaluator::fromProperty($postId);
		if ($row === null) {
			return self::repository()->delete($postId);
		}

		unset($row['checks']);

		return self::repository()->upsert($row);
	}

	public static function flush(): void
	{
		if (self::$dirty === array()) {
			return;
		}

		$propertyIds = array_keys(self::$dirty);
		self::$dirty = array();

		foreach ($propertyIds as $propertyId) {
			self::syncNow((int) $propertyId);
		}
	}

	public static function setRepository(?Repository $repository): void
	{
		self::$repository = $repository;
	}

	private static function repository(): Repository
	{
		if (self::$repository === null) {
			self::$repository = new Repository();
		}

		return self::$repository;
	}

	private static function markPropertiesUsingAttachment(int $attachmentId): void
	{
		if ($attachmentId < 1) {
			return;
		}

		global $wpdb;
		if (!isset($wpdb) || !method_exists($wpdb, 'prepare')) {
			return;
		}

		$galleryKey = MetaSchema::metaKey('gallery_ids');
		if ($galleryKey === null) {
			return;
		}

		$serializedNeedle = '%i:' . $wpdb->esc_like((string) $attachmentId) . ';%';
		$gallerySql = $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
			$galleryKey,
			$serializedNeedle
		);
		$featuredSql = $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id' AND meta_value = %d",
			$attachmentId
		);
		$propertyIds = array_merge((array) $wpdb->get_col($gallerySql), (array) $wpdb->get_col($featuredSql));

		foreach (array_unique(array_map('intval', $propertyIds)) as $propertyId) {
			if ($propertyId > 0 && get_post_type($propertyId) === PostType::POST_TYPE) {
				self::mark($propertyId);
			}
		}
	}
}
