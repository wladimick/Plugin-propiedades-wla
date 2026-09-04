<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Properties\PostType;

final class ContextHelp
{
	public static function add($screen): void
	{
		if (!is_object($screen) || !method_exists($screen, 'add_help_tab')) {
			return;
		}

		$isProperty = isset($screen->post_type) && $screen->post_type === PostType::POST_TYPE;
		$page = self::requestedPage();
		$isWlaPage = $page !== null && ScreenRegistry::isPluginPage($page);

		if (!$isProperty && !$isWlaPage) {
			return;
		}

		$helpUrl = admin_url('admin.php?page=wla-inmo-help');
		$content = '<p>' . esc_html__('WLA Inmo incluye ayuda en lenguaje simple para las tareas inmobiliarias habituales.', 'wla-inmo') . '</p>';
		$content .= '<p><a href="' . esc_url($helpUrl) . '">' . esc_html__('Abrir Centro de Ayuda', 'wla-inmo') . '</a></p>';

		$screen->add_help_tab(
			array(
				'id'      => 'wla-inmo-context-help',
				'title'   => __('Ayuda WLA Inmo', 'wla-inmo'),
				'content' => $content,
			)
		);
	}

	private static function requestedPage(): ?string
	{
		if (!isset($_GET['page'])) {
			return null;
		}

		$page = sanitize_key(wp_unslash((string) $_GET['page']));

		return $page === '' ? null : $page;
	}
}
