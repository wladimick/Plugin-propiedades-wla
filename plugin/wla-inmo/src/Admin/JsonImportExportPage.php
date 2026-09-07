<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Import\BatchHistoryRepository;
use WLA\Inmo\Import\BatchRepository;
use WLA\Inmo\Import\BatchRunResult;
use WLA\Inmo\Import\BatchRunner;
use WLA\Inmo\Import\BatchStatus;
use WLA\Inmo\Import\DryRunEngine;
use WLA\Inmo\Import\DryRunResult;
use WLA\Inmo\Import\IdentityRepository;
use WLA\Inmo\Import\JsonException;
use WLA\Inmo\Import\JsonExporter;
use WLA\Inmo\Import\JsonLinesReader;
use WLA\Inmo\Import\MappingException;
use WLA\Inmo\Import\MappingProfile;
use WLA\Inmo\Import\MappingProfileCodec;
use WLA\Inmo\Import\TargetRegistry;
use WLA\Inmo\Import\WordPressJsonExportSource;
use WLA\Inmo\Import\WordPressTaxonomyLookup;
use WLA\Inmo\Import\Workspace;

final class JsonImportExportPage
{
	private const UPLOAD_ACTION = 'wla_inmo_json_upload';
	private const SIMULATE_ACTION = 'wla_inmo_json_simulate';
	private const CONFIRM_ACTION = 'wla_inmo_json_confirm';
	private const RUN_ACTION = 'wla_inmo_json_run';
	private const CANCEL_ACTION = 'wla_inmo_json_cancel';
	private const DISCARD_ACTION = 'wla_inmo_json_discard';
	private const EXPORT_ACTION = 'wla_inmo_json_export';
	private const NONCE_UPLOAD = 'wla_inmo_json_upload';
	private const NONCE_SIMULATE = 'wla_inmo_json_simulate';
	private const NONCE_CONFIRM = 'wla_inmo_json_confirm';
	private const NONCE_RUN = 'wla_inmo_json_run';
	private const NONCE_CANCEL = 'wla_inmo_json_cancel';
	private const NONCE_DISCARD = 'wla_inmo_json_discard';
	private const NONCE_EXPORT = 'wla_inmo_json_export';
	private const DRY_RUN_TTL = 1800;
	private const ISSUE_LIMIT = 50;
	private const HISTORY_PAGE_SIZE = 20;

	public static function register(): void
	{
		add_action('admin_post_' . self::UPLOAD_ACTION, array(self::class, 'handleUpload'));
		add_action('admin_post_' . self::SIMULATE_ACTION, array(self::class, 'handleSimulate'));
		add_action('admin_post_' . self::CONFIRM_ACTION, array(self::class, 'handleConfirm'));
		add_action('admin_post_' . self::RUN_ACTION, array(self::class, 'handleRun'));
		add_action('admin_post_' . self::CANCEL_ACTION, array(self::class, 'handleCancel'));
		add_action('admin_post_' . self::DISCARD_ACTION, array(self::class, 'handleDiscard'));
		add_action('admin_post_' . self::EXPORT_ACTION, array(self::class, 'handleExport'));
	}

	public static function render(): void
	{
		self::authorizeImport();
		self::renderNotice();

		$draftToken = self::queryArg('draft');
		$batchUuid = self::queryArg('batch');
		$userId = get_current_user_id();
		$draft = $draftToken !== '' ? Workspace::loadDraft($draftToken, $userId) : null;
		$batch = $batchUuid !== '' ? (new BatchRepository())->find($batchUuid) : null;

		if ($draft !== null && Workspace::sourceFormat($draft) !== 'json') {
			$draft = null;
		}
		if ($batch !== null && ((string) ($batch['source_format'] ?? 'csv') !== 'json' || !self::canAccessBatch($batch))) {
			$batch = null;
		}

		self::renderTools();
		if ($batch !== null) {
			self::renderBatch($batch);
		} elseif ($draft !== null) {
			self::renderDraft($draft);
		} else {
			self::renderUpload();
		}
		self::renderHistory();
	}

	public static function handleUpload(): void
	{
		self::authorizeImport();
		check_admin_referer(self::NONCE_UPLOAD);

		$file = ImportRequest::uploadedFile('wla_json_file');
		$result = Workspace::storeUploadedJson($file, get_current_user_id());
		if (empty($result['ok'])) {
			self::redirect(array('wla_json_error' => (string) $result['code']));
		}

		self::redirect(array('draft' => (string) $result['token'], 'wla_json_notice' => 'upload_ready'));
	}

