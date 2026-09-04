<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Properties\Sanitizer;
use WLA\Inmo\Properties\Validator;
use WLA\Inmo\Taxonomies\Registry as TaxonomyRegistry;

final class PropertyEditor
{
	private const NONCE_ACTION = 'wla_inmo_save_property_editor';
	private const NONCE_NAME = 'wla_inmo_property_editor_nonce';
	private const FIELD_ROOT = 'wla_inmo_fields';
	private const TAXONOMY_ROOT = 'wla_inmo_taxonomies';
	private const STATE_PREFIX = 'wla_inmo_editor_state_';

	public static function register(): void
	{
		add_filter('use_block_editor_for_post_type', array(self::class, 'useBlockEditor'), 20, 2);
		add_action('add_meta_boxes_' . PostType::POST_TYPE, array(self::class, 'registerMetaBox'));
		add_action('add_meta_boxes_' . PostType::POST_TYPE, array(self::class, 'removeNativeTaxonomyBoxes'), 99);
		add_action('save_post_' . PostType::POST_TYPE, array(self::class, 'save'), 20, 3);
	}

	public static function useBlockEditor(bool $useBlockEditor, string $postType): bool
	{
		if ($postType !== PostType::POST_TYPE) {
			return $useBlockEditor;
		}

		return false;
	}

	public static function registerMetaBox(): void
	{
		add_meta_box(
			'wla-inmo-property-editor',
			__('Ficha de la propiedad', 'wla-inmo'),
			array(self::class, 'render'),
			PostType::POST_TYPE,
			'normal',
			'high',
			array('__block_editor_compatible_meta_box' => true)
		);
	}

	public static function removeNativeTaxonomyBoxes(): void
	{
		foreach (TaxonomyRegistry::keys() as $taxonomy) {
			remove_meta_box($taxonomy . 'div', PostType::POST_TYPE, 'side');
			remove_meta_box('tagsdiv-' . $taxonomy, PostType::POST_TYPE, 'side');
		}
	}

	public static function render($post): void
	{
		if (!is_object($post) || !isset($post->ID)) {
			return;
		}

		$postId = (int) $post->ID;
		$state = self::consumeState($postId);
		$values = self::editorValues($postId, $state);
		$errors = is_array($state['errors'] ?? null) ? $state['errors'] : array();
		$taxonomyValues = self::taxonomyValues($postId, $state);

		wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

		echo '<div class="wla-inmo-property-editor">';
		echo '<p class="wla-inmo-property-editor__intro">';
		echo esc_html__('Completa la ficha por secciones. Los datos técnicos se validan antes de guardarse y los campos privados están identificados explícitamente.', 'wla-inmo');
		echo '</p>';

		self::renderErrorSummary($errors);

		foreach (self::sections() as $sectionKey => $section) {
			self::renderSection($sectionKey, $section, $values, $taxonomyValues, $errors, $postId);
		}

		echo '</div>';
	}

	public static function save(int $postId, $post, bool $update): void
	{
		unset($update);

		if (!self::shouldHandleSave($postId, $post)) {
			return;
		}

		if (!self::verifyNonce()) {
			return;
		}

		if (!current_user_can('edit_post', $postId)) {
			return;
		}

		$rawFields = self::submittedFields();
		$rawTaxonomies = self::submittedTaxonomies();
		$errors = self::validateSubmission($rawFields, $rawTaxonomies, $postId);

		if ($errors !== array()) {
			self::storeState($postId, $rawFields, $rawTaxonomies, $errors);
			return;
		}

		$cleanFields = self::sanitizeFields($rawFields);
		$cleanTaxonomies = self::sanitizeTaxonomies($rawTaxonomies);
		$previousMeta = self::snapshotMeta($postId, array_keys($cleanFields));
		$previousTerms = self::snapshotTerms($postId, array_keys($cleanTaxonomies));

		self::persistMeta($postId, $cleanFields);

		$termError = self::persistTaxonomies($postId, $cleanTaxonomies);
		if ($termError !== null) {
			self::restoreMeta($postId, $previousMeta);
			self::restoreTerms($postId, $previousTerms);
			self::storeState(
				$postId,
				$rawFields,
				$rawTaxonomies,
				array('_form' => $termError)
			);
		}
	}

