<?php

/**
 * Minimal WordPress signatures required by the Frontend static-analysis gate.
 * Parsed by PHPStan only; never shipped with the plugin.
 */

if (!defined('WLA_INMO_DIR')) {
	define('WLA_INMO_DIR', '/tmp/wla-inmo/');
}
if (!defined('WLA_INMO_URL')) {
	define('WLA_INMO_URL', 'https://example.test/wp-content/plugins/wla-inmo/');
}
if (!defined('WLA_INMO_VERSION')) {
	define('WLA_INMO_VERSION', '0.1.0-alpha');
}

function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): bool { return true; }
/** @return mixed */
function apply_filters(string $hook_name, mixed $value, mixed ...$args) { return $value; }
function do_action(string $hook_name, mixed ...$args): void {}
/** @param array<int,string>|string $template_names @return mixed */
function locate_template(array|string $template_names, bool $load = false, bool $load_once = true, array $args = array()) { return ''; }
function get_stylesheet_directory(): string { return '/tmp/theme-child'; }
function get_template_directory(): string { return '/tmp/theme-parent'; }
function is_admin(): bool { return false; }
function wp_doing_ajax(): bool { return false; }
function is_feed(): bool { return false; }
/** @param array<int,string>|string $post_types */
function is_post_type_archive(array|string $post_types = ''): bool { return false; }
/** @param array<int,string>|string $post_types */
function is_singular(array|string $post_types = ''): bool { return false; }
/** @param array<int,string> $deps */
function wp_enqueue_style(string $handle, string $src = '', array $deps = array(), string|bool|null $ver = false, string $media = 'all'): void {}
