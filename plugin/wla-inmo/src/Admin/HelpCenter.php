<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Access\Capabilities as AccessCapabilities;
use WLA\Inmo\Properties\Capabilities as PropertyCapabilities;

final class HelpCenter
{
	public static function render(): void
	{
		if (!current_user_can(AccessCapabilities::VIEW_DASHBOARD)) {
			wp_die(esc_html__('No tienes permisos para ver el Centro de Ayuda.', 'wla-inmo'));
		}

		self::renderQuickActions();
		Onboarding::renderChecklist();
		self::renderTopicSearch();
		self::renderTopics();
		self::renderGlossary();
	}

	/** @return array<int,array<string,mixed>> */
	public static function topics(): array
	{
		return array(
			array(
				'id'       => 'primeros-pasos',
				'category' => __('Comenzar', 'wla-inmo'),
				'title'    => __('Primeros pasos con WLA Inmo', 'wla-inmo'),
				'status'   => 'available',
				'summary'  => __('Revisa el flujo recomendado antes de cargar el catálogo completo.', 'wla-inmo'),
				'steps'    => array(
					__('Completa el checklist de configuración inicial.', 'wla-inmo'),
					__('Crea una propiedad de prueba y revisa cómo se organiza la ficha.', 'wla-inmo'),
					__('Comprueba la Calidad del catálogo para detectar datos pendientes.', 'wla-inmo'),
				),
			),
			array(
				'id'       => 'crear-propiedad',
				'category' => __('Propiedades', 'wla-inmo'),
				'title'    => __('Crear una propiedad', 'wla-inmo'),
				'status'   => 'available',
				'summary'  => __('Crea una ficha usando únicamente los campos nativos de WLA Inmo.', 'wla-inmo'),
				'steps'    => array(
					__('Ve a WLA Inmo → Nueva propiedad.', 'wla-inmo'),
					__('Completa título, código, operación, tipo, estado y precio.', 'wla-inmo'),
					__('Agrega ubicación, superficies y características relevantes.', 'wla-inmo'),
					__('Selecciona una imagen principal y completa la galería.', 'wla-inmo'),
					__('Revisa la Calidad del catálogo y publica cuando la información esté lista.', 'wla-inmo'),
				),
			),
			array(
				'id'       => 'actualizar-propiedad',
				'category' => __('Propiedades', 'wla-inmo'),
				'title'    => __('Actualizar una propiedad', 'wla-inmo'),
				'status'   => 'available',
				'summary'  => __('Busca por título o código y actualiza la misma ficha canónica.', 'wla-inmo'),
				'steps'    => array(
					__('Abre WLA Inmo → Propiedades.', 'wla-inmo'),
					__('Busca por nombre, código o identificador disponible.', 'wla-inmo'),
					__('Edita precio, estado, descripción, ubicación o multimedia según corresponda.', 'wla-inmo'),
					__('Guarda con Actualizar y vuelve a revisar la Calidad.', 'wla-inmo'),
				),
			),
			array(
				'id'       => 'precio-estado',
				'category' => __('Propiedades', 'wla-inmo'),
				'title'    => __('Cambiar precio y estado', 'wla-inmo'),
				'status'   => 'available',
				'summary'  => __('Mantén precio y disponibilidad actualizados desde una sola fuente de verdad.', 'wla-inmo'),
				'steps'    => array(
					__('Abre la propiedad que necesitas actualizar.', 'wla-inmo'),
					__('Selecciona la moneda principal y registra el precio correspondiente.', 'wla-inmo'),
					__('Si el precio no debe publicarse, usa la opción de precio a consultar.', 'wla-inmo'),
					__('Actualiza el estado comercial, por ejemplo Disponible o Reservada.', 'wla-inmo'),
				),
			),
			array(
				'id'       => 'fotografias-galeria',
				'category' => __('Multimedia', 'wla-inmo'),
				'title'    => __('Fotografías y galería', 'wla-inmo'),
				'status'   => 'available',
				'summary'  => __('Usa la Biblioteca de Medios nativa, ordena la galería y agrega ALT útil.', 'wla-inmo'),
				'steps'    => array(
					__('Define la Imagen destacada como fotografía principal.', 'wla-inmo'),
					__('En Multimedia selecciona varias imágenes desde la Biblioteca de Medios.', 'wla-inmo'),
					__('Ordena las imágenes con los controles Mover antes y Mover después.', 'wla-inmo'),
					__('Agrega texto alternativo descriptivo a las imágenes relevantes.', 'wla-inmo'),
				),
			),
			array(
				'id'       => 'videos',
				'category' => __('Multimedia', 'wla-inmo'),
				'title'    => __('Agregar videos', 'wla-inmo'),
				'status'   => 'available',
				'summary'  => __('Guarda URLs de video seguras y evita pegar código embebido.', 'wla-inmo'),
				'steps'    => array(
					__('Abre la sección Multimedia de la propiedad.', 'wla-inmo'),
					__('Pega una URL HTTP o HTTPS por línea.', 'wla-inmo'),
					__('No pegues HTML, scripts ni código de inserción.', 'wla-inmo'),
				),
			),
			array(
				'id'       => 'publicar-revisar',
				'category' => __('Calidad', 'wla-inmo'),
				'title'    => __('Publicar y revisar una propiedad', 'wla-inmo'),
				'status'   => 'available',
				'summary'  => __('La Calidad del catálogo indica qué información falta antes o después de publicar.', 'wla-inmo'),
				'steps'    => array(
					__('Guarda la propiedad como borrador mientras la completas.', 'wla-inmo'),
					__('Abre WLA Inmo → Calidad del catálogo.', 'wla-inmo'),
					__('Corrige primero las propiedades con menor porcentaje o sin precio o imagen.', 'wla-inmo'),
					__('Publica cuando la ficha esté comercialmente lista.', 'wla-inmo'),
				),
			),
			array(
				'id'       => 'destacadas-inicio',
				'category' => __('Portada', 'wla-inmo'),
				'title'    => __('Destacar propiedades en el inicio', 'wla-inmo'),
				'status'   => 'planned',
				'summary'  => __('La base ya contempla propiedades destacadas. La gestión visual completa de portada se termina en una fase posterior.', 'wla-inmo'),
				'steps'    => array(
					__('Por ahora puedes preparar qué propiedades deberían recibir prioridad editorial.', 'wla-inmo'),
					__('La composición final de bloques de portada no forma parte de esta fase.', 'wla-inmo'),
				),
			),
			array(
				'id'       => 'importacion-masiva',
				'category' => __('Importar', 'wla-inmo'),
				'title'    => __('Importar propiedades masivamente', 'wla-inmo'),
				'status'   => 'planned',
				'summary'  => __('El importador XLSX, CSV y JSON se implementará en Fase 3. Todavía no cargues archivos esperando que sean procesados.', 'wla-inmo'),
				'steps'    => array(
					__('Prepara códigos de propiedad únicos y consistentes.', 'wla-inmo'),
					__('Mantén columnas separadas para precio, operación, tipo, comuna y demás datos.', 'wla-inmo'),
					__('En Fase 3 habrá mapeo, validación, vista previa y dry-run antes de importar.', 'wla-inmo'),
				),
			),
			array(
				'id'       => 'errores-importacion',
				'category' => __('Importar', 'wla-inmo'),
				'title'    => __('Resolver errores de importación', 'wla-inmo'),
				'status'   => 'planned',
				'summary'  => __('Esta guía se activará junto con el importador de Fase 3 y mostrará errores por fila con acciones sugeridas.', 'wla-inmo'),
				'steps'    => array(
					__('Los errores de código, tipo de dato, imágenes y campos obligatorios se reportarán antes de escribir datos.', 'wla-inmo'),
					__('El dry-run no creará propiedades ni descargará imágenes.', 'wla-inmo'),
				),
			),
			array(
				'id'       => 'seo-basico',
				'category' => __('Visibilidad', 'wla-inmo'),
				'title'    => __('SEO básico de una propiedad', 'wla-inmo'),
				'status'   => 'planned',
				'summary'  => __('El módulo SEO, GEO y AEO completo corresponde a Fase 6. Ya puedes preparar contenido descriptivo y verificable.', 'wla-inmo'),
				'steps'    => array(
					__('Usa títulos claros que describan el tipo de propiedad y ubicación.', 'wla-inmo'),
					__('Escribe descripciones reales, útiles y sin texto duplicado innecesario.', 'wla-inmo'),
					__('Completa ubicación, precio, superficies y disponibilidad con datos verificables.', 'wla-inmo'),
					__('El control técnico de indexación, canonical, Open Graph y schema llegará en Fase 6.', 'wla-inmo'),
				),
			),
			array(
				'id'       => 'consultas-visitas',
				'category' => __('Consultas', 'wla-inmo'),
				'title'    => __('Consultas y solicitudes de visita', 'wla-inmo'),
				'status'   => 'planned',
				'summary'  => __('La gestión nativa de consultas y solicitudes de visita corresponde a Fase 7.', 'wla-inmo'),
				'steps'    => array(
					__('Todavía no existe una bandeja funcional de leads dentro de WLA Inmo.', 'wla-inmo'),
					__('Cuando se implemente, cada consulta podrá quedar asociada a la propiedad y a su origen.', 'wla-inmo'),
				),
			),
			array(
				'id'       => 'faq',
				'category' => __('Ayuda', 'wla-inmo'),
				'title'    => __('Preguntas frecuentes', 'wla-inmo'),
				'status'   => 'available',
				'summary'  => __('Respuestas rápidas a dudas habituales del administrador inmobiliario.', 'wla-inmo'),
				'steps'    => array(
					__('¿Puedo usar dos veces el mismo código? No. El código identifica una propiedad y debe ser único.', 'wla-inmo'),
					__('¿Quitar una foto de la galería la borra de WordPress? No. Solo se desasocia de esa propiedad.', 'wla-inmo'),
					__('¿Un score bajo impide guardar? No. Calidad orienta y prioriza, pero no bloquea borradores.', 'wla-inmo'),
					__('¿WLA Inmo necesita los plugins o constructores usados por el sitio anterior? No. El Core no depende de ellos.', 'wla-inmo'),
				),
			),
		);
	}

