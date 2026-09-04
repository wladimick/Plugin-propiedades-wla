<?php

namespace WLA\Inmo\Admin;

final class Bootstrap
{
	private static bool $registered = false;

	public static function register(): void
	{
		if (self::$registered) {
			return;
		}

		self::$registered = true;

		add_action('admin_menu', array(Menu::class, 'register'), 20);
		add_action('admin_enqueue_scripts', array(Assets::class, 'enqueue'));
		add_action('current_screen', array(ContextHelp::class, 'add'));

		Onboarding::register();
		PropertyList::register();
		PropertyQualityList::register();
		PropertyEditor::register();
		PropertyMedia::register();
		QualityPage::register();
	}

	public static function resetForTests(): void
	{
		self::$registered = false;
	}
}
