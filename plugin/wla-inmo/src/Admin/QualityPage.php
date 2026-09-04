<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Quality\Evaluator;
use WLA\Inmo\Quality\Rebuilder;
use WLA\Inmo\Quality\Repository as QualityRepository;

final class QualityPage
{
	private const REBUILD_ACTION = 'wla_inmo_rebuild_quality';
	private const REBUILD_NONCE = 'wla_inmo_rebuild_quality';

	public static function register(): void
	{
		add_action('admin_post_' . self::REBUILD_ACTION, array(self::class, 'handleRebuild'));
	}

	public static function render(): void
	{
		$repository = new QualityRepository();
		$summary = $repository->summary();
		$priorityRows = $repository->lowestScores(25);
		$definitions = Evaluator::definitions();

		self::renderRebuildNotice();

		echo '<div class="notice notice-info inline"><p>';
		echo esc_html__('El porcentaje de Calidad es una guía interna de completitud del catálogo. No representa ni promete una posición o factor de ranking en Google.', 'wla-inmo');
		echo '</p></div>';

		echo '<section class="wla-inmo-admin__grid" aria-label="' . esc_attr__('Resumen de calidad', 'wla-inmo') . '">';
		self::renderMetricCard(__('Evaluadas', 'wla-inmo'), (int) $summary['total'], '');
		self::renderMetricCard(__('Completas', 'wla-inmo'), (int) $summary['complete'], 'complete');
		self::renderMetricCard(__('Incompletas', 'wla-inmo'), (int) $summary['incomplete'], 'incomplete');
		self::renderMetricCard(__('Sin precio', 'wla-inmo'), (int) $summary['no_price'], 'no_price');
		self::renderMetricCard(__('Sin imagen principal', 'wla-inmo'), (int) $summary['no_image'], 'no_image');
		echo '</section>';

		echo '<section class="wla-inmo-admin__panel">';
		echo '<h2>' . esc_html__('Prioridad de corrección', 'wla-inmo') . '</h2>';
		echo '<p>' . esc_html__('Se muestran primero las propiedades incompletas con menor porcentaje de completitud. Cada hallazgo indica una acción concreta.', 'wla-inmo') . '</p>';

		if ($priorityRows === array()) {
			echo '<p>' . esc_html__('No hay propiedades incompletas en la proyección actual. Si este sitio ya tenía propiedades antes de activar el módulo, ejecuta una reconstrucción para evaluarlas.', 'wla-inmo') . '</p>';
		} else {
			self::renderPriorityTable($priorityRows, $definitions);
		}

		self::renderRebuildForm();
		echo '</section>';
	}

	public static function handleRebuild(): void
	{
		if (!current_user_can(AccessCapabilities::MANAGE_SETTINGS)) {
			wp_die(
				esc_html__('No tienes permisos para reconstruir la calidad del catálogo.', 'wla-inmo'),
				esc_html__('Acceso denegado', 'wla-inmo'),
				array('response' => 403)
			);
		}

		check_admin_referer(self::REBUILD_NONCE);
		$count = Rebuilder::rebuildAll();
		$url = add_query_arg(
			array(
				'page' => 'wla-inmo-quality',
				'wla_quality_rebuilt' => $count,
			),
			admin_url('admin.php')
		);

		wp_safe_redirect($url);
		exit;
	}

	private static function renderMetricCard(string $label, int $value, string $filter): void
	{
		echo '<article class="wla-inmo-admin__card">';
		echo '<p class="wla-inmo-admin__eyebrow">' . esc_html($label) . '</p>';
		echo '<h2>' . esc_html(number_format_i18n($value)) . '</h2>';
		if ($filter !== '') {
			$url = add_query_arg(
				array(
					'post_type' => PostType::POST_TYPE,
					'wla_quality_filter' => $filter,
				),
				admin_url('edit.php')
			);
			echo '<p><a href="' . esc_url($url) . '">' . esc_html__('Ver propiedades', 'wla-inmo') . '</a></p>';
		}
		echo '</article>';
	}

