<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Activity\EventTypes;
use WLA\Inmo\Dashboard\Snapshot;
use WLA\Inmo\Properties\Capabilities as PropertyCapabilities;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Quality\Evaluator;

final class DashboardPage
{
	public static function render(): void
	{
		if (!current_user_can(AccessCapabilities::VIEW_DASHBOARD)) {
			wp_die(esc_html__('No tienes permisos para ver el Resumen de WLA Inmo.', 'wla-inmo'));
		}

		Onboarding::renderDashboardCard();

		$includeActivity = current_user_can(AccessCapabilities::VIEW_ACTIVITY);
		$snapshot = (new Snapshot())->build($includeActivity);

		self::renderAttention($snapshot);
		self::renderMetrics($snapshot);
		self::renderDistributions($snapshot);

		if ($includeActivity) {
			self::renderRecentActivity($snapshot['activity']);
		}

		self::renderQuickActions();
	}

	/** @param array<string,mixed> $snapshot */
	private static function renderAttention(array $snapshot): void
	{
		$quality = is_array($snapshot['quality'] ?? null) ? $snapshot['quality'] : array();
		$attention = is_array($snapshot['attention'] ?? null) ? $snapshot['attention'] : array();
		$definitions = Evaluator::definitions();

		echo '<section class="wla-inmo-dashboard__section wla-inmo-dashboard__section--attention" aria-labelledby="wla-dashboard-attention">';
		echo '<div class="wla-inmo-dashboard__section-heading">';
		echo '<div><p class="wla-inmo-admin__eyebrow">' . esc_html__('Prioridad', 'wla-inmo') . '</p><h2 id="wla-dashboard-attention">' . esc_html__('Necesita atención', 'wla-inmo') . '</h2></div>';
		echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=wla-inmo-quality')) . '">' . esc_html__('Ver calidad completa', 'wla-inmo') . '</a>';
		echo '</div>';

		echo '<div class="wla-inmo-dashboard__exception-grid">';
		self::renderExceptionCard(__('Incompletas', 'wla-inmo'), (int) ($quality['incomplete'] ?? 0), 'incomplete');
		self::renderExceptionCard(__('Sin precio', 'wla-inmo'), (int) ($quality['no_price'] ?? 0), 'no_price');
		self::renderExceptionCard(__('Sin imagen principal', 'wla-inmo'), (int) ($quality['no_image'] ?? 0), 'no_image');
		self::renderExceptionCard(__('Sin ubicación suficiente', 'wla-inmo'), (int) ($quality['no_location'] ?? 0), '');
		self::renderExceptionCard(__('Sin verificación registrada', 'wla-inmo'), (int) ($quality['not_verified'] ?? 0), '');
		echo '</div>';

		if ($attention === array()) {
			echo '<p class="wla-inmo-dashboard__empty">' . esc_html__('No hay propiedades incompletas en la proyección actual.', 'wla-inmo') . '</p>';
			echo '</section>';
			return;
		}

		echo '<div class="wla-inmo-dashboard__attention-list">';
		foreach ($attention as $row) {
			$postId = (int) ($row['property_id'] ?? 0);
			$title = trim((string) ($row['post_title'] ?? ''));
			$title = $title !== '' ? $title : sprintf(__('Propiedad #%d', 'wla-inmo'), $postId);
			$missingCodes = array_values(array_filter(array_map('sanitize_key', explode(',', (string) ($row['missing_codes'] ?? '')))));
			$missingLabels = array();
			foreach (array_slice($missingCodes, 0, 3) as $code) {
				$missingLabels[] = isset($definitions[$code]['label']) ? (string) $definitions[$code]['label'] : $code;
			}

			echo '<article class="wla-inmo-dashboard__attention-item">';
			echo '<div class="wla-inmo-dashboard__attention-main">';
			if ($postId > 0 && current_user_can('edit_post', $postId)) {
				$link = get_edit_post_link($postId, 'raw');
				if (is_string($link)) {
					echo '<h3><a href="' . esc_url($link) . '">' . esc_html($title) . '</a></h3>';
				} else {
					echo '<h3>' . esc_html($title) . '</h3>';
				}
			} else {
				echo '<h3>' . esc_html($title) . '</h3>';
			}
			echo '<p class="description">' . esc_html($missingLabels === array() ? __('Revisa la ficha para completar datos pendientes.', 'wla-inmo') : implode(' · ', $missingLabels)) . '</p>';
			echo '</div>';
			echo '<div class="wla-inmo-dashboard__score"><strong>' . esc_html((string) (int) ($row['score'] ?? 0)) . '%</strong><span>' . esc_html__('Calidad', 'wla-inmo') . '</span></div>';
			echo '</article>';
		}
		echo '</div>';
		echo '</section>';
	}

