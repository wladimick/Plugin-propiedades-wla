<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Import\BatchHistoryRepository;
use WLA\Inmo\Import\BatchRepository;
use WLA\Inmo\Import\BatchRunResult;
use WLA\Inmo\Import\BatchRunner;
use WLA\Inmo\Import\BatchStatus;
use WLA\Inmo\Import\CsvException;
use WLA\Inmo\Import\CsvReader;
use WLA\Inmo\Import\DryRunEngine;
use WLA\Inmo\Import\DryRunResult;
use WLA\Inmo\Import\IdentityRepository;
use WLA\Inmo\Import\MappingException;
use WLA\Inmo\Import\MappingProfile;
use WLA\Inmo\Import\MappingProfileCodec;
use WLA\Inmo\Import\TargetRegistry;
use WLA\Inmo\Import\WordPressTaxonomyLookup;
use WLA\Inmo\Import\Workspace;

final class ImportExportPage
{
	private const UPLOAD_ACTION = 'wla_inmo_import_upload';
	private const MAP_ACTION = 'wla_inmo_import_map';
	private const CONFIRM_ACTION = 'wla_inmo_import_confirm';
	private const RUN_ACTION = 'wla_inmo_import_run';
	private const CANCEL_ACTION = 'wla_inmo_import_cancel';
	private const DISCARD_ACTION = 'wla_inmo_import_discard';
	private const NONCE_UPLOAD = 'wla_inmo_import_upload';
	private const NONCE_MAP = 'wla_inmo_import_map';
	private const NONCE_CONFIRM = 'wla_inmo_import_confirm';
	private const NONCE_RUN = 'wla_inmo_import_run';
	private const NONCE_CANCEL = 'wla_inmo_import_cancel';
	private const NONCE_DISCARD = 'wla_inmo_import_discard';
	private const DRY_RUN_TTL = 1800;
	private const ISSUE_LIMIT = 50;
	private const HISTORY_PAGE_SIZE = 20;

	public static function register(): void
	{
		add_action('admin_post_' . self::UPLOAD_ACTION, array(self::class, 'handleUpload'));
		add_action('admin_post_' . self::MAP_ACTION, array(self::class, 'handleMap'));
		add_action('admin_post_' . self::CONFIRM_ACTION, array(self::class, 'handleConfirm'));
		add_action('admin_post_' . self::RUN_ACTION, array(self::class, 'handleRun'));
		add_action('admin_post_' . self::CANCEL_ACTION, array(self::class, 'handleCancel'));
		add_action('admin_post_' . self::DISCARD_ACTION, array(self::class, 'handleDiscard'));
	}

	public static function render(): void
	{
		self::authorize();
		self::renderNotice();

		$draftToken = self::queryArg('draft');
		$batchUuid = self::queryArg('batch');
		$currentUserId = get_current_user_id();
		$draft = $draftToken !== '' ? Workspace::loadDraft($draftToken, $currentUserId) : null;
		$batch = $batchUuid !== '' ? (new BatchRepository())->find($batchUuid) : null;

		if ($batch !== null && !self::canAccessBatch($batch)) {
			$batch = null;
		}

		self::renderSteps($draft, $batch);

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
		self::authorize();
		check_admin_referer(self::NONCE_UPLOAD);

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately above; file content is validated by Workspace.
		$file = isset($_FILES['wla_import_file']) && is_array($_FILES['wla_import_file']) ? $_FILES['wla_import_file'] : array();
		$result = Workspace::storeUploadedCsv($file, get_current_user_id());
		if (empty($result['ok'])) {
			self::redirect(array('wla_import_error' => (string) $result['code']));
		}

		self::redirect(
			array(
				'draft' => (string) $result['token'],
				'wla_import_notice' => 'upload_ready',
			)
		);
	}

