<?php

namespace WLA\Inmo\Admin;

final class Menu
{
	/** @var array<int, string> */
	private static array $hookSuffixes = array();

	public static function register(): void
	{
		$definitions = ScreenRegistry::definitions();
		$dashboard = $definitions['dashboard'];

		if (!current_user_can($dashboard['capability'])) {
			return;
		}

		$rootHook = add_menu_page(
			$dashboard['page_title'],
			__('WLA Inmo', 'wla-inmo'),
			$dashboard['capability'],
			ScreenRegistry::ROOT_SLUG,
			array(self::class, 'renderCurrentPage'),
			'dashicons-admin-home',
			25
		);
		self::rememberHook($rootHook);

		foreach ($definitions as $key => $definition) {
			if (!current_user_can($definition['capability'])) {
				continue;
			}

			if ($definition['kind'] === 'native') {
				// WordPress adds CPT native submenus because wla_property points its
				// show_in_menu to ScreenRegistry::ROOT_SLUG. Registering them here
				// would create duplicate entries.
				continue;
			}

			if ($key === 'dashboard') {
				$hook = add_submenu_page(
					ScreenRegistry::ROOT_SLUG,
					$definition['page_title'],
					$definition['menu_title'],
					$definition['capability'],
					$definition['slug'],
					array(self::class, 'renderCurrentPage')
				);
				self::rememberHook($hook);
				continue;
			}

			$hook = add_submenu_page(
				ScreenRegistry::ROOT_SLUG,
				$definition['page_title'],
				$definition['menu_title'],
				$definition['capability'],
				$definition['slug'],
				array(self::class, 'renderCurrentPage')
			);
			self::rememberHook($hook);
		}
	}

	public static function renderCurrentPage(): void
	{
		$page = self::requestedPage();
		$definition = $page === null ? null : ScreenRegistry::findBySlug($page);

		if ($definition === null || $definition['kind'] !== 'page') {
			wp_die(
				esc_html__('La pantalla solicitada de WLA Inmo no existe.', 'wla-inmo'),
				esc_html__('WLA Inmo', 'wla-inmo'),
				array('response' => 404)
			);
		}

		if (!current_user_can($definition['capability'])) {
			wp_die(
				esc_html__('No tienes permisos para acceder a esta sección de WLA Inmo.', 'wla-inmo'),
				esc_html__('Acceso restringido', 'wla-inmo'),
				array('response' => 403)
			);
		}

		PageRenderer::render($definition);
	}

	/**
	 * @return array<int, string>
	 */
	public static function hookSuffixes(): array
	{
		return self::$hookSuffixes;
	}

	public static function resetForTests(): void
	{
		self::$hookSuffixes = array();
	}

	private static function requestedPage(): ?string
	{
		if (!isset($_GET['page'])) {
			return null;
		}

		$page = sanitize_key(wp_unslash((string) $_GET['page']));

		return $page === '' ? null : $page;
	}

	private static function rememberHook($hook): void
	{
		if (is_string($hook) && $hook !== '') {
			self::$hookSuffixes[] = $hook;
		}
	}
}
