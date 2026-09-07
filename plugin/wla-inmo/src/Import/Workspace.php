<?php

namespace WLA\Inmo\Import;

final class Workspace
{
	private const DRAFT_PREFIX = 'wla_inmo_import_draft_';
	private const DRAFT_TTL = 3600;
	private const MAX_UPLOAD_BYTES = 10485760;
	private const MAX_ROWS = 10000;
	private const PREVIEW_ROWS = 5;
	private const FORMAT_CSV = 'csv';
	private const FORMAT_JSON = 'json';
	private const FORMAT_XLSX = 'xlsx';

	/**
	 * Store a real HTTP CSV upload in a server-controlled temporary path and return
	 * only metadata required by the wizard. Source row payloads are never stored
	 * in the transient state.
	 *
	 * @param array<string,mixed> $file `$_FILES` entry.
	 * @return array{ok:bool,code:string,token?:string,state?:array<string,mixed>}
	 */
	public static function storeUploadedCsv(array $file, int $createdBy): array
	{
		$validated = self::validateUpload($file, $createdBy, self::FORMAT_CSV);
		if (empty($validated['ok'])) {
			return self::failure((string) $validated['code']);
		}

		$token = (string) $validated['token'];
		$name = (string) $validated['name'];
		$tmpName = (string) $validated['tmp_name'];
		$path = self::draftPath($token, self::FORMAT_CSV);
		if ($path === null || file_exists($path) || !move_uploaded_file($tmpName, $path)) {
			return self::failure('upload_store_failed');
		}

		@chmod($path, 0600); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort hardening; host may not permit chmod.

		try {
			$inspection = self::inspectCsv($path);
		} catch (CsvException $exception) {
			@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup after validation failure.
			return self::failure($exception->reason());
		}

		$hash = hash_file('sha256', $path);
		if (!is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1) {
			@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup after hashing failure.
			return self::failure('source_hash_failed');
		}

		$state = array(
			'token'             => $token,
			'created_by'        => $createdBy,
			'original_name'     => $name,
			'source_format'     => self::FORMAT_CSV,
			'source_hash'       => $hash,
			'original_hash'     => $hash,
			'total_rows'        => (int) $inspection['total_rows'],
			'headers'           => $inspection['headers'],
			'canonical_mapping' => array(),
			'profile_json'      => '',
			'dry_run'           => array(),
			'created_at'        => time(),
			'updated_at'        => time(),
		);

		return self::persistNewDraft($token, $state, $path);
	}