	public static function handleMap(): void
	{
		self::authorize();
		check_admin_referer(self::NONCE_MAP);

		$token = self::postScalar('draft_token');
		$userId = get_current_user_id();
		$state = Workspace::loadDraft($token, $userId);
		if ($state === null) {
			self::redirect(array('wla_import_error' => 'draft_expired'));
		}

		$path = Workspace::draftSourcePath($token, $userId);
		if ($path === null) {
			self::redirect(array('wla_import_error' => 'source_unreadable'));
		}

		$headers = self::stateHeaders($state);
		if ($headers === array()) {
			self::redirect(array('draft' => $token, 'wla_import_error' => 'missing_header'));
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above; each scalar is sanitized and mapped only against stored headers/allowlisted targets.
		$rawMapping = isset($_POST['wla_mapping']) && is_array($_POST['wla_mapping']) ? wp_unslash($_POST['wla_mapping']) : array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above; separators are bounded and applied only to multi-value targets.
		$rawSeparators = isset($_POST['wla_separator']) && is_array($_POST['wla_separator']) ? wp_unslash($_POST['wla_separator']) : array();
		$mapping = array();
		$separators = array();

		foreach ($headers as $index => $header) {
			$rawTarget = $rawMapping[$index] ?? '';
			$target = is_scalar($rawTarget) ? sanitize_text_field((string) $rawTarget) : '';
			if ($target === '') {
				continue;
			}
			if (!TargetRegistry::isAllowed($target)) {
				self::redirect(array('draft' => $token, 'wla_import_error' => 'unknown_target'));
			}

			$mapping[$header] = $target;
			$rawSeparator = $rawSeparators[$index] ?? '';
			$separator = is_scalar($rawSeparator) ? sanitize_text_field((string) $rawSeparator) : '';
			if ($separator !== '' && TargetRegistry::isMultiple($target)) {
				$separators[$header] = substr($separator, 0, 8);
			}
		}

		$sourceKey = self::postScalar('source_key');
		$emptyPolicy = self::postScalar('empty_policy');
		if (!in_array($emptyPolicy, array(MappingProfile::EMPTY_PRESERVE, MappingProfile::EMPTY_CLEAR), true)) {
			$emptyPolicy = MappingProfile::EMPTY_PRESERVE;
		}

		try {
			$profile = new MappingProfile(
				$sourceKey,
				$mapping,
				(string) ($state['original_name'] ?? ''),
				$emptyPolicy,
				$separators
			);
			$profileJson = MappingProfileCodec::encode($profile);
		} catch (MappingException $exception) {
			self::redirect(array('draft' => $token, 'wla_import_error' => $exception->reason()));
		} catch (\InvalidArgumentException) {
			self::redirect(array('draft' => $token, 'wla_import_error' => 'invalid_source_key'));
		}

		$sourceHash = hash_file('sha256', $path);
		if (!is_string($sourceHash) || !hash_equals((string) ($state['source_hash'] ?? ''), $sourceHash)) {
			self::redirect(array('draft' => $token, 'wla_import_error' => 'source_hash_mismatch'));
		}

		$counts = array('new' => 0, 'update' => 0, 'error' => 0, 'warnings' => 0);
		$issues = array();
		$processed = 0;
		$rowFactory = static function () use ($path): iterable {
			return (new CsvReader(Workspace::maxRows()))->rows($path);
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
				self::collectIssues($issues, $result, 'warning', $result->warnings());
				self::collectIssues($issues, $result, 'error', $result->errors());
			}
		} catch (CsvException $exception) {
			self::redirect(array('draft' => $token, 'wla_import_error' => $exception->reason()));
		} catch (\Throwable) {
			self::redirect(array('draft' => $token, 'wla_import_error' => 'dry_run_failed'));
		}

		if ($processed !== (int) ($state['total_rows'] ?? -1)) {
			self::redirect(array('draft' => $token, 'wla_import_error' => 'source_row_count_mismatch'));
		}

		$state['profile_json'] = $profileJson;
		$state['dry_run'] = array(
			'created_at'   => time(),
			'expires_at'   => time() + self::DRY_RUN_TTL,
			'source_hash'  => $sourceHash,
			'profile_hash' => hash('sha256', $profileJson),
			'counts'       => $counts,
			'issues'       => array_slice($issues, 0, self::ISSUE_LIMIT),
			'issue_count'  => count($issues),
		);

		if (!Workspace::saveDraft($token, $state)) {
			self::redirect(array('draft' => $token, 'wla_import_error' => 'draft_store_failed'));
		}

		self::redirect(array('draft' => $token, 'wla_import_notice' => 'dry_run_ready'));
	}

	public static function handleConfirm(): void
	{
		self::authorize();
		check_admin_referer(self::NONCE_CONFIRM);

		$token = self::postScalar('draft_token');
		$userId = get_current_user_id();
		$state = Workspace::loadDraft($token, $userId);
		if ($state === null) {
			self::redirect(array('wla_import_error' => 'draft_expired'));
		}

		$dryRun = isset($state['dry_run']) && is_array($state['dry_run']) ? $state['dry_run'] : array();
		$counts = isset($dryRun['counts']) && is_array($dryRun['counts']) ? $dryRun['counts'] : array();
		if ($dryRun === array() || (int) ($dryRun['expires_at'] ?? 0) < time()) {
			self::redirect(array('draft' => $token, 'wla_import_error' => 'dry_run_expired'));
		}
		if ((int) ($counts['error'] ?? 0) > 0) {
			self::redirect(array('draft' => $token, 'wla_import_error' => 'dry_run_has_errors'));
		}

		$profileJson = isset($state['profile_json']) && is_string($state['profile_json']) ? $state['profile_json'] : '';
		if ($profileJson === '' || !hash_equals((string) ($dryRun['profile_hash'] ?? ''), hash('sha256', $profileJson))) {
			self::redirect(array('draft' => $token, 'wla_import_error' => 'profile_snapshot_mismatch'));
		}

		$path = Workspace::draftSourcePath($token, $userId);
		$sourceHash = $path !== null ? hash_file('sha256', $path) : false;
		if (!is_string($sourceHash)
			|| !hash_equals((string) ($state['source_hash'] ?? ''), $sourceHash)
			|| !hash_equals((string) ($dryRun['source_hash'] ?? ''), $sourceHash)) {
			self::redirect(array('draft' => $token, 'wla_import_error' => 'source_hash_mismatch'));
		}

		try {
			$profile = MappingProfileCodec::decode($profileJson);
		} catch (MappingException $exception) {
			self::redirect(array('draft' => $token, 'wla_import_error' => $exception->reason()));
		}

		$batchUuid = strtolower((string) wp_generate_uuid4());
		$batchPath = Workspace::promoteDraft($token, $batchUuid, $userId);
		if ($batchPath === null) {
			self::redirect(array('draft' => $token, 'wla_import_error' => 'source_promote_failed'));
		}

		$repository = new BatchRepository();
		$created = $repository->create(
			$profile->sourceKey(),
			$sourceHash,
			$profileJson,
			(int) ($state['total_rows'] ?? 0),
			$userId,
			$batchUuid
		);

		if ($created === null) {
			Workspace::restorePromoted($token, $batchUuid);
			self::redirect(array('draft' => $token, 'wla_import_error' => 'batch_create_failed'));
		}

		$revision = 0;
		foreach (array(BatchStatus::MAPPED, BatchStatus::VALIDATED, BatchStatus::DRY_RUN_READY, BatchStatus::CONFIRMED) as $status) {
			if (!$repository->transition($batchUuid, $status, $revision)) {
				self::redirect(array('batch' => $batchUuid, 'wla_import_error' => 'batch_confirm_failed'));
			}
			++$revision;
		}

		Workspace::deleteDraft($token, false);
		self::redirect(array('batch' => $batchUuid, 'wla_import_notice' => 'batch_confirmed'));
	}

