<?php

namespace WLA\Inmo\Import;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Taxonomies\Registry as TaxonomyRegistry;

final class WordPressJsonExportSource implements JsonExportSourceInterface
{
	/** @var array<int,string> */
	private array $postStatuses;

	/** @param array<int,string>|null $postStatuses Allowed WordPress statuses to export. */
	public function __construct(?array $postStatuses = null)
	{
		$allowed = array('publish', 'draft', 'pending', 'private');
		$postStatuses = $postStatuses ?? $allowed;
		$this->postStatuses = array_values(array_intersect($allowed, array_map('strval', $postStatuses)));
		if ($this->postStatuses === array()) {
			$this->postStatuses = array('publish');
		}
	}

	/** @return array<int,array<string,mixed>> */
	public function page(int $page, int $pageSize): array
	{
		$page = max(1, $page);
		$pageSize = max(1, min(250, $pageSize));
		$query = new \WP_Query(
			array(
				'post_type'              => PostType::POST_TYPE,
				'post_status'            => $this->postStatuses,
				'posts_per_page'         => $pageSize,
				'paged'                  => $page,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
			)
		);

		$posts = is_array($query->posts) ? $query->posts : array();
		if ($posts === array()) {
			return array();
		}

		$ids = array_values(array_filter(array_map(static fn (mixed $post): int => isset($post->ID) ? (int) $post->ID : 0, $posts)));
		if ($ids === array()) {
			return array();
		}

		$terms = $this->loadTerms($ids);
		$properties = array();
		foreach ($posts as $post) {
			$id = isset($post->ID) ? (int) $post->ID : 0;
			if ($id < 1) {
				continue;
			}

			$property = array(
				'post' => array(
					'title'   => (string) ($post->post_title ?? ''),
					'content' => (string) ($post->post_content ?? ''),
					'excerpt' => (string) ($post->post_excerpt ?? ''),
				),
			);

			$meta = $this->publicMeta($id);
			if ($meta !== array()) {
				$property['meta'] = $meta;
			}
			if (isset($terms[$id]) && $terms[$id] !== array()) {
				$property['taxonomies'] = $terms[$id];
			}
			$media = $this->portableMedia($id);
			if ($media !== array()) {
				$property['media'] = $media;
			}

			$properties[] = $property;
		}

		return $properties;
	}

	/** @return array<string,mixed> */
	private function publicMeta(int $postId): array
	{
		$meta = array();
		foreach (TargetRegistry::definitions() as $target => $definition) {
			if ((string) ($definition['kind'] ?? '') !== 'meta' || !empty($definition['private'])) {
				continue;
			}

			$metaKey = (string) ($definition['meta_key'] ?? '');
			$field = (string) ($definition['field'] ?? '');
			if ($metaKey === '' || $field === '' || !metadata_exists('post', $postId, $metaKey)) {
				continue;
			}

			$value = get_post_meta($postId, $metaKey, true);
			if (!self::isPortableValue($value)) {
				continue;
			}
			$meta[$field] = $value;
		}

		return $meta;
	}

	/** @return array<string,mixed> */
	private function portableMedia(int $postId): array
	{
		$media = array();
		$definitions = MetaSchema::definitions();
		$galleryDefinition = $definitions['gallery_ids'] ?? null;
		$galleryKey = is_array($galleryDefinition) ? (string) ($galleryDefinition['meta_key'] ?? '') : '';
		$galleryIds = $galleryKey !== '' ? get_post_meta($postId, $galleryKey, true) : array();
		$galleryUrls = array();

		if (is_array($galleryIds)) {
			foreach ($galleryIds as $attachmentId) {
				$url = wp_get_attachment_url((int) $attachmentId);
				if (self::isPortableHttpUrl($url)) {
					$galleryUrls[] = (string) $url;
				}
			}
		}
		$galleryUrls = array_values(array_unique($galleryUrls));
		if ($galleryUrls !== array()) {
			$media['gallery_urls'] = $galleryUrls;
		}

		$featuredId = (int) get_post_thumbnail_id($postId);
		if ($featuredId > 0) {
			$featuredUrl = wp_get_attachment_url($featuredId);
			if (self::isPortableHttpUrl($featuredUrl)) {
				$media['featured_image_url'] = (string) $featuredUrl;
			}
		}

		return $media;
	}

	/**
	 * Load each taxonomy once for the whole page instead of once per property.
	 *
	 * @param array<int,int> $ids Property IDs.
	 * @return array<int,array<string,mixed>>
	 */
	private function loadTerms(array $ids): array
	{
		$map = array(
			'operation'     => TaxonomyRegistry::OPERATION,
			'property_type' => TaxonomyRegistry::PROPERTY_TYPE,
			'region'        => TaxonomyRegistry::REGION,
			'commune'       => TaxonomyRegistry::COMMUNE,
			'sector'        => TaxonomyRegistry::SECTOR,
			'feature'       => TaxonomyRegistry::FEATURE,
		);
		$byPost = array();

		foreach ($map as $logical => $taxonomy) {
			$terms = wp_get_object_terms($ids, $taxonomy, array('fields' => 'all_with_object_id'));
			if (is_wp_error($terms)) {
				throw new JsonException('export_taxonomy_failed', 'JSON export could not read property taxonomies.');
			}

			foreach ($terms as $term) {
				$postId = isset($term->object_id) ? (int) $term->object_id : 0;
				$name = isset($term->name) ? trim((string) $term->name) : '';
				if ($postId < 1 || $name === '') {
					continue;
				}

				if ($logical === 'feature') {
					$existing = (array) ($byPost[$postId][$logical] ?? array());
					$existing[] = $name;
					$byPost[$postId][$logical] = array_values(array_unique($existing));
				} elseif (!isset($byPost[$postId][$logical])) {
					$byPost[$postId][$logical] = $name;
				}
			}
		}

		return $byPost;
	}

	private static function isPortableValue(mixed $value): bool
	{
		if (is_scalar($value) || $value === null) {
			return true;
		}
		if (!is_array($value) || !array_is_list($value)) {
			return false;
		}

		foreach ($value as $item) {
			if (!is_scalar($item) && $item !== null) {
				return false;
			}
		}

		return true;
	}

	private static function isPortableHttpUrl(mixed $url): bool
	{
		if (!is_string($url) || $url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
			return false;
		}
		$scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
		return in_array($scheme, array('http', 'https'), true);
	}
}
