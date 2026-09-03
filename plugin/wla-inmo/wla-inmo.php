<?php
/**
 * Plugin Name: WLA Inmo
 * Plugin URI: https://github.com/wladimick/Plugin-propiedades-wla
 * Description: Motor inmobiliario ligero, seguro y desacoplado para WordPress.
 * Version: 0.1.0-alpha.1
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: WLA
 * Text Domain: wla-inmo
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
	exit;
}

define('WLA_INMO_VERSION', '0.1.0-alpha.1');
define('WLA_INMO_MIN_PHP', '8.1');
define('WLA_INMO_MIN_WP', '6.6');
define('WLA_INMO_FILE', __FILE__);
define('WLA_INMO_DIR', plugin_dir_path(__FILE__));
define('WLA_INMO_URL', plugin_dir_url(__FILE__));
define('WLA_INMO_BASENAME', plugin_basename(__FILE__));

/**
 * Keep this bootstrap parseable on older PHP versions so WordPress can show
 * a useful incompatibility notice instead of causing a fatal error.
 */
if (version_compare(PHP_VERSION, WLA_INMO_MIN_PHP, '<')) {
	add_action('admin_notices', static function () {
		$message = sprintf(
			/* translators: %s: minimum supported PHP version. */
			__('WLA Inmo requires PHP %s or newer. The plugin was not loaded.', 'wla-inmo'),
			WLA_INMO_MIN_PHP
		);

		echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
	});

	return;
}

global $wp_version;

if (!is_string($wp_version) || version_compare($wp_version, WLA_INMO_MIN_WP, '<')) {
	add_action('admin_notices', static function () {
		$message = sprintf(
			/* translators: %s: minimum supported WordPress version. */
			__('WLA Inmo requires WordPress %s or newer. The plugin was not loaded.', 'wla-inmo'),
			WLA_INMO_MIN_WP
		);

		echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
	});

	return;
}

$composer_autoload = WLA_INMO_DIR . 'vendor/autoload.php';

if (is_readable($composer_autoload)) {
	require_once $composer_autoload;
} else {
	// Source-checkout fallback. Release ZIPs include Composer's optimized autoloader.
	require_once WLA_INMO_DIR . 'includes/autoload.php';
}

register_activation_hook(WLA_INMO_FILE, array('WLA\\Inmo\\Core\\Activator', 'activate'));
register_deactivation_hook(WLA_INMO_FILE, array('WLA\\Inmo\\Core\\Deactivator', 'deactivate'));

WLA\Inmo\Core\Plugin::instance()->boot();