	private static function renderQuickActions(): void
	{
		$actions = array();

		if (current_user_can(PropertyCapabilities::EDIT_POSTS)) {
			$actions[] = array(__('Ver propiedades', 'wla-inmo'), admin_url('edit.php?post_type=wla_property'));
			$actions[] = array(__('Crear propiedad', 'wla-inmo'), admin_url('post-new.php?post_type=wla_property'));
		}
		if (current_user_can(AccessCapabilities::VIEW_DASHBOARD)) {
			$actions[] = array(__('Revisar calidad', 'wla-inmo'), admin_url('admin.php?page=wla-inmo-quality'));
		}
		if (current_user_can(AccessCapabilities::MANAGE_SETTINGS)) {
			$actions[] = array(__('Abrir ajustes', 'wla-inmo'), admin_url('admin.php?page=wla-inmo-settings'));
		}

		if (empty($actions)) {
			return;
		}

		echo '<nav class="wla-inmo-help__quick-actions" aria-label="' . esc_attr__('Accesos rápidos de ayuda', 'wla-inmo') . '">';
		foreach ($actions as $action) {
			echo '<a class="button" href="' . esc_url($action[1]) . '">' . esc_html($action[0]) . '</a>';
		}
		echo '</nav>';
	}

	private static function renderTopicSearch(): void
	{
		echo '<section class="wla-inmo-help__search" aria-labelledby="wla-inmo-help-search-heading">';
		echo '<div><p class="wla-inmo-help__kicker">' . esc_html__('Guías', 'wla-inmo') . '</p><h2 id="wla-inmo-help-search-heading">' . esc_html__('¿Qué necesitas hacer?', 'wla-inmo') . '</h2></div>';
		echo '<label class="screen-reader-text" for="wla-inmo-help-search">' . esc_html__('Buscar en la ayuda', 'wla-inmo') . '</label>';
		echo '<input id="wla-inmo-help-search" class="regular-text" type="search" placeholder="' . esc_attr__('Buscar: precio, fotos, importar, SEO…', 'wla-inmo') . '" autocomplete="off">';
		echo '<p id="wla-inmo-help-search-status" class="wla-inmo-help__search-status" aria-live="polite"></p>';
		echo '</section>';
	}

