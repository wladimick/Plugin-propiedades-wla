<?php

namespace WLA\Inmo\Quality;

use WLA\Inmo\Properties\PostType;

final class Rebuilder
{
	public static function rebuildAll(int $batchSize = 200): int
	{
		$batchSize = max(10, min(500, $batchSize));
		$repository = new Repository();
		if (!$repository->clear()) {
			return 0;
		}

		$total = 0;
		$page = 1;

		do {
			$query = new \WP_Query(
				array(
					'post_type' => PostType::POST_TYPE,
					'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
					'posts_per_page' => $batchSize,
					'paged' => $page,
					'fields' => 'ids',
					'orderby' => 'ID',
					'order' => 'ASC',
					'no_found_rows' => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			$ids = is_array($query->posts) ? array_map('intval', $query->posts) : array();
			foreach ($ids as $postId) {
				if (Indexer::syncNow($postId)) {
					++$total;
				}
			}

			++$page;
			$maxPages = max(1, (int) $query->max_num_pages);
		} while ($ids !== array() && $page <= $maxPages);

		return $total;
	}
}
