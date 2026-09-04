<?php

namespace WLA\Inmo\Import;

use Throwable;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Properties\Sanitizer;
use WLA\Inmo\Quality\Indexer as QualityIndexer;
use WLA\Inmo\Search\Indexer as SearchIndexer;

final class WordPressPropertyWriter implements PropertyWriterInterface
{
	/**
	 * @param array<string,mixed> $values Canonical, taxonomy-resolved values.
	 */
	public function create(array $values, string $sourceKey): int
	{
		$sourceKey = $this->validatedSourceKey($sourceKey);
		$prepared = $this->prepareValues($values);
		$title = (string) ($prepared['post']['post_title'] ?? '');

		if (trim($title) === '') {
			throw new ExecutionException('title_required_for_new', TargetRegistry::POST_TITLE);
		}

		$postData = array(
			'post_type'    => PostType::POST_TYPE,
			'post_status'  => 'draft',
			'post_title'   => $title,
			'post_content' => (string) ($prepared['post']['post_content'] ?? ''),
			'post_excerpt' => (string) ($prepared['post']['post_excerpt'] ?? ''),
		);

		$propertyId = wp_insert_post($postData, true);
		if (is_wp_error($propertyId) || (int) $propertyId < 1) {
			throw new ExecutionException('property_create_failed', 'post');
		}

		$propertyId = (int) $propertyId;

		try {
			$this->applyPrepared($propertyId, $prepared, $sourceKey, false);
			$this->assertIdentityProjection($propertyId);
			$this->syncSecondaryProjections($propertyId);
		} catch (Throwable $exception) {
			$deleted = wp_delete_post($propertyId, true);
			(new IdentityRepository())->delete($propertyId);

			if ($deleted === false || $deleted === null) {
				throw new ExecutionException('rollback_failed', 'persistence', $exception);
			}

			if ($exception instanceof ExecutionException) {
				throw $exception;
			}

			throw new ExecutionException('property_create_failed', 'persistence', $exception);
		}

		return $propertyId;
	}

	/**
	 * @param array<string,mixed> $values Canonical, taxonomy-resolved values.
	 */
	public function update(int $propertyId, array $values, string $sourceKey): void
	{
		if ($propertyId < 1 || get_post_type($propertyId) !== PostType::POST_TYPE) {
			throw new ExecutionException('property_not_found', 'identity');
		}

		$sourceKey = $this->validatedSourceKey($sourceKey);
		$prepared = $this->prepareValues($values);
		$snapshot = $this->snapshot($propertyId, $prepared);

		try {
			$this->applyPrepared($propertyId, $prepared, $sourceKey, true);
			$this->assertIdentityProjection($propertyId);
			$this->syncSecondaryProjections($propertyId);
		} catch (Throwable $exception) {
			if (!$this->restoreSnapshot($propertyId, $snapshot)) {
				throw new ExecutionException('rollback_failed', 'persistence', $exception);
			}

			if ($exception instanceof ExecutionException) {
				throw $exception;
			}

			throw new ExecutionException('property_update_failed', 'persistence', $exception);
		}
	}

	private function validatedSourceKey(string $sourceKey): string
	{
		$normalized = SourceKey::normalize($sourceKey);
		if (!SourceKey::isValid($normalized)) {
			throw new ExecutionException('invalid_source_key', 'identity');
		}

		return $normalized;
	}

	/**
	 * Convert the execution payload into strict WordPress operations before any
	 * mutation occurs. Taxonomies must already have been resolved by dry-run.
	 *
	 * @param array<string,mixed> $values Canonical values.
	 * @return array{
	 *   post:array<string,string>,
	 *   meta:array<string,array{field:string,meta_key:string,definition:array<string,mixed>,value:mixed}>,
	 *   taxonomies:array<string,array{taxonomy:string,term_ids:array<int,int>}>,
	 *   touches_external_id:bool
	 * }
	 */
	private function prepareValues(array $values): array
	{
		$prepared = array(
			'post'                => array(),
			'meta'                => array(),
			'taxonomies'          => array(),
			'touches_external_id' => false,
		);

		foreach ($values as $target => $value) {
			$target = (string) $target;
			$definition = TargetRegistry::definition($target);
			if ($definition === null) {
				throw new ExecutionException('unknown_target', $target);
			}

			$kind = (string) ($definition['kind'] ?? '');
			if ($kind === 'post') {
				$this->preparePostValue($prepared['post'], $target, $value);
				continue;
			}

			if ($kind === 'meta') {
				$field = (string) ($definition['field'] ?? '');
				$metaKey = (string) ($definition['meta_key'] ?? '');
				if ($field === '' || $metaKey === '') {
					throw new ExecutionException('invalid_target_definition', $target);
				}

				$prepared['meta'][$target] = array(
					'field'      => $field,
					'meta_key'   => $metaKey,
					'definition' => $definition,
					'value'      => $value === null ? null : $this->sanitizeMetaValue($definition, $value, $target),
				);

				if ($target === 'meta.external_id') {
					$prepared['touches_external_id'] = true;
				}
				continue;
			}

			if ($kind === 'taxonomy') {
				$taxonomy = (string) ($definition['taxonomy'] ?? '');
				if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
					throw new ExecutionException('invalid_taxonomy', $target);
				}

				$prepared['taxonomies'][$target] = array(
					'taxonomy' => $taxonomy,
					'term_ids' => $this->resolvedTermIds($definition, $value, $target),
				);
				continue;
			}

			throw new ExecutionException('invalid_target_definition', $target);
		}

