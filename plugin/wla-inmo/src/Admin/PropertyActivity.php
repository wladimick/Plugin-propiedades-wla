<?php

namespace WLA\Inmo\Admin;

use WLA\Inmo\Access\Capabilities;
use WLA\Inmo\Activity\Repository;
use WLA\Inmo\Properties\PostType;

final class PropertyActivity
{
	public static function register(): void
	{
		add_action('add_meta_boxes_' . PostType::POST_TYPE, array(self::class, 'registerMetaBox'), 30);
	}

	public static function registerMetaBox(): void
	{
		if (!current_user_can(Capabilities::VIEW_ACTIVITY)) {
			return;
		}

		add_meta_box(
			'wla-inmo-property-activity',
			__('Historial operativo', 'wla-inmo'),
			array(self::class, 'render'),
			PostType::POST_TYPE,
			'normal',
			'low'
		);
	}

	public static function render($post): void
	{
		if (!current_user_can(Capabilities::VIEW_ACTIVITY) || !is_object($post) || !isset($post->ID)) {
			return;
		}

		$postId = absint($post->ID);
		if ($postId < 1) {
			return;
		}

		echo '<p class="description">' . esc_html__('Este historial resume cambios operativos relevantes. Las revisiones nativas de WordPress continúan conservando el contenido editorial.', 'wla-inmo') . '</p>';
		ActivityPage::renderPropertyTimeline(Repository::forObject(PostType::POST_TYPE, $postId, 10));
		echo '<p><a href="' . esc_url(admin_url('admin.php?page=wla-inmo-activity&object_id=' . $postId)) . '">' . esc_html__('Ver historial completo de esta propiedad', 'wla-inmo') . '</a></p>';
	}
}