	public static function handleRun(): void
	{
		self::authorize();
		check_admin_referer(self::NONCE_RUN);

		$batchUuid = self::postScalar('batch_uuid');
		$repository = new BatchRepository();
		$batch = $repository->find($batchUuid);
		if ($batch === null || !self::canAccessBatch($batch)) {
			self::redirect(array('wla_import_error' => 'batch_not_found'));
		}

		if (!in_array((string) $batch['status'], array(BatchStatus::CONFIRMED, BatchStatus::PROCESSING, BatchStatus::PAUSED, BatchStatus::FAILED), true)) {
			self::redirect(array('batch' => $batchUuid, 'wla_import_error' => 'batch_not_runnable'));
		}

		$path = Workspace::batchSourcePath($batchUuid);
		if ($path === null) {
			self::redirect(array('batch' => $batchUuid, 'wla_import_error' => 'source_unreadable'));
		}

		$result = (new BatchRunner())->run($batchUuid, $path, 25, 4.0);
		if (in_array($result->status(), array(BatchRunResult::STATUS_COMPLETED, BatchRunResult::STATUS_ALREADY_COMPLETED), true)) {
			Workspace::deleteBatchSource($batchUuid);
		}

		$args = array(
			'batch' => $batchUuid,
			'wla_import_notice' => 'run_' . sanitize_key($result->status()),
		);
		if (!$result->isSuccessful() && $result->reason() !== null) {
			$args['wla_import_error'] = sanitize_key($result->reason());
			unset($args['wla_import_notice']);
		}
		self::redirect($args);
	}

	public static function handleCancel(): void
	{
		self::authorize();
		check_admin_referer(self::NONCE_CANCEL);

		$batchUuid = self::postScalar('batch_uuid');
		$repository = new BatchRepository();
		$batch = $repository->find($batchUuid);
		if ($batch === null || !self::canAccessBatch($batch)) {
			self::redirect(array('wla_import_error' => 'batch_not_found'));
		}

		$status = (string) $batch['status'];
		if (!in_array($status, array(BatchStatus::CONFIRMED, BatchStatus::PAUSED, BatchStatus::FAILED), true)) {
			self::redirect(array('batch' => $batchUuid, 'wla_import_error' => 'unsafe_cancel_state'));
		}

		if (!$repository->transition($batchUuid, BatchStatus::CANCELLED, (int) $batch['revision'])) {
			self::redirect(array('batch' => $batchUuid, 'wla_import_error' => 'cancel_conflict'));
		}

		Workspace::deleteBatchSource($batchUuid);
		self::redirect(array('batch' => $batchUuid, 'wla_import_notice' => 'batch_cancelled'));
	}

	public static function handleDiscard(): void
	{
		self::authorize();
		check_admin_referer(self::NONCE_DISCARD);

		$token = self::postScalar('draft_token');
		if (Workspace::loadDraft($token, get_current_user_id()) !== null) {
			Workspace::deleteDraft($token, true);
		}
		self::redirect(array('wla_import_notice' => 'draft_discarded'));
	}

	/** @param array<string,mixed>|null $draft @param array<string,mixed>|null $batch */
	private static function renderSteps(?array $draft, ?array $batch): void
	{
		$active = 1;
		if ($draft !== null) {
			$active = empty($draft['dry_run']) ? 2 : 4;
		}
		if ($batch !== null) {
			$active = in_array((string) $batch['status'], array(BatchStatus::COMPLETED, BatchStatus::CANCELLED), true) ? 7 : 6;
		}

		$steps = array('Subir', 'Mapear', 'Validar', 'Simular', 'Confirmar', 'Procesar', 'Informe');
		echo '<ol class="wla-inmo-import__steps" aria-label="' . esc_attr__('Etapas de importación', 'wla-inmo') . '">';
		foreach ($steps as $index => $label) {
			$number = $index + 1;
			$class = $number < $active ? ' is-complete' : ($number === $active ? ' is-current' : '');
			echo '<li class="wla-inmo-import__step' . esc_attr($class) . '"><span>' . esc_html((string) $number) . '</span>' . esc_html__($label, 'wla-inmo') . '</li>';
		}
		echo '</ol>';
	}

