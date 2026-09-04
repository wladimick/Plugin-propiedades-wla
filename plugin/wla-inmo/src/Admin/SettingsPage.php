<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Access\Capabilities;
use WLA\Inmo\Settings\Repository as SettingsRepository;
use WLA\Inmo\Settings\RewriteManager;
use WLA\Inmo\Settings\Schema as SettingsSchema;

final class SettingsPage
{
	private const ACTION_SAVE = 'wla_inmo_save_settings';
	private const NONCE_ACTION = 'wla_inmo_save_settings';
	private const NONCE_NAME = 'wla_inmo_settings_nonce';

	public static function register(): void
	{
		add_action('admin_init', array(self::class, 'handleSave'), 20);
	}

	/** @return array<string,string> */
	public static function tabs(): array
	{
		return array(
			'general'      => __('General', 'wla-inmo'),
			'properties'   => __('Propiedades', 'wla-inmo'),
			'contact'      => __('Contacto', 'wla-inmo'),
			'seo'          => __('SEO', 'wla-inmo'),
			'integrations' => __('Integraciones', 'wla-inmo'),
			'performance'  => __('Rendimiento', 'wla-inmo'),
			'privacy'      => __('Privacidad', 'wla-inmo'),
			'advanced'     => __('Avanzado', 'wla-inmo'),
		);
	}

	/** @return array<int,string> */
	public static function fieldsForTab(string $tab): array
	{
		$fields = array(
			'general'      => array('business_name', 'country_code', 'currency_primary', 'area_unit'),
			'properties'   => array('property_base'),
			'contact'      => array('business_email', 'business_phone', 'whatsapp_number', 'business_address'),
			'integrations' => array('map_provider'),
			'privacy'      => array('lead_retention_months', 'activity_retention_months'),
		);

		return $fields[$tab] ?? array();
	}

