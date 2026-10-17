<?php

namespace WLA\Inmo\Frontend;

use WLA\Inmo\Properties\PostType;

/**
 * Keep the main public property archive bounded and stable without changing
 * administration, other post types, REST, or secondary theme queries.
 */
final class ArchiveQuery
{
	public const PAGE_SIZE = 12;

	public static function register(): void
	{
		add_action('pre_get_posts', array(self::class, 'configure'));
	}

	public static function configure(\WP_Query $query): void
	{
		if (is_admin() || (function_exists('wp_doing_ajax') && wp_doing_ajax())) {
			return;
		}

		if ((defined('REST_REQUEST') && REST_REQUEST) || !$query->is_main_query()) {
			return;
		}

		if (!$query->is_post_type_archive(PostType::POST_TYPE)) {
			return;
		}

		$query->set('post_type', PostType::POST_TYPE);
		$query->set('post_status', 'publish');
		$query->set('posts_per_page', self::PAGE_SIZE);
		$query->set('ignore_sticky_posts', true);
		$query->set('orderby', array('date' => 'DESC', 'ID' => 'DESC'));
		$query->set('no_found_rows', false);
		$query->set('update_post_meta_cache', true);
		$query->set('update_post_term_cache', true);

		// Public GET search is read-only: no state change or privileged action requires a nonce.
		// phpcs:disable WordPress.Security.NonceVerification -- Public archive GET parameters only.
		SearchFilters::apply($query, is_array($_GET) ? $_GET : array());
		// phpcs:enable WordPress.Security.NonceVerification
	}
}
