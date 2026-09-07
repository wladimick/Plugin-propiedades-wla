<?php

namespace WLA\Inmo\Import;

interface JsonExportSourceInterface
{
	/**
	 * Return one bounded page of portable WLA JSON property objects.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function page(int $page, int $pageSize): array;
}
