<?php

namespace WLA\Inmo\Import;

interface RollbackLockInterface
{
	public function acquire(string $batchUuid, int $ttlSeconds = 30): ?string;

	public function release(string $batchUuid, string $token): void;
}
