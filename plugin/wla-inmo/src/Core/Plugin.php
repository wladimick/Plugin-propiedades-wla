<?php

namespace WLA\Inmo\Core;

use WLA\Inmo\Access\RoleManager;
use WLA\Inmo\Properties\MetaSchema;
use WLA\Inmo\Properties\PostType;
use WLA\Inmo\Search\Indexer;
use WLA\Inmo\Taxonomies\Registry as TaxonomyRegistry;

final class Plugin
{
	private static ?self $instance = null;

	private bool $booted = false;

	private function __construct()
	{
	}

	public static function instance(): self
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void
	{
		if ($this->booted) {
			return;
		}

		$this->booted = true;

		add_action('init', array($this, 'loadTextDomain'), 0);
		add_action('init', array(PostType::class, 'register'), 5);
		add_action('init', array(TaxonomyRegistry::class, 'register'), 6);
		add_action('init', array(MetaSchema::class, 'register'), 7);
		add_action('admin_init', array(Installer::class, 'maybeUpgrade'), 1);
		add_action('admin_init', array(RoleManager::class, 'maybeUpgrade'), 2);

		Indexer::register();

		/**
		 * Fires after WLA Inmo Core has completed its bootstrap.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action('wla_inmo_loaded', $this);
	}

	public function loadTextDomain(): void
	{
		load_plugin_textdomain(
			'wla-inmo',
			false,
			dirname(WLA_INMO_BASENAME) . '/languages'
		);
	}

	public function isBooted(): bool
	{
		return $this->booted;
	}
}
