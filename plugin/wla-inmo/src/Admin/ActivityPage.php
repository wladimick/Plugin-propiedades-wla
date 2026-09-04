<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Access\Capabilities;
use WLA\Inmo\Activity\EventTypes;
use WLA\Inmo\Activity\Repository;
use WLA\Inmo\Properties\PostType;

final class ActivityPage
{
	public static function render(): void
	{
		if (!current_user_can(Capabilities::VIEW_ACTIVITY)) {
			wp_die(esc_html__('No tienes permisos para ver la actividad de WLA Inmo.', 'wla-inmo'));
		}

		$filters = self::filtersFromRequest();
		$page = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;
		$result = Repository::paginate($filters, $page, 30);

		self::renderFilters($filters);
		self::renderTable($result['items']);
		self::renderPagination($result);
	}

	/** @param array<int,object> $events */
	public static function renderPropertyTimeline(array $events): void
	{
		if (!current_user_can(Capabilities::VIEW_ACTIVITY)) {
			return;
		}

		if ($events === array()) {
			echo '<p>' . esc_html__('Aún no hay cambios operativos registrados para esta propiedad.', 'wla-inmo') . '</p>';
			return;
		}

		$actors = self::actorMap($events);
		echo '<ol class="wla-inmo-activity-timeline">';
		foreach ($events as $event) {
			echo '<li>';
			echo '<strong>' . esc_html(EventTypes::label((string) $event->event_type)) . '</strong> ';
			echo '<span class="description">' . esc_html(self::localDate((string) $event->created_at)) . '</span>';
			$actorId = isset($event->actor_user_id) ? (int) $event->actor_user_id : 0;
			if ($actorId > 0 && isset($actors[$actorId])) {
				echo ' <span class="description">· ' . esc_html($actors[$actorId]) . '</span>';
			}
			$detail = self::eventDetail($event);
			if ($detail !== '') {
				echo '<div class="description">' . esc_html($detail) . '</div>';
			}
			echo '</li>';
		}
		echo '</ol>';
	}

	/** @return array<string,mixed> */
	private static function filtersFromRequest(): array
	{
		return array(
			'event_type' => isset($_GET['event_type']) ? sanitize_text_field(wp_unslash((string) $_GET['event_type'])) : '',
			'object_id' => isset($_GET['object_id']) ? absint($_GET['object_id']) : 0,
			'actor_user_id' => isset($_GET['actor_user_id']) ? absint($_GET['actor_user_id']) : 0,
			'from' => isset($_GET['from']) ? sanitize_text_field(wp_unslash((string) $_GET['from'])) : '',
			'to' => isset($_GET['to']) ? sanitize_text_field(wp_unslash((string) $_GET['to'])) : '',
		);
	}

	/** @param array<string,mixed> $filters */
	private static function renderFilters(array $filters): void
	{
		echo '<form method="get" class="wla-inmo-activity-filters">';
		echo '<input type="hidden" name="page" value="wla-inmo-activity">';
		echo '<label><span>' . esc_html__('Evento', 'wla-inmo') . '</span> ';
		echo '<select name="event_type"><option value="">' . esc_html__('Todos', 'wla-inmo') . '</option>';
		foreach (array_keys(EventTypes::contextAllowlist()) as $eventType) {
			echo '<option value="' . esc_attr($eventType) . '"' . selected((string) $filters['event_type'], $eventType, false) . '>' . esc_html(EventTypes::label($eventType)) . '</option>';
		}
		echo '</select></label>';
		echo '<label><span>' . esc_html__('ID propiedad', 'wla-inmo') . '</span> <input type="number" min="1" name="object_id" value="' . esc_attr((string) $filters['object_id']) . '"></label>';
		echo '<label><span>' . esc_html__('Desde', 'wla-inmo') . '</span> <input type="date" name="from" value="' . esc_attr((string) $filters['from']) . '"></label>';
		echo '<label><span>' . esc_html__('Hasta', 'wla-inmo') . '</span> <input type="date" name="to" value="' . esc_attr((string) $filters['to']) . '"></label>';
		echo '<button type="submit" class="button">' . esc_html__('Filtrar', 'wla-inmo') . '</button>';
		echo ' <a class="button-link" href="' . esc_url(admin_url('admin.php?page=wla-inmo-activity')) . '">' . esc_html__('Limpiar', 'wla-inmo') . '</a>';
		echo '</form>';
	}