	public static function handleSave(): void
	{
		if (!isset($_POST['wla_inmo_settings_action'])) {
			return;
		}

		$action = sanitize_key(wp_unslash((string) $_POST['wla_inmo_settings_action']));
		if ($action !== self::ACTION_SAVE) {
			return;
		}

		if (!current_user_can(Capabilities::MANAGE_SETTINGS)) {
			wp_die(esc_html__('No tienes permisos para modificar los ajustes de WLA Inmo.', 'wla-inmo'));
		}

		$nonce = isset($_POST[self::NONCE_NAME]) ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME])) : '';
		if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
			wp_die(esc_html__('La solicitud para guardar los ajustes no es válida. Vuelve a intentarlo.', 'wla-inmo'));
		}

		$tab = isset($_POST['wla_inmo_settings_tab']) ? sanitize_key(wp_unslash((string) $_POST['wla_inmo_settings_tab'])) : 'general';
		if (!isset(self::tabs()[$tab])) {
			$tab = 'general';
		}

		$allowed = self::fieldsForTab($tab);
		if (empty($allowed)) {
			self::redirect($tab, 'nothing-to-save');
		}

		// Nested settings are sanitized field-by-field through SettingsSchema after this structural check.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$submittedRaw = isset($_POST['wla_inmo_settings']) ? wp_unslash($_POST['wla_inmo_settings']) : array();
		$submitted = is_array($submittedRaw) ? $submittedRaw : array();
		$current = SettingsRepository::all();
		$merged = $current;

		foreach ($allowed as $key) {
			if (!array_key_exists($key, $submitted)) {
				continue;
			}

			$value = $submitted[$key];
			$merged[$key] = is_scalar($value) || $value === null ? (string) $value : '';
		}

		$sanitized = SettingsSchema::sanitize($merged);
		if ($tab === 'contact') {
			$rawEmail = isset($submitted['business_email']) && is_scalar($submitted['business_email']) ? trim((string) $submitted['business_email']) : '';
			if ($rawEmail !== '' && $sanitized['business_email'] === '') {
				self::redirect($tab, 'invalid-email');
			}
		}

		$changed = array();
		foreach ($sanitized as $key => $value) {
			if (($current[$key] ?? null) !== $value) {
				$changed[] = $key;
			}
		}

		update_option(SettingsSchema::OPTION_NAME, $sanitized, false);
		SettingsRepository::resetCache();

		/**
		 * Fires after an authorized settings update.
		 *
		 * @param array<string,string> $sanitized New canonical settings.
		 * @param array<string,string> $current Previous canonical settings.
		 * @param array<int,string>    $changed Changed setting keys.
		 * @param string               $tab Source tab.
		 */
		do_action('wla_inmo_settings_saved', $sanitized, $current, $changed, $tab);

		self::redirect($tab, 'saved');
	}

	public static function render(): void
	{
		if (!current_user_can(Capabilities::MANAGE_SETTINGS)) {
			wp_die(esc_html__('No tienes permisos para ver los ajustes de WLA Inmo.', 'wla-inmo'));
		}

		$tab = self::requestedTab();
		$settings = SettingsRepository::all();

		self::renderNotices();
		self::renderTabs($tab);

		echo '<div class="wla-inmo-settings">';
		switch ($tab) {
			case 'properties':
				self::renderProperties($settings);
				break;
			case 'contact':
				self::renderContact($settings);
				break;
			case 'seo':
				self::renderSeo($settings);
				break;
			case 'integrations':
				self::renderIntegrations($settings);
				break;
			case 'performance':
				self::renderPerformance();
				break;
			case 'privacy':
				self::renderPrivacy($settings);
				break;
			case 'advanced':
				self::renderAdvanced($settings);
				break;
			case 'general':
			default:
				self::renderGeneral($settings);
				break;
		}
		echo '</div>';
	}

	private static function renderGeneral(array $settings): void
	{
		self::openForm('general', __('Datos generales', 'wla-inmo'), __('Información base que otros módulos y el frontend podrán reutilizar.', 'wla-inmo'));
		self::textField('business_name', __('Nombre de la inmobiliaria', 'wla-inmo'), $settings['business_name'], __('Nombre comercial público. No cambia el nombre técnico del plugin.', 'wla-inmo'));
		self::textField('country_code', __('País', 'wla-inmo'), $settings['country_code'], __('Código ISO de dos letras. Chile usa CL.', 'wla-inmo'), array('maxlength' => '2', 'class' => 'small-text'));
		self::textField('currency_primary', __('Moneda principal', 'wla-inmo'), $settings['currency_primary'], __('Código de tres letras, por ejemplo CLP o USD. Las propiedades pueden guardar también UF/USD según su ficha.', 'wla-inmo'), array('maxlength' => '3', 'class' => 'small-text'));
		self::selectField(
			'area_unit',
			__('Unidad de superficie', 'wla-inmo'),
			$settings['area_unit'],
			array('m2' => __('Metros cuadrados (m²)', 'wla-inmo'), 'ft2' => __('Pies cuadrados (ft²)', 'wla-inmo')),
			__('Define la unidad de referencia de la instalación.', 'wla-inmo')
		);
		self::closeForm();
	}

	private static function renderProperties(array $settings): void
	{
		self::openForm('properties', __('URLs de propiedades', 'wla-inmo'), __('Controla la base pública usada por el archivo y las fichas individuales.', 'wla-inmo'));
		echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__('Cambio sensible:', 'wla-inmo') . '</strong> ' . esc_html__('modificar esta base cambia las URLs públicas. Hazlo solo de forma planificada y conserva redirecciones cuando el sitio ya esté indexado.', 'wla-inmo') . '</p></div>';
		self::textField('property_base', __('Base de URL', 'wla-inmo'), $settings['property_base'], __('Ejemplo: “propiedades” produce rutas bajo /propiedades/. Al cambiarla, WLA Inmo dejará las reglas de enlaces pendientes hasta que las apliques en Avanzado.', 'wla-inmo'));
		self::closeForm(__('Guardar base de URL', 'wla-inmo'));

		if (RewriteManager::isPending()) {
			echo '<section class="wla-inmo-admin__panel wla-inmo-settings__pending">';
			echo '<h2>' . esc_html__('Reglas de enlaces pendientes', 'wla-inmo') . '</h2>';
			echo '<p>' . esc_html__('La nueva base ya está guardada. Antes de validar las URLs públicas, aplica las reglas desde la pestaña Avanzado en una solicitud separada.', 'wla-inmo') . '</p>';
			echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=wla-inmo-settings&tab=advanced')) . '">' . esc_html__('Ir a Avanzado', 'wla-inmo') . '</a></p>';
			echo '</section>';
		}
	}

	private static function renderContact(array $settings): void
	{
		self::openForm('contact', __('Datos de contacto', 'wla-inmo'), __('Información pública de la inmobiliaria que podrá reutilizarse en fichas, botones de contacto y leads futuros.', 'wla-inmo'));
		self::textField('business_email', __('Email público', 'wla-inmo'), $settings['business_email'], __('Correo general de contacto.', 'wla-inmo'), array('type' => 'email'));
		self::textField('business_phone', __('Teléfono', 'wla-inmo'), $settings['business_phone'], __('Puede incluir código de país y formato legible.', 'wla-inmo'), array('type' => 'tel'));
		self::textField('whatsapp_number', __('WhatsApp', 'wla-inmo'), $settings['whatsapp_number'], __('Idealmente en formato internacional, por ejemplo +56912345678.', 'wla-inmo'), array('type' => 'tel'));
		self::textField('business_address', __('Dirección pública de la inmobiliaria', 'wla-inmo'), $settings['business_address'], __('Dirección de oficina o atención. Es independiente de las direcciones privadas de cada propiedad.', 'wla-inmo'));
		self::closeForm();
	}

	private static function renderSeo(array $settings): void
	{
		echo '<section class="wla-inmo-admin__panel">';
		echo '<h2>' . esc_html__('Preparación SEO / GEO / AEO', 'wla-inmo') . '</h2>';
		echo '<p>' . esc_html__('El módulo técnico completo corresponde a Fase 6. Esta pantalla no inventa un “score SEO” ni activa metadatos parciales que puedan duplicar otro plugin.', 'wla-inmo') . '</p>';
		echo '<dl class="wla-inmo-settings__status-list">';
		self::statusItem(__('Base de propiedades', 'wla-inmo'), '/' . $settings['property_base'] . '/');
		self::statusItem(__('Nombre comercial', 'wla-inmo'), $settings['business_name'] !== '' ? $settings['business_name'] : __('Pendiente', 'wla-inmo'));
		self::statusItem(__('Datos de contacto', 'wla-inmo'), ($settings['business_email'] !== '' || $settings['business_phone'] !== '') ? __('Configurados', 'wla-inmo') : __('Pendientes', 'wla-inmo'));
		echo '</dl>';
		echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=wla-inmo-help#wla-help-seo-basico')) . '">' . esc_html__('Ver guía SEO básica', 'wla-inmo') . '</a></p>';
		echo '</section>';
	}

	private static function renderIntegrations(array $settings): void
	{
		self::openForm('integrations', __('Mapas', 'wla-inmo'), __('Selecciona el proveedor de referencia. El frontend final consumirá este contrato mediante un adapter desacoplado.', 'wla-inmo'));
		self::selectField(
			'map_provider',
			__('Proveedor de mapas', 'wla-inmo'),
			$settings['map_provider'],
			array(
				'osm'    => __('OpenStreetMap (referencia inicial)', 'wla-inmo'),
				'google' => __('Google Maps (adapter opcional futuro)', 'wla-inmo'),
				'none'   => __('Sin mapas', 'wla-inmo'),
			),
			__('Elegir Google aquí no instala credenciales ni activa llamadas remotas; la integración efectiva se implementará en su fase correspondiente.', 'wla-inmo')
		);
		self::closeForm();
	}

	private static function renderPerformance(): void
	{
		echo '<section class="wla-inmo-admin__panel">';
		echo '<h2>' . esc_html__('Rendimiento por diseño', 'wla-inmo') . '</h2>';
		echo '<p>' . esc_html__('WLA Inmo evita controles “mágicos” de optimización que oculten problemas. Las decisiones de performance se validan con budgets y pruebas reales.', 'wla-inmo') . '</p>';
		echo '<ul class="wla-inmo-settings__check-list">';
		foreach (array(
			__('Assets administrativos cargados solo en contextos WLA.', 'wla-inmo'),
			__('Índice público separado de postmeta para filtros de alto uso.', 'wla-inmo'),
			__('Proyección de Calidad separada para trabajo administrativo.', 'wla-inmo'),
			__('Frontend final y Core Web Vitals se medirán en Fases 4–5 y el Quality Gate.', 'wla-inmo'),
		) as $item) {
			echo '<li>' . esc_html($item) . '</li>';
		}
		echo '</ul>';
		echo '</section>';
	}

	private static function renderPrivacy(array $settings): void
	{
		self::openForm('privacy', __('Retención de datos', 'wla-inmo'), __('Define políticas de referencia ahora; se aplicarán cuando los módulos respectivos estén activos.', 'wla-inmo'));
		self::textField('lead_retention_months', __('Retención de leads', 'wla-inmo'), $settings['lead_retention_months'], __('Referencia inicial: 24 meses. El módulo de leads se implementa en Fase 7.', 'wla-inmo'), array('type' => 'number', 'min' => '1', 'max' => '120', 'class' => 'small-text'));
		self::textField('activity_retention_months', __('Retención de actividad', 'wla-inmo'), $settings['activity_retention_months'], __('Referencia inicial: 12 meses. La bitácora base se implementa en PR 2.8.', 'wla-inmo'), array('type' => 'number', 'min' => '1', 'max' => '120', 'class' => 'small-text'));
		self::closeForm();
		echo '<div class="notice notice-info inline"><p>' . esc_html__('Guardar estos valores no borra datos ahora. Son el contrato que los módulos futuros deberán respetar.', 'wla-inmo') . '</p></div>';
	}

	private static function renderAdvanced(array $settings): void
	{
		echo '<section class="wla-inmo-admin__panel">';
		echo '<h2>' . esc_html__('Reglas de enlaces', 'wla-inmo') . '</h2>';
		$pending = RewriteManager::pendingBase();
		if ($pending !== null) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html(sprintf(__('Hay un cambio pendiente para la base “%s”. El CPT ya se registró con este valor en esta solicitud; ahora es seguro regenerar las reglas una sola vez.', 'wla-inmo'), $pending)) . '</p></div>';
			RewriteManager::renderApplyButton();
		} else {
			echo '<p>' . esc_html__('No hay cambios de URL pendientes.', 'wla-inmo') . '</p>';
		}
		echo '</section>';

		echo '<section class="wla-inmo-admin__panel">';
		echo '<h2>' . esc_html__('Estado técnico', 'wla-inmo') . '</h2>';
		echo '<dl class="wla-inmo-settings__status-list">';
		self::statusItem(__('Versión WLA Inmo', 'wla-inmo'), defined('WLA_INMO_VERSION') ? WLA_INMO_VERSION : '—');
		self::statusItem(__('Base de propiedades activa', 'wla-inmo'), $settings['property_base']);
		self::statusItem(__('Proveedor de mapas', 'wla-inmo'), $settings['map_provider']);
		self::statusItem(__('PHP', 'wla-inmo'), PHP_VERSION);
		echo '</dl>';
		echo '<p>' . esc_html__('Esta sección no modifica wp-config.php, WP_DEBUG, contraseñas ni secrets. El diagnóstico exportable sanitizado se desarrolla en una fase posterior.', 'wla-inmo') . '</p>';
		echo '</section>';
	}

	private static function openForm(string $tab, string $title, string $description): void
	{
		echo '<form method="post" class="wla-inmo-settings__form">';
		wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
		echo '<input type="hidden" name="wla_inmo_settings_action" value="' . esc_attr(self::ACTION_SAVE) . '">';
		echo '<input type="hidden" name="wla_inmo_settings_tab" value="' . esc_attr($tab) . '">';
		echo '<section class="wla-inmo-admin__panel">';
		echo '<h2>' . esc_html($title) . '</h2>';
		echo '<p class="wla-inmo-settings__intro">' . esc_html($description) . '</p>';
		echo '<div class="wla-inmo-settings__fields">';
	}

	private static function closeForm(?string $label = null): void
	{
		echo '</div>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html($label ?? __('Guardar cambios', 'wla-inmo')) . '</button></p>';
		echo '</section>';
		echo '</form>';
	}

	/** @param array<string,string> $attributes */
	private static function textField(string $key, string $label, string $value, string $description, array $attributes = array()): void
	{
		$type = $attributes['type'] ?? 'text';
		$class = $attributes['class'] ?? 'regular-text';
		$id = 'wla-inmo-setting-' . $key;

		echo '<div class="wla-inmo-settings__field">';
		echo '<label for="' . esc_attr($id) . '"><strong>' . esc_html($label) . '</strong></label>';
		echo '<input id="' . esc_attr($id) . '" name="wla_inmo_settings[' . esc_attr($key) . ']" type="' . esc_attr($type) . '" class="' . esc_attr($class) . '" value="' . esc_attr($value) . '"';
		foreach (array('maxlength', 'min', 'max') as $attribute) {
			if (isset($attributes[$attribute])) {
				echo ' ' . esc_attr($attribute) . '="' . esc_attr($attributes[$attribute]) . '"';
			}
		}
		echo '>';
		echo '<p class="description">' . esc_html($description) . '</p>';
		echo '</div>';
	}

	/** @param array<string,string> $options */
	private static function selectField(string $key, string $label, string $value, array $options, string $description): void
	{
		$id = 'wla-inmo-setting-' . $key;
		echo '<div class="wla-inmo-settings__field">';
		echo '<label for="' . esc_attr($id) . '"><strong>' . esc_html($label) . '</strong></label>';
		echo '<select id="' . esc_attr($id) . '" name="wla_inmo_settings[' . esc_attr($key) . ']">';
		foreach ($options as $optionValue => $optionLabel) {
			echo '<option value="' . esc_attr($optionValue) . '" ' . selected($value, $optionValue, false) . '>' . esc_html($optionLabel) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html($description) . '</p>';
		echo '</div>';
	}

	private static function renderTabs(string $active): void
	{
		echo '<nav class="nav-tab-wrapper wla-inmo-settings__tabs" aria-label="' . esc_attr__('Secciones de ajustes', 'wla-inmo') . '">';
		foreach (self::tabs() as $key => $label) {
			$url = admin_url('admin.php?page=wla-inmo-settings&tab=' . rawurlencode($key));
			$class = 'nav-tab' . ($active === $key ? ' nav-tab-active' : '');
			echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '"' . ($active === $key ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
		}
		echo '</nav>';
	}

	private static function renderNotices(): void
	{
		$status = self::requestedStatus();
		if ($status === 'saved') {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Ajustes guardados.', 'wla-inmo') . '</p></div>';
		} elseif ($status === 'invalid-email') {
			echo '<div class="notice notice-error"><p>' . esc_html__('El email ingresado no es válido. No se guardaron los cambios de esta pestaña.', 'wla-inmo') . '</p></div>';
		}

		// Read-only status set after an authorized rewrite action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presentation-only query parameter.
		$rewrites = isset($_GET['rewrites']) ? sanitize_key(wp_unslash((string) $_GET['rewrites'])) : '';
		if ($rewrites === 'applied') {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Reglas de enlaces aplicadas correctamente.', 'wla-inmo') . '</p></div>';
		}
	}

	private static function statusItem(string $label, string $value): void
	{
		echo '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>';
	}

	private static function requestedTab(): string
	{
		// Read-only route selector.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presentation-only query parameter.
		$tab = isset($_GET['tab']) ? sanitize_key(wp_unslash((string) $_GET['tab'])) : 'general';

		return isset(self::tabs()[$tab]) ? $tab : 'general';
	}

	private static function requestedStatus(): string
	{
		// Read-only feedback after a POST redirect.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Presentation-only query parameter.
		return isset($_GET['settings-status']) ? sanitize_key(wp_unslash((string) $_GET['settings-status'])) : '';
	}

	private static function redirect(string $tab, string $status): void
	{
		$url = admin_url(
			'admin.php?page=wla-inmo-settings&tab=' . rawurlencode($tab) . '&settings-status=' . rawurlencode($status)
		);
		wp_safe_redirect($url);
		exit;
	}
}