	/**
	 * @param array<int,array<string,mixed>> $rows Quality rows.
	 * @param array<string,array{label:string,action:string}> $definitions Check definitions.
	 */
	private static function renderPriorityTable(array $rows, array $definitions): void
	{
		echo '<div class="table-responsive"><table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__('Propiedad', 'wla-inmo') . '</th>';
		echo '<th scope="col">' . esc_html__('Calidad', 'wla-inmo') . '</th>';
		echo '<th scope="col">' . esc_html__('Qué falta', 'wla-inmo') . '</th>';
		echo '<th scope="col">' . esc_html__('Acción', 'wla-inmo') . '</th>';
		echo '</tr></thead><tbody>';

		$rendered = 0;
		foreach ($rows as $row) {
			$postId = (int) ($row['property_id'] ?? 0);
			if ($postId < 1 || !current_user_can('edit_post', $postId)) {
				continue;
			}

			$title = get_the_title($postId);
			$title = is_string($title) && $title !== '' ? $title : sprintf(__('Propiedad #%d', 'wla-inmo'), $postId);
			$score = max(0, min(100, (int) ($row['score'] ?? 0)));
			$codes = self::missingCodes((string) ($row['missing_codes'] ?? ''));
			$actions = array();

			foreach ($codes as $code) {
				if (isset($definitions[$code])) {
					$actions[] = (string) $definitions[$code]['action'];
				}
			}

			echo '<tr>';
			echo '<th scope="row">' . esc_html($title) . '</th>';
			echo '<td><strong>' . esc_html($score . '%') . '</strong></td>';
			echo '<td>' . esc_html(self::missingLabels($codes, $definitions)) . '</td>';
			echo '<td>';
			if ($actions !== array()) {
				echo '<span class="description">' . esc_html(implode(' ', array_slice($actions, 0, 2))) . '</span><br>';
			}
			echo '<a class="button button-small" href="' . esc_url(get_edit_post_link($postId, 'raw')) . '">' . esc_html__('Corregir ficha', 'wla-inmo') . '</a>';
			echo '</td>';
			echo '</tr>';
			++$rendered;
		}

		if ($rendered === 0) {
			echo '<tr><td colspan="4">' . esc_html__('No hay propiedades incompletas que puedas editar con tu usuario.', 'wla-inmo') . '</td></tr>';
		}

		echo '</tbody></table></div>';
	}

	private static function renderRebuildForm(): void
	{
		if (!current_user_can(AccessCapabilities::MANAGE_SETTINGS)) {
			return;
		}

		echo '<hr>';
		echo '<h3>' . esc_html__('Reconstruir calidad', 'wla-inmo') . '</h3>';
		echo '<p class="description">' . esc_html__('Recalcula la proyección derivada para propiedades existentes. No modifica los datos canónicos de las fichas.', 'wla-inmo') . '</p>';
		echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr(self::REBUILD_ACTION) . '">';
		wp_nonce_field(self::REBUILD_NONCE);
		echo '<button type="submit" class="button">' . esc_html__('Reconstruir calidad del catálogo', 'wla-inmo') . '</button>';
		echo '</form>';
	}

	private static function renderRebuildNotice(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only post-redirect notice; normalized to integer immediately.
		$raw = isset($_GET['wla_quality_rebuilt']) ? wp_unslash($_GET['wla_quality_rebuilt']) : null;
		if ($raw === null || !is_scalar($raw)) {
			return;
		}

		$count = absint($raw);
		echo '<div class="notice notice-success inline is-dismissible"><p>';
		echo esc_html(sprintf(_n('Se reconstruyó la calidad de %d propiedad.', 'Se reconstruyó la calidad de %d propiedades.', $count, 'wla-inmo'), $count));
		echo '</p></div>';
	}

	/** @return array<int,string> */
	private static function missingCodes(string $value): array
	{
		$codes = array_filter(array_map('sanitize_key', explode(',', $value)));

		return array_values(array_unique($codes));
	}

	/**
	 * @param array<int,string> $codes Missing check codes.
	 * @param array<string,array{label:string,action:string}> $definitions Check definitions.
	 */
	private static function missingLabels(array $codes, array $definitions): string
	{
		$labels = array();
		foreach ($codes as $code) {
			if (isset($definitions[$code])) {
				$labels[] = (string) $definitions[$code]['label'];
			}
		}

		return $labels === array() ? __('Sin detalle disponible', 'wla-inmo') : implode(', ', $labels);
	}
}
