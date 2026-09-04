<?php

namespace WLA\Inmo\Core;

use WLA\Inmo\Activity\Retention as ActivityRetention;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Taxonomies\Registry as TaxonomyRegistry;

final class Deactivator
{
	public static function deactivate(): void
	{
		ActivityRetention::unschedule();

		foreach (TaxonomyRegistry::keys() as $taxonomy) {
			if (taxonomy_exists($taxonomy)) {
				unregister_taxonomy($taxonomy);
			}
		}

		if (post_type_exists(PostType::POST_TYPE)) {
			unregister_post_type(PostType::POST_TYPE);
		}

		flush_rewrite_rules(false);

		/**
		 * Fires when WLA Inmo is deactivated.
		 *
		 * Deactivation intentionally does not remove properties, settings, media,
		 * logs or index tables. Destructive cleanup belongs to an explicit
		 * uninstall flow and must remain opt-in.
		 */
		do_action('wla_inmo_deactivated');
	}
}
