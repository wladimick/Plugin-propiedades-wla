<?php

namespace WLA\Inmo\Import;

use Throwable;
use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Properties\Sanitizer;
use WLA\Inmo\Quality\Indexer as QualityIndexer;
use WLA\Inmo\Search\Indexer as SearchIndexer;

final class RollbackRestorer
{
	private RollbackInspector $inspector;
	private RollbackSnapshotter $snapshotter;

	public function __construct(?RollbackInspector $inspector = null, ?RollbackSnapshotter $snapshotter = null)
	{
		$this->snapshotter = $snapshotter ?? new RollbackSnapshotter();
		$this->inspector = $inspector ?? new RollbackInspector($this->snapshotter);
	}

	/** @param array<string,mixed> $journalRow */
	public function restore(array $journalRow): void
	{
		$inspection = $this->inspector->inspect($journalRow);
		if ($inspection->status() === RollbackInspection::NOOP) {
			$this->cleanupDeletedProperty((int) ($journalRow['property_id'] ?? 0));
			return;
		}
		if (!$inspection->isSafe()) {
			throw new RollbackException(
				$inspection->reason() !== '' ? $inspection->reason() : 'rollback_not_safe',
				'Rollback row is no longer safe to restore.'
			);
		}

		$action = (string) ($journalRow['original_action'] ?? '');
		if ($action === RollbackJournalState::ACTION_CREATED) {
			$this->deleteCreatedProperty($journalRow);
			return;
		}
		if ($action === RollbackJournalState::ACTION_UPDATED) {
			$this->restoreUpdatedProperty($journalRow);
			return;
		}

		throw new RollbackException('rollback_journal_invalid', 'Rollback journal action is invalid.');
	}

	/** @param array<string,mixed> $journalRow */
	private function deleteCreatedProperty(array $journalRow): void
	{
		$propertyId = (int) ($journalRow['property_id'] ?? 0);
		if ($propertyId < 1 || get_post_type($propertyId) !== PostType::POST_TYPE) {
			throw new RollbackException('rollback_created_property_missing', 'Created rollback property is unavailable.');
		}

		$deleted = wp_delete_post($propertyId, true);
		if ($deleted === false || $deleted === null || get_post($propertyId) !== null) {
			throw new RollbackException('rollback_created_delete_failed', 'Batch-created property could not be deleted.');
		}

		$this->cleanupDeletedProperty($propertyId);
	}

	private function cleanupDeletedProperty(int $propertyId): void
	{
		if ($propertyId < 1) {
			throw new RollbackException('rollback_property_invalid', 'Rollback property ID is invalid.');
		}

		$identityOk = (new IdentityRepository())->delete($propertyId);
		$searchOk = SearchIndexer::syncNow($propertyId);
		$qualityOk = QualityIndexer::syncNow($propertyId);
		if (!$identityOk || !$searchOk || !$qualityOk) {
			throw new RollbackException('rollback_projection_cleanup_failed', 'Rollback could not clean all property projections.');
		}
	}

	/** @param array<string,mixed> $journalRow */
	private function restoreUpdatedProperty(array $journalRow): void
	{
		$propertyId = (int) ($journalRow['property_id'] ?? 0);
		$beforeJson = $journalRow['before_json'] ?? null;
		$afterJson = $journalRow['after_json'] ?? null;
		if ($propertyId < 1 || !is_string($beforeJson) || !is_string($afterJson) || $beforeJson === '' || $afterJson === '') {
			throw new RollbackException('rollback_snapshot_missing', 'Rollback snapshots are incomplete.');
		}

		$before = RollbackSnapshotCodec::decode($beforeJson);
		$after = RollbackSnapshotCodec::decode($afterJson);
		$this->validateRestorableSnapshot($before);
		$this->validateRestorableSnapshot($after);

		try {
			$this->applySnapshot($propertyId, $before);
			$this->syncProjections($propertyId);
			$this->assertSnapshot($propertyId, $before);
		} catch (Throwable $exception) {
			try {
				$this->applySnapshot($propertyId, $after);
				$this->syncProjections($propertyId);
				$this->assertSnapshot($propertyId, $after);
			} catch (Throwable $restoreException) {
				throw new RollbackException(
					'rollback_restore_partial_failure',
					'Rollback failed and the imported state could not be restored safely.',
					$restoreException
				);
			}

			if ($exception instanceof RollbackException) {
				throw $exception;
			}
			throw new RollbackException('rollback_restore_failed', 'Rollback target state could not be restored.', $exception);
		}
	}

