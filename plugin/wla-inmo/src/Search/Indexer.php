<?php

namespace WLA\Inmo\Search;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Taxonomies\Registry as TaxonomyRegistry;

final class Indexer
{
	/** @var array<int, true> */
	private static array $dirty = array();

	private static ?IndexRepository $repository = null;

	public static function register(): void
	{
		add_action('save_post_' . PostType::POST_TYPE, array(self::class, 'onSave'), 20, 3);
		add_action('transition_post_status', array(self::class, 'onStatusTransition'), 20, 3);
		add_action('set_object_terms', array(self::class, 'onTermsChanged'), 20, 6);
		add_action('added_post_meta', array(self::class, 'onMetaChanged'), 20, 4);
		add_action('updated_post_meta', array(self::class, 'onMetaChanged'), 20, 4);
		add_action('deleted_post_meta', array(self::class, 'onMetaChanged'), 20, 4);
		add_action('before_delete_post', array(self::class, 'onBeforeDelete'), 20, 2);
		add_action('shutdown', array(self::class, 'flush'), 20);
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
	 * @param mixed $terms Terms submitted to WordPress.
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
	 * WordPress passes an int for added/updated meta and an array of IDs for
	 * deleted meta, so the first argument intentionally remains untyped.
	 *
	 * @param mixed $metaId Meta ID or IDs.
	 * @param mixed $metaValue Metadata value.
	 */
	public static function onMetaChanged($metaId, int $objectId, string $metaKey, $metaValue): void
	{
		unset($metaId, $metaValue);

		if (strpos($metaKey, MetaSchema::META_PREFIX) !== 0) {
			return;
		}

		if (get_post_type($objectId) === PostType::POST_TYPE) {
			self::mark($objectId);
		}
	}

	public static function onBeforeDelete(int $postId, $post): void
	{
		if (!is_object($post) || !isset($post->post_type) || $post->post_type !== PostType::POST_TYPE) {
			return;
		}

		unset(self::$dirty[$postId]);
		self::repository()->delete($postId);
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

		$row = Projection::fromProperty($postId);

		if ($row === null) {
			return self::repository()->delete($postId);
		}

		return self::repository()->upsert($row);
	}

	public static function flush(): void
	{
		if (empty(self::$dirty)) {
			return;
		}

		$propertyIds = array_keys(self::$dirty);
		self::$dirty = array();

		foreach ($propertyIds as $propertyId) {
			self::syncNow((int) $propertyId);
		}
	}

	/**
	 * Test/support seam; runtime callers should not replace the repository.
	 */
	public static function setRepository(?IndexRepository $repository): void
	{
		self::$repository = $repository;
	}

	private static function repository(): IndexRepository
	{
		if (self::$repository === null) {
			self::$repository = new IndexRepository();
		}

		return self::$repository;
	}
}
