<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Properties\Capabilities as PropertyCapabilities;

final class Onboarding
{
	private const META_PROGRESS = '_wla_inmo_onboarding_progress';
	private const META_DISMISSED = '_wla_inmo_onboarding_dismissed';
	private const NONCE_ACTION = 'wla_inmo_onboarding';
	private const NONCE_NAME = 'wla_inmo_onboarding_nonce';

	public static function register(): void
	{
		add_action('admin_init', array(self::class, 'handleRequest'), 20);
	}

	/** @return array<string,array{label:string,url:string,available:bool}> */
	public static function steps(): array
	{
		return array(
			'business' => array(
				'label'     => __('Revisar los datos generales de la inmobiliaria', 'wla-inmo'),
				'url'       => current_user_can(AccessCapabilities::MANAGE_SETTINGS) ? admin_url('admin.php?page=wla-inmo-settings') : '',
				'available' => current_user_can(AccessCapabilities::MANAGE_SETTINGS),
			),
			'first_property' => array(
				'label'     => __('Crear o revisar la primera propiedad', 'wla-inmo'),
				'url'       => current_user_can(PropertyCapabilities::EDIT_POSTS) ? admin_url('post-new.php?post_type=wla_property') : '',
				'available' => current_user_can(PropertyCapabilities::EDIT_POSTS),
			),
			'contact' => array(
				'label'     => __('Revisar contacto y WhatsApp', 'wla-inmo'),
				'url'       => current_user_can(AccessCapabilities::MANAGE_SETTINGS) ? admin_url('admin.php?page=wla-inmo-settings') : '',
				'available' => current_user_can(AccessCapabilities::MANAGE_SETTINGS),
			),
			'catalogue' => array(
				'label'     => __('Revisar el catálogo de propiedades', 'wla-inmo'),
				'url'       => current_user_can(PropertyCapabilities::EDIT_POSTS) ? admin_url('edit.php?post_type=wla_property') : '',
				'available' => current_user_can(PropertyCapabilities::EDIT_POSTS),
			),
			'quality' => array(
				'label'     => __('Revisar la calidad del catálogo', 'wla-inmo'),
				'url'       => current_user_can(AccessCapabilities::VIEW_DASHBOARD) ? admin_url('admin.php?page=wla-inmo-quality') : '',
				'available' => current_user_can(AccessCapabilities::VIEW_DASHBOARD),
			),
			'visibility' => array(
				'label'     => __('Conocer las recomendaciones de visibilidad y SEO', 'wla-inmo'),
				'url'       => admin_url('admin.php?page=wla-inmo-help#wla-help-seo-basico'),
				'available' => current_user_can(AccessCapabilities::VIEW_DASHBOARD),
			),
		);
	}

	/** @return array<int,string> */
	public static function progress(?int $userId = null): array
	{
		$userId = $userId ?? get_current_user_id();
		if ($userId < 1) {
			return array();
		}

		$value = get_user_meta($userId, self::META_PROGRESS, true);
		if (!is_array($value)) {
			return array();
		}

		$allowed = array_keys(self::steps());
		$progress = array();
		foreach ($value as $step) {
			$step = sanitize_key((string) $step);
			if ($step !== '' && in_array($step, $allowed, true)) {
				$progress[] = $step;
			}
		}

		return array_values(array_unique($progress));
	}

	public static function isDismissed(?int $userId = null): bool
	{
		$userId = $userId ?? get_current_user_id();

		return $userId > 0 && (string) get_user_meta($userId, self::META_DISMISSED, true) === '1';
	}

	public static function isComplete(?int $userId = null): bool
	{
		$steps = array_keys(self::steps());
		$progress = self::progress($userId);

		return count(array_diff($steps, $progress)) === 0;
	}

