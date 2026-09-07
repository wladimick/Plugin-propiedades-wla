<?php

namespace WLA\Inmo\Import;

use ZipArchive;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exceptions are internal parser/control-flow messages and are not rendered here.
final class XlsxArchiveInspector
{
	private const REQUIRED_PARTS = array(
		'[Content_Types].xml',
		'_rels/.rels',
		'xl/workbook.xml',
		'xl/_rels/workbook.xml.rels',
	);

	private const DANGEROUS_PATH_PATTERNS = array(
		'#(?:^|/).*\.bin$#i',
		'#^xl/(?:activeX|embeddings|macrosheets|dialogsheets)/#i',
		'#^customUI/#i',
	);

	private int $maxFileBytes;
	private int $maxEntries;
	private int $maxTotalUncompressedBytes;
	private int $maxEntryUncompressedBytes;
	private float $maxExpansionRatio;
	private int $maxMetadataBytes;
	private int $maxSheets;

	public function __construct(
		int $maxFileBytes = 10485760,
		int $maxEntries = 256,
		int $maxTotalUncompressedBytes = 67108864,
		int $maxEntryUncompressedBytes = 33554432,
		float $maxExpansionRatio = 100.0,
		int $maxMetadataBytes = 1048576,
		int $maxSheets = 16
	) {
		if (
			$maxFileBytes < 1
			|| $maxEntries < 1
			|| $maxTotalUncompressedBytes < 1
			|| $maxEntryUncompressedBytes < 1
			|| $maxExpansionRatio < 1.0
			|| $maxMetadataBytes < 1
			|| $maxSheets < 1
		) {
			throw new \InvalidArgumentException('XLSX archive limits are invalid.');
		}

		$this->maxFileBytes = $maxFileBytes;
		$this->maxEntries = $maxEntries;
		$this->maxTotalUncompressedBytes = $maxTotalUncompressedBytes;
		$this->maxEntryUncompressedBytes = $maxEntryUncompressedBytes;
		$this->maxExpansionRatio = $maxExpansionRatio;
		$this->maxMetadataBytes = $maxMetadataBytes;
		$this->maxSheets = $maxSheets;
	}