	public static function handleSimulate(): void
	{
		self::authorizeImport();
		check_admin_referer(self::NONCE_SIMULATE);

		$token = self::postScalar('draft_token');
		$userId = get_current_user_id();
		$state = Workspace::loadDraft($token, $userId);
		if ($state === null || Workspace::sourceFormat($state) !== 'json') {
			self::redirect(array('wla_json_error' => 'draft_expired'));
		}

		$path = Workspace::draftSourcePath($token, $userId);
		$headers = self::stateHeaders($state);
		$mapping = self::canonicalMapping($state, $headers);
		$sourceKey = isset($state['source_key']) && is_string($state['source_key']) ? $state['source_key'] : '';
		if ($path === null || $headers === array() || $mapping === null || $sourceKey === '') {
			self::redirect(array('draft' => $token, 'wla_json_error' => 'json_contract_invalid'));
		}

		$emptyPolicy = self::postScalar('empty_policy');
		if (!in_array($emptyPolicy, array(MappingProfile::EMPTY_PRESERVE, MappingProfile::EMPTY_CLEAR), true)) {
			$emptyPolicy = MappingProfile::EMPTY_PRESERVE;
		}

		try {
			$profile = new MappingProfile(
				$sourceKey,
				$mapping,
				(string) ($state['original_name'] ?? 'WLA JSON'),
				$emptyPolicy
			);
			$profileJson = MappingProfileCodec::encode($profile);
		} catch (MappingException $exception) {
			self::redirect(array('draft' => $token, 'wla_json_error' => $exception->reason()));
		} catch (\InvalidArgumentException) {
			self::redirect(array('draft' => $token, 'wla_json_error' => 'invalid_source_key'));
		}

		$sourceHash = hash_file('sha256', $path);
		if (!is_string($sourceHash) || !hash_equals((string) ($state['source_hash'] ?? ''), $sourceHash)) {
			self::redirect(array('draft' => $token, 'wla_json_error' => 'source_hash_mismatch'));
		}

		$counts = array('new' => 0, 'update' => 0, 'error' => 0, 'warnings' => 0);
		$issues = array();
		$issueCount = 0;
		$processed = 0;
		$rowFactory = static function () use ($path, $sourceHash): iterable {
			return (new JsonLinesReader(Workspace::maxRows()))->verifiedRows($path, $sourceHash);
		};

		try {
			$engine = new DryRunEngine(
				$profile,
				(new IdentityRepository())->resolver(),
				array(WordPressTaxonomyLookup::class, 'lookup')
			);
			foreach ($engine->results($rowFactory) as $result) {
				++$processed;
				$status = $result->status();
				if (isset($counts[$status])) {
					++$counts[$status];
				}
				$counts['warnings'] += count($result->warnings());
				self::collectIssues($issues, $issueCount, $result, 'warning', $result->warnings());
				self::collectIssues($issues, $issueCount, $result, 'error', $result->errors());
			}
		} catch (JsonException $exception) {
			self::redirect(array('draft' => $token, 'wla_json_error' => $exception->reason()));
		} catch (\Throwable) {
			self::redirect(array('draft' => $token, 'wla_json_error' => 'dry_run_failed'));
		}

		if ($processed !== (int) ($state['total_rows'] ?? -1)) {
			self::redirect(array('draft' => $token, 'wla_json_error' => 'source_row_count_mismatch'));
		}

		$state['profile_json'] = $profileJson;
		$state['dry_run'] = array(
			'created_at'   => time(),
			'expires_at'   => time() + self::DRY_RUN_TTL,
			'source_hash'  => $sourceHash,
			'profile_hash' => hash('sha256', $profileJson),
			'counts'       => $counts,
			'issues'       => $issues,
			'issue_count'  => $issueCount,
		);

		if (!Workspace::saveDraft($token, $state)) {
			self::redirect(array('draft' => $token, 'wla_json_error' => 'draft_store_failed'));
		}
		self::redirect(array('draft' => $token, 'wla_json_notice' => 'dry_run_ready'));
	}

	public static function handleConfirm(): void
	{
		self::authorizeImport();
		check_admin_referer(self::NONCE_CONFIRM);

		$token = self::postScalar('draft_token');
		$userId = get_current_user_id();
		$state = Workspace::loadDraft($token, $userId);
		if ($state === null || Workspace::sourceFormat($state) !== 'json') {
			self::redirect(array('wla_json_error' => 'draft_expired'));
		}

		$dryRun = isset($state['dry_run']) && is_array($state['dry_run']) ? $state['dry_run'] : array();
		$counts = isset($dryRun['counts']) && is_array($dryRun['counts']) ? $dryRun['counts'] : array();
		if ($dryRun === array() || (int) ($dryRun['expires_at'] ?? 0) < time()) {
			self::redirect(array('draft' => $token, 'wla_json_error' => 'dry_run_expired'));
		}
		if ((int) ($counts['error'] ?? 0) > 0) {
			self::redirect(array('draft' => $token, 'wla_json_error' => 'dry_run_has_errors'));
		}

		$profileJson = isset($state['profile_json']) && is_string($state['profile_json']) ? $state['profile_json'] : '';
		if ($profileJson === '' || !hash_equals((string) ($dryRun['profile_hash'] ?? ''), hash('sha256', $profileJson))) {
			self::redirect(array('draft' => $token, 'wla_json_error' => 'profile_snapshot_mismatch'));
		}

		$path = Workspace::draftSourcePath($token, $userId);
		$sourceHash = $path !== null ? hash_file('sha256', $path) : false;
		if (!is_string($sourceHash)
			|| !hash_equals((string) ($state['source_hash'] ?? ''), $sourceHash)
			|| !hash_equals((string) ($dryRun['source_hash'] ?? ''), $sourceHash)) {
			self::redirect(array('draft' => $token, 'wla_json_error' => 'source_hash_mismatch'));
		}

		try {
			$profile = MappingProfileCodec::decode($profileJson);
		} catch (MappingException $exception) {
			self::redirect(array('draft' => $token, 'wla_json_error' => $exception->reason()));
		}
		if ($profile->sourceKey() !== (string) ($state['source_key'] ?? '')) {
			self::redirect(array('draft' => $token, 'wla_json_error' => 'profile_source_mismatch'));
		}

		$batchUuid = strtolower((string) wp_generate_uuid4());
		if (Workspace::promoteDraft($token, $batchUuid, $userId) === null) {
			self::redirect(array('draft' => $token, 'wla_json_error' => 'source_promote_failed'));
		}

		$repository = new BatchRepository();
		$created = $repository->create(
			$profile->sourceKey(),
			$sourceHash,
			$profileJson,
			(int) ($state['total_rows'] ?? 0),
			$userId,
			$batchUuid,
			'json'
		);
		if ($created === null) {
			Workspace::restorePromoted($token, $batchUuid);
			self::redirect(array('draft' => $token, 'wla_json_error' => 'batch_create_failed'));
		}

		$revision = 0;
		foreach (array(BatchStatus::MAPPED, BatchStatus::VALIDATED, BatchStatus::DRY_RUN_READY, BatchStatus::CONFIRMED) as $status) {
			if (!$repository->transition($batchUuid, $status, $revision)) {
				self::redirect(array('batch' => $batchUuid, 'wla_json_error' => 'batch_confirm_failed'));
			}
			++$revision;
		}

		Workspace::deleteDraft($token, false);
		self::redirect(array('batch' => $batchUuid, 'wla_json_notice' => 'batch_confirmed'));
	}

