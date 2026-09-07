<?php

namespace WLA\Inmo\Import;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

final class XlsxChunkReadFilter implements IReadFilter
{
	private int $startRow = 1;
	private int $endRow = 1;

	public function setRows(int $startRow, int $endRow): void
	{
		if ($startRow < 1 || $endRow < $startRow) {
			throw new \InvalidArgumentException('XLSX chunk row range is invalid.');
		}

		$this->startRow = $startRow;
		$this->endRow = $endRow;
	}

	public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
	{
		return $row >= $this->startRow && $row <= $this->endRow;
	}
}
