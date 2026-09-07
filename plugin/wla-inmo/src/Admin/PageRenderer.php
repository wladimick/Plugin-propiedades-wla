<?php

namespace WLA\Inmo\Admin;

final class PageRenderer
{
	/**
	 * @param array<string, string> $definition Screen definition.
	 */
	public static function render(array $definition): void
	{
		echo '<div class="wrap wla-inmo-admin">';
		echo '<header class="wla-inmo-admin__header">';
		echo '<p class="wla-inmo-admin__eyebrow">' . esc_html__('WLA Inmo', 'wla-inmo') . '</p>';
		echo '<h1>' . esc_html($definition['page_title']) . '</h1>';

		if ($definition['description'] !== '') {
			echo '<p class="wla-inmo-admin__lead">' . esc_html($definition['description']) . '</p>';
		}
		echo '</header>';

		if ($definition['slug'] === ScreenRegistry::ROOT_SLUG) {
			DashboardPage::render();
		} elseif ($definition['slug'] === 'wla-inmo-help') {
			HelpCenter::render();
		} elseif ($definition['slug'] === 'wla-inmo-quality') {
			QualityPage::render();
		} elseif ($definition['slug'] === 'wla-inmo-settings') {
			SettingsPage::render();
		} elseif ($definition['slug'] === 'wla-inmo-activity') {
			ActivityPage::render();
		} elseif ($definition['slug'] === 'wla-inmo-import-export') {
			ImportExportPage::render();
		} else {
			self::renderPlaceholder($definition);
		}

		echo '</div>';
	}

	/**
	 * @param array<string, string> $definition Screen definition.
	 */
	private static function renderPlaceholder(array $definition): void
	{
		echo '<section class="wla-inmo-admin__panel">';
		echo '<h2>' . esc_html__('Sección preparada', 'wla-inmo') . '</h2>';
		echo '<p>' . esc_html__('La navegación y los permisos de esta sección ya forman parte del shell administrativo. Su funcionalidad se incorporará en la fase o PR indicada en la documentación del proyecto.', 'wla-inmo') . '</p>';
		echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=wla-inmo-help')) . '">' . esc_html__('Consultar ayuda', 'wla-inmo') . '</a></p>';
		echo '</section>';
	}
}
