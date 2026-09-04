<?php

namespace WLA\Inmo\Activity;

final class EventTypes
{
	public const PROPERTY_CREATED = 'property.created';
	public const PROPERTY_WP_STATUS_CHANGED = 'property.wp_status_changed';
	public const PROPERTY_PRICE_CHANGED = 'property.price_changed';
	public const PROPERTY_COMMERCIAL_STATUS_CHANGED = 'property.commercial_status_changed';
	public const PROPERTY_FEATURED_CHANGED = 'property.featured_changed';
	public const SETTINGS_CHANGED = 'settings.changed';
	public const PROPERTY_BASE_CHANGED = 'settings.property_base_changed';
	public const REWRITE_RULES_APPLIED = 'settings.rewrite_rules_applied';

	/** @return array<string,array<int,string>> */
	public static function contextAllowlist(): array
	{
		return array(
			self::PROPERTY_CREATED => array('post_status'),
			self::PROPERTY_WP_STATUS_CHANGED => array('old', 'new'),
			self::PROPERTY_PRICE_CHANGED => array('field', 'old', 'new', 'currency'),
			self::PROPERTY_COMMERCIAL_STATUS_CHANGED => array('old', 'new'),
			self::PROPERTY_FEATURED_CHANGED => array('old', 'new'),
			self::SETTINGS_CHANGED => array('keys'),
			self::PROPERTY_BASE_CHANGED => array('old', 'new'),
			self::REWRITE_RULES_APPLIED => array('property_base'),
		);
	}

	public static function isAllowed(string $eventType): bool
	{
		return isset(self::contextAllowlist()[$eventType]);
	}

	/** @return array<string,mixed> */
	public static function sanitizeContext(string $eventType, array $context): array
	{
		$allowed = self::contextAllowlist()[$eventType] ?? array();
		$clean = array();

		foreach ($allowed as $key) {
			if (!array_key_exists($key, $context)) {
				continue;
			}

			$value = $context[$key];
			if ($key === 'keys') {
				$items = is_array($value) ? $value : array();
				$items = array_map('sanitize_key', $items);
				$items = array_values(array_unique(array_filter($items)));
				$clean[$key] = array_slice($items, 0, 50);
				continue;
			}

			if ($key === 'field' || $key === 'currency' || $key === 'post_status' || $key === 'property_base') {
				$clean[$key] = sanitize_key(is_scalar($value) ? (string) $value : '');
				continue;
			}

			if (is_bool($value)) {
				$clean[$key] = $value;
			} elseif (is_int($value) || is_float($value)) {
				$clean[$key] = $value;
			} elseif ($value === null) {
				$clean[$key] = null;
			} elseif (is_scalar($value)) {
				$clean[$key] = sanitize_text_field((string) $value);
			}
		}

		return $clean;
	}

	public static function label(string $eventType): string
	{
		$labels = array(
			self::PROPERTY_CREATED => __('Propiedad creada', 'wla-inmo'),
			self::PROPERTY_WP_STATUS_CHANGED => __('Publicación actualizada', 'wla-inmo'),
			self::PROPERTY_PRICE_CHANGED => __('Precio actualizado', 'wla-inmo'),
			self::PROPERTY_COMMERCIAL_STATUS_CHANGED => __('Estado comercial actualizado', 'wla-inmo'),
			self::PROPERTY_FEATURED_CHANGED => __('Destacado actualizado', 'wla-inmo'),
			self::SETTINGS_CHANGED => __('Ajustes actualizados', 'wla-inmo'),
			self::PROPERTY_BASE_CHANGED => __('Base de URL actualizada', 'wla-inmo'),
			self::REWRITE_RULES_APPLIED => __('Reglas de enlaces aplicadas', 'wla-inmo'),
		);

		return $labels[$eventType] ?? __('Actividad de WLA Inmo', 'wla-inmo');
	}
}
