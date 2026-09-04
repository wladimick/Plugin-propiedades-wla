<?php

namespace WLA\Inmo\Quality;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Properties\Sanitizer;
use WLA\Inmo\Taxonomies\Registry as TaxonomyRegistry;

final class Evaluator
{
	public const MIN_DESCRIPTION_LENGTH = 80;
	public const MIN_IMAGE_COUNT = 3;

	/**
	 * Build the quality projection for one property from canonical WordPress data.
	 * Drafts are intentionally included; trash/auto-drafts are excluded.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function fromProperty(int $postId): ?array
	{
		$post = get_post($postId);
		if (!is_object($post) || !isset($post->post_type, $post->post_status) || $post->post_type !== PostType::POST_TYPE) {
			return null;
		}

		if (in_array((string) $post->post_status, array('trash', 'auto-draft', 'inherit'), true)) {
			return null;
		}

		$gallery = self::metaArray($postId, 'gallery_ids');
		$featuredId = (int) get_post_thumbnail_id($postId);
		$imageIds = array_values(array_unique(array_filter(array_merge($featuredId > 0 ? array($featuredId) : array(), $gallery))));
		$validImages = array();
		$altValues = array();

		foreach ($imageIds as $attachmentId) {
			$attachmentId = (int) $attachmentId;
			if ($attachmentId < 1 || get_post_type($attachmentId) !== 'attachment' || !wp_attachment_is_image($attachmentId)) {
				continue;
			}

			$validImages[] = $attachmentId;
			$altValues[$attachmentId] = trim((string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true));
		}

		$snapshot = array(
			'property_code' => self::meta($postId, 'property_code'),
			'operation_count' => self::termCount($postId, TaxonomyRegistry::OPERATION),
			'type_count' => self::termCount($postId, TaxonomyRegistry::PROPERTY_TYPE),
			'commune_count' => self::termCount($postId, TaxonomyRegistry::COMMUNE),
			'locality' => self::meta($postId, 'locality'),
			'public_address' => self::meta($postId, 'public_address'),
			'price_on_request' => (bool) self::meta($postId, 'price_on_request'),
			'price_clp' => self::meta($postId, 'price_clp'),
			'price_uf' => self::meta($postId, 'price_uf'),
			'price_usd' => self::meta($postId, 'price_usd'),
			'land_area_m2' => self::meta($postId, 'land_area_m2'),
			'built_area_m2' => self::meta($postId, 'built_area_m2'),
			'usable_area_m2' => self::meta($postId, 'usable_area_m2'),
			'description' => isset($post->post_content) ? (string) $post->post_content : '',
			'featured_image_id' => $featuredId,
			'image_ids' => $validImages,
			'image_alt' => $altValues,
			'last_verified_date' => self::meta($postId, 'last_verified_date'),
		);

		$result = self::evaluateSnapshot($snapshot);
		$result['property_id'] = $postId;
		$result['updated_at'] = current_time('mysql', true);

		return $result;
	}

	/**
	 * Pure, deterministic quality calculation used by runtime and tests.
	 *
	 * @param array<string,mixed> $snapshot Canonical property snapshot.
	 * @return array<string,mixed>
	 */
	public static function evaluateSnapshot(array $snapshot): array
	{
		$description = self::plainText((string) ($snapshot['description'] ?? ''));
		$imageIds = isset($snapshot['image_ids']) && is_array($snapshot['image_ids']) ? array_values(array_unique(array_map('intval', $snapshot['image_ids']))) : array();
		$imageIds = array_values(array_filter($imageIds, static fn (int $id): bool => $id > 0));
		$altValues = isset($snapshot['image_alt']) && is_array($snapshot['image_alt']) ? $snapshot['image_alt'] : array();
		$allImagesHaveAlt = $imageIds !== array();

		foreach ($imageIds as $imageId) {
			$alt = isset($altValues[$imageId]) && is_scalar($altValues[$imageId]) ? trim((string) $altValues[$imageId]) : '';
			if ($alt === '') {
				$allImagesHaveAlt = false;
				break;
			}
		}

		$hasPrice = !empty($snapshot['price_on_request'])
			|| self::positiveNumber($snapshot['price_clp'] ?? null)
			|| self::positiveNumber($snapshot['price_uf'] ?? null)
			|| self::positiveNumber($snapshot['price_usd'] ?? null);
		$hasLocation = (int) ($snapshot['commune_count'] ?? 0) > 0
			|| self::nonEmpty($snapshot['locality'] ?? '')
			|| self::nonEmpty($snapshot['public_address'] ?? '');
		$hasSurface = self::positiveNumber($snapshot['land_area_m2'] ?? null)
			|| self::positiveNumber($snapshot['built_area_m2'] ?? null)
			|| self::positiveNumber($snapshot['usable_area_m2'] ?? null);
		$hasFeaturedImage = (int) ($snapshot['featured_image_id'] ?? 0) > 0;
		$lastVerified = isset($snapshot['last_verified_date']) && is_scalar($snapshot['last_verified_date']) ? trim((string) $snapshot['last_verified_date']) : '';

		$checks = array(
			'property_code' => self::nonEmpty($snapshot['property_code'] ?? ''),
			'operation' => (int) ($snapshot['operation_count'] ?? 0) > 0,
			'property_type' => (int) ($snapshot['type_count'] ?? 0) > 0,
			'price' => $hasPrice,
			'location' => $hasLocation,
			'surface' => $hasSurface,
			'description' => self::textLength($description) >= self::MIN_DESCRIPTION_LENGTH,
			'featured_image' => $hasFeaturedImage,
			'image_count' => count($imageIds) >= self::MIN_IMAGE_COUNT,
			'image_alt' => $allImagesHaveAlt,
			'last_verified' => $lastVerified !== '' && Sanitizer::isValidDate($lastVerified),
		);

		$passed = count(array_filter($checks));
		$total = count($checks);
		$missing = array_keys(array_filter($checks, static fn (bool $passedCheck): bool => !$passedCheck));
		$score = $total > 0 ? (int) round(($passed / $total) * 100) : 0;

		return array(
			'score' => max(0, min(100, $score)),
			'passed_checks' => $passed,
			'total_checks' => $total,
			'is_complete' => $passed === $total ? 1 : 0,
			'has_price' => $hasPrice ? 1 : 0,
			'has_image' => $hasFeaturedImage ? 1 : 0,
			'missing_codes' => implode(',', $missing),
			'checks' => $checks,
		);
	}

