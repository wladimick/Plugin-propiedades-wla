<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Properties\Capabilities as PropertyCapabilities;

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
			self::renderDashboard();
		} elseif ($definition['slug'] === 'wla-inmo-help') {
			HelpCenter::render();
		} elseif ($definition['slug'] === 'wla-inmo-quality') {
			QualityPage::render();
		} elseif ($definition['slug'] === 'wla-inmo-settings') {
			SettingsPage::render();
		} else {
			self::renderPlaceholder($definition);
		}

		echo '</div>';
	}

	private static function renderDashboard(): void
	{
		Onboarding::renderDashboardCard();
		echo '<section class="wla-inmo-admin__grid" aria-label="' . esc_attr__('Accesos rápidos', 'wla-inmo') . '">';

		if (current_user_can(PropertyCapabilities::EDIT_POSTS)) {
			self::renderActionCard(
				__('Propiedades', 'wla-inmo'),
				__('Revisa y administra el catálogo inmobiliario.', 'wla-inmo'),
				admin_url('edit.php?post_type=wla_property'),
				__('Ver propiedades', 'wla-inmo')
			);
			self::renderActionCard(
				__('Nueva propiedad', 'wla-inmo'),
				__('Crea una nueva ficha y complétala paso a paso.', 'wla-inmo'),
				admin_url('post-new.php?post_type=wla_property'),
				__('Crear propiedad', 'wla-inmo')
			);
			self::renderActionCard(
				__('Calidad del catálogo', 'wla-inmo'),
				__('Detecta fichas incompletas, sin precio o sin imagen principal.', 'wla-inmo'),
				admin_url('admin.php?page=wla-inmo-quality'),
				__('Revisar calidad', 'wla-inmo')
			);
		}

		self::renderActionCard(
			__('Ayuda', 'wla-inmo'),
			__('Encuentra instrucciones en lenguaje simple para las tareas habituales.', 'wla-inmo'),
			admin_url('admin.php?page=wla-inmo-help'),
			__('Abrir ayuda', 'wla-inmo')
		);

		if (current_user_can(AccessCapabilities::MANAGE_SETTINGS)) {
			self::renderActionCard(
				__('Ajustes', 'wla-inmo'),
				__('Configuración del motor inmobiliario y sus integraciones.', 'wla-inmo'),
				admin_url('admin.php?page=wla-inmo-settings'),
				__('Ver ajustes', 'wla-inmo')
			);
		}

		echo '</section>';
		echo '<div class="notice notice-info inline wla-inmo-admin__notice"><p>';
		echo esc_html__('El Resumen operativo incorporará métricas reales al final de Fase 2. Esta versión ya enlaza Calidad, Ayuda y Ajustes.', 'wla-inmo');
		echo '</p></div>';
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

	private static function renderActionCard(string $title, string $description, string $url, string $action): void
	{
		echo '<article class="wla-inmo-admin__card">';
		echo '<h2>' . esc_html($title) . '</h2>';
		echo '<p>' . esc_html($description) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url($url) . '">' . esc_html($action) . '</a></p>';
		echo '</article>';
	}
}