	/**
	 * Validate a WLA JSON v1 upload and materialize a private NDJSON source.
	 * The original JSON is deleted after validation; only its SHA-256 metadata
	 * and the normalized, resumable source remain in the workspace.
	 *
	 * @param array<string,mixed> $file `$_FILES` entry.
	 * @return array{ok:bool,code:string,token?:string,state?:array<string,mixed>}
	 */
	public static function storeUploadedJson(array $file, int $createdBy): array
	{
		$validated = self::validateUpload($file, $createdBy, self::FORMAT_JSON);
		if (empty($validated['ok'])) {
			return self::failure((string) $validated['code']);
		}

		$token = (string) $validated['token'];
		$name = (string) $validated['name'];
		$tmpName = (string) $validated['tmp_name'];
		$uploadPath = self::jsonUploadPath($token);
		$normalizedPath = self::draftPath($token, self::FORMAT_JSON);
		if (
			$uploadPath === null
			|| $normalizedPath === null
			|| file_exists($uploadPath)
			|| file_exists($normalizedPath)
			|| !move_uploaded_file($tmpName, $uploadPath)
		) {
			return self::failure('upload_store_failed');
		}

		@chmod($uploadPath, 0600); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort hardening.

		try {
			$inspection = (new JsonDocumentReader(self::MAX_UPLOAD_BYTES, self::MAX_ROWS))->normalizeToNdjson($uploadPath, $normalizedPath);
		} catch (JsonException $exception) {
			@unlink($uploadPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup invalid upload.
			@unlink($normalizedPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup incomplete normalized source.
			return self::failure($exception->reason());
		} catch (\Throwable) {
			@unlink($uploadPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup unexpected validation failure.
			@unlink($normalizedPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup unexpected validation failure.
			return self::failure('json_validation_failed');
		}

		@unlink($uploadPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Original JSON is no longer needed after safe normalization.
		@chmod($normalizedPath, 0600); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort hardening.

		$state = array(
			'token'             => $token,
			'created_by'        => $createdBy,
			'original_name'     => $name,
			'source_format'     => self::FORMAT_JSON,
			'format_version'    => (int) $inspection['format_version'],
			'source_key'        => (string) $inspection['source_key'],
			'source_hash'       => (string) $inspection['source_hash'],
			'original_hash'     => (string) $inspection['original_hash'],
			'exported_at'       => (string) $inspection['exported_at'],
			'total_rows'        => (int) $inspection['total_rows'],
			'headers'           => $inspection['headers'],
			'canonical_mapping' => $inspection['mapping'],
			'profile_json'      => '',
			'dry_run'           => array(),
			'created_at'        => time(),
			'updated_at'        => time(),
		);

		return self::persistNewDraft($token, $state, $normalizedPath);
	}


	/**
	 * Store a real HTTP XLSX upload after ZIP/OOXML preflight. The workbook stays
	 * private until the user explicitly selects which worksheet will be normalized.
	 *
	 * @param array<string,mixed> $file `$_FILES` entry.
	 * @return array{ok:bool,code:string,token?:string,state?:array<string,mixed>}
	 */
	public static function storeUploadedXlsx(array $file, int $createdBy): array
	{
		$validated = self::validateUpload($file, $createdBy, self::FORMAT_XLSX);
		if (empty($validated['ok'])) {
			return self::failure((string) $validated['code']);
		}

		$token = (string) $validated['token'];
		$name = (string) $validated['name'];
		$tmpName = (string) $validated['tmp_name'];
		$uploadPath = self::xlsxUploadPath($token);
		if ($uploadPath === null || file_exists($uploadPath) || !move_uploaded_file($tmpName, $uploadPath)) {
			return self::failure('upload_store_failed');
		}
		@chmod($uploadPath, 0600); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort hardening.

		try {
			$inspection = (new XlsxDocumentReader())->worksheets($uploadPath);
		} catch (XlsxException $exception) {
			@unlink($uploadPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup invalid workbook.
			return self::failure($exception->reason());
		} catch (\Throwable) {
			@unlink($uploadPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup unexpected validation failure.
			return self::failure('xlsx_validation_failed');
		}

		$sheets = $inspection['sheets'];
		if ($sheets === array()) {
			@unlink($uploadPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Empty workbook cleanup.
			return self::failure('missing_worksheet');
		}

		$state = array(
			'token'             => $token,
			'created_by'        => $createdBy,
			'original_name'     => $name,
			'source_format'     => self::FORMAT_XLSX,
			'source_hash'       => '',
			'original_hash'     => (string) $inspection['original_hash'],
			'total_rows'        => 0,
			'headers'           => array(),
			'xlsx_sheets'       => $sheets,
			'selected_sheet'    => '',
			'canonical_mapping' => array(),
			'profile_json'      => '',
			'dry_run'           => array(),
			'created_at'        => time(),
			'updated_at'        => time(),
		);

		return self::persistNewDraft($token, $state, $uploadPath);
	}

	/**
	 * Normalize only the explicitly selected worksheet to private NDJSON.
	 *
	 * @return array{ok:bool,code:string,state?:array<string,mixed>}
	 */
	public static function selectUploadedXlsxSheet(string $token, int $userId, string $sheetName): array
	{
		$state = self::loadDraft($token, $userId);
		if ($state === null || self::stateFormat($state) !== self::FORMAT_XLSX) {
			return self::failure('draft_expired');
		}
		if ((string) ($state['selected_sheet'] ?? '') !== '') {
			return self::failure('sheet_already_selected');
		}

		$sheetName = trim($sheetName);
		$allowed = false;
		$sheets = isset($state['xlsx_sheets']) && is_array($state['xlsx_sheets']) ? $state['xlsx_sheets'] : array();
		foreach ($sheets as $sheet) {
			if (is_array($sheet) && isset($sheet['name']) && is_string($sheet['name']) && hash_equals($sheet['name'], $sheetName)) {
				$allowed = true;
				break;
			}
		}
		if (!$allowed) {
			return self::failure('unknown_sheet');
		}

		$uploadPath = self::xlsxUploadPath($token);
		$normalizedPath = self::draftPath($token, self::FORMAT_XLSX);
		if ($uploadPath === null || $normalizedPath === null || !is_file($uploadPath) || file_exists($normalizedPath)) {
			return self::failure('source_unreadable');
		}

		try {
			$inspection = (new XlsxDocumentReader(null, 500, self::MAX_ROWS))->normalizeToNdjson($uploadPath, $normalizedPath, $sheetName);
		} catch (XlsxException $exception) {
			@unlink($normalizedPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup failed normalization.
			return self::failure($exception->reason());
		} catch (\Throwable) {
			@unlink($normalizedPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup unexpected normalization failure.
			return self::failure('xlsx_normalization_failed');
		}

		if (!hash_equals((string) ($state['original_hash'] ?? ''), (string) $inspection['original_hash'])) {
			@unlink($normalizedPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Workbook changed after upload.
			return self::failure('source_hash_mismatch');
		}

		$state['selected_sheet'] = (string) $inspection['sheet_name'];
		$state['source_hash'] = (string) $inspection['source_hash'];
		$state['total_rows'] = (int) $inspection['total_rows'];
		$state['headers'] = $inspection['headers'];
		$state['profile_json'] = '';
		$state['dry_run'] = array();
		if (!self::saveDraft($token, $state)) {
			@unlink($normalizedPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Keep original workbook if state cannot be committed.
			return self::failure('draft_store_failed');
		}

		@unlink($uploadPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Original workbook no longer needed after safe normalization.
		return array('ok' => true, 'code' => 'sheet_ready', 'state' => $state);
	}

	/**
	 * @return array<string,mixed>|null
	 */
	public static function loadDraft(string $token, int $userId): ?array
	{
		$token = strtolower(trim($token));
		if (!self::isUuid($token) || $userId < 1) {
			return null;
		}

		$state = get_transient(self::transientKey($token));
		if (!is_array($state)) {
			self::deleteDraftFileOnly($token);
			return null;
		}
		if ((int) ($state['created_by'] ?? 0) !== $userId) {
			return null;
		}

		$format = self::stateFormat($state);
		$path = $format === self::FORMAT_XLSX && empty($state['selected_sheet'])
			? self::xlsxUploadPath($token)
			: self::draftPath($token, $format);
		if ($path === null || !is_file($path) || !is_readable($path)) {
			self::deleteDraft($token);
			return null;
		}

		return $state;
	}

	/**
	 * @param array<string,mixed> $state Draft state without source row payloads.
	 */
	public static function saveDraft(string $token, array $state): bool
	{
		$token = strtolower(trim($token));
		if (!self::isUuid($token) || !self::isSupportedFormat(self::stateFormat($state))) {
			return false;
		}

		$state['token'] = $token;
		$state['updated_at'] = time();

		return set_transient(self::transientKey($token), $state, self::DRAFT_TTL);
	}

	public static function deleteDraft(string $token, bool $deleteSource = true): void
	{
		$token = strtolower(trim($token));
		if (!self::isUuid($token)) {
			return;
		}

		delete_transient(self::transientKey($token));
		if ($deleteSource) {
			self::deleteDraftFileOnly($token);
		}
	}

	/**
	 * @return array{headers:array<int,string>,rows:array<int,array<string,string>>}|null
	 */
	public static function preview(string $token, int $userId, int $limit = self::PREVIEW_ROWS): ?array
	{
		$state = self::loadDraft($token, $userId);
		if ($state === null) {
			return null;
		}

		$format = self::stateFormat($state);
		$path = self::draftPath($token, $format);
		if ($path === null) {
			return null;
		}

		$limit = max(1, min(self::PREVIEW_ROWS, $limit));
		$rows = array();

		try {
			$sourceRows = in_array($format, array(self::FORMAT_JSON, self::FORMAT_XLSX), true)
				? (new JsonLinesReader(self::MAX_ROWS))->verifiedRows($path, (string) ($state['source_hash'] ?? ''))
				: (new CsvReader(self::MAX_ROWS))->rows($path);

			foreach ($sourceRows as $row) {
				$clean = array();
				foreach ($row['data'] as $header => $value) {
					$clean[(string) $header] = self::previewValue($value);
				}
				$rows[] = $clean;
				if (count($rows) >= $limit) {
					break;
				}
			}
		} catch (CsvException|JsonException) {
			return null;
		}

		$headers = isset($state['headers']) && is_array($state['headers'])
			? array_values(array_map('strval', $state['headers']))
			: array();

		return array('headers' => $headers, 'rows' => $rows);
	}

	public static function draftSourcePath(string $token, int $userId): ?string
	{
		$state = self::loadDraft($token, $userId);
		if ($state === null) {
			return null;
		}
		if (self::stateFormat($state) === self::FORMAT_XLSX && empty($state['selected_sheet'])) {
			return null;
		}

		return self::draftPath($token, self::stateFormat($state));
	}

	/**
	 * Move a validated draft source into the deterministic, UUID-only batch path.
	 * The UUID is generated by the server and validated before becoming a path.
	 */
	public static function promoteDraft(string $token, string $batchUuid, int $userId): ?string
	{
		$state = self::loadDraft($token, $userId);
		if ($state === null) {
			return null;
		}

		$format = self::stateFormat($state);
		$source = self::draftPath($token, $format);
		$target = self::batchPath($batchUuid, $format);
		if ($source === null || $target === null || file_exists($target)) {
			return null;
		}

		if (!rename($source, $target)) {
			return null;
		}

		@chmod($target, 0600); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort hardening.

		return $target;
	}

	public static function restorePromoted(string $token, string $batchUuid): bool
	{
		$state = get_transient(self::transientKey(strtolower(trim($token))));
		$format = is_array($state) ? self::stateFormat($state) : self::FORMAT_CSV;
		$source = self::batchPath($batchUuid, $format);
		$target = self::draftPath($token, $format);
		if ($source === null || $target === null || !is_file($source) || file_exists($target)) {
			return false;
		}

		return rename($source, $target);
	}

	public static function batchSourcePath(string $batchUuid, string $sourceFormat = self::FORMAT_CSV): ?string
	{
		$path = self::batchPath($batchUuid, $sourceFormat);

		return $path !== null && is_file($path) && is_readable($path) ? $path : null;
	}

	public static function deleteBatchSource(string $batchUuid, string $sourceFormat = self::FORMAT_CSV): void
	{
		$path = self::batchPath($batchUuid, $sourceFormat);
		if ($path !== null && is_file($path)) {
			@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort temporary-file cleanup.
		}
	}

	public static function maxUploadBytes(): int
	{
		return self::MAX_UPLOAD_BYTES;
	}

	public static function maxRows(): int
	{
		return self::MAX_ROWS;
	}

	/**
	 * @param array<string,mixed> $state Draft state.
	 */
	public static function sourceFormat(array $state): string
	{
		return self::stateFormat($state);
	}

	/**
	 * Delete only abandoned draft files. Batch files are never deleted by age,
	 * because paused or failed imports must remain resumable until an explicit
	 * terminal action removes their source.
	 */
	private static function cleanupExpiredDraftFiles(): void
	{
		$files = array();
		foreach (array('*.csv', '*.ndjson', '*.json') as $suffix) {
			$matches = glob(self::tempRoot() . 'wla-inmo-import-draft-' . $suffix);
			if (is_array($matches)) {
				$files = array_merge($files, $matches);
			}
		}
		$xlsxUploads = glob(self::tempRoot() . 'wla-inmo-import-upload-*.xlsx');
		if (is_array($xlsxUploads)) {
			$files = array_merge($files, $xlsxUploads);
		}

		$cutoff = time() - (self::DRAFT_TTL * 2);
		foreach (array_values(array_unique($files)) as $path) {
			if (!is_file($path)) {
				continue;
			}
			$modified = filemtime($path);
			if ($modified !== false && $modified < $cutoff) {
				@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort cleanup of plugin-owned stale draft files only.
			}
		}
	}

	private static function deleteDraftFileOnly(string $token): void
	{
		foreach (array(self::FORMAT_CSV, self::FORMAT_JSON, self::FORMAT_XLSX) as $format) {
			$path = self::draftPath($token, $format);
			if ($path !== null && is_file($path)) {
				@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort plugin-owned draft cleanup.
			}
		}

		$uploadPath = self::jsonUploadPath($token);
		if ($uploadPath !== null && is_file($uploadPath)) {
			@unlink($uploadPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort cleanup of an interrupted JSON normalization.
		}

		$xlsxUploadPath = self::xlsxUploadPath($token);
		if ($xlsxUploadPath !== null && is_file($xlsxUploadPath)) {
			@unlink($xlsxUploadPath); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort cleanup of an interrupted XLSX selection.
		}
	}

	/**
	 * @return array{headers:array<int,string>,total_rows:int}
	 */
	private static function inspectCsv(string $path): array
	{
		$reader = new CsvReader(self::MAX_ROWS);
		$headers = array();
		$totalRows = 0;

		foreach ($reader->rows($path) as $row) {
			++$totalRows;
			if ($headers === array()) {
				$headers = array_map('strval', array_keys($row['data']));
			}
		}

		if ($totalRows < 1 || $headers === array()) {
			throw new CsvException('empty_csv', 'CSV must contain a header and at least one data row.');
		}

		return array('headers' => $headers, 'total_rows' => $totalRows);
	}

	/**
	 * @param array<string,mixed> $file Uploaded file metadata.
	 * @return array{ok:bool,code:string,token?:string,name?:string,tmp_name?:string}
	 */
	private static function validateUpload(array $file, int $createdBy, string $format): array
	{
		if ($createdBy < 1 || !self::isSupportedFormat($format)) {
			return self::failure('invalid_user');
		}

		self::cleanupExpiredDraftFiles();

		$error = isset($file['error']) && is_scalar($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ($error !== UPLOAD_ERR_OK) {
			return self::failure('upload_failed');
		}

		$size = isset($file['size']) && is_scalar($file['size']) ? (int) $file['size'] : 0;
		if ($size < 1) {
			return self::failure('empty_file');
		}
		if ($size > self::MAX_UPLOAD_BYTES) {
			return self::failure('file_too_large');
		}

		$name = isset($file['name']) && is_scalar($file['name']) ? sanitize_file_name((string) $file['name']) : '';
		if (strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== $format) {
			return self::failure('invalid_extension');
		}

		$tmpName = isset($file['tmp_name']) && is_scalar($file['tmp_name']) ? (string) $file['tmp_name'] : '';
		if ($tmpName === '' || !is_uploaded_file($tmpName)) {
			return self::failure('invalid_upload_source');
		}

		$mime = self::detectMime($tmpName);
		if ($mime !== '' && !in_array($mime, self::allowedMimes($format), true)) {
			return self::failure('invalid_mime');
		}

		return array(
			'ok'       => true,
			'code'     => 'upload_valid',
			'token'    => self::uuid4(),
			'name'     => $name,
			'tmp_name' => $tmpName,
		);
	}

	/**
	 * @param array<string,mixed> $state Draft metadata.
	 * @return array{ok:bool,code:string,token?:string,state?:array<string,mixed>}
	 */
	private static function persistNewDraft(string $token, array $state, string $path): array
	{
		if (!self::saveDraft($token, $state)) {
			@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup after transient failure.
			return self::failure('draft_store_failed');
		}

		return array(
			'ok'    => true,
			'code'  => 'uploaded',
			'token' => $token,
			'state' => $state,
		);
	}

	private static function previewValue(mixed $value): string
	{
		if (is_array($value)) {
			$encoded = wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			return is_string($encoded) ? $encoded : '';
		}
		if (is_scalar($value) || $value === null) {
			return (string) $value;
		}

		return '';
	}

	/** @param array<string,mixed> $state Draft metadata. */
	private static function stateFormat(array $state): string
	{
		$format = strtolower(trim((string) ($state['source_format'] ?? self::FORMAT_CSV)));
		return self::isSupportedFormat($format) ? $format : self::FORMAT_CSV;
	}

	private static function draftPath(string $token, string $format = self::FORMAT_CSV): ?string
	{
		$token = strtolower(trim($token));
		if (!self::isUuid($token) || !self::isSupportedFormat($format)) {
			return null;
		}

		if (in_array($format, array(self::FORMAT_JSON, self::FORMAT_XLSX), true)) {
			return self::tempRoot() . 'wla-inmo-import-draft-' . $token . '.ndjson';
		}

		return self::tempRoot() . 'wla-inmo-import-draft-' . $token . '.csv';
	}

	private static function jsonUploadPath(string $token): ?string
	{
		$token = strtolower(trim($token));
		if (!self::isUuid($token)) {
			return null;
		}

		return self::tempRoot() . 'wla-inmo-import-upload-' . $token . '.json';
	}


	private static function xlsxUploadPath(string $token): ?string
	{
		$token = strtolower(trim($token));
		if (!self::isUuid($token)) {
			return null;
		}

		return self::tempRoot() . 'wla-inmo-import-upload-' . $token . '.xlsx';
	}

	private static function batchPath(string $batchUuid, string $format = self::FORMAT_CSV): ?string
	{
		$batchUuid = strtolower(trim($batchUuid));
		if (!self::isUuid($batchUuid) || !self::isSupportedFormat($format)) {
			return null;
		}

		if (in_array($format, array(self::FORMAT_JSON, self::FORMAT_XLSX), true)) {
			return self::tempRoot() . 'wla-inmo-import-batch-' . $batchUuid . '.ndjson';
		}

		return self::tempRoot() . 'wla-inmo-import-batch-' . $batchUuid . '.csv';
	}

	private static function tempRoot(): string
	{
		$root = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();

		return trailingslashit($root);
	}

	private static function transientKey(string $token): string
	{
		return self::DRAFT_PREFIX . str_replace('-', '', $token);
	}

	private static function detectMime(string $path): string
	{
		if (!class_exists('finfo')) {
			return '';
		}

		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$mime = $finfo->file($path);

		return is_string($mime) ? strtolower(trim($mime)) : '';
	}

	/** @return array<int,string> */
	private static function allowedMimes(string $format): array
	{
		if ($format === self::FORMAT_XLSX) {
			return array(
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'application/zip',
				'application/octet-stream',
			);
		}

		if ($format === self::FORMAT_JSON) {
			return array(
				'application/json',
				'text/json',
				'text/plain',
				'application/octet-stream',
			);
		}

		return array(
			'text/plain',
			'text/csv',
			'text/tab-separated-values',
			'application/csv',
			'application/vnd.ms-excel',
			'application/octet-stream',
		);
	}

	private static function isSupportedFormat(string $format): bool
	{
		return in_array(strtolower(trim($format)), array(self::FORMAT_CSV, self::FORMAT_JSON, self::FORMAT_XLSX), true);
	}

	/** @return array{ok:false,code:string} */
	private static function failure(string $code): array
	{
		return array('ok' => false, 'code' => sanitize_key($code));
	}

	private static function isUuid(string $value): bool
	{
		return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $value) === 1;
	}

	private static function uuid4(): string
	{
		if (function_exists('wp_generate_uuid4')) {
			return strtolower((string) wp_generate_uuid4());
		}

		$bytes = random_bytes(16);
		$bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
		$bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
		$hex = bin2hex($bytes);

		return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
	}
}