	/** @param array<int,object> $events */
	private static function renderTable(array $events): void
	{
		$actors = self::actorMap($events);
		$properties = self::propertyMap($events);

		echo '<table class="widefat fixed striped wla-inmo-activity-table">';
		echo '<thead><tr><th>' . esc_html__('Fecha', 'wla-inmo') . '</th><th>' . esc_html__('Evento', 'wla-inmo') . '</th><th>' . esc_html__('Propiedad / objeto', 'wla-inmo') . '</th><th>' . esc_html__('Usuario', 'wla-inmo') . '</th><th>' . esc_html__('Detalle', 'wla-inmo') . '</th></tr></thead><tbody>';

		if ($events === array()) {
			echo '<tr><td colspan="5">' . esc_html__('No hay actividad para los filtros seleccionados.', 'wla-inmo') . '</td></tr>';
		} else {
			foreach ($events as $event) {
				$objectId = isset($event->object_id) ? (int) $event->object_id : 0;
				$actorId = isset($event->actor_user_id) ? (int) $event->actor_user_id : 0;
				echo '<tr>';
				echo '<td>' . esc_html(self::localDate((string) $event->created_at)) . '</td>';
				echo '<td><strong>' . esc_html(EventTypes::label((string) $event->event_type)) . '</strong></td>';
				echo '<td>' . self::objectHtml((string) $event->object_type, $objectId, $properties) . '</td>';
				echo '<td>' . esc_html($actorId > 0 ? ($actors[$actorId] ?? __('Usuario eliminado', 'wla-inmo')) : __('Sistema', 'wla-inmo')) . '</td>';
				echo '<td>' . esc_html(self::eventDetail($event)) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	/** @param array<string,mixed> $result */
	private static function renderPagination(array $result): void
	{
		if ((int) $result['pages'] <= 1) {
			return;
		}
		$links = paginate_links(array(
			'base' => add_query_arg('paged', '%#%'),
			'format' => '',
			'current' => (int) $result['page'],
			'total' => (int) $result['pages'],
			'type' => 'list',
		));
		if (is_string($links)) {
			echo '<nav class="wla-inmo-activity-pagination" aria-label="' . esc_attr__('Páginas de actividad', 'wla-inmo') . '">' . wp_kses_post($links) . '</nav>';
		}
	}

	/** @param array<int,object> $events @return array<int,string> */
	private static function actorMap(array $events): array
	{
		$ids = array();
		foreach ($events as $event) {
			$id = isset($event->actor_user_id) ? (int) $event->actor_user_id : 0;
			if ($id > 0) {
				$ids[] = $id;
			}
		}
		$ids = array_values(array_unique($ids));
		if ($ids === array()) {
			return array();
		}
		$users = get_users(array('include' => $ids, 'fields' => array('ID', 'display_name')));
		$map = array();
		foreach ($users as $user) {
			$map[(int) $user->ID] = (string) $user->display_name;
		}
		return $map;
	}

	/** @param array<int,object> $events @return array<int,object> */
	private static function propertyMap(array $events): array
	{
		$ids = array();
		foreach ($events as $event) {
			if ((string) $event->object_type === PostType::POST_TYPE && (int) $event->object_id > 0) {
				$ids[] = (int) $event->object_id;
			}
		}
		$ids = array_values(array_unique($ids));
		if ($ids === array()) {
			return array();
		}
		$posts = get_posts(array('post_type' => PostType::POST_TYPE, 'post_status' => 'any', 'post__in' => $ids, 'posts_per_page' => count($ids), 'orderby' => 'post__in'));
		$map = array();
		foreach ($posts as $post) {
			$map[(int) $post->ID] = $post;
		}
		return $map;
	}

	/** @param array<int,object> $properties */
	private static function objectHtml(string $objectType, int $objectId, array $properties): string
	{
		if ($objectType === PostType::POST_TYPE && $objectId > 0) {
			$title = isset($properties[$objectId]) ? get_the_title($properties[$objectId]) : sprintf(__('Propiedad #%d', 'wla-inmo'), $objectId);
			if (isset($properties[$objectId]) && current_user_can('edit_post', $objectId)) {
				$link = get_edit_post_link($objectId, 'raw');
				if (is_string($link)) {
					return '<a href="' . esc_url($link) . '">' . esc_html($title) . '</a>';
				}
			}
			return esc_html($title);
		}
		if ($objectType === 'settings') {
			return esc_html__('Ajustes', 'wla-inmo');
		}
		return esc_html($objectType !== '' ? $objectType : __('Sistema', 'wla-inmo'));
	}

	private static function localDate(string $gmtDate): string
	{
		$format = get_option('date_format') . ' ' . get_option('time_format');
		return get_date_from_gmt($gmtDate, $format);
	}

	private static function eventDetail(object $event): string
	{
		$context = isset($event->context) && is_array($event->context) ? $event->context : array();
		$type = (string) $event->event_type;
		if ($type === EventTypes::PROPERTY_PRICE_CHANGED) {
			return sprintf(__('Campo %1$s: %2$s → %3$s', 'wla-inmo'), (string) ($context['field'] ?? ''), self::displayValue($context['old'] ?? null), self::displayValue($context['new'] ?? null));
		}
		if (in_array($type, array(EventTypes::PROPERTY_WP_STATUS_CHANGED, EventTypes::PROPERTY_COMMERCIAL_STATUS_CHANGED, EventTypes::PROPERTY_BASE_CHANGED), true)) {
			return sprintf(__('%1$s → %2$s', 'wla-inmo'), self::displayValue($context['old'] ?? null), self::displayValue($context['new'] ?? null));
		}
		if ($type === EventTypes::PROPERTY_FEATURED_CHANGED) {
			return !empty($context['new']) ? __('Marcada como destacada', 'wla-inmo') : __('Quitada de destacadas', 'wla-inmo');
		}
		if ($type === EventTypes::SETTINGS_CHANGED) {
			$keys = isset($context['keys']) && is_array($context['keys']) ? $context['keys'] : array();
			return $keys === array() ? '' : sprintf(__('Campos modificados: %s', 'wla-inmo'), implode(', ', $keys));
		}
		if ($type === EventTypes::REWRITE_RULES_APPLIED) {
			return sprintf(__('Base activa: %s', 'wla-inmo'), (string) ($context['property_base'] ?? ''));
		}
		if ($type === EventTypes::PROPERTY_CREATED) {
			return sprintf(__('Estado inicial: %s', 'wla-inmo'), (string) ($context['post_status'] ?? ''));
		}
		return '';
	}

	private static function displayValue($value): string
	{
		if ($value === null || $value === '') {
			return '—';
		}
		if (is_bool($value)) {
			return $value ? __('Sí', 'wla-inmo') : __('No', 'wla-inmo');
		}
		return is_scalar($value) ? (string) $value : '—';
	}
}
