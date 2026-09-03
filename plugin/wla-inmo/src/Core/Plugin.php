<?php

namespace WLA\Inmo\Core;

use WLA\Inmo\Properties\PostType;

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
