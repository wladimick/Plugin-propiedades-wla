<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Import\BatchRepository;
use WLA\Inmo\Import\BatchStatus;
use WLA\Inmo\Import\RollbackJournalRepository;
use WLA\Inmo\Import\RollbackJournalState;
use WLA\Inmo\Import\RollbackPreviewService;
use WLA\Inmo\Import\RollbackRunResult;
use WLA\Inmo\Import\RollbackService;

final class RollbackAdmin
{
	private const PREVIEW_ACTION = 'wla_inmo_rollback_preview';
	private const CONFIRM_ACTION = 'wla_inmo_rollback_confirm';
	private const RUN_ACTION = 'wla_inmo_rollback_run';
	private const NONCE_PREVIEW = 'wla_inmo_rollback_preview';
	private const NONCE_CONFIRM = 'wla_inmo_rollback_confirm';
	private const NONCE_RUN = 'wla_inmo_rollback_run';
	private const PREVIEW_TTL = 900;

	public static function register(): void
	{
		add_action('admin_post_' . self::PREVIEW_ACTION, array(self::class, 'handlePreview'));
		add_action('admin_post_' . self::CONFIRM_ACTION, array(self::class, 'handleConfirm'));
		add_action('admin_post_' . self::RUN_ACTION, array(self::class, 'handleRun'));
	}

	public static function renderContextual(): void
	{
		if (!current_user_can(AccessCapabilities::ROLLBACK_IMPORTS)) {
			return;
		}

		$batchUuid = ImportRequest::queryScalar('batch');
		if ($batchUuid === '') {
			return;
		}

		$batch = (new BatchRepository())->find($batchUuid);
		if ($batch === null || !self::canAccessBatch($batch)) {
			return;
		}

		$status = (string) ($batch['status'] ?? '');
		echo '<section class="wla-inmo-admin__panel wla-inmo-import__panel">';
		echo '<p class="wla-inmo-admin__eyebrow">' . esc_html__('Rollback seguro', 'wla-inmo') . '</p>';
		echo '<h2>' . esc_html__('Revertir esta importación', 'wla-inmo') . '</h2>';
		echo '<p>' . esc_html__('WLA solo revierte cambios que todavía puede demostrar que pertenecen a este batch. Si detecta una edición posterior en el alcance importado, bloquea la reversa en lugar de sobrescribirla.', 'wla-inmo') . '</p>';

		if ($status === BatchStatus::COMPLETED) {
			self::renderCompleted($batch);
		} elseif ($status === BatchStatus::ROLLBACK_PROCESSING) {
			self::renderProcessing($batch);
		} elseif ($status === BatchStatus::ROLLED_BACK) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__('Este batch ya fue revertido de forma segura.', 'wla-inmo') . '</p></div>';
		} elseif ($status === BatchStatus::ROLLBACK_BLOCKED) {
			self::renderBlocked($batchUuid);
		} else {
			echo '<p class="description">' . esc_html__('El rollback solo está disponible para importaciones completadas.', 'wla-inmo') . '</p>';
		}