	public static function handleRequest(): void
	{
		if (!isset($_POST['wla_inmo_onboarding_action'])) {
			return;
		}

		if (!current_user_can(AccessCapabilities::VIEW_DASHBOARD)) {
			return;
		}

		$nonce = isset($_POST[self::NONCE_NAME]) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME])) : '';
		if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
			return;
		}

		$action = sanitize_key(wp_unslash((string) $_POST['wla_inmo_onboarding_action']));
		$userId = get_current_user_id();
		if ($userId < 1) {
			return;
		}

		if ($action === 'save') {
			// The field is an array; each element is sanitized and checked against the explicit allowlist below.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$submittedRaw = isset($_POST['wla_inmo_onboarding_steps']) ? wp_unslash($_POST['wla_inmo_onboarding_steps']) : array();
			$submitted = is_array($submittedRaw) ? $submittedRaw : array();
			$allowed = array_keys(self::steps());
			$progress = array();

			foreach ($submitted as $step) {
				$step = sanitize_key((string) $step);
				if (in_array($step, $allowed, true)) {
					$progress[] = $step;
				}
			}

			update_user_meta($userId, self::META_PROGRESS, array_values(array_unique($progress)));
			delete_user_meta($userId, self::META_DISMISSED);
		} elseif ($action === 'dismiss') {
			update_user_meta($userId, self::META_DISMISSED, '1');
		} elseif ($action === 'reset') {
			delete_user_meta($userId, self::META_PROGRESS);
			delete_user_meta($userId, self::META_DISMISSED);
		} else {
			return;
		}

		$redirect = admin_url('admin.php?page=wla-inmo-help&onboarding=' . rawurlencode($action));
		wp_safe_redirect($redirect);
		exit;
	}

	public static function renderDashboardCard(): void
	{
		if (!current_user_can(AccessCapabilities::VIEW_DASHBOARD) || self::isDismissed() || self::isComplete()) {
			return;
		}

		$progress = self::progress();
		$total = count(self::steps());
		$done = count($progress);

		echo '<section class="wla-inmo-admin__panel wla-inmo-onboarding-card" aria-labelledby="wla-inmo-onboarding-title">';
		echo '<h2 id="wla-inmo-onboarding-title">' . esc_html__('Primeros pasos', 'wla-inmo') . '</h2>';
		echo '<p>' . esc_html(sprintf(__('Has completado %1$d de %2$d pasos recomendados.', 'wla-inmo'), $done, $total)) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=wla-inmo-help#wla-inmo-onboarding')) . '">' . esc_html__('Continuar configuración', 'wla-inmo') . '</a></p>';
		echo '</section>';
	}

	public static function renderChecklist(): void
	{
		if (!current_user_can(AccessCapabilities::VIEW_DASHBOARD)) {
			return;
		}

		$steps = self::steps();
		$progress = self::progress();
		$dismissed = self::isDismissed();

		echo '<section id="wla-inmo-onboarding" class="wla-inmo-help__onboarding" aria-labelledby="wla-inmo-onboarding-heading">';
		echo '<div class="wla-inmo-help__section-heading">';
		echo '<div><p class="wla-inmo-help__kicker">' . esc_html__('Configuración inicial', 'wla-inmo') . '</p><h2 id="wla-inmo-onboarding-heading">' . esc_html__('Primeros pasos', 'wla-inmo') . '</h2></div>';
		if ($dismissed) {
			echo '<span class="wla-inmo-help__status">' . esc_html__('Oculto en Resumen', 'wla-inmo') . '</span>';
		}
		echo '</div>';
		echo '<p>' . esc_html__('Marca lo que ya revisaste. El progreso pertenece a tu usuario y no cambia la configuración de otros administradores.', 'wla-inmo') . '</p>';
		echo '<form method="post" class="wla-inmo-help__checklist">';
		wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
		foreach ($steps as $key => $step) {
			$checked = in_array($key, $progress, true);
			echo '<div class="wla-inmo-help__check-item">';
			echo '<label><input type="checkbox" name="wla_inmo_onboarding_steps[]" value="' . esc_attr($key) . '" ' . checked($checked, true, false) . '> <span>' . esc_html($step['label']) . '</span></label>';
			if ($step['available'] && $step['url'] !== '') {
				echo '<a href="' . esc_url($step['url']) . '">' . esc_html__('Abrir', 'wla-inmo') . '</a>';
			}
			echo '</div>';
		}
		echo '<div class="wla-inmo-help__actions">';
		echo '<button type="submit" class="button button-primary" name="wla_inmo_onboarding_action" value="save">' . esc_html__('Guardar progreso', 'wla-inmo') . '</button>';
		if (!$dismissed) {
			echo '<button type="submit" class="button" name="wla_inmo_onboarding_action" value="dismiss">' . esc_html__('Ocultar en Resumen', 'wla-inmo') . '</button>';
		}
		echo '<button type="submit" class="button-link" name="wla_inmo_onboarding_action" value="reset">' . esc_html__('Reiniciar checklist', 'wla-inmo') . '</button>';
		echo '</div>';
		echo '</form>';
		echo '</section>';
	}
}
