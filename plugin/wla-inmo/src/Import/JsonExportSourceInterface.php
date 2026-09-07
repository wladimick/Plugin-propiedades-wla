<?php

namespace WLA\Inmo\Import;

interface JsonExportSourceInterface
{
	/**
	 * Return one bounded page of candidate WLA JSON property objects.
	 *
	 * The exporter validates each element at runtime before encoding so custom
	 * sources cannot bypass the portable-object contract.
	 *
	 * @return array<int,mixed>
	 */
	public function page(int $page, int $pageSize): array;
}
