<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Properties\Sanitizer;
use WLA\Inmo\Properties\Validator;

final class PropertyMedia
{
	private const NONCE_ACTION = 'wla_inmo_save_property_media';
	private const NONCE_NAME = 'wla_inmo_property_media_nonce';
	private const FIELD_ROOT = 'wla_inmo_media';
	private const ALT_ROOT = 'wla_inmo_media_alt';
	private const STATE_PREFIX = 'wla_inmo_media_state_';

	public static function register(): void
	{
		add_action('add_meta_boxes_' . PostType::POST_TYPE, array(self::class, 'registerMetaBox'), 20);
		add_action('save_post_' . PostType::POST_TYPE, array(self::class, 'save'), 30, 3);
	}

	public static function registerMetaBox(): void
	{
		add_meta_box(
			'wla-inmo-property-media',
			__('Multimedia', 'wla-inmo'),
			array(self::class, 'render'),
			PostType::POST_TYPE,
			'normal',
			'high'
		);
	}

	public static function render($post): void
	{
		if (!is_object($post) || !isset($post->ID)) {
			return;
		}

		$postId = (int) $post->ID;
		$state = self::consumeState($postId);
		$errors = is_array($state['errors'] ?? null) ? $state['errors'] : array();
		$galleryInput = $state['gallery_input'] ?? get_post_meta($postId, (string) MetaSchema::metaKey('gallery_ids'), true);
		$videoInput = $state['video_input'] ?? get_post_meta($postId, (string) MetaSchema::metaKey('video_urls'), true);
		$galleryIds = Sanitizer::positiveIntegerArray(self::normalizeGalleryInput($galleryInput));
		$videoUrls = Sanitizer::httpUrlArray(self::normalizeVideoInput($videoInput));
		$galleryValue = implode(',', $galleryIds);
		$videoValue = implode("\n", $videoUrls);

		wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

		echo '<div class="wla-inmo-property-media" data-wla-property-media';
		echo ' data-label-remove="' . esc_attr__('Quitar de la galería', 'wla-inmo') . '"';
		echo ' data-label-move-prev="' . esc_attr__('Mover antes', 'wla-inmo') . '"';
		echo ' data-label-move-next="' . esc_attr__('Mover después', 'wla-inmo') . '"';
		echo ' data-label-alt="' . esc_attr__('Texto ALT', 'wla-inmo') . '"';
		echo ' data-label-image="' . esc_attr__('Imagen', 'wla-inmo') . '"';
		echo ' data-count-singular="' . esc_attr__('1 imagen en la galería', 'wla-inmo') . '"';
		echo ' data-count-plural="' . esc_attr__('%d imágenes en la galería', 'wla-inmo') . '"';
		echo ' data-count-empty="' . esc_attr__('La galería está vacía', 'wla-inmo') . '">';

		self::renderErrorSummary($errors);

		echo '<p class="description">';
		echo esc_html__('La imagen principal continúa administrándose con “Imagen destacada” de WordPress. Esta galería complementa la ficha sin duplicar ni borrar archivos de la Biblioteca de Medios.', 'wla-inmo');
		echo '</p>';

		echo '<div class="wla-inmo-property-media__section">';
		echo '<div class="wla-inmo-property-media__heading">';
		echo '<div><strong>' . esc_html__('Galería de imágenes', 'wla-inmo') . '</strong><br>';
		echo '<span class="description" data-wla-media-count aria-live="polite">' . esc_html(self::countLabel(count($galleryIds))) . '</span></div>';
		if (current_user_can('upload_files')) {
			echo '<button type="button" class="button button-secondary" data-wla-media-add>' . esc_html__('Seleccionar imágenes', 'wla-inmo') . '</button>';
		}
		echo '</div>';

		echo '<input type="hidden" name="' . esc_attr(self::FIELD_ROOT . '[gallery_ids]') . '" value="' . esc_attr($galleryValue) . '" data-wla-media-gallery-input>';
		echo '<ol class="wla-inmo-property-media__list" data-wla-media-list>';
		foreach ($galleryIds as $attachmentId) {
			self::renderGalleryItem($attachmentId);
		}
		echo '</ol>';

		if (isset($errors['gallery_ids'])) {
			echo '<p class="wla-inmo-property-media__error" role="alert">' . esc_html(self::errorMessage('gallery_ids', (string) $errors['gallery_ids'])) . '</p>';
		}
		echo '<p class="description">' . esc_html__('Usa los botones “Mover antes” y “Mover después” para definir un orden accesible. “Quitar” solo retira la imagen de esta propiedad; el archivo permanece en WordPress.', 'wla-inmo') . '</p>';
		echo '</div>';

		echo '<div class="wla-inmo-property-media__section">';
		echo '<label for="wla-inmo-video-urls"><strong>' . esc_html__('Videos', 'wla-inmo') . '</strong></label>';
		echo '<textarea id="wla-inmo-video-urls" class="large-text code" rows="5" name="' . esc_attr(self::FIELD_ROOT . '[video_urls]') . '"';
		if (isset($errors['video_urls'])) {
			echo ' aria-invalid="true"';
		}
		echo '>' . esc_textarea($videoValue) . '</textarea>';
		echo '<p class="description">' . esc_html__('Una URL HTTP/HTTPS por línea. No se aceptan iframes, scripts ni HTML como dato canónico.', 'wla-inmo') . '</p>';
		if (isset($errors['video_urls'])) {
			echo '<p class="wla-inmo-property-media__error" role="alert">' . esc_html(self::errorMessage('video_urls', (string) $errors['video_urls'])) . '</p>';
		}
		echo '</div>';

		echo '</div>';
	}

