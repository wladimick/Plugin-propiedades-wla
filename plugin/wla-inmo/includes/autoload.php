<?php
/**
 * Lightweight PSR-4 fallback for source checkouts without vendor/.
 *
 * Release artifacts are built with Composer's optimized autoloader. This file
 * exists so a development checkout can still load the plugin before running
 * Composer, and mirrors the same WLA\Inmo\ => src/ mapping.
 */

if (!defined('ABSPATH')) {
	exit;
}

spl_autoload_register(static function ($class) {
	$prefix = 'WLA\\Inmo\\';
	$length = strlen($prefix);

	if (strncmp($class, $prefix, $length) !== 0) {
		return;
	}

	$relative_class = substr($class, $length);
	$file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative_class) . '.php';

	if (is_readable($file)) {
		require_once $file;
	}
});