	/** @param array<string,mixed> $snapshot */
	private function validateRestorableSnapshot(array $snapshot): void
	{
		$propertyId = (int) ($snapshot['property_id'] ?? 0);
		$targets = $snapshot['targets'] ?? null;
		$values = $snapshot['values'] ?? null;
		if ($propertyId < 1 || !is_array($targets) || $targets === array() || !is_array($values)) {
			throw new RollbackException('rollback_snapshot_invalid', 'Rollback snapshot shape is invalid.');
		}

		foreach ($targets as $target) {
			$target = (string) $target;
			$definition = TargetRegistry::definition($target);
			if ($definition === null || !array_key_exists($target, $values)) {
				throw new RollbackException('rollback_snapshot_invalid', 'Rollback snapshot target is invalid.');
			}
			$kind = (string) ($definition['kind'] ?? '');
			$value = $values[$target];

			if ($kind === 'taxonomy') {
				if (!is_array($value)) {
					throw new RollbackException('rollback_snapshot_invalid', 'Rollback taxonomy snapshot is invalid.');
				}
				$taxonomy = (string) ($definition['taxonomy'] ?? '');
				foreach ($value as $termId) {
					if ((int) $termId < 1 || term_exists((int) $termId, $taxonomy) === null) {
						throw new RollbackException('rollback_previous_term_missing', 'A previous taxonomy term no longer exists.');
					}
				}
				continue;
			}

			if ($kind === 'media') {
				$this->validateMediaSnapshot($target, $value);
			}
		}
	}

	private function validateMediaSnapshot(string $target, mixed $value): void
	{
		if (!is_array($value)) {
			throw new RollbackException('rollback_media_snapshot_invalid', 'Rollback media snapshot is invalid.');
		}

		$attachmentIds = array();
		if ($target === TargetRegistry::MEDIA_GALLERY_URLS) {
			$attachmentIds = Sanitizer::positiveIntegerArray($value['gallery_ids'] ?? array());
		} elseif ($target === TargetRegistry::MEDIA_FEATURED_IMAGE_URL) {
			$attachmentId = (int) ($value['attachment_id'] ?? 0);
			$attachmentIds = $attachmentId > 0 ? array($attachmentId) : array();
		}

		foreach ($attachmentIds as $attachmentId) {
			if (get_post_type($attachmentId) !== 'attachment') {
				throw new RollbackException('rollback_previous_attachment_missing', 'A previous media attachment no longer exists.');
			}
		}
	}

	/** @param array<string,mixed> $snapshot */
	private function applySnapshot(int $propertyId, array $snapshot): void
	{
		if ((int) ($snapshot['property_id'] ?? 0) !== $propertyId || get_post_type($propertyId) !== PostType::POST_TYPE) {
			throw new RollbackException('rollback_property_changed', 'Rollback property identity changed.');
		}

		$targets = (array) $snapshot['targets'];
		$values = (array) $snapshot['values'];
		$postUpdate = array('ID' => $propertyId);
		$hasPostUpdate = false;

		foreach ($targets as $target) {
			$target = (string) $target;
			$definition = TargetRegistry::definition($target);
			if ($definition === null) {
				throw new RollbackException('rollback_snapshot_invalid', 'Rollback snapshot target is invalid.');
			}
			$value = $values[$target];
			$kind = (string) ($definition['kind'] ?? '');

			if ($kind === 'post') {
				$field = match ($target) {
					TargetRegistry::POST_TITLE => 'post_title',
					TargetRegistry::POST_CONTENT => 'post_content',
					TargetRegistry::POST_EXCERPT => 'post_excerpt',
					default => '',
				};
				if ($field === '' || !is_string($value)) {
					throw new RollbackException('rollback_snapshot_invalid', 'Rollback post snapshot is invalid.');
				}
				$postUpdate[$field] = $value;
				$hasPostUpdate = true;
				continue;
			}

			if ($kind === 'meta') {
				$this->restoreMetaTarget($propertyId, $target, $definition, $value);
				continue;
			}

			if ($kind === 'taxonomy') {
				$termIds = array_values(array_map('intval', (array) $value));
				$result = wp_set_object_terms($propertyId, $termIds, (string) $definition['taxonomy'], false);
				if (is_wp_error($result)) {
					throw new RollbackException('rollback_taxonomy_restore_failed', 'Rollback taxonomy could not be restored.');
				}
				continue;
			}

			if ($kind === 'media') {
				$this->restoreMediaTarget($propertyId, $target, $value);
				continue;
			}

			throw new RollbackException('rollback_snapshot_invalid', 'Rollback target kind is invalid.');
		}

		if ($hasPostUpdate) {
			$result = wp_update_post($postUpdate, true);
			if (is_wp_error($result) || (int) $result !== $propertyId) {
				throw new RollbackException('rollback_post_restore_failed', 'Rollback post fields could not be restored.');
			}
		}
	}

