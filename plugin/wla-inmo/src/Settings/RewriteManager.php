<?php

namespace WLA\Inmo\Settings;

use WLA\Inmo\Access\Capabilities;

final class RewriteManager
{
	public const PENDING_OPTION = 'wla_inmo_rewrite_flush_pending';
	private const ACTION = 'wla_inmo_apply_rewrite_rules';
	private const NONCE_ACTION = 'wla_inmo_apply_rewrite_rules';
	private const NONCE_NAME = 'wla_inmo_rewrite_nonce';

	private static bool $registered = false;

	public static function register(): void
	{
		if (self::$registered) {
			return;
		}

		self::$registered = true;
		add_action('add_option_' . Schema::OPTION_NAME, array(self::class, 'onSettingsAdded'), 10, 2);
		add_action('update_option_' . Schema::OPTION_NAME, array(self::class, 'onSettingsUpdated'), 10, 2);
		add_action('admin_init', array(self::class, 'handleApplyRequest'), 30);
	}

	/**
	 * WordPress fires a different hook when update_option() creates an option
	 * for the first time. Compare that first stored value with canonical defaults
	 * so a non-default property base cannot bypass the pending-rewrite workflow.
	 *
	 * @param mixed $optionName Added option name.
	 * @param mixed $value Added option value.
	 */
	public static function onSettingsAdded($optionName, $value): void
	{
		unset($optionName);
		$old = Schema::defaults();
		$new = Schema::sanitize(is_array($value) ? $value : array());

		if ($old['property_base'] !== $new['property_base']) {
			self::markPending($new['property_base']);
		}
	}

	/**
	 * @param mixed $oldValue Previous option value.
	 * @param mixed $newValue New option value.
	 */
	public static function onSettingsUpdated($oldValue, $newValue): void
	{
		$old = Schema::sanitize(is_array($oldValue) ? $oldValue : array());
		$new = Schema::sanitize(is_array($newValue) ? $newValue : array());

		if ($old['property_base'] === $new['property_base']) {
			return;
		}

		self::markPending($new['property_base']);
	}

	public static function markPending(string $propertyBase): void
	{
		$propertyBase = sanitize_key($propertyBase);
		if ($propertyBase === '') {
			return;
		}

		update_option(self::PENDING_OPTION, $propertyBase, false);
	}

	public static function pendingBase(): ?string
	{
		$value = get_option(self::PENDING_OPTION, '');
		$value = is_scalar($value) ? sanitize_key((string) $value) : '';

		return $value === '' ? null : $value;
	}

	public static function isPending(): bool
	{
		return self::pendingBase() !== null;
	}

	public static function handleApplyRequest(): void
	{
		if (!isset($_POST['wla_inmo_settings_action'])) {
			return;
		}

		$action = sanitize_key(wp_unslash((string) $_POST['wla_inmo_settings_action']));
		if ($action !== self::ACTION) {
			return;
		}

		if (!current_user_can(Capabilities::MANAGE_SETTINGS)) {
			wp_die(esc_html__('No tienes permisos para aplicar las reglas de enlaces.', 'wla-inmo'));
		}

		$nonce = isset($_POST[self::NONCE_NAME]) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME])) : '';
		if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
			wp_die(esc_html__('La solicitud para actualizar los enlaces no es válida. Vuelve a intentarlo.', 'wla-inmo'));
		}

		$pendingBase = self::pendingBase();
		if ($pendingBase !== null) {
			flush_rewrite_rules(false);
			delete_option(self::PENDING_OPTION);
			do_action('wla_inmo_rewrite_rules_applied', $pendingBase);
		}

		wp_safe_redirect(admin_url('admin.php?page=wla-inmo-settings&tab=advanced&rewrites=applied'));
		exit;
	}

	public static function renderApplyButton(): void
	{
		if (!current_user_can(Capabilities::MANAGE_SETTINGS) || !self::isPending()) {
			return;
		}

		echo '<form method="post" class="wla-inmo-settings__rewrite-form">';
		wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
		echo '<input type="hidden" name="wla_inmo_settings_action" value="' . esc_attr(self::ACTION) . '">';
		echo '<button type="submit" class="button button-primary">' . esc_html__('Aplicar reglas de enlaces', 'wla-inmo') . '</button>';
		echo '</form>';
	}

	public static function resetForTests(): void
	{
		self::$registered = false;
	}
}