		return $prepared;
	}

	/**
	 * @param array<string,string> $postData Prepared WordPress post data.
	 */
	private function preparePostValue(array &$postData, string $target, mixed $value): void
	{
		$value = $value === null ? '' : $value;

		if ($target === TargetRegistry::POST_TITLE) {
			$postData['post_title'] = Sanitizer::text($value);
			return;
		}

		if ($target === TargetRegistry::POST_CONTENT) {
			$postData['post_content'] = Sanitizer::textarea($value);
			return;
		}

		if ($target === TargetRegistry::POST_EXCERPT) {
			$postData['post_excerpt'] = Sanitizer::textarea($value);
			return;
		}

		throw new ExecutionException('unknown_post_target', $target);
	}

	/**
	 * @param array<string,mixed> $definition Target definition.
	 */
	private function sanitizeMetaValue(array $definition, mixed $value, string $target): mixed
	{
		$callback = $definition['sanitize_callback'] ?? null;
		if (!is_callable($callback)) {
			throw new ExecutionException('invalid_target_sanitizer', $target);
		}

		$clean = call_user_func($callback, $value);
		$type = (string) ($definition['type'] ?? '');

		$valid = match ($type) {
			'boolean' => is_bool($clean),
			'integer' => is_int($clean),
			'number'  => is_int($clean) || is_float($clean),
			'array'   => is_array($clean),
			'string'  => is_string($clean),
			default   => false,
		};

		if (!$valid) {
			throw new ExecutionException('invalid_target_value', $target);
		}

		if (in_array($target, array('meta.external_id', 'meta.property_code'), true) && trim((string) $clean) === '') {
			throw new ExecutionException('invalid_identity_value', $target);
		}

		return $clean;
	}

	/**
	 * @param array<string,mixed> $definition Taxonomy target definition.
	 * @return array<int,int>
	 */
	private function resolvedTermIds(array $definition, mixed $value, string $target): array
	{
		if ($value === null || $value === array()) {
			return array();
		}

		$multiple = !empty($definition['multiple']);
		$items = $multiple ? $value : array($value);
		if (!is_array($items) || $items === array()) {
			throw new ExecutionException('unresolved_taxonomy_value', $target);
		}

		$taxonomy = (string) $definition['taxonomy'];
		$termIds = array();

		foreach ($items as $item) {
			if (!is_array($item)) {
				throw new ExecutionException('unresolved_taxonomy_value', $target);
			}

			$termId = (int) ($item['id'] ?? 0);
			if ($termId < 1 || term_exists($termId, $taxonomy) === null) {
				throw new ExecutionException('taxonomy_term_missing_since_dry_run', $target);
			}

			$termIds[] = $termId;
		}

		$termIds = array_values(array_unique($termIds));
		if (!$multiple && count($termIds) !== 1) {
			throw new ExecutionException('multiple_terms_for_single_target', $target);
		}

		return $termIds;
	}

	/**
	 * @param array<string,mixed> $prepared Prepared values.
	 */
	private function applyPrepared(int $propertyId, array $prepared, string $sourceKey, bool $updatePost): void
	{
		if ($updatePost && $prepared['post'] !== array()) {
			$postData = array_merge(array('ID' => $propertyId), $prepared['post']);
			$updatedId = wp_update_post($postData, true);
			if (is_wp_error($updatedId) || (int) $updatedId !== $propertyId) {
				throw new ExecutionException('post_update_failed', 'post');
			}
		}

		if (!empty($prepared['touches_external_id'])) {
			$external = $prepared['meta']['meta.external_id']['value'] ?? null;
			if ($external === null || trim((string) $external) === '') {
				$this->clearMeta($propertyId, IdentityMeta::SOURCE_KEY_META);
			} else {
				$this->setRawMeta($propertyId, IdentityMeta::SOURCE_KEY_META, $sourceKey, 'identity');
			}
		}

		foreach ($prepared['meta'] as $target => $operation) {
			$metaKey = (string) $operation['meta_key'];
			$value = $operation['value'];

			if ($value === null) {
				$this->clearMeta($propertyId, $metaKey);
				continue;
			}

			update_post_meta($propertyId, $metaKey, $value);
			if (!$this->metaMatches($propertyId, $operation['definition'], $value)) {
				throw new ExecutionException('meta_write_failed', (string) $target);
			}
		}

		foreach ($prepared['taxonomies'] as $target => $operation) {
			$result = wp_set_object_terms(
				$propertyId,
				$operation['term_ids'],
				(string) $operation['taxonomy'],
				false
			);

			if (is_wp_error($result)) {
				throw new ExecutionException('taxonomy_write_failed', (string) $target);
			}
		}
	}

	private function assertIdentityProjection(int $propertyId): void
	{
		$projection = IdentityProjection::fromProperty($propertyId);
		if ($projection === null || !(new IdentityRepository())->upsert($projection)) {
			throw new ExecutionException('identity_projection_conflict', 'identity');
		}
	}

	private function syncSecondaryProjections(int $propertyId): void
	{
		if (!SearchIndexer::syncNow($propertyId) || !QualityIndexer::syncNow($propertyId)) {
			throw new ExecutionException('secondary_projection_sync_failed', 'persistence');
		}
	}

	/**
	 * @param array<string,mixed> $definition Meta target definition.
	 */
	private function metaMatches(int $propertyId, array $definition, mixed $expected): bool
	{
		$metaKey = (string) ($definition['meta_key'] ?? '');
		$callback = $definition['sanitize_callback'] ?? null;
		if ($metaKey === '' || !is_callable($callback)) {
			return false;
		}

		$actual = get_post_meta($propertyId, $metaKey, true);
		$actual = call_user_func($callback, $actual);

		if (is_float($expected) || is_int($expected)) {
			return is_numeric($actual) && (float) $actual === (float) $expected;
		}

		return $actual === $expected;
	}

	private function setRawMeta(int $propertyId, string $metaKey, string $value, string $target): void
	{
		update_post_meta($propertyId, $metaKey, $value);
		if ((string) get_post_meta($propertyId, $metaKey, true) !== $value) {
			throw new ExecutionException('meta_write_failed', $target);
		}
	}

	private function clearMeta(int $propertyId, string $metaKey): void
	{
		delete_post_meta($propertyId, $metaKey);
		if (metadata_exists('post', $propertyId, $metaKey)) {
			throw new ExecutionException('meta_clear_failed', $metaKey);
		}
	}

	/**
	 * @param array<string,mixed> $prepared Prepared values.
	 * @return array<string,mixed>
	 */
	private function snapshot(int $propertyId, array $prepared): array
	{
		$post = get_post($propertyId);
		if (!is_object($post) || !isset($post->ID, $post->post_type) || $post->post_type !== PostType::POST_TYPE) {
			throw new ExecutionException('property_not_found', 'identity');
		}

		$snapshot = array(
			'post' => array(
				'ID'           => $propertyId,
				'post_title'   => (string) $post->post_title,
				'post_content' => (string) $post->post_content,
				'post_excerpt' => (string) $post->post_excerpt,
			),
			'meta' => array(),
			'taxonomies' => array(),
		);

		foreach ($prepared['meta'] as $operation) {
			$metaKey = (string) $operation['meta_key'];
			$snapshot['meta'][$metaKey] = array(
				'exists' => metadata_exists('post', $propertyId, $metaKey),
				'value'  => get_post_meta($propertyId, $metaKey, true),
			);
		}

		if (!empty($prepared['touches_external_id'])) {
			$snapshot['meta'][IdentityMeta::SOURCE_KEY_META] = array(
				'exists' => metadata_exists('post', $propertyId, IdentityMeta::SOURCE_KEY_META),
				'value'  => get_post_meta($propertyId, IdentityMeta::SOURCE_KEY_META, true),
			);
		}

		foreach ($prepared['taxonomies'] as $operation) {
			$taxonomy = (string) $operation['taxonomy'];
			$terms = wp_get_object_terms($propertyId, $taxonomy, array('fields' => 'ids'));
			if (is_wp_error($terms)) {
				throw new ExecutionException('taxonomy_snapshot_failed', $taxonomy);
			}

			$snapshot['taxonomies'][$taxonomy] = array_values(array_map('intval', (array) $terms));
		}

		return $snapshot;
	}

	/**
	 * @param array<string,mixed> $snapshot Previous canonical state.
	 */
	private function restoreSnapshot(int $propertyId, array $snapshot): bool
	{
		$postRestored = wp_update_post($snapshot['post'], true);
		if (is_wp_error($postRestored) || (int) $postRestored !== $propertyId) {
			return false;
		}

		foreach ($snapshot['meta'] as $metaKey => $state) {
			if (!empty($state['exists'])) {
				update_post_meta($propertyId, (string) $metaKey, $state['value']);
			} else {
				delete_post_meta($propertyId, (string) $metaKey);
			}
		}

		foreach ($snapshot['taxonomies'] as $taxonomy => $termIds) {
			$result = wp_set_object_terms($propertyId, $termIds, (string) $taxonomy, false);
			if (is_wp_error($result)) {
				return false;
			}
		}

		$projection = IdentityProjection::fromProperty($propertyId);
		$identityRepository = new IdentityRepository();
		if ($projection === null) {
			$identityRepository->delete($propertyId);
		} elseif (!$identityRepository->upsert($projection)) {
			return false;
		}

		return SearchIndexer::syncNow($propertyId) && QualityIndexer::syncNow($propertyId);
	}
}