	public static function handleRun(): void
	{
		self::authorizeImport();
		check_admin_referer(self::NONCE_RUN);

		$batchUuid = self::postScalar('batch_uuid');
		$repository = new BatchRepository();
		$batch = $repository->find($batchUuid);
		if ($batch === null || (string) ($batch['source_format'] ?? '') !== 'json' || !self::canAccessBatch($batch)) {
			self::redirect(array('wla_json_error' => 'batch_not_found'));
		}
		if (!in_array((string) $batch['status'], array(BatchStatus::CONFIRMED, BatchStatus::PROCESSING, BatchStatus::PAUSED, BatchStatus::FAILED), true)) {
			self::redirect(array('batch' => $batchUuid, 'wla_json_error' => 'batch_not_runnable'));
		}

		$path = Workspace::batchSourcePath($batchUuid, 'json');
		if ($path === null) {
			self::redirect(array('batch' => $batchUuid, 'wla_json_error' => 'source_unreadable'));
		}

		$result = (new BatchRunner())->run($batchUuid, $path, 25, 4.0);
		if (in_array($result->status(), array(BatchRunResult::STATUS_COMPLETED, BatchRunResult::STATUS_ALREADY_COMPLETED), true)) {
			Workspace::deleteBatchSource($batchUuid, 'json');
		}

		$args = array('batch' => $batchUuid, 'wla_json_notice' => 'run_' . sanitize_key($result->status()));
		if (!$result->isSuccessful() && $result->reason() !== null) {
			$args['wla_json_error'] = sanitize_key($result->reason());
			unset($args['wla_json_notice']);
		}
		self::redirect($args);
	}

	public static function handleCancel(): void
	{
		self::authorizeImport();
		check_admin_referer(self::NONCE_CANCEL);

		$batchUuid = self::postScalar('batch_uuid');
		$repository = new BatchRepository();
		$batch = $repository->find($batchUuid);
		if ($batch === null || (string) ($batch['source_format'] ?? '') !== 'json' || !self::canAccessBatch($batch)) {
			self::redirect(array('wla_json_error' => 'batch_not_found'));
		}
		if (!in_array((string) $batch['status'], array(BatchStatus::CONFIRMED, BatchStatus::PAUSED, BatchStatus::FAILED), true)) {
			self::redirect(array('batch' => $batchUuid, 'wla_json_error' => 'unsafe_cancel_state'));
		}
		if (!$repository->transition($batchUuid, BatchStatus::CANCELLED, (int) $batch['revision'])) {
			self::redirect(array('batch' => $batchUuid, 'wla_json_error' => 'cancel_conflict'));
		}

		Workspace::deleteBatchSource($batchUuid, 'json');
		self::redirect(array('batch' => $batchUuid, 'wla_json_notice' => 'batch_cancelled'));
	}

	public static function handleDiscard(): void
	{
		self::authorizeImport();
		check_admin_referer(self::NONCE_DISCARD);

		$token = self::postScalar('draft_token');
		$state = Workspace::loadDraft($token, get_current_user_id());
		if ($state !== null && Workspace::sourceFormat($state) === 'json') {
			Workspace::deleteDraft($token, true);
		}
		self::redirect(array('wla_json_notice' => 'draft_discarded'));
	}

