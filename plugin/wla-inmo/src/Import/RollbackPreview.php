<?php

namespace WLA\Inmo\Import;

final class RollbackPreview
{
	private string $batchUuid;
	private int $revision;
	private int $safe;
	private int $noop;
	private int $blocked;
	private int $errors;
	private int $createdToDelete;
	private int $updatesToRestore;
	private string $hash;

	public function __construct(
		string $batchUuid,
		int $revision,
		int $safe,
		int $noop,
		int $blocked,
		int $errors,
		int $createdToDelete,
		int $updatesToRestore,
		string $hash
	) {
		$this->batchUuid = $batchUuid;
		$this->revision = $revision;
		$this->safe = max(0, $safe);
		$this->noop = max(0, $noop);
		$this->blocked = max(0, $blocked);
		$this->errors = max(0, $errors);
		$this->createdToDelete = max(0, $createdToDelete);
		$this->updatesToRestore = max(0, $updatesToRestore);
		$this->hash = $hash;
	}

	public function batchUuid(): string { return $this->batchUuid; }
	public function revision(): int { return $this->revision; }
	public function safe(): int { return $this->safe; }
	public function noop(): int { return $this->noop; }
	public function blocked(): int { return $this->blocked; }
	public function errors(): int { return $this->errors; }
	public function createdToDelete(): int { return $this->createdToDelete; }
	public function updatesToRestore(): int { return $this->updatesToRestore; }
	public function hash(): string { return $this->hash; }

	public function canConfirm(): bool
	{
		return $this->blocked === 0 && $this->errors === 0 && ($this->safe + $this->noop) > 0;
	}

	/** @return array<string,int|string|bool> */
	public function toArray(): array
	{
		return array(
			'batch_uuid'         => $this->batchUuid,
			'revision'           => $this->revision,
			'safe'               => $this->safe,
			'noop'               => $this->noop,
			'blocked'            => $this->blocked,
			'errors'             => $this->errors,
			'created_to_delete'  => $this->createdToDelete,
			'updates_to_restore' => $this->updatesToRestore,
			'preview_hash'       => $this->hash,
			'can_confirm'        => $this->canConfirm(),
		);
	}
}
