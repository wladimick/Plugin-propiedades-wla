<?php

namespace WLA\Inmo\Properties;

final class MetaSchema
{
	public const META_PREFIX = '_wla_inmo_';

	/**
	 * Register canonical property metadata with WordPress.
	 */
	public static function register(): void
	{
		foreach (self::definitions() as $definition) {
			$args = array(
				'type'              => $definition['type'],
				'single'            => true,
				'show_in_rest'      => false,
				'description'       => $definition['description'],
				'sanitize_callback' => $definition['sanitize_callback'],
				'auth_callback'     => array(self::class, 'authorize'),
			);

			if ($definition['has_default']) {
				$args['default'] = $definition['default'];
			}

			register_post_meta(
				PostType::POST_TYPE,
				$definition['meta_key'],
				$args
			);
		}
	}

	/**
	 * Protected metadata is editable only by users who can edit the property.
	 *
	 * @param mixed  $allowed Existing authorization result.
	 * @param string $metaKey Meta key.
	 * @param int    $postId Property ID.
	 */
	public static function authorize($allowed, string $metaKey, int $postId): bool
	{
		unset($allowed, $metaKey);

		return $postId > 0 && current_user_can('edit_post', $postId);
	}

	/**
	 * Canonical domain schema.
	 *
	 * Operation, property type, region, commune and sector intentionally do not
	 * appear here: those concepts are taxonomies and must not be duplicated.
	 * Title, description, excerpt, featured image and revisions remain native
	 * WordPress post fields/features.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions(): array
	{
		return array(
			'property_code' => self::field('property_code', 'string', array(Sanitizer::class, 'text'), true, __('Código visible y estable de la propiedad.', 'wla-inmo')),
			'external_id' => self::field('external_id', 'string', array(Sanitizer::class, 'text'), false, __('Identificador interno de una fuente o integración externa.', 'wla-inmo')),
			'status' => self::field('status', 'string', array(Sanitizer::class, 'key'), true, __('Estado comercial normalizado de la propiedad.', 'wla-inmo')),

			'price_clp' => self::field('price_clp', 'integer', array(Sanitizer::class, 'nonNegativeInteger'), true, __('Precio canónico en pesos chilenos.', 'wla-inmo')),
			'price_uf' => self::field('price_uf', 'number', array(Sanitizer::class, 'nonNegativeNumber'), true, __('Precio canónico en UF cuando haya sido ingresado como dato real.', 'wla-inmo')),
			'price_usd' => self::field('price_usd', 'number', array(Sanitizer::class, 'nonNegativeNumber'), true, __('Precio canónico en dólares cuando haya sido ingresado como dato real.', 'wla-inmo')),
			'price_on_request' => self::field('price_on_request', 'boolean', array(Sanitizer::class, 'boolean'), true, __('Indica que el precio debe mostrarse como a consultar.', 'wla-inmo'), true, false),
			'currency_primary' => self::field('currency_primary', 'string', array(Sanitizer::class, 'currency'), true, __('Moneda principal del precio publicado.', 'wla-inmo')),
			'common_expenses_clp' => self::field('common_expenses_clp', 'integer', array(Sanitizer::class, 'nonNegativeInteger'), true, __('Gastos comunes mensuales expresados en CLP.', 'wla-inmo')),

			'locality' => self::field('locality', 'string', array(Sanitizer::class, 'text'), true, __('Ciudad o localidad complementaria a las taxonomías geográficas.', 'wla-inmo')),
			'public_address' => self::field('public_address', 'string', array(Sanitizer::class, 'text'), true, __('Dirección segura para mostrar públicamente.', 'wla-inmo')),
			'private_address' => self::field('private_address', 'string', array(Sanitizer::class, 'text'), false, __('Dirección exacta privada; nunca debe exponerse automáticamente.', 'wla-inmo')),
			'latitude' => self::field('latitude', 'number', array(Sanitizer::class, 'latitude'), true, __('Latitud geográfica validada.', 'wla-inmo')),
			'longitude' => self::field('longitude', 'number', array(Sanitizer::class, 'longitude'), true, __('Longitud geográfica validada.', 'wla-inmo')),
			'show_map' => self::field('show_map', 'boolean', array(Sanitizer::class, 'boolean'), true, __('Permite mostrar la ubicación geográfica pública en mapas.', 'wla-inmo'), true, true),
			'location_text' => self::field('location_text', 'string', array(Sanitizer::class, 'textarea'), true, __('Texto editorial breve sobre la ubicación.', 'wla-inmo')),

			'land_area_m2' => self::field('land_area_m2', 'number', array(Sanitizer::class, 'nonNegativeNumber'), true, __('Superficie de terreno en metros cuadrados.', 'wla-inmo')),
			'built_area_m2' => self::field('built_area_m2', 'number', array(Sanitizer::class, 'nonNegativeNumber'), true, __('Superficie construida en metros cuadrados.', 'wla-inmo')),
			'usable_area_m2' => self::field('usable_area_m2', 'number', array(Sanitizer::class, 'nonNegativeNumber'), true, __('Superficie útil en metros cuadrados.', 'wla-inmo')),
			'terrace_area_m2' => self::field('terrace_area_m2', 'number', array(Sanitizer::class, 'nonNegativeNumber'), true, __('Superficie de terraza en metros cuadrados.', 'wla-inmo')),

			'bedrooms' => self::field('bedrooms', 'integer', array(Sanitizer::class, 'nonNegativeInteger'), true, __('Cantidad de dormitorios.', 'wla-inmo')),
			'bathrooms' => self::field('bathrooms', 'integer', array(Sanitizer::class, 'nonNegativeInteger'), true, __('Cantidad de baños.', 'wla-inmo')),
			'parking' => self::field('parking', 'integer', array(Sanitizer::class, 'nonNegativeInteger'), true, __('Cantidad de estacionamientos.', 'wla-inmo')),
			'storage_units' => self::field('storage_units', 'integer', array(Sanitizer::class, 'nonNegativeInteger'), true, __('Cantidad de bodegas.', 'wla-inmo')),
			'pool' => self::field('pool', 'boolean', array(Sanitizer::class, 'boolean'), true, __('Indica si la propiedad dispone de piscina.', 'wla-inmo'), true, false),
			'heating' => self::field('heating', 'string', array(Sanitizer::class, 'text'), true, __('Descripción normalizada del sistema de calefacción.', 'wla-inmo')),
			'construction_year' => self::field('construction_year', 'integer', array(Sanitizer::class, 'nonNegativeInteger'), true, __('Año de construcción o referencia equivalente.', 'wla-inmo')),
			'orientation' => self::field('orientation', 'string', array(Sanitizer::class, 'text'), true, __('Orientación principal de la propiedad.', 'wla-inmo')),

			'gallery_ids' => self::field('gallery_ids', 'array', array(Sanitizer::class, 'positiveIntegerArray'), true, __('IDs ordenados de imágenes de la galería.', 'wla-inmo'), true, array()),
			'video_urls' => self::field('video_urls', 'array', array(Sanitizer::class, 'httpUrlArray'), true, __('URLs HTTP/HTTPS permitidas para videos asociados.', 'wla-inmo'), true, array()),

			'featured' => self::field('featured', 'boolean', array(Sanitizer::class, 'boolean'), true, __('Indica si la propiedad está marcada como destacada.', 'wla-inmo'), true, false),
			'home_order' => self::field('home_order', 'integer', array(Sanitizer::class, 'nonNegativeInteger'), false, __('Orden editorial interno para portada/destacados.', 'wla-inmo'), true, 0),
			'availability_date' => self::field('availability_date', 'string', array(Sanitizer::class, 'date'), true, __('Fecha de disponibilidad en formato YYYY-MM-DD.', 'wla-inmo')),
			'hide_price' => self::field('hide_price', 'boolean', array(Sanitizer::class, 'boolean'), true, __('Permite ocultar deliberadamente los valores de precio.', 'wla-inmo'), true, false),
			'indexable' => self::field('indexable', 'boolean', array(Sanitizer::class, 'boolean'), false, __('Decisión editorial interna de indexación de la ficha.', 'wla-inmo'), true, true),
			'last_verified_date' => self::field('last_verified_date', 'string', array(Sanitizer::class, 'date'), true, __('Fecha de la última verificación comercial de la propiedad.', 'wla-inmo')),
			'internal_notes' => self::field('internal_notes', 'string', array(Sanitizer::class, 'textarea'), false, __('Notas internas nunca destinadas al frontend o API pública.', 'wla-inmo')),
		);
	}

	/**
	 * @return array<int, string>
	 */
	public static function publicFields(): array
	{
		return self::fieldsByVisibility(true);
	}

	/**
	 * @return array<int, string>
	 */
	public static function privateFields(): array
	{
		return self::fieldsByVisibility(false);
	}

	public static function metaKey(string $field): ?string
	{
		$definitions = self::definitions();

		return isset($definitions[$field]) ? (string) $definitions[$field]['meta_key'] : null;
	}

	/**
	 * @param callable $sanitizeCallback Sanitizer callback.
	 * @return array<string, mixed>
	 */
	private static function field(
		string $field,
		string $type,
		$sanitizeCallback,
		bool $public,
		string $description,
		bool $hasDefault = false,
		$default = null
	): array {
		return array(
			'field'             => $field,
			'meta_key'          => self::META_PREFIX . $field,
			'type'              => $type,
			'public'            => $public,
			'description'       => $description,
			'sanitize_callback' => $sanitizeCallback,
			'has_default'       => $hasDefault,
			'default'           => $default,
		);
	}

	/**
	 * @return array<int, string>
	 */
	private static function fieldsByVisibility(bool $public): array
	{
		$fields = array();

		foreach (self::definitions() as $field => $definition) {
			if ((bool) $definition['public'] === $public) {
				$fields[] = $field;
			}
		}

		return $fields;
	}
}
