<?php

namespace WLA\Inmo\Import;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Properties\Sanitizer;

final class RollbackSnapshotter
{
	/**
	 * Capture only targets touched by one import row.
	 *
	 * @param array<int,string> $targets Canonical TargetRegistry targets.
	 * @return array<string,mixed>
	 */
	public function captureScope(int $propertyId, array $targets): array
	{
		$post = get_post($propertyId);
		if (!is_object($post) || (string) ($post->post_type ?? '') !== PostType::POST_TYPE) {
			throw new RollbackException('rollback_property_missing', 'Rollback target property does not exist.');
		}

		$targets = array_values(array_unique(array_map('strval', $targets)));
		sort($targets, SORT_STRING);
		$values = array();

		foreach ($targets as $target) {
			$definition = TargetRegistry::definition($target);
			if ($definition === null) {
				throw new RollbackException('rollback_unknown_target', 'Rollback target is not registered.');
			}

			$kind = (string) ($definition['kind'] ?? '');
			if ($kind === 'post') {
				$values[$target] = $this->postValue($post, $target);
				continue;
			}

			if ($kind === 'meta') {
				$metaKey = (string) ($definition['meta_key'] ?? '');
				if ($metaKey === '') {
					throw new RollbackException('rollback_target_invalid', 'Rollback meta target is invalid.');
				}
				$state = $this->metaState($propertyId, $metaKey);
				if ($target === 'meta.external_id') {
					$state['source_key'] = $this->metaState($propertyId, IdentityMeta::SOURCE_KEY_META);
				}
				$values[$target] = $state;
				continue;
			}

			if ($kind === 'taxonomy') {
				$taxonomy = (string) ($definition['taxonomy'] ?? '');
				$terms = wp_get_object_terms($propertyId, $taxonomy, array('fields' => 'ids'));
				if (is_wp_error($terms)) {
					throw new RollbackException('rollback_taxonomy_read_failed', 'Rollback taxonomy state could not be read.');
				}
				$ids = array_values(array_unique(array_map('intval', (array) $terms)));
				sort($ids, SORT_NUMERIC);
				$values[$target] = $ids;
				continue;
			}

			if ($kind === 'media') {
				$values[$target] = $this->mediaState($propertyId, $target);
				continue;
			}

			throw new RollbackException('rollback_target_invalid', 'Rollback target kind is not supported.');
		}

		return array(
			'property_id' => $propertyId,
			'targets'     => $targets,
			'values'      => $values,
		);
	}

	/** @return array<int,string> */
	public function fullCanonicalTargets(): array
	{
		$targets = array_keys(TargetRegistry::definitions());
		$targets = array_values(array_unique(array_map('strval', $targets)));
		sort($targets, SORT_STRING);
		return $targets;
	}

	/**
	 * Hash-only footprint for deciding whether a property created by a batch can
	 * still be deleted safely. Unknown/third-party meta, all property taxonomies
	 * and native editorial fields are intentionally part of this fingerprint so
	 * later additions or edits fail closed without persisting their values.
	 */
	public function createdObjectHash(int $propertyId): string
	{
		$post = get_post($propertyId);
		if (!is_object($post) || (string) ($post->post_type ?? '') !== PostType::POST_TYPE) {
			throw new RollbackException('rollback_property_missing', 'Rollback target property does not exist.');
		}

		$postState = array(
			'post_status'           => (string) ($post->post_status ?? ''),
			'post_title'            => (string) ($post->post_title ?? ''),
			'post_content'          => (string) ($post->post_content ?? ''),
			'post_excerpt'          => (string) ($post->post_excerpt ?? ''),
			'post_name'             => (string) ($post->post_name ?? ''),
			'post_author'           => (int) ($post->post_author ?? 0),
			'post_parent'           => (int) ($post->post_parent ?? 0),
			'menu_order'            => (int) ($post->menu_order ?? 0),
			'post_password'         => (string) ($post->post_password ?? ''),
			'comment_status'        => (string) ($post->comment_status ?? ''),
			'ping_status'           => (string) ($post->ping_status ?? ''),
			'comment_count'         => (int) ($post->comment_count ?? 0),
			'post_date_gmt'         => (string) ($post->post_date_gmt ?? ''),
			'post_modified_gmt'     => (string) ($post->post_modified_gmt ?? ''),
			'post_content_filtered' => (string) ($post->post_content_filtered ?? ''),
			'post_mime_type'        => (string) ($post->post_mime_type ?? ''),
			'to_ping'               => (string) ($post->to_ping ?? ''),
			'pinged'                => (string) ($post->pinged ?? ''),
		);

		$meta = get_post_meta($propertyId);
		if (!is_array($meta)) {
			$meta = array();
		}

		$taxonomyState = array();
		$taxonomies = get_object_taxonomies(PostType::POST_TYPE, 'names');
		if (!is_array($taxonomies)) {
			$taxonomies = array();
		}
		foreach ($taxonomies as $taxonomy) {
			$taxonomy = (string) $taxonomy;
			$terms = wp_get_object_terms($propertyId, $taxonomy, array('fields' => 'ids'));
			if (is_wp_error($terms)) {
				throw new RollbackException('rollback_taxonomy_read_failed', 'Rollback taxonomy state could not be read.');
			}
			$ids = array_values(array_unique(array_map('intval', (array) $terms)));
			sort($ids, SORT_NUMERIC);
			$taxonomyState[$taxonomy] = $ids;
		}

		return RollbackSnapshotCodec::hash(
			array(
				'post'       => $postState,
				'meta'       => $meta,
				'taxonomies' => $taxonomyState,
			)
		);
	}

	private function postValue(object $post, string $target): string
	{
		return match ($target) {
			TargetRegistry::POST_TITLE => (string) ($post->post_title ?? ''),
			TargetRegistry::POST_CONTENT => (string) ($post->post_content ?? ''),
			TargetRegistry::POST_EXCERPT => (string) ($post->post_excerpt ?? ''),
			default => throw new RollbackException('rollback_target_invalid', 'Rollback post target is invalid.'),
		};
	}

	/** @return array{exists:bool,value:mixed} */
	private function metaState(int $propertyId, string $metaKey): array
	{
		return array(
			'exists' => metadata_exists('post', $propertyId, $metaKey),
			'value'  => get_post_meta($propertyId, $metaKey, true),
		);
	}

	/** @return array<string,mixed> */
	private function mediaState(int $propertyId, string $target): array
	{
		if ($target === TargetRegistry::MEDIA_GALLERY_URLS) {
			$galleryKey = MetaSchema::metaKey('gallery_ids');
			if ($galleryKey === null) {
				throw new RollbackException('rollback_media_schema_missing', 'Gallery schema is unavailable.');
			}
			return array(
				'exists'      => metadata_exists('post', $propertyId, $galleryKey),
				'gallery_ids' => Sanitizer::positiveIntegerArray(get_post_meta($propertyId, $galleryKey, true)),
			);
		}

		if ($target === TargetRegistry::MEDIA_FEATURED_IMAGE_URL) {
			return array('attachment_id' => (int) get_post_thumbnail_id($propertyId));
		}

		throw new RollbackException('rollback_target_invalid', 'Rollback media target is invalid.');
	}
}