	public static function save(int $postId, $post, bool $update): void
	{
		unset($update);

		if (!self::shouldHandleSave($postId, $post) || !self::verifyNonce() || !current_user_can('edit_post', $postId)) {
			return;
		}

		$submitted = self::submittedValues();
		$galleryInput = $submitted['gallery_ids'] ?? '';
		$videoInput = $submitted['video_urls'] ?? '';
		$galleryIds = self::normalizeGalleryInput($galleryInput);
		$videoUrls = self::normalizeVideoInput($videoInput);
		$errors = self::validateValues($galleryIds, $videoUrls);

		if ($errors !== array()) {
			self::storeState($postId, $galleryInput, $videoInput, $errors);
			return;
		}

		$cleanGallery = Sanitizer::positiveIntegerArray($galleryIds);
		$cleanVideos = Sanitizer::httpUrlArray($videoUrls);
		self::persistCanonicalMeta($postId, 'gallery_ids', $cleanGallery);
		self::persistCanonicalMeta($postId, 'video_urls', $cleanVideos);
		self::persistAltText($cleanGallery);
	}

	/**
	 * @return array<int,mixed>
	 */
	public static function normalizeGalleryInput($value): array
	{
		if (is_array($value)) {
			return array_values($value);
		}

		if (!is_scalar($value) || trim((string) $value) === '') {
			return array();
		}

		$parts = preg_split('/[\s,]+/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY);

		return is_array($parts) ? array_values($parts) : array();
	}

	/**
	 * @return array<int,mixed>
	 */
	public static function normalizeVideoInput($value): array
	{
		$values = is_array($value) ? $value : preg_split('/\r\n|\r|\n/', is_scalar($value) ? (string) $value : '');
		if (!is_array($values)) {
			return array();
		}

		$result = array();
		foreach ($values as $item) {
			if (!is_scalar($item)) {
				continue;
			}

			$item = trim((string) $item);
			if ($item !== '') {
				$result[] = $item;
			}
		}

		return $result;
	}

	/**
	 * @param array<int,mixed> $galleryIds Gallery attachment candidates.
	 * @param array<int,mixed> $videoUrls Video URL candidates.
	 * @return array<string,string>
	 */
	public static function validateValues(array $galleryIds, array $videoUrls): array
	{
		$errors = Validator::validate(
			array(
				'gallery_ids' => $galleryIds,
				'video_urls' => $videoUrls,
			)
		);

		if (!isset($errors['gallery_ids'])) {
			foreach ($galleryIds as $attachmentId) {
				$id = Sanitizer::nonNegativeInteger($attachmentId);
				if ($id === null || $id < 1 || get_post_type($id) !== 'attachment' || !wp_attachment_is_image($id)) {
					$errors['gallery_ids'] = 'invalid_image_attachment';
					break;
				}
			}
		}

		return $errors;
	}

	public static function isPropertyEditorContext(string $hookSuffix, $screen = null): bool
	{
		if (!in_array($hookSuffix, array('post.php', 'post-new.php'), true)) {
			return false;
		}

		if ($screen === null && function_exists('get_current_screen')) {
			$screen = get_current_screen();
		}

		return is_object($screen) && isset($screen->post_type) && $screen->post_type === PostType::POST_TYPE;
	}

	private static function renderGalleryItem(int $attachmentId): void
	{
		$title = get_the_title($attachmentId);
		$title = is_string($title) && $title !== '' ? $title : sprintf(__('Imagen #%d', 'wla-inmo'), $attachmentId);
		$alt = (string) get_post_meta($attachmentId, '_wp_attachment_image_alt', true);
		$canEditAlt = current_user_can('edit_post', $attachmentId);
		$image = wp_get_attachment_image(
			$attachmentId,
			'thumbnail',
			false,
			array(
				'class' => 'wla-inmo-property-media__thumb',
				'alt' => '',
				'loading' => 'lazy',
			)
		);

		echo '<li class="wla-inmo-property-media__item" data-wla-media-item data-attachment-id="' . esc_attr((string) $attachmentId) . '">';
		echo '<div class="wla-inmo-property-media__preview">';
		if (is_string($image) && $image !== '') {
			echo wp_kses_post($image);
		} else {
			echo '<span class="wla-inmo-property-media__missing">' . esc_html__('Sin miniatura', 'wla-inmo') . '</span>';
		}
		echo '</div>';
		echo '<div class="wla-inmo-property-media__meta">';
		echo '<strong>' . esc_html($title) . '</strong>';
		if ($canEditAlt) {
			$inputId = 'wla-inmo-media-alt-' . $attachmentId;
			echo '<label for="' . esc_attr($inputId) . '">' . esc_html__('Texto ALT', 'wla-inmo') . '</label>';
			echo '<input class="regular-text" type="text" id="' . esc_attr($inputId) . '" name="' . esc_attr(self::ALT_ROOT . '[' . $attachmentId . ']') . '" value="' . esc_attr($alt) . '">';
		} else {
			echo '<span class="description">' . esc_html__('ALT:', 'wla-inmo') . ' ' . esc_html($alt !== '' ? $alt : __('Sin texto ALT', 'wla-inmo')) . '</span>';
		}
		echo '</div>';
		echo '<div class="wla-inmo-property-media__actions">';
		echo '<button type="button" class="button button-small" data-wla-media-move-prev>' . esc_html__('Mover antes', 'wla-inmo') . '</button>';
		echo '<button type="button" class="button button-small" data-wla-media-move-next>' . esc_html__('Mover después', 'wla-inmo') . '</button>';
		echo '<button type="button" class="button button-small" data-wla-media-remove>' . esc_html__('Quitar', 'wla-inmo') . '</button>';
		echo '</div>';
		echo '</li>';
	}

	private static function renderErrorSummary(array $errors): void
	{
		if ($errors === array()) {
			return;
		}

		echo '<div class="notice notice-error inline" role="alert">';
		echo '<p><strong>' . esc_html__('No se guardó Multimedia porque hay datos que debes revisar.', 'wla-inmo') . '</strong></p><ul>';
		foreach ($errors as $field => $code) {
			echo '<li>' . esc_html(self::errorMessage((string) $field, (string) $code)) . '</li>';
		}
		echo '</ul></div>';
	}

	private static function errorMessage(string $field, string $code): string
	{
		$label = $field === 'video_urls' ? __('Videos', 'wla-inmo') : __('Galería', 'wla-inmo');
		$messages = array(
			'invalid_array' => __('El valor enviado no tiene el formato esperado.', 'wla-inmo'),
			'invalid_attachment_id' => __('La galería contiene un identificador de archivo no válido.', 'wla-inmo'),
			'invalid_image_attachment' => __('Cada elemento de la galería debe existir y ser una imagen de la Biblioteca de Medios.', 'wla-inmo'),
			'invalid_http_url' => __('Cada video debe ser una URL HTTP o HTTPS válida, sin HTML ni iframe.', 'wla-inmo'),
		);

		return sprintf(__('%1$s: %2$s', 'wla-inmo'), $label, $messages[$code] ?? __('Revisa este valor.', 'wla-inmo'));
	}

	private static function countLabel(int $count): string
	{
		if ($count === 0) {
			return __('La galería está vacía', 'wla-inmo');
		}

		if ($count === 1) {
			return __('1 imagen en la galería', 'wla-inmo');
		}

		return sprintf(__('%d imágenes en la galería', 'wla-inmo'), $count);
	}

	private static function shouldHandleSave(int $postId, $post): bool
	{
		if ($postId < 1 || !is_object($post) || !isset($post->post_type) || $post->post_type !== PostType::POST_TYPE) {
			return false;
		}

		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return false;
		}

		return !wp_is_post_revision($postId) && !wp_is_post_autosave($postId);
	}

