<?php

namespace WLA\Inmo\Import;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;

final class IdentityMeta
{
	public const SOURCE_KEY_META = '_wla_inmo_external_source_key';

	public static function register(): void
	{
		register_post_meta(
			PostType::POST_TYPE,
			self::SOURCE_KEY_META,
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => false,
				'description'       => __('Fuente normalizada asociada al identificador externo.', 'wla-inmo'),
				'sanitize_callback' => array(self::class, 'sanitize'),
				'auth_callback'     => array(MetaSchema::class, 'authorize'),
			)
		);
	}

	/**
	 * Sanitize without throwing from WordPress metadata callbacks.
	 *
	 * @param mixed $value Raw source key.
	 */
	public static function sanitize($value): string
	{
		if (!is_scalar($value)) {
			return '';
		}

		$normalized = SourceKey::normalize((string) $value);

		return SourceKey::isValid($normalized) ? $normalized : '';
	}
}