		echo '</section>';
	}

	public static function handlePreview(): void
	{
		self::authorize();
		check_admin_referer(self::NONCE_PREVIEW);

		$batchUuid = ImportRequest::postScalar('batch_uuid');
		$batch = self::accessibleBatch($batchUuid);
		if ($batch === null) {
			self::redirect($batchUuid, 'rollback_batch_not_found', true);
		}

		$preview = (new RollbackPreviewService())->preview($batchUuid);
		set_transient(
			self::previewKey($batchUuid),
			array(
				'preview'    => $preview->toArray(),
				'expires_at' => time() + self::PREVIEW_TTL,
			),
			self::PREVIEW_TTL
		);

		self::redirect($batchUuid, 'rollback_preview_ready', false, $batch);
	}

	public static function handleConfirm(): void
	{
		self::authorize();
		check_admin_referer(self::NONCE_CONFIRM);

		$batchUuid = ImportRequest::postScalar('batch_uuid');
		$batch = self::accessibleBatch($batchUuid);
		if ($batch === null) {
			self::redirect($batchUuid, 'rollback_batch_not_found', true);
		}

		$expectedRevision = absint(ImportRequest::postScalar('rollback_revision'));
		$expectedHash = strtolower(ImportRequest::postScalar('rollback_preview_hash'));
		$service = new RollbackService();
		$begin = $service->begin($batchUuid, $expectedRevision, $expectedHash);
		if ($begin->status() !== RollbackRunResult::STARTED) {
			self::redirect($batchUuid, $begin->reason() !== '' ? $begin->reason() : 'rollback_start_failed', true, $batch);
		}

		delete_transient(self::previewKey($batchUuid));
		$result = $service->run($batchUuid, 25, 5.0);
		self::redirectResult($batchUuid, $result, $batch);
	}

	public static function handleRun(): void
	{
		self::authorize();
		check_admin_referer(self::NONCE_RUN);

		$batchUuid = ImportRequest::postScalar('batch_uuid');
		$batch = self::accessibleBatch($batchUuid);
		if ($batch === null) {
			self::redirect($batchUuid, 'rollback_batch_not_found', true);
		}

		$result = (new RollbackService())->run($batchUuid, 25, 5.0);
		self::redirectResult($batchUuid, $result, $batch);
	}

	/** @param array<string,mixed> $batch */
	private static function renderCompleted(array $batch): void
	{
		$batchUuid = (string) $batch['batch_uuid'];
		$stored = get_transient(self::previewKey($batchUuid));
		$preview = is_array($stored) && isset($stored['preview']) && is_array($stored['preview'])
			? $stored['preview']
			: null;

		if (
			$preview === null
			|| (int) ($stored['expires_at'] ?? 0) < time()
			|| (int) ($preview['revision'] ?? -1) !== (int) $batch['revision']
		) {
			delete_transient(self::previewKey($batchUuid));
			echo '<p class="description">' . esc_html__('Primero ejecuta una previsualización. Es de solo lectura y comprueba todas las filas antes de habilitar cualquier acción destructiva.', 'wla-inmo') . '</p>';
			self::previewForm($batchUuid);
			return;
		}

		echo '<div class="wla-inmo-admin__grid">';
		self::metric(__('Seguras', 'wla-inmo'), (int) ($preview['safe'] ?? 0));
		self::metric(__('Sin acción', 'wla-inmo'), (int) ($preview['noop'] ?? 0));
		self::metric(__('Bloqueadas', 'wla-inmo'), (int) ($preview['blocked'] ?? 0));
		self::metric(__('Errores', 'wla-inmo'), (int) ($preview['errors'] ?? 0));
		echo '</div>';
		echo '<p>' . esc_html(sprintf(
			/* translators: 1: created properties to delete, 2: updated properties to restore. */
			__('La reversa eliminaría %1$d propiedades creadas por el batch y restauraría %2$d propiedades actualizadas.', 'wla-inmo'),
			(int) ($preview['created_to_delete'] ?? 0),
			(int) ($preview['updates_to_restore'] ?? 0)
		)) . '</p>';

		self::renderReasons(isset($preview['reasons']) && is_array($preview['reasons']) ? $preview['reasons'] : array());

		if (empty($preview['can_confirm'])) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__('La previsualización detectó condiciones que impiden un rollback seguro. No se habilita la confirmación.', 'wla-inmo') . '</p></div>';
			self::previewForm($batchUuid, __('Volver a comprobar', 'wla-inmo'));
			return;
		}

		echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__('Acción destructiva.', 'wla-inmo') . '</strong> ' . esc_html__('La confirmación vuelve a calcular el estado completo antes de iniciar. Si algo cambió desde esta vista, se bloquea automáticamente.', 'wla-inmo') . '</p></div>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::CONFIRM_ACTION) . '">';
		echo '<input type="hidden" name="batch_uuid" value="' . esc_attr($batchUuid) . '">';
		echo '<input type="hidden" name="rollback_revision" value="' . esc_attr((string) (int) $preview['revision']) . '">';
		echo '<input type="hidden" name="rollback_preview_hash" value="' . esc_attr((string) $preview['preview_hash']) . '">';
		wp_nonce_field(self::NONCE_CONFIRM);
		echo '<button type="submit" class="button button-primary">' . esc_html__('Confirmar e iniciar rollback seguro', 'wla-inmo') . '</button>';
		echo '</form>';
	}

	/** @param array<string,mixed> $batch */
	private static function renderProcessing(array $batch): void
	{
		$batchUuid = (string) $batch['batch_uuid'];
		$journal = new RollbackJournalRepository();
		$pending = $journal->countRollbackStatus($batchUuid, RollbackJournalState::ROLLBACK_PENDING);
		$done = $journal->countRollbackStatus($batchUuid, RollbackJournalState::ROLLBACK_ROLLED_BACK);

		echo '<div class="wla-inmo-admin__grid">';
		self::metric(__('Revertidas', 'wla-inmo'), $done);
		self::metric(__('Pendientes', 'wla-inmo'), $pending);
		echo '</div>';
		echo '<p class="description">' . esc_html__('El rollback está en curso por lotes pequeños. Cada fila se vuelve a validar inmediatamente antes de tocar WordPress.', 'wla-inmo') . '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::RUN_ACTION) . '">';
		echo '<input type="hidden" name="batch_uuid" value="' . esc_attr($batchUuid) . '">';
		wp_nonce_field(self::NONCE_RUN);
		echo '<button type="submit" class="button button-primary">' . esc_html__('Continuar rollback', 'wla-inmo') . '</button>';
		echo '</form>';
	}

	private static function renderBlocked(string $batchUuid): void
	{
		$journal = new RollbackJournalRepository();
		$blocked = $journal->countRollbackStatus($batchUuid, RollbackJournalState::ROLLBACK_BLOCKED);
		$errors = $journal->countRollbackStatus($batchUuid, RollbackJournalState::ROLLBACK_ERROR);
		echo '<div class="notice notice-error inline"><p>' . esc_html__('El rollback se detuvo porque WLA ya no puede demostrar que todas las filas sean seguras. No se sobrescribirán cambios posteriores.', 'wla-inmo') . '</p></div>';
		echo '<p>' . esc_html(sprintf(
			/* translators: 1: blocked rows, 2: error rows. */
			__('Filas bloqueadas: %1$d · errores técnicos: %2$d.', 'wla-inmo'),
			$blocked,
			$errors
		)) . '</p>';
	}

	private static function previewForm(string $batchUuid, ?string $label = null): void
	{
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::PREVIEW_ACTION) . '">';
		echo '<input type="hidden" name="batch_uuid" value="' . esc_attr($batchUuid) . '">';
		wp_nonce_field(self::NONCE_PREVIEW);
		echo '<button type="submit" class="button">' . esc_html($label ?? __('Previsualizar rollback', 'wla-inmo')) . '</button>';
		echo '</form>';
	}

	/** @param array<string,mixed> $reasons */
	private static function renderReasons(array $reasons): void
	{
		if ($reasons === array()) {
			return;
		}
		echo '<ul class="ul-disc">';
		foreach ($reasons as $reason => $count) {
			$count = max(0, (int) $count);
			if ($count < 1) {
				continue;
			}
			echo '<li><strong>' . esc_html(number_format_i18n($count)) . '</strong> — ' . esc_html(self::reasonLabel((string) $reason)) . '</li>';
		}
		echo '</ul>';
	}

	private static function reasonLabel(string $reason): string
	{
		$labels = array(
			'rollback_journal_incomplete' => __('Este batch no tiene evidencia de rollback completa; importaciones históricas anteriores a 3.11 no se pueden revertir automáticamente.', 'wla-inmo'),
			'rollback_created_property_changed' => __('Una propiedad creada por el batch recibió cambios posteriores.', 'wla-inmo'),
			'rollback_touched_scope_changed' => __('Un campo que el batch había modificado cambió posteriormente.', 'wla-inmo'),
			'rollback_updated_property_missing' => __('Una propiedad actualizada ya no existe.', 'wla-inmo'),
			'rollback_journal_not_ready' => __('El journal de una fila está incompleto.', 'wla-inmo'),
			'rollback_after_snapshot_corrupt' => __('La evidencia posterior de una fila no supera la verificación de integridad.', 'wla-inmo'),
			'rollback_snapshot_scope_mismatch' => __('Los scopes before/after de una fila no coinciden.', 'wla-inmo'),
		);
		return $labels[$reason] ?? __('La fila no puede revertirse de forma demostrablemente segura.', 'wla-inmo');
	}

	private static function metric(string $label, int $value): void
	{
		echo '<article class="wla-inmo-admin__card"><p class="wla-inmo-admin__eyebrow">' . esc_html($label) . '</p><h2>' . esc_html(number_format_i18n(max(0, $value))) . '</h2></article>';
	}

	private static function authorize(): void
	{
		if (!current_user_can(AccessCapabilities::ROLLBACK_IMPORTS)) {
			wp_die(
				esc_html__('No tienes permisos para revertir importaciones.', 'wla-inmo'),
				esc_html__('Acceso denegado', 'wla-inmo'),
				array('response' => 403)
			);
		}
	}

	/** @return array<string,mixed>|null */
	private static function accessibleBatch(string $batchUuid): ?array
	{
		$batch = (new BatchRepository())->find($batchUuid);
		return $batch !== null && self::canAccessBatch($batch) ? $batch : null;
	}

	/** @param array<string,mixed> $batch */
	private static function canAccessBatch(array $batch): bool
	{
		return current_user_can(AccessCapabilities::MANAGE_TOOLS)
			|| (int) ($batch['created_by'] ?? 0) === get_current_user_id();
	}

	private static function previewKey(string $batchUuid): string
	{
		return 'wla_inmo_rb_preview_' . get_current_user_id() . '_' . sha1(strtolower(trim($batchUuid)));
	}

	/** @param array<string,mixed>|null $batch */
	private static function redirect(string $batchUuid, string $code, bool $error, ?array $batch = null): never
	{
		$batch = $batch ?? (new BatchRepository())->find($batchUuid);
		$args = array(
			'page'  => 'wla-inmo-import-export',
			'batch' => $batchUuid,
			$error ? 'wla_import_error' : 'wla_import_notice' => $code,
		);
		$format = is_array($batch) ? (string) ($batch['source_format'] ?? 'csv') : 'csv';
		if (in_array($format, array('json', 'xlsx'), true)) {
			$args['wla_format'] = $format;
		}
		wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
		exit;
	}

	/** @param array<string,mixed> $batch */
	private static function redirectResult(string $batchUuid, RollbackRunResult $result, array $batch): never
	{
		$code = match ($result->status()) {
			RollbackRunResult::PROCESSING => 'rollback_processing',
			RollbackRunResult::ROLLED_BACK, RollbackRunResult::ALREADY_ROLLED_BACK => 'rollback_completed',
			RollbackRunResult::BLOCKED => $result->reason() !== '' ? $result->reason() : 'rollback_blocked',
			RollbackRunResult::CONFLICT => $result->reason() !== '' ? $result->reason() : 'rollback_conflict',
			default => $result->reason() !== '' ? $result->reason() : 'rollback_failed',
		};
		$error = in_array($result->status(), array(RollbackRunResult::BLOCKED, RollbackRunResult::CONFLICT, RollbackRunResult::FAILED), true);
		self::redirect($batchUuid, $code, $error, $batch);
	}
}