	private static function renderExceptionCard(string $label, int $value, string $filter): void
	{
		$url = admin_url('admin.php?page=wla-inmo-quality');
		if ($filter !== '') {
			$url = add_query_arg('quality_filter', $filter, $url);
		}

		echo '<a class="wla-inmo-dashboard__exception" href="' . esc_url($url) . '">';
		echo '<strong>' . esc_html(number_format_i18n($value)) . '</strong>';
		echo '<span>' . esc_html($label) . '</span>';
		echo '</a>';
	}

	/** @param array<string,mixed> $snapshot */
	private static function renderMetrics(array $snapshot): void
	{
		$properties = is_array($snapshot['properties'] ?? null) ? $snapshot['properties'] : array();
		$quality = is_array($snapshot['quality'] ?? null) ? $snapshot['quality'] : array();
		$draftWork = (int) ($properties['draft'] ?? 0) + (int) ($properties['pending'] ?? 0);

		echo '<section class="wla-inmo-dashboard__section" aria-labelledby="wla-dashboard-catalog">';
		echo '<div class="wla-inmo-dashboard__section-heading"><div><p class="wla-inmo-admin__eyebrow">' . esc_html__('Catálogo', 'wla-inmo') . '</p><h2 id="wla-dashboard-catalog">' . esc_html__('Estado del catálogo', 'wla-inmo') . '</h2></div></div>';
		echo '<div class="wla-inmo-dashboard__metric-grid">';
		self::renderMetric(__('Propiedades', 'wla-inmo'), (int) ($properties['total'] ?? 0), __('Total administrado', 'wla-inmo'));
		self::renderMetric(__('Publicadas', 'wla-inmo'), (int) ($properties['published'] ?? 0), __('Visibles como publicación', 'wla-inmo'));
		self::renderMetric(__('En preparación', 'wla-inmo'), $draftWork, __('Borradores + pendientes', 'wla-inmo'));
		self::renderMetric(__('Destacadas', 'wla-inmo'), (int) ($snapshot['featured'] ?? 0), __('Marcadas para promoción', 'wla-inmo'));
		self::renderMetric(__('Calidad promedio', 'wla-inmo'), (int) ($quality['average_score'] ?? 0), __('Porcentaje interno de completitud', 'wla-inmo'), '%');
		self::renderMetric(__('Actualizadas 7 días', 'wla-inmo'), (int) ($properties['recently_updated'] ?? 0), __('Actividad reciente del catálogo', 'wla-inmo'));
		echo '</div>';
		echo '</section>';
	}

	private static function renderMetric(string $label, int $value, string $description, string $suffix = ''): void
	{
		echo '<article class="wla-inmo-dashboard__metric">';
		echo '<span class="wla-inmo-dashboard__metric-label">' . esc_html($label) . '</span>';
		echo '<strong>' . esc_html(number_format_i18n($value) . $suffix) . '</strong>';
		echo '<span class="description">' . esc_html($description) . '</span>';
		echo '</article>';
	}

	/** @param array<string,mixed> $snapshot */
	private static function renderDistributions(array $snapshot): void
	{
		$operations = is_array($snapshot['operations'] ?? null) ? $snapshot['operations'] : array();
		$statuses = is_array($snapshot['commercial_statuses'] ?? null) ? $snapshot['commercial_statuses'] : array();

		echo '<section class="wla-inmo-dashboard__section" aria-labelledby="wla-dashboard-distributions">';
		echo '<div class="wla-inmo-dashboard__section-heading"><div><p class="wla-inmo-admin__eyebrow">' . esc_html__('Distribución', 'wla-inmo') . '</p><h2 id="wla-dashboard-distributions">' . esc_html__('Cómo está compuesto el catálogo', 'wla-inmo') . '</h2></div></div>';
		echo '<div class="wla-inmo-dashboard__split">';
		self::renderDistributionPanel(__('Operaciones', 'wla-inmo'), self::operationItems($operations));
		self::renderDistributionPanel(__('Estados comerciales', 'wla-inmo'), self::statusItems($statuses));
		echo '</div>';
		echo '</section>';
	}

