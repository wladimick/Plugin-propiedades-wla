<?php

namespace WLA\Inmo\Import;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Properties\Sanitizer;

final class IdentityProjection
{
	/**
	 * Build an identity projection for every real WLA property, including drafts.
	 * Trash and auto-drafts are intentionally excluded.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function fromProperty(int $postId): ?array
	{
		if ($postId < 1 || get_post_type($postId) !== PostType::POST_TYPE) {
			return null;
		}

		$status = (string) get_post_status($postId);
		if (in_array($status, array('trash', 'auto-draft'), true)) {
			return null;
		}

		$sourceKey = IdentityMeta::sanitize(get_post_meta($postId, IdentityMeta::SOURCE_KEY_META, true));
		$externalId = self::textMeta($postId, 'external_id');
		$propertyCode = self::textMeta($postId, 'property_code');

		if (($sourceKey === '') !== ($externalId === '')) {
			return null;
		}

		return array(
			'property_id'   => $postId,
			'source_key'    => $sourceKey === '' ? null : $sourceKey,
			'external_id'   => $externalId === '' ? null : $externalId,
			'property_code' => $propertyCode === '' ? null : $propertyCode,
			'updated_at'    => self::modifiedAt($postId),
		);
	}

	private static function textMeta(int $postId, string $field): string
	{
		$metaKey = MetaSchema::metaKey($field);
		if ($metaKey === null) {
			return '';
		}

		return Sanitizer::text(get_post_meta($postId, $metaKey, true));
	}

	private static function modifiedAt(int $postId): string
	{
		$modified = get_post_modified_time('Y-m-d H:i:s', true, $postId);

		return is_string($modified) && $modified !== '' ? $modified : gmdate('Y-m-d H:i:s');
	}
}
