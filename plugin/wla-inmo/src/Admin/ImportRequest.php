<?php

namespace WLA\Inmo\Admin;

final class ImportRequest
{
	/** @return array<string,mixed> */
	public static function uploadedFile(string $key): array
	{
		// PHP's upload transport cannot be sanitized like ordinary text before
		// `is_uploaded_file()` validates tmp_name. We copy only known keys and
		// sanitize every user-controlled scalar before handing it to Workspace.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$entry = isset($_FILES[$key]) && is_array($_FILES[$key]) ? $_FILES[$key] : array();
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return array(
			'name'     => isset($entry['name']) && is_scalar($entry['name']) ? sanitize_file_name(wp_unslash((string) $entry['name'])) : '',
			'tmp_name' => isset($entry['tmp_name']) && is_scalar($entry['tmp_name']) ? sanitize_text_field(wp_unslash((string) $entry['tmp_name'])) : '',
			'size'     => isset($entry['size']) && is_scalar($entry['size']) ? absint($entry['size']) : 0,
			'error'    => isset($entry['error']) && is_scalar($entry['error']) ? absint($entry['error']) : UPLOAD_ERR_NO_FILE,
		);
	}

	/** @return array<int|string,string> */
	public static function postTextArray(string $key): array
	{
		// Callers verify their action nonce before invoking this method.
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$value = isset($_POST[$key]) && is_array($_POST[$key]) ? wp_unslash($_POST[$key]) : array();
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return map_deep($value, 'sanitize_text_field');
	}

	public static function queryScalar(string $key): string
	{
		// Read-only navigation/filter state. The dynamic key is internal and the
		// scalar is sanitized before leaving this boundary.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$value = isset($_GET[$key]) && is_scalar($_GET[$key]) ? wp_unslash((string) $_GET[$key]) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return sanitize_text_field($value);
	}

	public static function postScalar(string $key): string
	{
		// Callers verify their action nonce before invoking this method.
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$value = isset($_POST[$key]) && is_scalar($_POST[$key]) ? wp_unslash((string) $_POST[$key]) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return sanitize_text_field($value);
	}
}
