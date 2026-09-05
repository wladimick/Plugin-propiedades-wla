<?php

namespace WLA\Inmo\Import;

final class Workspace
{
	private const DRAFT_PREFIX = 'wla_inmo_import_draft_';
	private const DRAFT_TTL = 3600;
	private const MAX_UPLOAD_BYTES = 10485760;
	private const MAX_ROWS = 10000;
	private const PREVIEW_ROWS = 5;

	/**
	 * Store a real HTTP upload in a server-controlled temporary path and return
	 * only metadata required by the wizard. Source row payloads are never stored
	 * in the transient state.
	 *
	 * @param array<string,mixed> $file `$_FILES` entry.
	 * @return array{ok:bool,code:string,token?:string,state?:array<string,mixed>}
	 */
	public static function storeUploadedCsv(array $file, int $createdBy): array
	{
		if ($createdBy < 1) {
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
		if (strtolower((string) pathinfo($name, PATHINFO_EXTENSION)) !== 'csv') {
			return self::failure('invalid_extension');
		}

		$tmpName = isset($file['tmp_name']) && is_scalar($file['tmp_name']) ? (string) $file['tmp_name'] : '';
		if ($tmpName === '' || !is_uploaded_file($tmpName)) {
			return self::failure('invalid_upload_source');
		}

		$mime = self::detectMime($tmpName);
		if ($mime !== '' && !in_array($mime, self::allowedMimes(), true)) {
			return self::failure('invalid_mime');
		}

		$token = self::uuid4();
		$path = self::draftPath($token);
		if ($path === null || file_exists($path) || !move_uploaded_file($tmpName, $path)) {
			return self::failure('upload_store_failed');
		}

		@chmod($path, 0600); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort hardening; host may not permit chmod.

		try {
			$inspection = self::inspect($path);
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
			'token'         => $token,
			'created_by'    => $createdBy,
			'original_name' => $name,
			'source_hash'   => $hash,
			'total_rows'    => (int) $inspection['total_rows'],
			'headers'       => $inspection['headers'],
			'profile_json'  => '',
			'dry_run'       => array(),
			'created_at'    => time(),
			'updated_at'    => time(),
		);

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

		$path = self::draftPath($token);
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
		if (!self::isUuid($token)) {
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

		$path = self::draftPath($token);
		if ($path === null) {
			return null;
		}

		$limit = max(1, min(self::PREVIEW_ROWS, $limit));
		$rows = array();
		$reader = new CsvReader(self::MAX_ROWS);

		try {
			foreach ($reader->rows($path) as $row) {
				$clean = array();
				foreach ($row['data'] as $header => $value) {
					$clean[(string) $header] = (string) $value;
				}
				$rows[] = $clean;
				if (count($rows) >= $limit) {
					break;
				}
			}
		} catch (CsvException) {
			return null;
		}

		$headers = isset($state['headers']) && is_array($state['headers'])
			? array_values(array_map('strval', $state['headers']))
			: array();

		return array('headers' => $headers, 'rows' => $rows);
	}

	public static function draftSourcePath(string $token, int $userId): ?string
	{
		return self::loadDraft($token, $userId) === null ? null : self::draftPath($token);
	}

	/**
	 * Move a validated draft source into the deterministic, UUID-only batch path.
	 * The UUID is generated by the server and validated before becoming a path.
	 */
	public static function promoteDraft(string $token, string $batchUuid, int $userId): ?string
	{
		if (self::loadDraft($token, $userId) === null) {
			return null;
		}

		$source = self::draftPath($token);
		$target = self::batchPath($batchUuid);
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
		$source = self::batchPath($batchUuid);
		$target = self::draftPath($token);
		if ($source === null || $target === null || !is_file($source) || file_exists($target)) {
			return false;
		}

		return rename($source, $target);
	}

	public static function batchSourcePath(string $batchUuid): ?string
	{
		$path = self::batchPath($batchUuid);

		return $path !== null && is_file($path) && is_readable($path) ? $path : null;
	}

	public static function deleteBatchSource(string $batchUuid): void
	{
		$path = self::batchPath($batchUuid);
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
	 * Delete only abandoned draft files. Batch files are never deleted by age,
	 * because paused or failed imports must remain resumable until an explicit
	 * terminal action removes their source.
	 */
	private static function cleanupExpiredDraftFiles(): void
	{
		$pattern = self::tempRoot() . 'wla-inmo-import-draft-*.csv';
		$files = glob($pattern);
		if (!is_array($files)) {
			return;
		}

		$cutoff = time() - (self::DRAFT_TTL * 2);
		foreach ($files as $path) {
			if (!is_string($path) || !is_file($path)) {
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
		$path = self::draftPath($token);
		if ($path !== null && is_file($path)) {
			@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort plugin-owned draft cleanup.
		}
	}

	/**
	 * @return array{headers:array<int,string>,total_rows:int}
	 */
	private static function inspect(string $path): array
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

	private static function draftPath(string $token): ?string
	{
		$token = strtolower(trim($token));
		if (!self::isUuid($token)) {
			return null;
		}

		return self::tempRoot() . 'wla-inmo-import-draft-' . $token . '.csv';
	}

	private static function batchPath(string $batchUuid): ?string
	{
		$batchUuid = strtolower(trim($batchUuid));
		if (!self::isUuid($batchUuid)) {
			return null;
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
	private static function allowedMimes(): array
	{
		return array(
			'text/plain',
			'text/csv',
			'text/tab-separated-values',
			'application/csv',
			'application/vnd.ms-excel',
			'application/octet-stream',
		);
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