	private static function renderTopics(): void
	{
		echo '<section id="wla-inmo-help-topics" class="wla-inmo-help__topics" aria-label="' . esc_attr__('Temas de ayuda', 'wla-inmo') . '">';
		foreach (self::topics() as $topic) {
			$status = (string) $topic['status'];
			$searchText = strtolower((string) $topic['category'] . ' ' . (string) $topic['title'] . ' ' . (string) $topic['summary'] . ' ' . implode(' ', $topic['steps']));
			echo '<article id="wla-help-' . esc_attr((string) $topic['id']) . '" class="wla-inmo-help__topic" data-wla-help-topic data-search="' . esc_attr($searchText) . '">';
			echo '<div class="wla-inmo-help__topic-meta"><span>' . esc_html((string) $topic['category']) . '</span>';
			if ($status === 'planned') {
				echo '<span class="wla-inmo-help__badge">' . esc_html__('Próximamente', 'wla-inmo') . '</span>';
			} else {
				echo '<span class="wla-inmo-help__badge wla-inmo-help__badge--available">' . esc_html__('Disponible', 'wla-inmo') . '</span>';
			}
			echo '</div>';
			echo '<h3>' . esc_html((string) $topic['title']) . '</h3>';
			echo '<p>' . esc_html((string) $topic['summary']) . '</p>';
			echo '<ol>';
			foreach ($topic['steps'] as $step) {
				echo '<li>' . esc_html((string) $step) . '</li>';
			}
			echo '</ol>';
			echo '</article>';
		}
		echo '</section>';
		echo '<div id="wla-inmo-help-empty" class="wla-inmo-help__empty" hidden><p>' . esc_html__('No encontramos un tema con esas palabras. Prueba con otro término o revisa las categorías disponibles.', 'wla-inmo') . '</p></div>';
	}