	/** @param array<int,array{label:string,count:int}> $items */
	private static function renderDistributionPanel(string $title, array $items): void
	{
		$total = array_sum(array_column($items, 'count'));
		echo '<article class="wla-inmo-dashboard__distribution"><h3>' . esc_html($title) . '</h3>';
		if ($items === array()) {
			echo '<p class="description">' . esc_html__('Aún no hay datos suficientes para esta distribución.', 'wla-inmo') . '</p></article>';
			return;
		}
		echo '<ul>';
		foreach (array_slice($items, 0, 6) as $item) {
			$count = (int) $item['count'];
			$percent = $total > 0 ? (int) round(($count / $total) * 100) : 0;
			echo '<li><div><span>' . esc_html($item['label']) . '</span><strong>' . esc_html(number_format_i18n($count)) . '</strong></div>';
			echo '<div class="wla-inmo-dashboard__bar" role="img" aria-label="' . esc_attr(sprintf(__('%1$s: %2$d%%', 'wla-inmo'), $item['label'], $percent)) . '"><span style="width:' . esc_attr((string) $percent) . '%"></span></div></li>';
		}
		echo '</ul></article>';
	}

	/** @param array<string,array{name:string,count:int}> $operations @return array<int,array{label:string,count:int}> */
	private static function operationItems(array $operations): array
	{
		$items = array();
		foreach ($operations as $operation) {
			if (!is_array($operation)) {
				continue;
			}
			$items[] = array(
				'label' => (string) ($operation['name'] ?? ''),
				'count' => (int) ($operation['count'] ?? 0),
			);
		}
		return $items;
	}

	/** @param array<string,int> $statuses @return array<int,array{label:string,count:int}> */
	private static function statusItems(array $statuses): array
	{
		$items = array();
		foreach ($statuses as $status => $count) {
			$items[] = array('label' => self::commercialStatusLabel((string) $status), 'count' => (int) $count);
		}
		return $items;
	}

	/** @param array<int,object> $events */
	private static function renderRecentActivity(array $events): void
	{
		echo '<section class="wla-inmo-dashboard__section" aria-labelledby="wla-dashboard-activity">';
		echo '<div class="wla-inmo-dashboard__section-heading"><div><p class="wla-inmo-admin__eyebrow">' . esc_html__('Cambios', 'wla-inmo') . '</p><h2 id="wla-dashboard-activity">' . esc_html__('Actividad reciente', 'wla-inmo') . '</h2></div><a class="button" href="' . esc_url(admin_url('admin.php?page=wla-inmo-activity')) . '">' . esc_html__('Ver toda la actividad', 'wla-inmo') . '</a></div>';

		if ($events === array()) {
			echo '<p class="wla-inmo-dashboard__empty">' . esc_html__('Aún no hay actividad operativa registrada.', 'wla-inmo') . '</p></section>';
			return;
		}

		$actors = self::actorMap($events);
		$properties = self::propertyMap($events);
		echo '<ol class="wla-inmo-dashboard__activity-list">';
		foreach ($events as $event) {
			$actorId = isset($event->actor_user_id) ? (int) $event->actor_user_id : 0;
			$objectId = isset($event->object_id) ? (int) $event->object_id : 0;
			$objectLabel = self::activityObjectLabel((string) ($event->object_type ?? ''), $objectId, $properties);
			$actorLabel = $actorId > 0 ? ($actors[$actorId] ?? __('Usuario eliminado', 'wla-inmo')) : __('Sistema', 'wla-inmo');
			echo '<li><div><strong>' . esc_html(EventTypes::label((string) $event->event_type)) . '</strong>';
			if ($objectLabel !== '') {
				echo '<span>' . esc_html($objectLabel) . '</span>';
			}
			echo '</div><div class="description"><span>' . esc_html($actorLabel) . '</span><span> · ' . esc_html(self::localDate((string) $event->created_at)) . '</span></div></li>';
		}
		echo '</ol></section>';
	}

