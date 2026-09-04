<?php

namespace WLA\Inmo\Import;

use WLA\Inmo\Properties\PostType;

final class IdentityIndexer
{
	public static function register(): void
	{
		add_action('save_post_' . PostType::POST_TYPE, array(self::class, 'sync'), 40, 3);
		add_action('before_delete_post', array(self::class, 'delete'), 10, 2);
		add_action('wp_trash_post', array(self::class, 'trash'), 10, 1);
		add_action('untrash_post', array(self::class, 'untrash'), 10, 1);
	}

	public static function sync(int $postId, $post = null, bool $update = false): void
	{
		unset($post, $update);

		if ($postId < 1 || wp_is_post_revision($postId)) {
			return;
		}

		$repository = new IdentityRepository();
		$projection = IdentityProjection::fromProperty($postId);

		if ($projection === null) {
			$repository->delete($postId);
			return;
		}

		if (!$repository->upsert($projection) && function_exists('do_action')) {
			do_action('wla_inmo_import_identity_conflict', $postId, $projection);
		}
	}

	public static function delete(int $postId, $post = null): void
	{
		if ($post !== null && isset($post->post_type) && $post->post_type !== PostType::POST_TYPE) {
			return;
		}

		(new IdentityRepository())->delete($postId);
	}

	public static function trash(int $postId): void
	{
		if (get_post_type($postId) !== PostType::POST_TYPE) {
			return;
		}

		(new IdentityRepository())->delete($postId);
	}

	public static function untrash(int $postId): void
	{
		self::sync($postId);
	}
}