	private static function renderGlossary(): void
	{
		$terms = array(
			__('Código de propiedad', 'wla-inmo') => __('Identificador único de negocio usado para reconocer la misma propiedad aunque cambie su título.', 'wla-inmo'),
			__('Operación', 'wla-inmo') => __('Forma comercial de ofrecer la propiedad, por ejemplo venta o arriendo.', 'wla-inmo'),
			__('Precio principal', 'wla-inmo') => __('Moneda y valor que WLA Inmo usa como referencia principal de la propiedad.', 'wla-inmo'),
			__('Dirección pública', 'wla-inmo') => __('Ubicación que puede mostrarse a visitantes. La dirección privada se mantiene separada.', 'wla-inmo'),
			__('Texto alternativo', 'wla-inmo') => __('Descripción breve de una imagen útil para accesibilidad y comprensión del contenido.', 'wla-inmo'),
			__('Propiedad destacada', 'wla-inmo') => __('Marca editorial para priorizar una propiedad en componentes que admitan destacados.', 'wla-inmo'),
			__('Calidad del catálogo', 'wla-inmo') => __('Guía interna de completitud. No es un factor de ranking de Google.', 'wla-inmo'),
			__('Indexar / no indexar', 'wla-inmo') => __('Indicación técnica sobre si una URL debería ser candidata a aparecer en buscadores. Su control completo llega en Fase 6.', 'wla-inmo'),
		);

		echo '<section class="wla-inmo-help__glossary" aria-labelledby="wla-inmo-help-glossary-heading">';
		echo '<p class="wla-inmo-help__kicker">' . esc_html__('Glosario', 'wla-inmo') . '</p><h2 id="wla-inmo-help-glossary-heading">' . esc_html__('Conceptos frecuentes', 'wla-inmo') . '</h2>';
		echo '<dl>';
		foreach ($terms as $term => $description) {
			echo '<div><dt>' . esc_html($term) . '</dt><dd>' . esc_html($description) . '</dd></div>';
		}
		echo '</dl>';
		echo '</section>';
	}
}