	public static function handleExport(): never
	{
		if (!current_user_can(AccessCapabilities::EXPORT_PROPERTIES)) {
			wp_die(esc_html__('No tienes permisos para exportar propiedades.', 'wla-inmo'), esc_html__('Acceso denegado', 'wla-inmo'), array('response' => 403));
		}
		check_admin_referer(self::NONCE_EXPORT);

		$path = trailingslashit(get_temp_dir()) . 'wla-inmo-export-' . strtolower((string) wp_generate_uuid4()) . '.json';
		try {
			$result = (new JsonExporter(new WordPressJsonExportSource()))->export($path, 'wla_export', 100);
		} catch (\Throwable) {
			if (is_file($path)) {
				@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup failed export.
			}
			self::redirect(array('wla_json_error' => 'export_failed'));
		}

		nocache_headers();
		header('Content-Type: application/json; charset=utf-8');
		header('Content-Disposition: attachment; filename="wla-inmo-export-' . gmdate('Ymd-His') . '.json"');
		header('Content-Length: ' . (string) $result['bytes']);
		readfile($path); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Private plugin-created temporary export is streamed once then deleted.
		@unlink($path); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort cleanup after download.
		exit;
	}

	private static function renderTools(): void
	{
		echo '<section class="wla-inmo-admin__panel wla-inmo-import__panel">';
		echo '<h2>' . esc_html__('JSON WLA versionado', 'wla-inmo') . '</h2>';
		echo '<p>' . esc_html__('Formato lógico interoperable. La importación valida format_version, simula primero y procesa por el mismo runner reanudable usado por CSV.', 'wla-inmo') . '</p>';
		if (current_user_can(AccessCapabilities::EXPORT_PROPERTIES)) {
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr(self::EXPORT_ACTION) . '">';
			wp_nonce_field(self::NONCE_EXPORT);
			echo '<button type="submit" class="button">' . esc_html__('Exportar respaldo JSON WLA', 'wla-inmo') . '</button>';
			echo '<p class="description">' . esc_html__('Exporta campos portables/públicos por páginas; direcciones privadas, notas internas y otros campos privados quedan excluidos.', 'wla-inmo') . '</p>';
			echo '</form>';
		}
		echo '</section>';
	}

