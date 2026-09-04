<?php

namespace WLA\Inmo\Search;

use WLA\Inmo\Properties\PostType;

final class Rebuilder
{
	public static function reset(?IndexRepository $repository = null): bool
	{
		$repository = $repository ?? new IndexRepository();

		return $repository->clear();
	}

	/**
	 * Rebuild one deterministic page of published properties.
	 *
	 * @return array{processed:int,page:int,next_page:int|null,done:bool,failed:array<int,int>}
	 */
	public static function batch(int $page = 1, int $perPage = 100): array
	{
		$page = max(1, $page);
		$perPage = max(1, min(500, $perPage));

		$ids = get_posts(
			array(
				'post_type'              => PostType::POST_TYPE,
				'post_status'            => 'publish',
				'fields'                 => 'ids',
				'posts_per_page'         => $perPage,
				'paged'                  => $page,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => false,
			)
		);

		$ids = is_array($ids) ? array_values(array_map('intval', $ids)) : array();
		$failed = array();

		foreach ($ids as $propertyId) {
			if ($propertyId < 1 || !Indexer::syncNow($propertyId)) {
				$failed[] = $propertyId;
			}
		}

		$done = count($ids) < $perPage;

		return array(
			'processed' => count($ids),
			'page'      => $page,
			'next_page' => $done ? null : $page + 1,
			'done'      => $done,
			'failed'    => $failed,
		);
	}
}
