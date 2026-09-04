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

		if ($isProperty) {
			$content .= '<h3>' . esc_html__('Datos de la propiedad', 'wla-inmo') . '</h3>';
			$content .= '<p>' . esc_html__('El código debe ser único. Completa primero operación, tipo, estado, precio y ubicación antes de publicar.', 'wla-inmo') . '</p>';
			$content .= '<h3>' . esc_html__('Multimedia', 'wla-inmo') . '</h3>';
			$content .= '<p>' . esc_html__('La Imagen destacada es la fotografía principal. La galería puede ordenarse sin borrar archivos. Los videos se guardan como URLs, no como iframes.', 'wla-inmo') . '</p>';
			$content .= '<h3>' . esc_html__('Calidad', 'wla-inmo') . '</h3>';
			$content .= '<p>' . esc_html__('Calidad orienta la completitud de la ficha. Un porcentaje bajo no bloquea borradores ni representa un ranking de Google.', 'wla-inmo') . '</p>';
			$content .= '<p><a href="' . esc_url($helpUrl . '#wla-help-crear-propiedad') . '">' . esc_html__('Ver guía para crear y revisar propiedades', 'wla-inmo') . '</a></p>';
		} else {
			$content .= '<p><a href="' . esc_url($helpUrl) . '">' . esc_html__('Abrir Centro de Ayuda', 'wla-inmo') . '</a></p>';
		}

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
		// `page` only determines whether contextual help applies to this read-only route.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing parameter.
		if (!isset($_GET['page'])) {
			return null;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing parameter; sanitized immediately.
		$page = sanitize_key(wp_unslash((string) $_GET['page']));

		return $page === '' ? null : $page;
	}
}