	private static function renderUpload(): void
	{
		echo '<section class="wla-inmo-admin__panel wla-inmo-import__panel">';
		echo '<h2>' . esc_html__('Nueva importación JSON WLA', 'wla-inmo') . '</h2>';
		echo '<p>' . esc_html__('Sube un JSON WLA v1. El archivo se valida y normaliza a una fuente privada reanudable antes de cualquier simulación.', 'wla-inmo') . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::UPLOAD_ACTION) . '">';
		wp_nonce_field(self::NONCE_UPLOAD);
		echo '<p><label for="wla-json-file"><strong>' . esc_html__('Archivo JSON WLA', 'wla-inmo') . '</strong></label><br>';
		echo '<input id="wla-json-file" type="file" name="wla_json_file" accept=".json,application/json" required></p>';
		echo '<p class="description">' . esc_html(sprintf(__('Máximo %1$s MB y %2$s propiedades.', 'wla-inmo'), number_format_i18n(Workspace::maxUploadBytes() / 1048576, 0), number_format_i18n(Workspace::maxRows()))) . '</p>';
		echo '<button type="submit" class="button button-primary">' . esc_html__('Subir y validar JSON', 'wla-inmo') . '</button>';
		echo '</form></section>';
	}

	/** @param array<string,mixed> $state */
	private static function renderDraft(array $state): void
	{
		$token = (string) $state['token'];
		$preview = Workspace::preview($token, get_current_user_id());
		$profile = null;
		if (!empty($state['profile_json']) && is_string($state['profile_json'])) {
			try {
				$profile = MappingProfileCodec::decode($state['profile_json']);
			} catch (MappingException) {
				$profile = null;
			}
		}
		$emptyPolicy = $profile !== null ? $profile->emptyPolicy() : MappingProfile::EMPTY_PRESERVE;
		$mapping = self::canonicalMapping($state, self::stateHeaders($state));

		echo '<section class="wla-inmo-admin__grid">';
		self::metricCard(__('Formato', 'wla-inmo'), 'JSON WLA v' . (string) ($state['format_version'] ?? '1'));
		self::metricCard(__('Propiedades', 'wla-inmo'), number_format_i18n((int) ($state['total_rows'] ?? 0)));
		self::metricCard(__('Origen', 'wla-inmo'), (string) ($state['source_key'] ?? ''));
		echo '</section>';

		if ($preview !== null) {
			self::renderPreview($preview);
		}

		echo '<section class="wla-inmo-admin__panel wla-inmo-import__panel">';
		echo '<h2>' . esc_html__('Contrato detectado y simulación', 'wla-inmo') . '</h2>';
		echo '<p>' . esc_html__('El origen y los campos del JSON son canónicos y no se pueden remapear desde el navegador. Esto evita que una petición altere el contrato ya validado.', 'wla-inmo') . '</p>';
		if ($mapping !== null) {
			echo '<div class="table-responsive"><table class="widefat striped"><thead><tr><th>' . esc_html__('Clave interna', 'wla-inmo') . '</th><th>' . esc_html__('Campo canónico', 'wla-inmo') . '</th></tr></thead><tbody>';
			foreach ($mapping as $header => $target) {
				echo '<tr><td><code>' . esc_html($header) . '</code></td><td>' . esc_html(self::targetLabel($target)) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::SIMULATE_ACTION) . '"><input type="hidden" name="draft_token" value="' . esc_attr($token) . '">';
		wp_nonce_field(self::NONCE_SIMULATE);
		echo '<p><label for="wla-json-empty-policy"><strong>' . esc_html__('Si un valor viene vacío', 'wla-inmo') . '</strong></label><br><select id="wla-json-empty-policy" name="empty_policy">';
		echo '<option value="preserve"' . selected($emptyPolicy, MappingProfile::EMPTY_PRESERVE, false) . '>' . esc_html__('Conservar el valor existente (recomendado)', 'wla-inmo') . '</option>';
		echo '<option value="clear"' . selected($emptyPolicy, MappingProfile::EMPTY_CLEAR, false) . '>' . esc_html__('Borrar el valor existente', 'wla-inmo') . '</option>';
		echo '</select></p><button type="submit" class="button button-primary">' . esc_html__('Validar y simular JSON', 'wla-inmo') . '</button></form>';
		echo '</section>';

		if (!empty($state['dry_run']) && is_array($state['dry_run'])) {
			self::renderDryRun($state);
		}

		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="' . esc_attr(self::DISCARD_ACTION) . '"><input type="hidden" name="draft_token" value="' . esc_attr($token) . '">';
		wp_nonce_field(self::NONCE_DISCARD);
		echo '<button type="submit" class="button">' . esc_html__('Descartar carga JSON', 'wla-inmo') . '</button></form>';
	}

	/** @param array{headers:array<int,string>,rows:array<int,array<string,string>>} $preview */
	private static function renderPreview(array $preview): void
	{
		echo '<section class="wla-inmo-admin__panel wla-inmo-import__panel"><h2>' . esc_html__('Vista previa normalizada', 'wla-inmo') . '</h2>';
		echo '<p class="description">' . esc_html__('Solo se muestran unas pocas propiedades; el dataset completo nunca se envía al navegador.', 'wla-inmo') . '</p><div class="table-responsive"><table class="widefat striped"><thead><tr>';
		foreach ($preview['headers'] as $header) {
			echo '<th scope="col">' . esc_html($header) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ($preview['rows'] as $row) {
			echo '<tr>';
			foreach ($preview['headers'] as $header) {
				echo '<td>' . esc_html(self::previewCell((string) ($row[$header] ?? ''))) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div></section>';
	}

	/** @param array<string,mixed> $state */
	private static function renderDryRun(array $state): void
	{
		$dryRun = $state['dry_run'];
		$counts = isset($dryRun['counts']) && is_array($dryRun['counts']) ? $dryRun['counts'] : array();
		$errorCount = (int) ($counts['error'] ?? 0);
		$expired = (int) ($dryRun['expires_at'] ?? 0) < time();

		echo '<section class="wla-inmo-admin__panel wla-inmo-import__panel"><h2>' . esc_html__('Resultado de la simulación JSON', 'wla-inmo') . '</h2><div class="wla-inmo-admin__grid">';
		self::metricCard(__('Nuevas', 'wla-inmo'), number_format_i18n((int) ($counts['new'] ?? 0)));
		self::metricCard(__('Actualizaciones', 'wla-inmo'), number_format_i18n((int) ($counts['update'] ?? 0)));
		self::metricCard(__('Advertencias', 'wla-inmo'), number_format_i18n((int) ($counts['warnings'] ?? 0)));
		self::metricCard(__('Errores', 'wla-inmo'), number_format_i18n($errorCount));
		echo '</div>';

		$issues = isset($dryRun['issues']) && is_array($dryRun['issues']) ? $dryRun['issues'] : array();
		if ($issues !== array()) {
			echo '<div class="table-responsive"><table class="widefat striped"><thead><tr><th>' . esc_html__('Fila', 'wla-inmo') . '</th><th>' . esc_html__('Tipo', 'wla-inmo') . '</th><th>' . esc_html__('Código', 'wla-inmo') . '</th><th>' . esc_html__('Campo', 'wla-inmo') . '</th></tr></thead><tbody>';
			foreach ($issues as $issue) {
				if (!is_array($issue)) {
					continue;
				}
				echo '<tr><td>' . esc_html((string) absint($issue['row'] ?? 0)) . '</td><td>' . esc_html((string) ($issue['kind'] ?? '')) . '</td><td><code>' . esc_html((string) ($issue['code'] ?? '')) . '</code></td><td>' . esc_html(self::targetLabel((string) ($issue['target'] ?? ''))) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}

		if ($expired) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__('La simulación venció. Vuelve a simular antes de confirmar.', 'wla-inmo') . '</p></div>';
		} elseif ($errorCount > 0) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__('El JSON contiene errores bloqueantes y no puede confirmarse.', 'wla-inmo') . '</p></div>';
		} else {
			echo '<div class="notice notice-success inline"><p>' . esc_html__('El JSON pasó el dry-run. Confirmar congelará hash y perfil antes de ejecutar.', 'wla-inmo') . '</p></div>';
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="' . esc_attr(self::CONFIRM_ACTION) . '"><input type="hidden" name="draft_token" value="' . esc_attr((string) $state['token']) . '">';
			wp_nonce_field(self::NONCE_CONFIRM);
			echo '<button type="submit" class="button button-primary">' . esc_html__('Confirmar importación JSON', 'wla-inmo') . '</button></form>';
		}
		echo '</section>';
	}

	/** @param array<string,mixed> $batch */
	private static function renderBatch(array $batch): void
	{
		$status = (string) $batch['status'];
		$total = max(0, (int) $batch['total_rows']);
		$processed = max(0, (int) $batch['processed_rows']);
		$percent = $total > 0 ? min(100, (int) round(($processed / $total) * 100)) : 0;

		echo '<section class="wla-inmo-admin__panel wla-inmo-import__panel"><div class="wla-inmo-import__batch-heading"><div><p class="wla-inmo-admin__eyebrow">JSON WLA</p><h2>' . esc_html(self::statusLabel($status)) . '</h2></div><code>' . esc_html((string) $batch['batch_uuid']) . '</code></div>';
		echo '<div class="wla-inmo-import__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr((string) $percent) . '"><span style="width:' . esc_attr((string) $percent) . '%"></span></div>';
		echo '<p>' . esc_html(sprintf(__('%1$s de %2$s propiedades procesadas (%3$d%%).', 'wla-inmo'), number_format_i18n($processed), number_format_i18n($total), $percent)) . '</p><div class="wla-inmo-admin__grid">';
		self::metricCard(__('Creadas', 'wla-inmo'), number_format_i18n((int) $batch['created_count']));
		self::metricCard(__('Actualizadas', 'wla-inmo'), number_format_i18n((int) $batch['updated_count']));
		self::metricCard(__('Omitidas', 'wla-inmo'), number_format_i18n((int) $batch['skipped_count']));
		self::metricCard(__('Errores', 'wla-inmo'), number_format_i18n((int) $batch['error_count']));
		echo '</div>';

		if (in_array($status, array(BatchStatus::CONFIRMED, BatchStatus::PROCESSING, BatchStatus::PAUSED, BatchStatus::FAILED), true)) {
			echo '<form class="wla-inmo-import__inline-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="' . esc_attr(self::RUN_ACTION) . '"><input type="hidden" name="batch_uuid" value="' . esc_attr((string) $batch['batch_uuid']) . '">';
			wp_nonce_field(self::NONCE_RUN);
			echo '<button type="submit" class="button button-primary">' . esc_html($processed > 0 ? __('Continuar / reanudar', 'wla-inmo') : __('Iniciar procesamiento', 'wla-inmo')) . '</button></form>';
		}
		if (in_array($status, array(BatchStatus::CONFIRMED, BatchStatus::PAUSED, BatchStatus::FAILED), true)) {
			echo '<form class="wla-inmo-import__inline-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="' . esc_attr(self::CANCEL_ACTION) . '"><input type="hidden" name="batch_uuid" value="' . esc_attr((string) $batch['batch_uuid']) . '">';
			wp_nonce_field(self::NONCE_CANCEL);
			echo '<button type="submit" class="button">' . esc_html__('Cancelar en este checkpoint', 'wla-inmo') . '</button></form>';
		}
		if ($status === BatchStatus::COMPLETED) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__('Importación JSON completada. La fuente temporal normalizada fue eliminada.', 'wla-inmo') . '</p></div>';
		}
		echo '</section>';
	}

	private static function renderHistory(): void
	{
		$userId = get_current_user_id();
		$createdBy = current_user_can(AccessCapabilities::MANAGE_TOOLS) ? null : $userId;
		$page = max(1, absint(self::queryArg('wla_json_history_page')));
		$offset = ($page - 1) * self::HISTORY_PAGE_SIZE;
		$history = new BatchHistoryRepository();
		$rows = $history->recent(self::HISTORY_PAGE_SIZE, $offset, $createdBy, null, 'json');
		$total = $history->count($createdBy, null, 'json');

		echo '<section class="wla-inmo-admin__panel wla-inmo-import__panel"><h2>' . esc_html__('Historial JSON', 'wla-inmo') . '</h2>';
		if ($rows === array()) {
			echo '<p>' . esc_html__('Todavía no hay batches JSON.', 'wla-inmo') . '</p>';
		} else {
			echo '<div class="table-responsive"><table class="widefat striped"><thead><tr><th>' . esc_html__('Fecha', 'wla-inmo') . '</th><th>' . esc_html__('Origen', 'wla-inmo') . '</th><th>' . esc_html__('Estado', 'wla-inmo') . '</th><th>' . esc_html__('Progreso', 'wla-inmo') . '</th><th>' . esc_html__('Acción', 'wla-inmo') . '</th></tr></thead><tbody>';
			foreach ($rows as $row) {
				$url = add_query_arg(array('page' => 'wla-inmo-import-export', 'wla_format' => 'json', 'batch' => (string) $row['batch_uuid']), admin_url('admin.php'));
				echo '<tr><td>' . esc_html(self::displayDate((string) $row['created_at'])) . '</td><td><code>' . esc_html((string) $row['source_key']) . '</code></td><td>' . esc_html(self::statusLabel((string) $row['status'])) . '</td><td>' . esc_html(number_format_i18n((int) $row['processed_rows']) . ' / ' . number_format_i18n((int) $row['total_rows'])) . '</td><td><a class="button button-small" href="' . esc_url($url) . '">' . esc_html__('Ver', 'wla-inmo') . '</a></td></tr>';
			}
			echo '</tbody></table></div>';
		}

		$pages = max(1, (int) ceil($total / self::HISTORY_PAGE_SIZE));
		if ($pages > 1) {
			echo '<nav class="wla-inmo-import__pagination" aria-label="' . esc_attr__('Paginación del historial JSON', 'wla-inmo') . '">';
			if ($page > 1) {
				echo '<a class="button" href="' . esc_url(self::historyUrl($page - 1)) . '">' . esc_html__('Anterior', 'wla-inmo') . '</a>';
			}
			echo '<span>' . esc_html(sprintf(__('Página %1$d de %2$d', 'wla-inmo'), $page, $pages)) . '</span>';
			if ($page < $pages) {
				echo '<a class="button" href="' . esc_url(self::historyUrl($page + 1)) . '">' . esc_html__('Siguiente', 'wla-inmo') . '</a>';
			}
			echo '</nav>';
		}
		echo '</section>';
	}

	/** @param array<string,mixed> $state @param array<int,string> $headers @return array<string,string>|null */
	private static function canonicalMapping(array $state, array $headers): ?array
	{
		$raw = isset($state['canonical_mapping']) && is_array($state['canonical_mapping']) ? $state['canonical_mapping'] : array();
		if ($raw === array() || $headers === array()) {
			return null;
		}

		$mapping = array();
		foreach ($headers as $header) {
			$target = $raw[$header] ?? null;
			if (!is_string($target) || !TargetRegistry::isAllowed($target)) {
				return null;
			}
			$mapping[$header] = $target;
		}
		if (count($mapping) !== count($raw)) {
			return null;
		}

		return $mapping;
	}

	/** @param array<int,array<string,mixed>> $issues @param array<int,array{code:string,target:string}> $messages */
	private static function collectIssues(array &$issues, int &$issueCount, DryRunResult $result, string $kind, array $messages): void
	{
		foreach ($messages as $message) {
			++$issueCount;
			if (count($issues) >= self::ISSUE_LIMIT) {
				continue;
			}
			$issues[] = array(
				'row'    => $result->rowNumber(),
				'kind'   => sanitize_key($kind),
				'code'   => sanitize_key((string) ($message['code'] ?? '')),
				'target' => sanitize_text_field((string) ($message['target'] ?? '')),
			);
		}
	}

	/** @param array<string,mixed> $batch */
	private static function canAccessBatch(array $batch): bool
	{
		return current_user_can(AccessCapabilities::MANAGE_TOOLS) || (int) ($batch['created_by'] ?? 0) === get_current_user_id();
	}

	private static function authorizeImport(): void
	{
		if (!current_user_can(AccessCapabilities::IMPORT_PROPERTIES)) {
			wp_die(esc_html__('No tienes permisos para importar propiedades.', 'wla-inmo'), esc_html__('Acceso denegado', 'wla-inmo'), array('response' => 403));
		}
	}

	/** @param array<string,string> $args */
	private static function redirect(array $args): never
	{
		$url = add_query_arg(array_merge(array('page' => 'wla-inmo-import-export', 'wla_format' => 'json'), $args), admin_url('admin.php'));
		wp_safe_redirect($url);
		exit;
	}

	private static function queryArg(string $key): string
	{
		return ImportRequest::queryScalar($key);
	}

	private static function postScalar(string $key): string
	{
		return ImportRequest::postScalar($key);
	}

	/** @param array<string,mixed> $state @return array<int,string> */
	private static function stateHeaders(array $state): array
	{
		if (!isset($state['headers']) || !is_array($state['headers'])) {
			return array();
		}
		return array_values(array_filter(array_map('strval', $state['headers']), static fn (string $header): bool => $header !== ''));
	}

	private static function previewCell(string $value): string
	{
		$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
		return function_exists('mb_substr') ? mb_substr($value, 0, 120) : substr($value, 0, 120);
	}

	private static function targetLabel(string $target): string
	{
		$parts = explode('.', $target, 2);
		$label = count($parts) === 2 ? $parts[1] : $target;
		$label = str_replace('_', ' ', $label);
		return $label === '' ? __('General', 'wla-inmo') : ucwords($label);
	}

	private static function statusLabel(string $status): string
	{
		$labels = array(
			BatchStatus::UPLOADED => __('Subido', 'wla-inmo'),
			BatchStatus::MAPPED => __('Mapeado', 'wla-inmo'),
			BatchStatus::VALIDATED => __('Validado', 'wla-inmo'),
			BatchStatus::DRY_RUN_READY => __('Simulado', 'wla-inmo'),
			BatchStatus::CONFIRMED => __('Confirmado', 'wla-inmo'),
			BatchStatus::PROCESSING => __('Procesando', 'wla-inmo'),
			BatchStatus::PAUSED => __('Pausado', 'wla-inmo'),
			BatchStatus::FAILED => __('Detenido con error', 'wla-inmo'),
			BatchStatus::COMPLETED => __('Completado', 'wla-inmo'),
			BatchStatus::CANCELLED => __('Cancelado', 'wla-inmo'),
		);
		return $labels[$status] ?? $status;
	}

	private static function metricCard(string $label, string $value): void
	{
		echo '<article class="wla-inmo-admin__card"><p class="wla-inmo-admin__eyebrow">' . esc_html($label) . '</p><h2>' . esc_html($value) . '</h2></article>';
	}

	private static function displayDate(string $value): string
	{
		$timestamp = strtotime($value . ' UTC');
		return $timestamp === false ? $value : wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
	}

	private static function historyUrl(int $page): string
	{
		return add_query_arg(array('page' => 'wla-inmo-import-export', 'wla_format' => 'json', 'wla_json_history_page' => max(1, $page)), admin_url('admin.php'));
	}

	private static function renderNotice(): void
	{
		$error = self::queryArg('wla_json_error');
		$success = self::queryArg('wla_json_notice');
		if ($error !== '') {
			echo '<div class="notice notice-error inline is-dismissible"><p>' . esc_html(self::message($error)) . '</p></div>';
		} elseif ($success !== '') {
			echo '<div class="notice notice-success inline is-dismissible"><p>' . esc_html(self::message($success)) . '</p></div>';
		}
	}

	private static function message(string $code): string
	{
		$messages = array(
			'upload_ready' => __('JSON WLA validado y normalizado. Revisa el contrato y ejecuta la simulación.', 'wla-inmo'),
			'dry_run_ready' => __('Simulación JSON actualizada.', 'wla-inmo'),
			'batch_confirmed' => __('Importación JSON confirmada. Ya puedes procesarla por lotes.', 'wla-inmo'),
			'run_paused' => __('Se procesó un lote y el batch JSON quedó pausado en un checkpoint seguro.', 'wla-inmo'),
			'run_completed' => __('Importación JSON completada.', 'wla-inmo'),
			'run_already_completed' => __('Este batch JSON ya estaba completado.', 'wla-inmo'),
			'batch_cancelled' => __('Importación JSON cancelada en un checkpoint seguro.', 'wla-inmo'),
			'draft_discarded' => __('Carga JSON descartada y archivos temporales eliminados.', 'wla-inmo'),
			'invalid_extension' => __('Solo se aceptan archivos .json en esta pestaña.', 'wla-inmo'),
			'invalid_mime' => __('El tipo MIME no coincide con un JSON permitido.', 'wla-inmo'),
			'file_too_large' => __('El JSON supera el tamaño permitido.', 'wla-inmo'),
			'malformed_json' => __('El documento JSON está malformado o supera la profundidad permitida.', 'wla-inmo'),
			'missing_format_version' => __('El JSON debe declarar format_version.', 'wla-inmo'),
			'unsupported_format_version' => __('La versión del formato JSON WLA no es compatible.', 'wla-inmo'),
			'missing_source_key' => __('El JSON debe declarar un source_key válido.', 'wla-inmo'),
			'unknown_root_key' => __('El JSON contiene una clave raíz no permitida.', 'wla-inmo'),
			'unknown_property_section' => __('Una propiedad contiene una sección no permitida.', 'wla-inmo'),
			'unknown_target' => __('Una propiedad contiene un campo que no forma parte del contrato WLA.', 'wla-inmo'),
			'invalid_target_value' => __('Un campo JSON contiene una estructura no portable para ese target.', 'wla-inmo'),
			'property_limit_exceeded' => __('El JSON supera la cantidad máxima de propiedades permitida.', 'wla-inmo'),
			'json_contract_invalid' => __('El contrato normalizado del JSON ya no coincide con la carga validada.', 'wla-inmo'),
			'draft_expired' => __('La carga JSON temporal venció o ya no está disponible.', 'wla-inmo'),
			'dry_run_expired' => __('La simulación venció. Vuelve a simular antes de confirmar.', 'wla-inmo'),
			'dry_run_has_errors' => __('La simulación contiene errores bloqueantes.', 'wla-inmo'),
			'source_hash_mismatch' => __('La fuente normalizada cambió después de validarse; la operación fue bloqueada.', 'wla-inmo'),
			'source_unreadable' => __('La fuente temporal del batch ya no está disponible.', 'wla-inmo'),
			'batch_not_found' => __('No se encontró un batch JSON accesible para tu usuario.', 'wla-inmo'),
			'batch_not_runnable' => __('El estado actual del batch JSON no permite procesarlo.', 'wla-inmo'),
			'unsafe_cancel_state' => __('Solo se puede cancelar desde un checkpoint seguro.', 'wla-inmo'),
			'row_validation_failed' => __('Una propiedad dejó de ser válida durante la ejecución. El cursor no avanzó.', 'wla-inmo'),
			'row_execution_failed' => __('Una propiedad no pudo persistirse. El cursor no avanzó.', 'wla-inmo'),
			'export_failed' => __('No fue posible generar el respaldo JSON.', 'wla-inmo'),
		);
		return $messages[$code] ?? __('La operación JSON no pudo completarse con el estado actual.', 'wla-inmo');
	}
}