	/** @param array<string,mixed> $definition */
	private function restoreMetaTarget(int $propertyId, string $target, array $definition, mixed $value): void
	{
		if (!is_array($value) || !array_key_exists('exists', $value)) {
			throw new RollbackException('rollback_meta_snapshot_invalid', 'Rollback meta snapshot is invalid.');
		}
		$metaKey = (string) ($definition['meta_key'] ?? '');
		if ($metaKey === '') {
			throw new RollbackException('rollback_meta_snapshot_invalid', 'Rollback meta key is invalid.');
		}

		$this->restoreMetaState($propertyId, $metaKey, $value);
		if ($target === 'meta.external_id') {
			$sourceState = $value['source_key'] ?? null;
			if (!is_array($sourceState) || !array_key_exists('exists', $sourceState)) {
				throw new RollbackException('rollback_identity_snapshot_invalid', 'Rollback source identity snapshot is invalid.');
			}
			$this->restoreMetaState($propertyId, IdentityMeta::SOURCE_KEY_META, $sourceState);
		}
	}

	/** @param array<string,mixed> $state */
	private function restoreMetaState(int $propertyId, string $metaKey, array $state): void
	{
		if (!empty($state['exists'])) {
			update_post_meta($propertyId, $metaKey, $state['value'] ?? null);
			if (!metadata_exists('post', $propertyId, $metaKey) || get_post_meta($propertyId, $metaKey, true) !== ($state['value'] ?? null)) {
				throw new RollbackException('rollback_meta_restore_failed', 'Rollback metadata could not be restored.');
			}
			return;
		}

		delete_post_meta($propertyId, $metaKey);
		if (metadata_exists('post', $propertyId, $metaKey)) {
			throw new RollbackException('rollback_meta_restore_failed', 'Rollback metadata could not be cleared.');
		}
	}

	private function restoreMediaTarget(int $propertyId, string $target, mixed $value): void
	{
		if (!is_array($value)) {
			throw new RollbackException('rollback_media_snapshot_invalid', 'Rollback media snapshot is invalid.');
		}

		if ($target === TargetRegistry::MEDIA_GALLERY_URLS) {
			$galleryKey = MetaSchema::metaKey('gallery_ids');
			if ($galleryKey === null || !array_key_exists('exists', $value)) {
				throw new RollbackException('rollback_media_snapshot_invalid', 'Rollback gallery snapshot is invalid.');
			}
			$ids = Sanitizer::positiveIntegerArray($value['gallery_ids'] ?? array());
			if (!empty($value['exists'])) {
				update_post_meta($propertyId, $galleryKey, $ids);
			} else {
				delete_post_meta($propertyId, $galleryKey);
			}
			return;
		}

		if ($target === TargetRegistry::MEDIA_FEATURED_IMAGE_URL) {
			$attachmentId = (int) ($value['attachment_id'] ?? 0);
			if ($attachmentId > 0) {
				if (!set_post_thumbnail($propertyId, $attachmentId)) {
					throw new RollbackException('rollback_featured_restore_failed', 'Rollback featured image could not be restored.');
				}
			} else {
				delete_post_thumbnail($propertyId);
			}
			return;
		}

		throw new RollbackException('rollback_media_snapshot_invalid', 'Rollback media target is invalid.');
	}

	private function syncProjections(int $propertyId): void
	{
		$projection = IdentityProjection::fromProperty($propertyId);
		$identity = new IdentityRepository();
		$identityOk = $projection === null ? $identity->delete($propertyId) : $identity->upsert($projection);
		if (!$identityOk || !SearchIndexer::syncNow($propertyId) || !QualityIndexer::syncNow($propertyId)) {
			throw new RollbackException('rollback_projection_sync_failed', 'Rollback projections could not be synchronized.');
		}
	}

	/** @param array<string,mixed> $snapshot */
	private function assertSnapshot(int $propertyId, array $snapshot): void
	{
		$targets = $snapshot['targets'] ?? null;
		if (!is_array($targets)) {
			throw new RollbackException('rollback_snapshot_invalid', 'Rollback snapshot scope is invalid.');
		}
		$current = $this->snapshotter->captureScope($propertyId, array_values(array_map('strval', $targets)));
		if (!RollbackSnapshotCodec::equals($current, $snapshot)) {
			throw new RollbackException('rollback_verification_failed', 'Rollback verification did not match the expected snapshot.');
		}
	}
}