	/**
	 * Validate raw submitted values without mutating WordPress.
	 *
	 * @param array<string,mixed> $rawFields Submitted canonical fields.
	 * @param array<string,mixed> $rawTaxonomies Submitted taxonomy IDs.
	 * @return array<string,string>
	 */
	public static function validateSubmission(array $rawFields, array $rawTaxonomies, int $postId): array
	{
		$errors = Validator::validate($rawFields);

		$code = '';
		if (array_key_exists('property_code', $rawFields)) {
			$code = Sanitizer::text($rawFields['property_code']);
		}

		if ($code !== '') {
			$owner = self::duplicateCodeOwner($code, $postId);
			if ($owner !== null) {
				$errors['property_code'] = 'duplicate_property_code';
			}
		}

		foreach ($rawTaxonomies as $taxonomy => $termId) {
			if (!in_array($taxonomy, TaxonomyRegistry::keys(), true)) {
				$errors['_taxonomy_' . $taxonomy] = 'invalid_taxonomy';
				continue;
			}

			$taxonomyObject = get_taxonomy($taxonomy);
			if (!is_object($taxonomyObject) || !isset($taxonomyObject->cap->assign_terms)) {
				$errors['_taxonomy_' . $taxonomy] = 'invalid_taxonomy';
				continue;
			}

			if (!current_user_can((string) $taxonomyObject->cap->assign_terms)) {
				$errors['_taxonomy_' . $taxonomy] = 'forbidden_taxonomy';
				continue;
			}

			$termId = absint($termId);
			if ($termId < 1) {
				continue;
			}

			if (term_exists($termId, $taxonomy) === null) {
				$errors['_taxonomy_' . $taxonomy] = 'invalid_term';
			}
		}

		return $errors;
	}

