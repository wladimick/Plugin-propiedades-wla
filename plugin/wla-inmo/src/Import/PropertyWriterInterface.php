<?php

namespace WLA\Inmo\Import;

interface PropertyWriterInterface
{
	/**
	 * Persist a new WLA property from canonical dry-run values.
	 *
	 * Implementations must throw ExecutionException on persistence failure and
	 * leave no newly-created property behind when creation cannot complete.
	 *
	 * @param array<string,mixed> $values Canonical, taxonomy-resolved values.
	 */
	public function create(array $values, string $sourceKey): int;

	/**
	 * Persist an update to an existing WLA property.
	 *
	 * Implementations must restore the previous canonical state when an update
	 * fails part way through, or throw `rollback_failed` when restoration itself
	 * cannot be completed safely.
	 *
	 * @param array<string,mixed> $values Canonical, taxonomy-resolved values.
	 */
	public function update(int $propertyId, array $values, string $sourceKey): void;
}
