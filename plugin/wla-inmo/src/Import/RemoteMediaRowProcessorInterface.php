<?php

namespace WLA\Inmo\Import;

interface RemoteMediaRowProcessorInterface
{
	/**
	 * @param array<string,mixed> $mediaValues Canonical import-only media values.
	 * @return array<int,array{code:string,target:string}>
	 */
	public function process(int $propertyId, array $mediaValues): array;
}