	private static function renderUpload(): void
	{
		echo '<section class="wla-inmo-admin__panel wla-inmo-import__panel">';
		echo '<h2>' . esc_html__('Nueva importación CSV', 'wla-inmo') . '</h2>';
		echo '<p>' . esc_html__('Sube un CSV UTF-8. Primero se revisa y simula: ninguna propiedad se crea o modifica hasta que confirmes un dry-run sin errores.', 'wla-inmo') . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::UPLOAD_ACTION) . '">';
		wp_nonce_field(self::NONCE_UPLOAD);
		echo '<p><label for="wla-import-file"><strong>' . esc_html__('Archivo CSV', 'wla-inmo') . '</strong></label><br>';
		echo '<input id="wla-import-file" type="file" name="wla_import_file" accept=".csv,text/csv" required></p>';
		echo '<p class="description">' . esc_html(sprintf(__('Máximo %1$s MB y %2$s filas en esta etapa.', 'wla-inmo'), number_format_i18n(Workspace::maxUploadBytes() / 1048576, 0), number_format_i18n(Workspace::maxRows()))) . '</p>';
		echo '<button type="submit" class="button button-primary">' . esc_html__('Subir y revisar', 'wla-inmo') . '</button>';
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

		echo '<section class="wla-inmo-admin__grid">';
		self::metricCard(__('Archivo', 'wla-inmo'), (string) ($state['original_name'] ?? 'CSV'));
		self::metricCard(__('Filas', 'wla-inmo'), number_format_i18n((int) ($state['total_rows'] ?? 0)));
		self::metricCard(__('Columnas', 'wla-inmo'), number_format_i18n(count(self::stateHeaders($state))));
		echo '</section>';

		if ($preview !== null) {
			self::renderPreview($preview);
		}
		self::renderMappingForm($state, $profile);

		if (!empty($state['dry_run']) && is_array($state['dry_run'])) {
			self::renderDryRun($state);
		}

