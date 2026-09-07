<?php

namespace WLA\Inmo\Import;

interface RemoteMediaDownloaderInterface
{
	public function download(string $url): RemoteMediaDownloadedFile;
}