	private static function renderQuickActions(): void
	{
		echo '<section class="wla-inmo-dashboard__section" aria-labelledby="wla-dashboard-actions">';
		echo '<div class="wla-inmo-dashboard__section-heading"><div><p class="wla-inmo-admin__eyebrow">' . esc_html__('Acciones', 'wla-inmo') . '</p><h2 id="wla-dashboard-actions">' . esc_html__('Accesos rápidos', 'wla-inmo') . '</h2></div></div>';
		echo '<div class="wla-inmo-dashboard__actions">';

		if (current_user_can(PropertyCapabilities::EDIT_POSTS)) {
			self::renderAction(__('Nueva propiedad', 'wla-inmo'), __('Crear una ficha nueva', 'wla-inmo'), admin_url('post-new.php?post_type=' . PostType::POST_TYPE));
			self::renderAction(__('Propiedades', 'wla-inmo'), __('Administrar el catálogo', 'wla-inmo'), admin_url('edit.php?post_type=' . PostType::POST_TYPE));
			self::renderAction(__('Calidad', 'wla-inmo'), __('Corregir fichas incompletas', 'wla-inmo'), admin_url('admin.php?page=wla-inmo-quality'));
		}
		if (current_user_can(AccessCapabilities::VIEW_ACTIVITY)) {
			self::renderAction(__('Actividad', 'wla-inmo'), __('Revisar cambios recientes', 'wla-inmo'), admin_url('admin.php?page=wla-inmo-activity'));
		}
		self::renderAction(__('Ayuda', 'wla-inmo'), __('Consultar guías del producto', 'wla-inmo'), admin_url('admin.php?page=wla-inmo-help'));
		if (current_user_can(AccessCapabilities::MANAGE_SETTINGS)) {
			self::renderAction(__('Ajustes', 'wla-inmo'), __('Configurar WLA Inmo', 'wla-inmo'), admin_url('admin.php?page=wla-inmo-settings'));
		}

		echo '</div></section>';
	}

	private static function renderAction(string $title, string $description, string $url): void
	{
		echo '<a class="wla-inmo-dashboard__action" href="' . esc_url($url) . '"><strong>' . esc_html($title) . '</strong><span>' . esc_html($description) . '</span></a>';
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

	/** @param array<int,object> $events @return array<int,string> */
	private static function propertyMap(array $events): array
	{
		$ids = array();
		foreach ($events as $event) {
			if ((string) ($event->object_type ?? '') === PostType::POST_TYPE && (int) ($event->object_id ?? 0) > 0) {
				$ids[] = (int) $event->object_id;
			}
		}
		$ids = array_values(array_unique($ids));
		if ($ids === array()) {
			return array();
		}
		$posts = get_posts(array(
			'post_type' => PostType::POST_TYPE,
			'post_status' => 'any',
			'post__in' => $ids,
			'posts_per_page' => count($ids),
			'orderby' => 'post__in',
			'no_found_rows' => true,
		));
		$map = array();
		foreach ($posts as $post) {
			$map[(int) $post->ID] = (string) get_the_title($post);
		}
		return $map;
	}

	/** @param array<int,string> $properties */
	private static function activityObjectLabel(string $objectType, int $objectId, array $properties): string
	{
		if ($objectType === PostType::POST_TYPE && $objectId > 0) {
			return $properties[$objectId] ?? sprintf(__('Propiedad #%d', 'wla-inmo'), $objectId);
		}
		if ($objectType === 'settings') {
			return __('Ajustes', 'wla-inmo');
		}
		return $objectType !== '' ? $objectType : '';
	}

	private static function localDate(string $gmtDate): string
	{
		$format = get_option('date_format') . ' ' . get_option('time_format');
		return get_date_from_gmt($gmtDate, $format);
	}

	private static function commercialStatusLabel(string $status): string
	{
		$labels = array(
			'available' => __('Disponible', 'wla-inmo'),
			'reserved' => __('Reservada', 'wla-inmo'),
			'sold' => __('Vendida', 'wla-inmo'),
			'rented' => __('Arrendada', 'wla-inmo'),
			'unavailable' => __('No disponible', 'wla-inmo'),
		);
		if (isset($labels[$status])) {
			return $labels[$status];
		}
		return ucwords(str_replace(array('-', '_'), ' ', sanitize_text_field($status)));
	}
}