	/** @return array{source_hash:string,source_bytes:int,entries:int,uncompressed_bytes:int,worksheet_parts:int} */
	public function inspect(string $path): array
	{
		if ($path === '' || !is_file($path) || !is_readable($path)) {
			throw new XlsxException('unreadable_file', 'XLSX file is not readable.');
		}

		$handle = fopen($path, 'rb');
		if ($handle === false || !flock($handle, LOCK_SH)) {
			if (is_resource($handle)) {
				fclose($handle);
			}
			throw new XlsxException('source_lock_failed', 'XLSX source could not be locked for inspection.');
		}

		$zip = new ZipArchive();
		$zipOpen = false;

		try {
			$before = fstat($handle);
			if (!is_array($before)) {
				throw new XlsxException('source_stat_failed', 'XLSX source metadata could not be read.');
			}

			$sourceBytes = (int) $before['size'];
			if ($sourceBytes < 1) {
				throw new XlsxException('empty_file', 'XLSX file is empty.');
			}
			if ($sourceBytes > $this->maxFileBytes) {
				throw new XlsxException('file_too_large', 'XLSX file exceeds the compressed byte limit.');
			}

			$sourceHash = $this->hashLockedHandle($handle);
			$openResult = $zip->open($path, ZipArchive::RDONLY);
			if ($openResult !== true) {
				throw new XlsxException('invalid_zip', 'XLSX file is not a readable ZIP archive.');
			}
			$zipOpen = true;

			$result = $this->inspectOpenArchive($zip, $sourceBytes);
			if (!$zip->close()) {
				throw new XlsxException('archive_close_failed', 'XLSX archive could not be finalized after inspection.');
			}
			$zipOpen = false;

			$after = fstat($handle);
			if (!is_array($after) || !$this->sameFileState($before, $after)) {
				throw new XlsxException('source_changed_during_inspection', 'XLSX source changed during archive inspection.');
			}
			$afterHash = $this->hashLockedHandle($handle);
			if (!hash_equals($sourceHash, $afterHash)) {
				throw new XlsxException('source_changed_during_inspection', 'XLSX source changed during archive inspection.');
			}

			return array(
				'source_hash'        => $sourceHash,
				'source_bytes'       => $sourceBytes,
				'entries'            => $result['entries'],
				'uncompressed_bytes' => $result['uncompressed_bytes'],
				'worksheet_parts'    => $result['worksheet_parts'],
			);
		} finally {
			if ($zipOpen) {
				$zip->close();
			}
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	/** @return array{entries:int,uncompressed_bytes:int,worksheet_parts:int} */
	private function inspectOpenArchive(ZipArchive $zip, int $sourceBytes): array
	{
		$entries = $zip->numFiles;
		if ($entries < 1) {
			throw new XlsxException('empty_archive', 'XLSX archive does not contain any parts.');
		}
		if ($entries > $this->maxEntries) {
			throw new XlsxException('entry_limit_exceeded', 'XLSX archive contains too many ZIP entries.');
		}

		$seen = array();
		$totalUncompressed = 0;
		$worksheetParts = 0;
		$relationshipIndexes = array();

		for ($index = 0; $index < $entries; ++$index) {
			$stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
			if ($stat === false) {
				throw new XlsxException('entry_stat_failed', 'XLSX ZIP entry metadata could not be read.');
			}

			$name = (string) $stat['name'];
			$this->assertSafePath($name);
			$this->assertSupportedPart($name);
			if (isset($seen[$name])) {
				throw new XlsxException('duplicate_zip_entry', 'XLSX archive contains a duplicate ZIP entry.');
			}
			$seen[$name] = true;

			$uncompressed = (int) $stat['size'];
			$compressed = (int) $stat['comp_size'];
			if ($uncompressed < 0 || $compressed < 0) {
				throw new XlsxException('invalid_entry_size', 'XLSX ZIP entry contains invalid size metadata.');
			}
			if ($uncompressed > $this->maxEntryUncompressedBytes) {
				throw new XlsxException('entry_size_limit_exceeded', 'XLSX ZIP entry exceeds the uncompressed byte limit.');
			}
			$totalUncompressed += $uncompressed;
			if ($totalUncompressed > $this->maxTotalUncompressedBytes) {
				throw new XlsxException('archive_size_limit_exceeded', 'XLSX archive exceeds the total uncompressed byte limit.');
			}
			if ($uncompressed >= 1024 && $compressed > 0 && ($uncompressed / $compressed) > $this->maxExpansionRatio) {
				throw new XlsxException('expansion_ratio_exceeded', 'XLSX ZIP entry exceeds the allowed expansion ratio.');
			}
			if ((int) $stat['encryption_method'] !== 0) {
				throw new XlsxException('encrypted_entry', 'Encrypted XLSX ZIP entries are not supported.');
			}

			if (preg_match('#^xl/worksheets/[^/]+\.xml$#i', $name) === 1) {
				++$worksheetParts;
				if ($worksheetParts > $this->maxSheets) {
					throw new XlsxException('sheet_limit_exceeded', 'XLSX workbook contains too many worksheet parts.');
				}
			}
			if (str_ends_with(strtolower($name), '.rels')) {
				$relationshipIndexes[] = $index;
			}
		}

		foreach (self::REQUIRED_PARTS as $requiredPart) {
			if (!isset($seen[$requiredPart])) {
				throw new XlsxException('missing_required_part', 'XLSX archive is missing a required OOXML part.');
			}
		}
		if ($worksheetParts < 1) {
			throw new XlsxException('missing_worksheet', 'XLSX archive does not contain a worksheet part.');
		}
		if ($totalUncompressed >= 4194304 && ($totalUncompressed / max(1, $sourceBytes)) > $this->maxExpansionRatio) {
			throw new XlsxException('archive_expansion_ratio_exceeded', 'XLSX archive exceeds the allowed total expansion ratio.');
		}

		$contentTypes = $this->readMetadataPart($zip, '[Content_Types].xml');
		$this->assertWorkbookContentType($contentTypes);
		foreach ($relationshipIndexes as $index) {
			$relationships = $this->readMetadataIndex($zip, $index);
			if (preg_match('/TargetMode\s*=\s*["\']External["\']/i', $relationships) === 1) {
				throw new XlsxException('external_relationship', 'External XLSX relationships are not supported.');
			}
		}

		return array(
			'entries'            => $entries,
			'uncompressed_bytes' => $totalUncompressed,
			'worksheet_parts'    => $worksheetParts,
		);
	}

	private function assertSafePath(string $name): void
	{
		if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')) {
			throw new XlsxException('unsafe_entry_path', 'XLSX ZIP entry contains an unsafe path.');
		}
		if ($name[0] === '/' || preg_match('/^[A-Za-z]:/', $name) === 1) {
			throw new XlsxException('unsafe_entry_path', 'XLSX ZIP entry contains an absolute path.');
		}
		foreach (explode('/', $name) as $segment) {
			if ($segment === '..' || $segment === '.') {
				throw new XlsxException('unsafe_entry_path', 'XLSX ZIP entry contains path traversal.');
			}
		}
	}

	private function assertSupportedPart(string $name): void
	{
		foreach (self::DANGEROUS_PATH_PATTERNS as $pattern) {
			if (preg_match($pattern, $name) === 1) {
				throw new XlsxException('unsupported_executable_part', 'XLSX archive contains a macro or embedded executable part.');
			}
		}
	}

	private function assertWorkbookContentType(string $contentTypes): void
	{
		if (
			stripos($contentTypes, 'application/vnd.ms-excel.sheet.macroEnabled.main+xml') !== false
			|| stripos($contentTypes, 'application/vnd.ms-office.vbaProject') !== false
		) {
			throw new XlsxException('macro_enabled_workbook', 'Macro-enabled workbooks are not supported.');
		}
		if (stripos($contentTypes, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml') === false) {
			throw new XlsxException('invalid_workbook_content_type', 'XLSX workbook content type is not supported.');
		}
	}

	private function readMetadataPart(ZipArchive $zip, string $name): string
	{
		$index = $zip->locateName($name, ZipArchive::FL_UNCHANGED);
		if ($index === false) {
			throw new XlsxException('missing_required_part', 'XLSX archive is missing a required OOXML part.');
		}
		return $this->readMetadataIndex($zip, $index);
	}

	private function readMetadataIndex(ZipArchive $zip, int $index): string
	{
		$stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
		if ($stat === false) {
			throw new XlsxException('entry_stat_failed', 'XLSX metadata part could not be inspected.');
		}
		$size = (int) $stat['size'];
		if ($size < 0 || $size > $this->maxMetadataBytes) {
			throw new XlsxException('metadata_size_limit_exceeded', 'XLSX metadata part exceeds the byte limit.');
		}
		$content = $zip->getFromIndex($index, $this->maxMetadataBytes + 1, ZipArchive::FL_UNCHANGED);
		if (!is_string($content) || strlen($content) > $this->maxMetadataBytes) {
			throw new XlsxException('metadata_read_failed', 'XLSX metadata part could not be read safely.');
		}
		return $content;
	}

	/** @param resource $handle */
	private function hashLockedHandle($handle): string
	{
		if (fseek($handle, 0) !== 0) {
			throw new XlsxException('source_read_failed', 'XLSX source could not be rewound for hashing.');
		}
		$context = hash_init('sha256');
		$read = 0;
		while (!feof($handle)) {
			$remaining = ($this->maxFileBytes + 1) - $read;
			if ($remaining < 1) {
				throw new XlsxException('file_too_large', 'XLSX file exceeds the compressed byte limit.');
			}
			$chunk = fread($handle, min(1048576, $remaining));
			if ($chunk === false) {
				throw new XlsxException('source_read_failed', 'XLSX source could not be read for hashing.');
			}
			if ($chunk === '') {
				break;
			}
			$read += strlen($chunk);
			hash_update($context, $chunk);
		}
		return hash_final($context);
	}

	/**
	 * @param array<int|string,int> $before
	 * @param array<int|string,int> $after
	 */
	private function sameFileState(array $before, array $after): bool
	{
		foreach (array('dev', 'ino', 'size', 'mtime') as $key) {
			if (isset($before[$key], $after[$key]) && (string) $before[$key] !== (string) $after[$key]) {
				return false;
			}
		}
		return true;
	}
}