		echo '<section class="wla-inmo-import__secondary">';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::DISCARD_ACTION) . '">';
		echo '<input type="hidden" name="draft_token" value="' . esc_attr($token) . '">';
		wp_nonce_field(self::NONCE_DISCARD);
		echo '<button type="submit" class="button">' . esc_html__('Descartar carga', 'wla-inmo') . '</button>';
		echo '</form></section>';
	}

	/** @param array{headers:array<int,string>,rows:array<int,array<string,string>>} $preview */
	private static function renderPreview(array $preview): void
	{
		echo '<section class="wla-inmo-admin__panel wla-inmo-import__panel">';
		echo '<h2>' . esc_html__('Vista previa', 'wla-inmo') . '</h2>';
		echo '<p class="description">' . esc_html__('Solo se muestran unas pocas filas en pantalla. La importación completa permanece en el archivo temporal y no se copia al navegador.', 'wla-inmo') . '</p>';
		echo '<div class="table-responsive"><table class="widefat striped"><thead><tr>';
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
	private static function renderMappingForm(array $state, ?MappingProfile $profile): void
	{
		$headers = self::stateHeaders($state);
		$currentMapping = $profile !== null ? $profile->mapping() : array();
		$currentSeparators = $profile !== null ? $profile->separators() : array();
		$sourceKey = $profile !== null ? $profile->sourceKey() : 'carga_manual';
		$emptyPolicy = $profile !== null ? $profile->emptyPolicy() : MappingProfile::EMPTY_PRESERVE;
		$targets = TargetRegistry::definitions();

		echo '<section class="wla-inmo-admin__panel wla-inmo-import__panel">';
		echo '<h2>' . esc_html__('Mapear columnas y simular', 'wla-inmo') . '</h2>';
		echo '<p>' . esc_html__('Relaciona cada columna con un campo de WLA Inmo. Debes incluir al menos un identificador: código de propiedad o ID externo. Para propiedades nuevas también se necesita título.', 'wla-inmo') . '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::MAP_ACTION) . '">';
		echo '<input type="hidden" name="draft_token" value="' . esc_attr((string) $state['token']) . '">';
		wp_nonce_field(self::NONCE_MAP);
		echo '<div class="wla-inmo-import__settings">';
		echo '<p><label for="wla-source-key"><strong>' . esc_html__('Origen', 'wla-inmo') . '</strong></label><br><input id="wla-source-key" type="text" name="source_key" value="' . esc_attr($sourceKey) . '" pattern="[A-Za-z0-9._-]+" required></p>';
		echo '<p><label for="wla-empty-policy"><strong>' . esc_html__('Si una celda está vacía', 'wla-inmo') . '</strong></label><br><select id="wla-empty-policy" name="empty_policy">';
		echo '<option value="preserve"' . selected($emptyPolicy, MappingProfile::EMPTY_PRESERVE, false) . '>' . esc_html__('Conservar el valor existente (recomendado)', 'wla-inmo') . '</option>';
		echo '<option value="clear"' . selected($emptyPolicy, MappingProfile::EMPTY_CLEAR, false) . '>' . esc_html__('Borrar el valor existente', 'wla-inmo') . '</option>';
		echo '</select></p></div>';

		echo '<div class="table-responsive"><table class="widefat striped wla-inmo-import__mapping"><thead><tr><th>' . esc_html__('Columna CSV', 'wla-inmo') . '</th><th>' . esc_html__('Campo WLA Inmo', 'wla-inmo') . '</th><th>' . esc_html__('Separador múltiple', 'wla-inmo') . '</th></tr></thead><tbody>';
		foreach ($headers as $index => $header) {
			$current = $currentMapping[$header] ?? self::suggestTarget($header);
			echo '<tr><th scope="row">' . esc_html($header) . '</th><td><select name="wla_mapping[' . esc_attr((string) $index) . ']">';
			echo '<option value="">' . esc_html__('Ignorar columna', 'wla-inmo') . '</option>';
			foreach ($targets as $target => $definition) {
				$label = self::targetLabel($target);
				if (!empty($definition['private'])) {
					$label .= ' — ' . __('privado', 'wla-inmo');
				}
				echo '<option value="' . esc_attr($target) . '"' . selected($current, $target, false) . '>' . esc_html($label) . '</option>';
			}
			echo '</select></td><td><input type="text" maxlength="8" size="8" name="wla_separator[' . esc_attr((string) $index) . ']" value="' . esc_attr((string) ($currentSeparators[$header] ?? '')) . '" aria-label="' . esc_attr(sprintf(__('Separador para %s', 'wla-inmo'), $header)) . '"></td></tr>';
		}
		echo '</tbody></table></div>';
		echo '<p><button type="submit" class="button button-primary">' . esc_html__('Validar y simular', 'wla-inmo') . '</button></p>';
		echo '</form></section>';
	}

	/** @param array<string,mixed> $state */
	private static function renderDryRun(array $state): void
	{
		$dryRun = $state['dry_run'];
		$counts = isset($dryRun['counts']) && is_array($dryRun['counts']) ? $dryRun['counts'] : array();
		$errorCount = (int) ($counts['error'] ?? 0);
		$expired = (int) ($dryRun['expires_at'] ?? 0) < time();

		echo '<section class="wla-inmo-admin__panel wla-inmo-import__panel">';
		echo '<h2>' . esc_html__('Resultado de la simulación', 'wla-inmo') . '</h2>';
		echo '<div class="wla-inmo-admin__grid">';
		self::metricCard(__('Nuevas', 'wla-inmo'), number_format_i18n((int) ($counts['new'] ?? 0)));
		self::metricCard(__('Actualizaciones', 'wla-inmo'), number_format_i18n((int) ($counts['update'] ?? 0)));
		self::metricCard(__('Advertencias', 'wla-inmo'), number_format_i18n((int) ($counts['warnings'] ?? 0)));
		self::metricCard(__('Errores', 'wla-inmo'), number_format_i18n($errorCount));
		echo '</div>';

		if ($expired) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__('Esta simulación venció. Vuelve a validar antes de confirmar para evitar usar un estado antiguo.', 'wla-inmo') . '</p></div>';
		} elseif ($errorCount > 0) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__('Corrige el archivo o el mapping y vuelve a simular. No se puede confirmar mientras existan errores.', 'wla-inmo') . '</p></div>';
		} else {
			echo '<div class="notice notice-success inline"><p>' . esc_html__('La simulación no encontró errores bloqueantes. Confirmar congelará este archivo y este mapping antes de procesar.', 'wla-inmo') . '</p></div>';
		}

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
			if ((int) ($dryRun['issue_count'] ?? 0) > count($issues)) {
				echo '<p class="description">' . esc_html(sprintf(__('Se muestran los primeros %d hallazgos; el resumen mantiene el conteo total.', 'wla-inmo'), count($issues))) . '</p>';
			}
		}

		if (!$expired && $errorCount === 0) {
			echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr(self::CONFIRM_ACTION) . '">';
			echo '<input type="hidden" name="draft_token" value="' . esc_attr((string) $state['token']) . '">';
			wp_nonce_field(self::NONCE_CONFIRM);
			echo '<p><button type="submit" class="button button-primary">' . esc_html__('Confirmar importación', 'wla-inmo') . '</button></p>';
			echo '</form>';
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

		echo '<section class="wla-inmo-admin__panel wla-inmo-import__panel">';
		echo '<div class="wla-inmo-import__batch-heading"><div><p class="wla-inmo-admin__eyebrow">' . esc_html__('Batch', 'wla-inmo') . '</p><h2>' . esc_html(self::statusLabel($status)) . '</h2></div><code>' . esc_html((string) $batch['batch_uuid']) . '</code></div>';
		echo '<div class="wla-inmo-import__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr((string) $percent) . '"><span style="width:' . esc_attr((string) $percent) . '%"></span></div>';
		echo '<p>' . esc_html(sprintf(__('%1$s de %2$s filas procesadas (%3$d%%).', 'wla-inmo'), number_format_i18n($processed), number_format_i18n($total), $percent)) . '</p>';

		echo '<div class="wla-inmo-admin__grid">';
		self::metricCard(__('Creadas', 'wla-inmo'), number_format_i18n((int) $batch['created_count']));
		self::metricCard(__('Actualizadas', 'wla-inmo'), number_format_i18n((int) $batch['updated_count']));
		self::metricCard(__('Omitidas', 'wla-inmo'), number_format_i18n((int) $batch['skipped_count']));
		self::metricCard(__('Errores', 'wla-inmo'), number_format_i18n((int) $batch['error_count']));
		echo '</div>';

		if (in_array($status, array(BatchStatus::CONFIRMED, BatchStatus::PROCESSING, BatchStatus::PAUSED, BatchStatus::FAILED), true)) {
			echo '<form class="wla-inmo-import__inline-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr(self::RUN_ACTION) . '"><input type="hidden" name="batch_uuid" value="' . esc_attr((string) $batch['batch_uuid']) . '">';
			wp_nonce_field(self::NONCE_RUN);
			echo '<button type="submit" class="button button-primary">' . esc_html($processed > 0 ? __('Continuar / reanudar', 'wla-inmo') : __('Iniciar procesamiento', 'wla-inmo')) . '</button></form>';
		}

		if (in_array($status, array(BatchStatus::CONFIRMED, BatchStatus::PAUSED, BatchStatus::FAILED), true)) {
			echo '<form class="wla-inmo-import__inline-form" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr(self::CANCEL_ACTION) . '"><input type="hidden" name="batch_uuid" value="' . esc_attr((string) $batch['batch_uuid']) . '">';
			wp_nonce_field(self::NONCE_CANCEL);
			echo '<button type="submit" class="button">' . esc_html__('Cancelar en este checkpoint', 'wla-inmo') . '</button></form>';
		}

		if ($status === BatchStatus::COMPLETED) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__('Importación completada. El archivo temporal de ejecución fue eliminado.', 'wla-inmo') . '</p></div>';
		} elseif ($status === BatchStatus::CANCELLED) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__('Importación cancelada en un checkpoint seguro. No se procesarán más filas.', 'wla-inmo') . '</p></div>';
		} elseif ($status === BatchStatus::FAILED) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__('El batch se detuvo. Revisa el código de error mostrado arriba y reanuda únicamente cuando la causa esté resuelta.', 'wla-inmo') . '</p></div>';
		}
		echo '</section>';
	}

	private static function renderHistory(): void
	{
		$currentUser = get_current_user_id();
		$canSeeAll = current_user_can(AccessCapabilities::MANAGE_TOOLS);
		$createdBy = $canSeeAll ? null : $currentUser;
		$status = self::queryArg('wla_batch_status');
		if ($status !== '' && !BatchStatus::isValid($status)) {
			$status = '';
		}
		$page = max(1, absint(self::queryArg('wla_history_page')));
		$offset = ($page - 1) * self::HISTORY_PAGE_SIZE;
		$history = new BatchHistoryRepository();
		$rows = $history->recent(self::HISTORY_PAGE_SIZE, $offset, $createdBy, $status !== '' ? $status : null);
		$total = $history->count($createdBy, $status !== '' ? $status : null);

		echo '<section class="wla-inmo-admin__panel wla-inmo-import__panel">';
		echo '<h2>' . esc_html__('Historial de importaciones', 'wla-inmo') . '</h2>';
		echo '<form class="wla-inmo-import__filters" method="get" action="' . esc_url(admin_url('admin.php')) . '"><input type="hidden" name="page" value="wla-inmo-import-export"><label for="wla-batch-status">' . esc_html__('Estado', 'wla-inmo') . '</label><select id="wla-batch-status" name="wla_batch_status"><option value="">' . esc_html__('Todos', 'wla-inmo') . '</option>';
		foreach (BatchStatus::all() as $candidate) {
			echo '<option value="' . esc_attr($candidate) . '"' . selected($status, $candidate, false) . '>' . esc_html(self::statusLabel($candidate)) . '</option>';
		}
		echo '</select><button class="button" type="submit">' . esc_html__('Filtrar', 'wla-inmo') . '</button></form>';

		if ($rows === array()) {
			echo '<p>' . esc_html__('Todavía no hay batches para este filtro.', 'wla-inmo') . '</p>';
		} else {
			echo '<div class="table-responsive"><table class="widefat striped"><thead><tr><th>' . esc_html__('Fecha', 'wla-inmo') . '</th><th>' . esc_html__('Origen', 'wla-inmo') . '</th><th>' . esc_html__('Estado', 'wla-inmo') . '</th><th>' . esc_html__('Progreso', 'wla-inmo') . '</th><th>' . esc_html__('Resultado', 'wla-inmo') . '</th><th>' . esc_html__('Acción', 'wla-inmo') . '</th></tr></thead><tbody>';
			foreach ($rows as $row) {
				$totalRows = max(0, (int) $row['total_rows']);
				$processed = max(0, (int) $row['processed_rows']);
				$url = add_query_arg(array('page' => 'wla-inmo-import-export', 'batch' => (string) $row['batch_uuid']), admin_url('admin.php'));
				echo '<tr><td>' . esc_html(self::displayDate((string) $row['created_at'])) . '</td><td><code>' . esc_html((string) $row['source_key']) . '</code></td><td>' . esc_html(self::statusLabel((string) $row['status'])) . '</td><td>' . esc_html(number_format_i18n($processed) . ' / ' . number_format_i18n($totalRows)) . '</td><td>' . esc_html(sprintf(__('C:%1$d · A:%2$d · E:%3$d', 'wla-inmo'), (int) $row['created_count'], (int) $row['updated_count'], (int) $row['error_count'])) . '</td><td><a class="button button-small" href="' . esc_url($url) . '">' . esc_html__('Ver', 'wla-inmo') . '</a></td></tr>';
			}
			echo '</tbody></table></div>';
		}

		$pages = max(1, (int) ceil($total / self::HISTORY_PAGE_SIZE));
		if ($pages > 1) {
			echo '<nav class="wla-inmo-import__pagination" aria-label="' . esc_attr__('Paginación del historial', 'wla-inmo') . '">';
			if ($page > 1) {
				echo '<a class="button" href="' . esc_url(self::historyUrl($page - 1, $status)) . '">' . esc_html__('Anterior', 'wla-inmo') . '</a>';
			}
			echo '<span>' . esc_html(sprintf(__('Página %1$d de %2$d', 'wla-inmo'), $page, $pages)) . '</span>';
			if ($page < $pages) {
				echo '<a class="button" href="' . esc_url(self::historyUrl($page + 1, $status)) . '">' . esc_html__('Siguiente', 'wla-inmo') . '</a>';
			}
			echo '</nav>';
		}
		echo '</section>';
	}

	/** @param array<int,array<string,mixed>> $issues @param array<int,array{code:string,target:string}> $messages */
	private static function collectIssues(array &$issues, DryRunResult $result, string $kind, array $messages): void
	{
		foreach ($messages as $message) {
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
		return current_user_can(AccessCapabilities::MANAGE_TOOLS)
			|| (int) ($batch['created_by'] ?? 0) === get_current_user_id();
	}

	private static function authorize(): void
	{
		if (!current_user_can(AccessCapabilities::IMPORT_PROPERTIES)) {
			wp_die(
				esc_html__('No tienes permisos para importar propiedades.', 'wla-inmo'),
				esc_html__('Acceso denegado', 'wla-inmo'),
				array('response' => 403)
			);
		}
	}

	/** @param array<string,string> $args */
	private static function redirect(array $args): never
	{
		$url = add_query_arg(array_merge(array('page' => 'wla-inmo-import-export'), $args), admin_url('admin.php'));
		wp_safe_redirect($url);
		exit;
	}

	private static function queryArg(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation/filter state, sanitized immediately.
		$value = isset($_GET[$key]) && is_scalar($_GET[$key]) ? wp_unslash((string) $_GET[$key]) : '';

		return sanitize_text_field($value);
	}

	private static function postScalar(string $key): string
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callers verify the action nonce before reading request fields.
		$value = isset($_POST[$key]) && is_scalar($_POST[$key]) ? wp_unslash((string) $_POST[$key]) : '';

		return sanitize_text_field($value);
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
		if (function_exists('mb_substr')) {
			return mb_substr($value, 0, 120);
		}

		return substr($value, 0, 120);
	}

	private static function suggestTarget(string $header): string
	{
		$key = strtolower(trim($header));
		$aliases = array(
			'titulo' => 'post.title',
			'título' => 'post.title',
			'title' => 'post.title',
			'descripcion' => 'post.content',
			'descripción' => 'post.content',
			'description' => 'post.content',
			'codigo' => 'meta.property_code',
			'código' => 'meta.property_code',
			'property_code' => 'meta.property_code',
			'external_id' => 'meta.external_id',
			'id_externo' => 'meta.external_id',
			'precio' => 'meta.price_clp',
			'price' => 'meta.price_clp',
			'operacion' => 'taxonomy.operation',
			'operación' => 'taxonomy.operation',
			'tipo' => 'taxonomy.property_type',
			'region' => 'taxonomy.region',
			'región' => 'taxonomy.region',
			'comuna' => 'taxonomy.commune',
			'sector' => 'taxonomy.sector',
		);

		return $aliases[$key] ?? '';
	}

	private static function targetLabel(string $target): string
	{
		$labels = array(
			'post.title' => __('Título', 'wla-inmo'),
			'post.content' => __('Descripción', 'wla-inmo'),
			'post.excerpt' => __('Extracto', 'wla-inmo'),
			'meta.property_code' => __('Código de propiedad', 'wla-inmo'),
			'meta.external_id' => __('ID externo', 'wla-inmo'),
			'meta.price_clp' => __('Precio CLP', 'wla-inmo'),
			'meta.price_uf' => __('Precio UF', 'wla-inmo'),
			'meta.price_usd' => __('Precio USD', 'wla-inmo'),
			'taxonomy.operation' => __('Operación', 'wla-inmo'),
			'taxonomy.property_type' => __('Tipo de propiedad', 'wla-inmo'),
			'taxonomy.region' => __('Región', 'wla-inmo'),
			'taxonomy.commune' => __('Comuna', 'wla-inmo'),
			'taxonomy.sector' => __('Sector', 'wla-inmo'),
			'taxonomy.feature' => __('Características', 'wla-inmo'),
		);
		if (isset($labels[$target])) {
			return $labels[$target];
		}

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
			BatchStatus::ROLLED_BACK => __('Revertido', 'wla-inmo'),
			BatchStatus::ROLLBACK_BLOCKED => __('Rollback bloqueado', 'wla-inmo'),
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
		if ($timestamp === false) {
			return $value;
		}

		return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
	}

	private static function historyUrl(int $page, string $status): string
	{
		$args = array('page' => 'wla-inmo-import-export', 'wla_history_page' => max(1, $page));
		if ($status !== '') {
			$args['wla_batch_status'] = $status;
		}

		return add_query_arg($args, admin_url('admin.php'));
	}

	private static function renderNotice(): void
	{
		$success = self::queryArg('wla_import_notice');
		$error = self::queryArg('wla_import_error');
		if ($error !== '') {
			echo '<div class="notice notice-error inline is-dismissible"><p>' . esc_html(self::message($error)) . '</p></div>';
			return;
		}
		if ($success !== '') {
			echo '<div class="notice notice-success inline is-dismissible"><p>' . esc_html(self::message($success)) . '</p></div>';
		}
	}

	private static function message(string $code): string
	{
		$messages = array(
			'upload_ready' => __('Archivo recibido. Revisa columnas y mapping antes de simular.', 'wla-inmo'),
			'dry_run_ready' => __('Simulación actualizada.', 'wla-inmo'),
			'batch_confirmed' => __('Importación confirmada. Ya puedes iniciar el procesamiento por lotes.', 'wla-inmo'),
			'run_paused' => __('Se procesó un lote y el batch quedó pausado en un checkpoint seguro.', 'wla-inmo'),
			'run_completed' => __('Importación completada.', 'wla-inmo'),
			'run_already_completed' => __('Este batch ya estaba completado.', 'wla-inmo'),
			'batch_cancelled' => __('Importación cancelada en un checkpoint seguro.', 'wla-inmo'),
			'draft_discarded' => __('Carga descartada y archivo temporal eliminado.', 'wla-inmo'),
			'file_too_large' => __('El archivo supera el tamaño permitido para esta etapa.', 'wla-inmo'),
			'invalid_extension' => __('Solo se aceptan archivos CSV en esta etapa.', 'wla-inmo'),
			'invalid_mime' => __('El tipo de archivo no coincide con un CSV permitido.', 'wla-inmo'),
			'empty_csv' => __('El CSV debe contener encabezados y al menos una fila de datos.', 'wla-inmo'),
			'draft_expired' => __('La carga temporal venció o ya no está disponible. Vuelve a subir el CSV.', 'wla-inmo'),
			'dry_run_expired' => __('La simulación venció. Vuelve a validar antes de confirmar.', 'wla-inmo'),
			'dry_run_has_errors' => __('La simulación contiene errores bloqueantes y no puede confirmarse.', 'wla-inmo'),
			'source_hash_mismatch' => __('El archivo cambió después de ser revisado. La operación fue bloqueada.', 'wla-inmo'),
			'source_unreadable' => __('El archivo temporal ya no está disponible para continuar.', 'wla-inmo'),
			'invalid_source_key' => __('El identificador de origen no es válido. Usa letras, números, punto, guion o guion bajo.', 'wla-inmo'),
			'unknown_target' => __('El mapping incluye un campo no permitido.', 'wla-inmo'),
			'missing_identity' => __('Cada fila debe poder resolverse por código de propiedad o ID externo.', 'wla-inmo'),
			'batch_not_found' => __('No se encontró un batch accesible para tu usuario.', 'wla-inmo'),
			'batch_not_runnable' => __('El estado actual del batch no permite procesarlo.', 'wla-inmo'),
			'unsafe_cancel_state' => __('Solo se puede cancelar desde un checkpoint seguro.', 'wla-inmo'),
			'checkpoint_conflict' => __('Otro proceso avanzó el batch. Recarga el estado antes de continuar.', 'wla-inmo'),
			'cancel_conflict' => __('El estado cambió antes de cancelar. Recarga el batch.', 'wla-inmo'),
			'row_validation_failed' => __('Una fila dejó de ser válida durante la ejecución. El cursor no avanzó.', 'wla-inmo'),
			'row_execution_failed' => __('Una fila no pudo persistirse. El cursor no avanzó.', 'wla-inmo'),
			'source_row_count_mismatch' => __('La cantidad de filas ya no coincide con el archivo confirmado.', 'wla-inmo'),
		);

		return $messages[$code] ?? __('La operación no pudo completarse con el estado actual. Revisa el archivo y vuelve a intentarlo.', 'wla-inmo');
	}
}