	private static function verifyNonce(): bool
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized and verified immediately below.
		$rawNonce = isset($_POST[self::NONCE_NAME]) ? wp_unslash($_POST[self::NONCE_NAME]) : '';
		$nonce = is_scalar($rawNonce) ? sanitize_text_field((string) $rawNonce) : '';

		return $nonce !== '' && wp_verify_nonce($nonce, self::NONCE_ACTION) !== false;
	}

	/** @return array<string,mixed> */
	private static function submittedValues(): array
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is already verified; values are validated before persistence.
		$raw = isset($_POST[self::FIELD_ROOT]) ? wp_unslash($_POST[self::FIELD_ROOT]) : array();
		if (!is_array($raw)) {
			return array();
		}

		$result = array();
		foreach (array('gallery_ids', 'video_urls') as $field) {
			if (isset($raw[$field]) && (is_scalar($raw[$field]) || is_array($raw[$field]))) {
				$result[$field] = $raw[$field];
			}
		}

		return $result;
	}

	/** @param array<int,int> $galleryIds */
	private static function persistAltText(array $galleryIds): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce is already verified; each value is sanitized and object-authorized below.
		$raw = isset($_POST[self::ALT_ROOT]) ? wp_unslash($_POST[self::ALT_ROOT]) : array();
		if (!is_array($raw)) {
			return;
		}

		foreach ($galleryIds as $attachmentId) {
			if (!current_user_can('edit_post', $attachmentId) || !array_key_exists($attachmentId, $raw) || !is_scalar($raw[$attachmentId])) {
				continue;
			}

			$alt = sanitize_text_field((string) $raw[$attachmentId]);
			update_post_meta($attachmentId, '_wp_attachment_image_alt', $alt);
		}
	}

	private static function persistCanonicalMeta(int $postId, string $field, array $value): void
	{
		$metaKey = MetaSchema::metaKey($field);
		if ($metaKey === null) {
			return;
		}

		if ($value === array()) {
			delete_post_meta($postId, $metaKey);
			return;
		}

		update_post_meta($postId, $metaKey, $value);
	}

	private static function stateKey(int $postId): string
	{
		return self::STATE_PREFIX . get_current_user_id() . '_' . $postId;
	}

	private static function storeState(int $postId, $galleryInput, $videoInput, array $errors): void
	{
		set_transient(
			self::stateKey($postId),
			array(
				'gallery_input' => is_scalar($galleryInput) || is_array($galleryInput) ? $galleryInput : '',
				'video_input' => is_scalar($videoInput) || is_array($videoInput) ? $videoInput : '',
				'errors' => $errors,
			),
			5 * MINUTE_IN_SECONDS
		);
	}

	/** @return array<string,mixed> */
	private static function consumeState(int $postId): array
	{
		$key = self::stateKey($postId);
		$state = get_transient($key);
		delete_transient($key);

		return is_array($state) ? $state : array();
	}
}
