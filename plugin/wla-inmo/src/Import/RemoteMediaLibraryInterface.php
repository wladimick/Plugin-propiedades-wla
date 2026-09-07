<?php

namespace WLA\Inmo\Import;

interface RemoteMediaLibraryInterface
{
	public function ingest(RemoteMediaDownloadedFile $file, int $propertyId): int;
}