	/**
	 * @return array<string,array{label:string,action:string}>
	 */
	public static function definitions(): array
	{
		return array(
			'property_code' => self::definition(__('Código', 'wla-inmo'), __('Agrega un código único y estable para identificar la propiedad.', 'wla-inmo')),
			'operation' => self::definition(__('Operación', 'wla-inmo'), __('Selecciona si la propiedad está en venta, arriendo u otra operación permitida.', 'wla-inmo')),
			'property_type' => self::definition(__('Tipo de propiedad', 'wla-inmo'), __('Selecciona el tipo de propiedad.', 'wla-inmo')),
			'price' => self::definition(__('Precio', 'wla-inmo'), __('Ingresa un precio válido o marca la propiedad como “precio a consultar”.', 'wla-inmo')),
			'location' => self::definition(__('Ubicación', 'wla-inmo'), __('Completa comuna, localidad o una dirección pública segura.', 'wla-inmo')),
			'surface' => self::definition(__('Superficie', 'wla-inmo'), __('Agrega al menos una superficie útil, construida o de terreno.', 'wla-inmo')),
			'description' => self::definition(__('Descripción', 'wla-inmo'), sprintf(__('Amplía la descripción hasta al menos %d caracteres útiles.', 'wla-inmo'), self::MIN_DESCRIPTION_LENGTH)),
			'featured_image' => self::definition(__('Imagen principal', 'wla-inmo'), __('Define una Imagen destacada para la ficha.', 'wla-inmo')),
			'image_count' => self::definition(__('Cantidad de imágenes', 'wla-inmo'), sprintf(__('Incluye al menos %d imágenes válidas considerando principal y galería.', 'wla-inmo'), self::MIN_IMAGE_COUNT)),
			'image_alt' => self::definition(__('Texto ALT', 'wla-inmo'), __('Completa el texto ALT de todas las imágenes asociadas a la ficha.', 'wla-inmo')),
			'last_verified' => self::definition(__('Última verificación', 'wla-inmo'), __('Registra la fecha de la última verificación comercial.', 'wla-inmo')),
		);
	}

	/** @return array{label:string,action:string} */
	private static function definition(string $label, string $action): array
	{
		return array('label' => $label, 'action' => $action);
	}

	private static function meta(int $postId, string $field)
	{
		$metaKey = MetaSchema::metaKey($field);

		return $metaKey === null ? '' : get_post_meta($postId, $metaKey, true);
	}

	/** @return array<int,int> */
	private static function metaArray(int $postId, string $field): array
	{
		$value = self::meta($postId, $field);

		return is_array($value) ? array_values(array_map('intval', $value)) : array();
	}

	private static function termCount(int $postId, string $taxonomy): int
	{
		$terms = wp_get_object_terms($postId, $taxonomy, array('fields' => 'ids'));

		return is_wp_error($terms) || !is_array($terms) ? 0 : count($terms);
	}

	private static function positiveNumber($value): bool
	{
		return is_numeric($value) && (float) $value > 0;
	}

	private static function nonEmpty($value): bool
	{
		return is_scalar($value) && trim((string) $value) !== '';
	}

	private static function plainText(string $value): string
	{
		if (function_exists('wp_strip_all_tags')) {
			return trim((string) wp_strip_all_tags($value, true));
		}

		return trim(strip_tags($value));
	}

	private static function textLength(string $value): int
	{
		return function_exists('mb_strlen') ? (int) mb_strlen($value) : strlen($value);
	}
}