	public static function duplicateCodeOwner(string $code, int $postId): ?int
	{
		$code = Sanitizer::text($code);
		if ($code === '') {
			return null;
		}

		$metaKey = MetaSchema::metaKey('property_code');
		if ($metaKey === null) {
			return null;
		}

		$matches = get_posts(
			array(
				'post_type'              => PostType::POST_TYPE,
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'post__not_in'           => $postId > 0 ? array($postId) : array(),
				'meta_key'               => $metaKey,
				'meta_value'             => $code,
				'meta_compare'           => '=',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if (!is_array($matches) || $matches === array()) {
			return null;
		}

		$owner = absint($matches[0]);

		return $owner > 0 ? $owner : null;
	}

	/**
	 * @param array<string,mixed> $rawFields Raw submitted values.
	 * @return array<string,mixed>
	 */
	public static function sanitizeFields(array $rawFields): array
	{
		$definitions = MetaSchema::definitions();
		$clean = array();

		foreach ($rawFields as $field => $value) {
			if (!isset($definitions[$field]) || !in_array($field, self::editableFields(), true)) {
				continue;
			}

			$callback = $definitions[$field]['sanitize_callback'];
			if (!is_callable($callback)) {
				continue;
			}

			$clean[$field] = call_user_func($callback, $value);
		}

		return $clean;
	}

	/**
	 * Presentation-only editor sections. Domain definitions remain MetaSchema.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function sections(): array
	{
		return array(
			'publication' => array(
				'title' => __('1. Estado de publicación', 'wla-inmo'),
				'description' => __('La visibilidad de WordPress se administra en Publicar. Aquí defines el estado comercial y la vigencia de la ficha.', 'wla-inmo'),
				'fields' => array('status', 'featured', 'availability_date', 'last_verified_date'),
			),
			'identification' => array(
				'title' => __('2. Identificación', 'wla-inmo'),
				'description' => __('Código, operación y tipo permiten identificar la propiedad sin depender del título.', 'wla-inmo'),
				'fields' => array('property_code', 'external_id'),
				'taxonomies' => array(TaxonomyRegistry::OPERATION, TaxonomyRegistry::PROPERTY_TYPE),
			),
			'price' => array(
				'title' => __('3. Precio', 'wla-inmo'),
				'description' => __('Los precios se guardan en campos canónicos independientes. La moneda principal determina cuál se muestra primero.', 'wla-inmo'),
				'fields' => array('currency_primary', 'price_clp', 'price_uf', 'price_usd', 'common_expenses_clp', 'price_on_request', 'hide_price'),
			),
			'areas' => array(
				'title' => __('4. Superficies', 'wla-inmo'),
				'description' => __('Ingresa superficies en metros cuadrados, sin símbolos ni unidades dentro del valor.', 'wla-inmo'),
				'fields' => array('land_area_m2', 'built_area_m2', 'usable_area_m2', 'terrace_area_m2'),
			),
			'features' => array(
				'title' => __('5. Características', 'wla-inmo'),
				'description' => __('Características estructuradas para filtros, ficha pública y futuras integraciones.', 'wla-inmo'),
				'fields' => array('bedrooms', 'bathrooms', 'parking', 'storage_units', 'pool', 'heating', 'construction_year', 'orientation'),
			),
			'location' => array(
				'title' => __('6. Ubicación', 'wla-inmo'),
				'description' => __('Distingue la ubicación publicable de la dirección exacta privada. La dirección privada nunca debe salir automáticamente al frontend.', 'wla-inmo'),
				'fields' => array('locality', 'public_address', 'private_address', 'latitude', 'longitude', 'show_map', 'location_text'),
				'taxonomies' => array(TaxonomyRegistry::REGION, TaxonomyRegistry::COMMUNE, TaxonomyRegistry::SECTOR),
			),
			'description' => array(
				'title' => __('7. Descripción', 'wla-inmo'),
				'description' => __('El título y la descripción larga se mantienen en los campos nativos de WordPress ubicados sobre esta ficha. Así conservamos revisiones y compatibilidad editorial.', 'wla-inmo'),
				'fields' => array(),
			),
			'multimedia' => array(
				'title' => __('8. Multimedia', 'wla-inmo'),
				'description' => __('La imagen principal continúa en Imagen destacada. La galería ordenable y los videos se completarán en PR 2.4 sin aceptar HTML o iframes arbitrarios.', 'wla-inmo'),
				'fields' => array(),
			),
			'contact' => array(
				'title' => __('9. Contacto y privacidad', 'wla-inmo'),
				'description' => __('El contacto público se toma de la configuración general de WLA Inmo. Las notas internas de esta propiedad permanecen privadas.', 'wla-inmo'),
				'fields' => array('internal_notes'),
			),
			'seo' => array(
				'title' => __('10. SEO / GEO / AEO', 'wla-inmo'),
				'description' => __('Puedes decidir si la ficha es indexable. La generación completa de metadatos y datos estructurados corresponde a Fase 6.', 'wla-inmo'),
				'fields' => array('indexable'),
			),
			'quality' => array(
				'title' => __('11. Calidad', 'wla-inmo'),
				'description' => __('Los borradores pueden quedar incompletos. PR 2.5 agregará checks explicables y enlaces directos para corregir lo que falte.', 'wla-inmo'),
				'fields' => array(),
			),
			'history' => array(
				'title' => __('12. Historial', 'wla-inmo'),
				'description' => __('WordPress conserva revisiones del contenido. La bitácora inmobiliaria de cambios críticos llegará en PR 2.8.', 'wla-inmo'),
				'fields' => array(),
			),
		);
	}

	/**
	 * UI controls only. Type, sanitization, visibility and defaults are owned by MetaSchema.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function controls(): array
	{
		return array(
			'property_code' => self::control(__('Código de propiedad', 'wla-inmo'), 'text', __('Ej.: 001254. Debe ser único entre propiedades activas y borradores.', 'wla-inmo')),
			'external_id' => self::control(__('ID externo', 'wla-inmo'), 'text', __('Dato interno para futuras integraciones; no se publica.', 'wla-inmo'), '', true),
			'status' => self::control(__('Estado comercial', 'wla-inmo'), 'select', __('Independiente del estado de publicación de WordPress.', 'wla-inmo'), '', false, self::statusOptions()),
			'featured' => self::control(__('Propiedad destacada', 'wla-inmo'), 'checkbox', __('Permite priorizarla en bloques editoriales compatibles.', 'wla-inmo')),
			'availability_date' => self::control(__('Disponible desde', 'wla-inmo'), 'date', __('Fecha opcional de disponibilidad.', 'wla-inmo')),
			'last_verified_date' => self::control(__('Última verificación', 'wla-inmo'), 'date', __('Fecha en que se confirmó por última vez precio/disponibilidad.', 'wla-inmo')),
			'currency_primary' => self::control(__('Moneda principal', 'wla-inmo'), 'select', __('Moneda prioritaria al presentar el precio.', 'wla-inmo'), '', false, array('' => __('Seleccionar', 'wla-inmo'), 'CLP' => 'CLP', 'UF' => 'UF', 'USD' => 'USD')),
			'price_clp' => self::control(__('Precio CLP', 'wla-inmo'), 'number', __('Pesos chilenos, sin puntos ni símbolo $.', 'wla-inmo'), '1'),
			'price_uf' => self::control(__('Precio UF', 'wla-inmo'), 'number', __('Valor UF real ingresado para esta propiedad.', 'wla-inmo'), '0.01'),
			'price_usd' => self::control(__('Precio USD', 'wla-inmo'), 'number', __('Dólares estadounidenses.', 'wla-inmo'), '0.01'),
			'common_expenses_clp' => self::control(__('Gastos comunes CLP', 'wla-inmo'), 'number', __('Monto mensual opcional.', 'wla-inmo'), '1'),
			'price_on_request' => self::control(__('Precio a consultar', 'wla-inmo'), 'checkbox', __('Muestra “A consultar” en lugar del valor numérico.', 'wla-inmo')),
			'hide_price' => self::control(__('Ocultar precio', 'wla-inmo'), 'checkbox', __('Oculta deliberadamente el precio publicado.', 'wla-inmo')),
			'land_area_m2' => self::control(__('Terreno m²', 'wla-inmo'), 'number', '', '0.01'),
			'built_area_m2' => self::control(__('Construidos m²', 'wla-inmo'), 'number', '', '0.01'),
			'usable_area_m2' => self::control(__('Útiles m²', 'wla-inmo'), 'number', '', '0.01'),
			'terrace_area_m2' => self::control(__('Terraza m²', 'wla-inmo'), 'number', '', '0.01'),
			'bedrooms' => self::control(__('Dormitorios', 'wla-inmo'), 'number', '', '1'),
			'bathrooms' => self::control(__('Baños', 'wla-inmo'), 'number', '', '1'),
			'parking' => self::control(__('Estacionamientos', 'wla-inmo'), 'number', '', '1'),
			'storage_units' => self::control(__('Bodegas', 'wla-inmo'), 'number', '', '1'),
			'pool' => self::control(__('Piscina', 'wla-inmo'), 'checkbox', __('Marca si la propiedad dispone de piscina.', 'wla-inmo')),
			'heating' => self::control(__('Calefacción', 'wla-inmo'), 'text', __('Ej.: central, combustión lenta, eléctrica.', 'wla-inmo')),
			'construction_year' => self::control(__('Año de construcción', 'wla-inmo'), 'number', '', '1'),
			'orientation' => self::control(__('Orientación', 'wla-inmo'), 'text', __('Ej.: norte, nororiente.', 'wla-inmo')),
			'locality' => self::control(__('Ciudad / localidad', 'wla-inmo'), 'text', __('Complementa región, comuna y sector.', 'wla-inmo')),
			'public_address' => self::control(__('Dirección pública', 'wla-inmo'), 'text', __('Versión segura que puede mostrarse a visitantes.', 'wla-inmo')),
			'private_address' => self::control(__('Dirección exacta privada', 'wla-inmo'), 'text', __('Uso interno. Nunca se expone automáticamente.', 'wla-inmo'), '', true),
			'latitude' => self::control(__('Latitud', 'wla-inmo'), 'number', __('Entre -90 y 90.', 'wla-inmo'), '0.0000001'),
			'longitude' => self::control(__('Longitud', 'wla-inmo'), 'number', __('Entre -180 y 180.', 'wla-inmo'), '0.0000001'),
			'show_map' => self::control(__('Mostrar mapa', 'wla-inmo'), 'checkbox', __('Habilita el uso público de las coordenadas cuando el frontend lo soporte.', 'wla-inmo')),
			'location_text' => self::control(__('Texto de ubicación', 'wla-inmo'), 'textarea', __('Descripción breve del sector y entorno.', 'wla-inmo')),
			'internal_notes' => self::control(__('Notas internas', 'wla-inmo'), 'textarea', __('Solo administración. No se publica ni se expone por REST público.', 'wla-inmo'), '', true),
			'indexable' => self::control(__('Permitir indexación', 'wla-inmo'), 'checkbox', __('Decisión editorial interna que el módulo SEO respetará.', 'wla-inmo')),
		);
	}

	/** @return array<int,string> */
	public static function editableFields(): array
	{
		return array_keys(self::controls());
	}

	private static function renderSection(
		string $sectionKey,
		array $section,
		array $values,
		array $taxonomyValues,
		array $errors,
		int $postId
	): void {
		$open = in_array($sectionKey, array('publication', 'identification', 'price'), true);
		echo '<details class="wla-inmo-property-editor__section"' . ($open ? ' open' : '') . '>';
		echo '<summary><strong>' . esc_html((string) $section['title']) . '</strong></summary>';
		echo '<div class="wla-inmo-property-editor__section-body">';
		echo '<p class="description">' . esc_html((string) $section['description']) . '</p>';

		$fields = is_array($section['fields'] ?? null) ? $section['fields'] : array();
		$taxonomies = is_array($section['taxonomies'] ?? null) ? $section['taxonomies'] : array();

		if ($fields !== array() || $taxonomies !== array()) {
			echo '<div class="wla-inmo-property-editor__grid">';

			foreach ($taxonomies as $taxonomy) {
				self::renderTaxonomyControl((string) $taxonomy, $taxonomyValues, $errors, $postId);
			}

			foreach ($fields as $field) {
				self::renderField((string) $field, $values, $errors);
			}

			echo '</div>';
		}

		echo '</div>';
		echo '</details>';
	}

	private static function renderField(string $field, array $values, array $errors): void
	{
		$controls = self::controls();
		if (!isset($controls[$field]) || MetaSchema::metaKey($field) === null) {
			return;
		}

		$control = $controls[$field];
		$value = $values[$field] ?? '';
		$error = isset($errors[$field]) ? self::errorMessage($field, (string) $errors[$field]) : '';
		$id = 'wla-inmo-field-' . $field;
		$name = self::FIELD_ROOT . '[' . $field . ']';
		$classes = 'wla-inmo-property-editor__field';
		if (!empty($control['private'])) {
			$classes .= ' wla-inmo-property-editor__field--private';
		}
		if ($error !== '') {
			$classes .= ' wla-inmo-property-editor__field--error';
		}

		echo '<div class="' . esc_attr($classes) . '">';
		echo '<label for="' . esc_attr($id) . '"><strong>' . esc_html((string) $control['label']) . '</strong>';
		if (!empty($control['private'])) {
			echo ' <span class="wla-inmo-property-editor__private-badge">' . esc_html__('Privado', 'wla-inmo') . '</span>';
		}
		echo '</label>';

		$type = (string) $control['input'];
		if ($type === 'checkbox') {
			echo '<input type="hidden" name="' . esc_attr($name) . '" value="0">';
			echo '<label class="wla-inmo-property-editor__checkbox" for="' . esc_attr($id) . '">';
			echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="1"' . checked(self::isTruthy($value), true, false) . '> ';
			echo esc_html__('Sí', 'wla-inmo');
			echo '</label>';
		} elseif ($type === 'select') {
			echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"' . self::ariaInvalid($error) . '>';
			$options = is_array($control['options'] ?? null) ? $control['options'] : array();
			$current = is_scalar($value) ? (string) $value : '';
			if ($current !== '' && !array_key_exists($current, $options)) {
				$options[$current] = $current;
			}
			foreach ($options as $optionValue => $optionLabel) {
				echo '<option value="' . esc_attr((string) $optionValue) . '"' . selected($current, (string) $optionValue, false) . '>' . esc_html((string) $optionLabel) . '</option>';
			}
			echo '</select>';
		} elseif ($type === 'textarea') {
			echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" rows="4"' . self::ariaInvalid($error) . '>' . esc_textarea(is_scalar($value) ? (string) $value : '') . '</textarea>';
		} else {
			$step = (string) ($control['step'] ?? '');
			echo '<input class="regular-text" type="' . esc_attr($type) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr(is_scalar($value) ? (string) $value : '') . '"';
			if ($step !== '') {
				echo ' step="' . esc_attr($step) . '"';
			}
			if ($type === 'number') {
				echo ' min="0"';
			}
			echo self::ariaInvalid($error) . '>';
		}

		if ((string) $control['help'] !== '') {
			echo '<p class="description">' . esc_html((string) $control['help']) . '</p>';
		}

		if ($error !== '') {
			echo '<p class="wla-inmo-property-editor__error" role="alert">' . esc_html($error) . '</p>';
		}

		echo '</div>';
	}

	private static function renderTaxonomyControl(string $taxonomy, array $values, array $errors, int $postId): void
	{
		$taxonomyObject = get_taxonomy($taxonomy);
		if (!is_object($taxonomyObject)) {
			return;
		}

		$current = absint($values[$taxonomy] ?? 0);
		$errorKey = '_taxonomy_' . $taxonomy;
		$error = isset($errors[$errorKey]) ? self::errorMessage($errorKey, (string) $errors[$errorKey]) : '';
		$canAssign = isset($taxonomyObject->cap->assign_terms) && current_user_can((string) $taxonomyObject->cap->assign_terms);
		$label = isset($taxonomyObject->labels->singular_name) ? (string) $taxonomyObject->labels->singular_name : $taxonomy;
		$id = 'wla-inmo-taxonomy-' . $taxonomy;

		echo '<div class="wla-inmo-property-editor__field' . ($error !== '' ? ' wla-inmo-property-editor__field--error' : '') . '">';
		echo '<label for="' . esc_attr($id) . '"><strong>' . esc_html($label) . '</strong></label>';

		if (!$canAssign) {
			$names = wp_get_object_terms($postId, $taxonomy, array('fields' => 'names'));
			$text = is_array($names) && $names !== array() ? implode(', ', array_map('strval', $names)) : __('Sin asignar', 'wla-inmo');
			echo '<p>' . esc_html($text) . '</p>';
			echo '</div>';
			return;
		}

		wp_dropdown_categories(
			array(
				'taxonomy' => $taxonomy,
				'name' => self::TAXONOMY_ROOT . '[' . $taxonomy . ']',
				'id' => $id,
				'show_option_none' => __('Sin asignar', 'wla-inmo'),
				'option_none_value' => '0',
				'hide_empty' => false,
				'hierarchical' => is_taxonomy_hierarchical($taxonomy),
				'value_field' => 'term_id',
				'orderby' => 'name',
				'selected' => $current,
			)
		);

		if ($error !== '') {
			echo '<p class="wla-inmo-property-editor__error" role="alert">' . esc_html($error) . '</p>';
		}

		echo '</div>';
	}

	private static function renderErrorSummary(array $errors): void
	{
		if ($errors === array()) {
			return;
		}

		echo '<div class="notice notice-error inline wla-inmo-property-editor__error-summary" role="alert">';
		echo '<p><strong>' . esc_html__('No se guardaron los campos WLA Inmo porque hay datos que debes revisar.', 'wla-inmo') . '</strong></p>';
		echo '<ul>';
		foreach ($errors as $field => $code) {
			echo '<li>' . esc_html(self::errorMessage((string) $field, (string) $code)) . '</li>';
		}
		echo '</ul>';
		echo '<p>' . esc_html__('El título, descripción u otros campos nativos de WordPress pueden haber sido guardados por WordPress; los campos inmobiliarios WLA se mantuvieron sin cambios.', 'wla-inmo') . '</p>';
		echo '</div>';
	}

	private static function errorMessage(string $field, string $code): string
	{
		$controls = self::controls();
		$label = isset($controls[$field]['label']) ? (string) $controls[$field]['label'] : __('Ficha', 'wla-inmo');

		$messages = array(
			'duplicate_property_code' => __('Ese código ya está siendo usado por otra propiedad.', 'wla-inmo'),
			'invalid_text' => __('El valor debe ser texto.', 'wla-inmo'),
			'too_long' => __('El texto supera el largo permitido.', 'wla-inmo'),
			'invalid_key' => __('El valor contiene un identificador no válido.', 'wla-inmo'),
			'unsupported_currency' => __('Selecciona CLP, UF o USD.', 'wla-inmo'),
			'invalid_non_negative_integer' => __('Ingresa un número entero igual o mayor que cero.', 'wla-inmo'),
			'invalid_non_negative_number' => __('Ingresa un número igual o mayor que cero.', 'wla-inmo'),
			'invalid_latitude' => __('La latitud debe estar entre -90 y 90.', 'wla-inmo'),
			'invalid_longitude' => __('La longitud debe estar entre -180 y 180.', 'wla-inmo'),
			'invalid_date' => __('Ingresa una fecha válida.', 'wla-inmo'),
			'invalid_year' => __('Ingresa un año de construcción válido.', 'wla-inmo'),
			'invalid_taxonomy' => __('La clasificación enviada no es válida.', 'wla-inmo'),
			'forbidden_taxonomy' => __('No tienes permisos para cambiar esta clasificación.', 'wla-inmo'),
			'invalid_term' => __('La opción seleccionada ya no existe o no corresponde a esta clasificación.', 'wla-inmo'),
			'taxonomy_write_failed' => __('No fue posible guardar una clasificación. Los campos WLA fueron restaurados.', 'wla-inmo'),
		);

		$message = $messages[$code] ?? __('Revisa este valor.', 'wla-inmo');

		return sprintf(__('%1$s: %2$s', 'wla-inmo'), $label, $message);
	}

	private static function shouldHandleSave(int $postId, $post): bool
	{
		if ($postId < 1 || !is_object($post) || !isset($post->post_type) || $post->post_type !== PostType::POST_TYPE) {
			return false;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return false;
		}

		if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
			return false;
		}

		return true;
	}

	private static function verifyNonce(): bool
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce is verified immediately after extraction.
		$nonce = isset($_POST[self::NONCE_NAME]) ? wp_unslash($_POST[self::NONCE_NAME]) : '';
		$nonce = is_scalar($nonce) ? sanitize_text_field((string) $nonce) : '';

		return $nonce !== '' && wp_verify_nonce($nonce, self::NONCE_ACTION) !== false;
	}

