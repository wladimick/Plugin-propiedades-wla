<?php

/**
 * Minimal WordPress signatures required by the Import domain static-analysis gate.
 * This file is parsed by PHPStan only and is never shipped as runtime code.
 */

class WP_Query
{
	/** @var mixed */
	public $posts = array();

	/** @param array<string,mixed> $args */
	public function __construct(array $args = array())
	{
	}
}

function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): bool { return true; }
function wp_is_post_revision(int $post_id): int|false { return false; }
function get_post_type(int $post_id): string|false { return false; }
function get_post_status(int $post_id): string|false { return false; }
/** @return mixed */
function get_post_meta(int $post_id, string $key = '', bool $single = false) { return null; }
function get_post_modified_time(string $format = 'U', bool $gmt = false, int $post = 0): string|false { return false; }
/** @param array<string,mixed> $args */
function register_post_meta(string $post_type, string $meta_key, array $args): bool { return true; }
function __(string $text, string $domain = 'default'): string { return $text; }
function do_action(string $hook_name, mixed ...$args): void {}
/** @param array<string,mixed> $postarr @return mixed */
function wp_insert_post(array $postarr, bool $wp_error = false) { return 1; }
/** @param array<string,mixed> $postarr @return mixed */
function wp_update_post(array $postarr, bool $wp_error = false) { return 1; }
/** @return mixed */
function wp_delete_post(int $post_id, bool $force_delete = false) { return null; }
function is_wp_error(mixed $thing): bool { return false; }
function taxonomy_exists(string $taxonomy): bool { return true; }
/** @return array<int,string> */
function get_object_taxonomies(string|array|object $object_type, string $output = 'names'): array { return array(); }
function sanitize_title(string $title): string { return strtolower(trim($title)); }
function sanitize_file_name(string $filename): string { return $filename; }
function sanitize_key(string $key): string { return strtolower($key); }
function absint(mixed $value): int { return abs((int) $value); }
/** @return mixed */
function get_transient(string $transient) { return false; }
function set_transient(string $transient, mixed $value, int $expiration = 0): bool { return true; }
function delete_transient(string $transient): bool { return true; }
/** @return mixed */
function get_option(string $option, mixed $default_value = false) { return $default_value; }
function add_option(string $option, mixed $value = '', string $deprecated = '', bool|string $autoload = true): bool { return true; }
function delete_option(string $option): bool { return true; }
function maybe_serialize(mixed $data): mixed { return $data; }
function wp_cache_delete(int|string $key, string $group = ''): bool { return true; }
function trailingslashit(string $value): string { return rtrim($value, '/\\') . '/'; }
function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false { return json_encode($value, $flags, $depth); }
/** @param array<string,mixed> $args @return mixed */
function get_terms(array $args = array()) { return array(); }
/** @return mixed */
function term_exists(int|string $term, string $taxonomy = '', ?int $parent_term = null) { return array('term_id' => 1, 'term_taxonomy_id' => 1); }
/** @param int|array<int,int|string>|string $terms @return mixed */
function wp_set_object_terms(int $object_id, int|array|string $terms, string $taxonomy, bool $append = false) { return array(); }
/** @return int|false */
function update_post_meta(int $post_id, string $meta_key, mixed $meta_value, mixed $prev_value = '') { return 1; }
function delete_post_meta(int $post_id, string $meta_key, mixed $meta_value = ''): bool { return true; }
function metadata_exists(string $meta_type, int $object_id, string $meta_key): bool { return false; }
/** @return object|false|null */
function get_post(int $post_id) { return null; }
/** @param array<string,mixed> $args @return mixed */
function wp_get_object_terms(int|array $object_ids, string|array $taxonomies, array $args = array()) { return array(); }
/** @param array<int,mixed> $args */
function wp_next_scheduled(string $hook, array $args = array()): int|false { return false; }
/** @param array<int,mixed> $args */
function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = array(), bool $wp_error = false): bool { return true; }
/** @param array<int,mixed> $args */
function wp_clear_scheduled_hook(string $hook, array $args = array(), bool $wp_error = false): int|false { return 0; }
/** @param array<string,mixed> $args @return mixed */
function wp_safe_remote_get(string $url, array $args = array()) { return array(); }
function wp_remote_retrieve_response_code(mixed $response): int { return 200; }
/** @return mixed */
function wp_remote_retrieve_header(mixed $response, string $header) { return ''; }
function wp_tempnam(string $filename = '', ?string $dir = null): string|false { return false; }
/** @param array<string,mixed> $args @return array<int,int> */
function get_posts(array $args = array()): array { return array(); }
/** @param int|object|null $post */
function get_post_mime_type(int|object|null $post = null): string|false { return false; }
function get_attached_file(int $attachment_id, bool $unfiltered = false): string|false { return false; }
function wp_get_attachment_url(int $attachment_id): string|false { return false; }
/** @param array<string,mixed> $file_array @param array<string,mixed> $post_data @return mixed */
function media_handle_sideload(array $file_array, int $post_id = 0, ?string $desc = null, array $post_data = array()) { return 1; }
/** @return mixed */
function wp_delete_attachment(int $post_id, bool $force_delete = false) { return null; }
function get_post_thumbnail_id(int $post = 0): int|false { return 0; }
/** @return int|bool */
function set_post_thumbnail(int $post, int $thumbnail_id) { return true; }
function delete_post_thumbnail(int $post): bool { return true; }
