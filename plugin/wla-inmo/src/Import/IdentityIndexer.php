<?php

namespace WLA\Inmo\Import;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;

final class IdentityIndexer
{
	/** @var array<int,bool> */
	private static array $dirtyPostIds = array();

	public static function register(): void
	{
		add_action('save_post_' . PostType::POST_TYPE, array(self::class, 'sync'), 40, 3);
		add_action('added_post_meta', array(self::class, 'metaChanged'), 40, 4);
		add_action('updated_post_meta', array(self::class, 'metaChanged'), 40, 4);
		add_action('deleted_post_meta', array(self::class, 'metaChanged'), 40, 4);
		add_action('before_delete_post', array(self::class, 'delete'), 10, 2);
		add_action('deleted_post', array(self::class, 'delete'), 10, 2);
		add_action('wp_trash_post', array(self::class, 'trash'), 10, 1);
		add_action('untrash_post', array(self::class, 'untrash'), 10, 1);
		add_action('shutdown', array(self::class, 'flushDirty'), 20);
	}

	public static function sync(int $postId, mixed $post = null, bool $update = false): void
	{
		unset($post, $update);

		if ($postId < 1 || wp_is_post_revision($postId)) {
			return;
		}

		unset(self::$dirtyPostIds[$postId]);

		$repository = new IdentityRepository();
		$projection = IdentityProjection::fromProperty($postId);

		if ($projection === null) {
			$repository->delete($postId);
			return;
		}

		if (!$repository->upsert($projection)) {
			do_action('wla_inmo_import_identity_conflict', $postId, $projection);
		}
	}

	/**
	 * Direct meta integrations may update source key, external ID and code in
	 * several consecutive calls. Mark the property dirty and rebuild once after
	 * the write sequence so no transient identity pair becomes queryable.
	 */
	public static function metaChanged(mixed $metaId, int $postId, string $metaKey, mixed $metaValue): void
	{
		unset($metaId, $metaValue);

		if ($postId < 1 || !in_array($metaKey, self::identityMetaKeys(), true)) {
			return;
		}

		if (get_post_type($postId) !== PostType::POST_TYPE) {
			return;
		}

		self::$dirtyPostIds[$postId] = true;
	}

	public static function flushDirty(): void
	{
		$postIds = array_keys(self::$dirtyPostIds);
		self::$dirtyPostIds = array();

		foreach ($postIds as $postId) {
			self::sync((int) $postId);
		}
	}

	public static function delete(int $postId, mixed $post = null): void
	{
		if ($post !== null && isset($post->post_type) && $post->post_type !== PostType::POST_TYPE) {
			return;
		}

		unset(self::$dirtyPostIds[$postId]);
		(new IdentityRepository())->delete($postId);
	}

	public static function trash(int $postId): void
	{
		if (get_post_type($postId) !== PostType::POST_TYPE) {
			return;
		}

		unset(self::$dirtyPostIds[$postId]);
		(new IdentityRepository())->delete($postId);
	}

	public static function untrash(int $postId): void
	{
		self::sync($postId);
	}

	/**
	 * @return array<int,string>
	 */
	public static function identityMetaKeys(): array
	{
		return array(
			IdentityMeta::SOURCE_KEY_META,
			MetaSchema::META_PREFIX . 'external_id',
			MetaSchema::META_PREFIX . 'property_code',
		);
	}
}