	/** @return array<string,mixed> */
	private static function submittedFields(): array
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is checked before this helper; domain validation/sanitization follows before persistence.
		$raw = isset($_POST[self::FIELD_ROOT]) ? wp_unslash($_POST[self::FIELD_ROOT]) : array();
		if (!is_array($raw)) {
			return array();
		}

		$submitted = array();
		foreach (self::editableFields() as $field) {
			if (!array_key_exists($field, $raw)) {
				continue;
			}

			$value = $raw[$field];
			if (is_scalar($value) || $value === null || is_array($value)) {
				$submitted[$field] = $value;
			}
		}

		return $submitted;
	}

	/** @return array<string,mixed> */
	private static function submittedTaxonomies(): array
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is checked before this helper; IDs are allowlisted/absint validated before persistence.
		$raw = isset($_POST[self::TAXONOMY_ROOT]) ? wp_unslash($_POST[self::TAXONOMY_ROOT]) : array();
		if (!is_array($raw)) {
			return array();
		}

		$submitted = array();
		foreach (TaxonomyRegistry::keys() as $taxonomy) {
			if (array_key_exists($taxonomy, $raw) && is_scalar($raw[$taxonomy])) {
				$submitted[$taxonomy] = $raw[$taxonomy];
			}
		}

		return $submitted;
	}

	/** @return array<string,int> */
	private static function sanitizeTaxonomies(array $rawTaxonomies): array
	{
		$clean = array();
		foreach ($rawTaxonomies as $taxonomy => $termId) {
			if (in_array($taxonomy, TaxonomyRegistry::keys(), true)) {
				$clean[$taxonomy] = absint($termId);
			}
		}

		return $clean;
	}

	private static function persistMeta(int $postId, array $fields): void
	{
		foreach ($fields as $field => $value) {
			$metaKey = MetaSchema::metaKey($field);
			if ($metaKey === null) {
				continue;
			}

			if ($value === null || $value === '' || $value === array()) {
				delete_post_meta($postId, $metaKey);
				continue;
			}

			update_post_meta($postId, $metaKey, $value);
		}
	}

	private static function persistTaxonomies(int $postId, array $taxonomies): ?string
	{
		foreach ($taxonomies as $taxonomy => $termId) {
			$terms = $termId > 0 ? array($termId) : array();
			$result = wp_set_object_terms($postId, $terms, $taxonomy, false);
			if (is_wp_error($result)) {
				return 'taxonomy_write_failed';
			}
		}

		return null;
	}

	/** @return array<string,array{exists:bool,value:mixed}> */
	private static function snapshotMeta(int $postId, array $fields): array
	{
		$snapshot = array();
		foreach ($fields as $field) {
			$metaKey = MetaSchema::metaKey((string) $field);
			if ($metaKey === null) {
				continue;
			}

			$snapshot[(string) $field] = array(
				'exists' => metadata_exists('post', $postId, $metaKey),
				'value' => get_post_meta($postId, $metaKey, true),
			);
		}

		return $snapshot;
	}

	/** @return array<string,array<int,int>> */
	private static function snapshotTerms(int $postId, array $taxonomies): array
	{
		$snapshot = array();
		foreach ($taxonomies as $taxonomy) {
			$ids = wp_get_object_terms($postId, (string) $taxonomy, array('fields' => 'ids'));
			$snapshot[(string) $taxonomy] = is_array($ids) ? array_map('absint', $ids) : array();
		}

		return $snapshot;
	}

	private static function restoreMeta(int $postId, array $snapshot): void
	{
		foreach ($snapshot as $field => $previous) {
			$metaKey = MetaSchema::metaKey((string) $field);
			if ($metaKey === null) {
				continue;
			}

			if (!empty($previous['exists'])) {
				update_post_meta($postId, $metaKey, $previous['value'] ?? '');
			} else {
				delete_post_meta($postId, $metaKey);
			}
		}
	}

	private static function restoreTerms(int $postId, array $snapshot): void
	{
		foreach ($snapshot as $taxonomy => $termIds) {
			wp_set_object_terms($postId, $termIds, (string) $taxonomy, false);
		}
	}

	private static function storeState(int $postId, array $fields, array $taxonomies, array $errors): void
	{
		$userId = get_current_user_id();
		if ($userId < 1) {
			return;
		}

		set_transient(
			self::stateKey($userId, $postId),
			array(
				'fields' => self::safeDisplayFields($fields),
				'taxonomies' => self::sanitizeTaxonomies($taxonomies),
				'errors' => $errors,
			),
			5 * MINUTE_IN_SECONDS
		);
	}

	/** @return array<string,mixed> */
	private static function consumeState(int $postId): array
	{
		$userId = get_current_user_id();
		if ($userId < 1) {
			return array();
		}

		$key = self::stateKey($userId, $postId);
		$state = get_transient($key);
		delete_transient($key);

		return is_array($state) ? $state : array();
	}

	private static function stateKey(int $userId, int $postId): string
	{
		return self::STATE_PREFIX . $userId . '_' . $postId;
	}

	/** @return array<string,mixed> */
	private static function editorValues(int $postId, array $state): array
	{
		if (isset($state['fields']) && is_array($state['fields'])) {
			return $state['fields'];
		}

		$values = array();
		foreach (self::editableFields() as $field) {
			$metaKey = MetaSchema::metaKey($field);
			if ($metaKey === null) {
				continue;
			}

			$values[$field] = get_post_meta($postId, $metaKey, true);
		}

		return $values;
	}

	/** @return array<string,int> */
	private static function taxonomyValues(int $postId, array $state): array
	{
		if (isset($state['taxonomies']) && is_array($state['taxonomies'])) {
			return self::sanitizeTaxonomies($state['taxonomies']);
		}

		$values = array();
		foreach (TaxonomyRegistry::keys() as $taxonomy) {
			$ids = wp_get_object_terms($postId, $taxonomy, array('fields' => 'ids'));
			$values[$taxonomy] = is_array($ids) && $ids !== array() ? absint($ids[0]) : 0;
		}

		return $values;
	}

	/** @return array<string,mixed> */
	private static function safeDisplayFields(array $fields): array
	{
		$controls = self::controls();
		$safe = array();

		foreach ($fields as $field => $value) {
			if (!isset($controls[$field])) {
				continue;
			}

			if (!is_scalar($value) && $value !== null) {
				continue;
			}

			if ((string) $controls[$field]['input'] === 'textarea') {
				$safe[$field] = sanitize_textarea_field((string) $value);
			} else {
				$safe[$field] = sanitize_text_field((string) $value);
			}
		}

		return $safe;
	}

	/** @return array<string,string> */
	private static function statusOptions(): array
	{
		return array(
			'' => __('Sin estado comercial', 'wla-inmo'),
			'available' => __('Disponible', 'wla-inmo'),
			'reserved' => __('Reservada', 'wla-inmo'),
			'sold' => __('Vendida', 'wla-inmo'),
			'rented' => __('Arrendada', 'wla-inmo'),
			'unavailable' => __('No disponible', 'wla-inmo'),
		);
	}

	private static function control(
		string $label,
		string $input,
		string $help = '',
		string $step = '',
		bool $private = false,
		array $options = array()
	): array {
		return array(
			'label' => $label,
			'input' => $input,
			'help' => $help,
			'step' => $step,
			'private' => $private,
			'options' => $options,
		);
	}

	private static function isTruthy($value): bool
	{
		return Sanitizer::boolean($value);
	}

	private static function ariaInvalid(string $error): string
	{
		return $error === '' ? '' : ' aria-invalid="true"';
	}
}
