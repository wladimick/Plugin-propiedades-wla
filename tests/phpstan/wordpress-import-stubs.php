<?php

/**
 * Minimal WordPress signatures required by the Import domain static-analysis gate.
 * This file is parsed by PHPStan only and is never shipped as runtime code.
 */

function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): bool
{
	return true;
}

function wp_is_post_revision(int $post_id): int|false
{
	return false;
}

function get_post_type(int $post_id): string|false
{
	return false;
}

function get_post_status(int $post_id): string|false
{
	return false;
}

/** @return mixed */
function get_post_meta(int $post_id, string $key = '', bool $single = false)
{
	return null;
}

function get_post_modified_time(string $format = 'U', bool $gmt = false, int $post = 0): string|false
{
	return false;
}

/**
 * @param array<string,mixed> $args
 */
function register_post_meta(string $post_type, string $meta_key, array $args): bool
{
	return true;
}

function __(string $text, string $domain = 'default'): string
{
	return $text;
}

function do_action(string $hook_name, mixed ...$args): void
{
}

/**
 * @param array<string,mixed> $postarr
 * @return mixed
 */
function wp_insert_post(array $postarr, bool $wp_error = false)
{
	return 1;
}

/**
 * @param array<string,mixed> $postarr
 * @return mixed
 */
function wp_update_post(array $postarr, bool $wp_error = false)
{
	return 1;
}

/** @return mixed */
function wp_delete_post(int $post_id, bool $force_delete = false)
{
	return null;
}

function is_wp_error(mixed $thing): bool
{
	return false;
}

function taxonomy_exists(string $taxonomy): bool
{
	return true;
}

function sanitize_title(string $title): string
{
	return strtolower(trim($title));
}

function sanitize_file_name(string $filename): string
{
	return $filename;
}

function sanitize_key(string $key): string
{
	return strtolower($key);
}

function absint(mixed $value): int
{
	return abs((int) $value);
}

/** @return mixed */
function get_transient(string $transient)
{
	return false;
}

function set_transient(string $transient, mixed $value, int $expiration = 0): bool
{
	return true;
}

function delete_transient(string $transient): bool
{
	return true;
}

function trailingslashit(string $value): string
{
	return rtrim($value, '/\\') . '/';
}

/**
 * @param array<string,mixed> $args
 * @return mixed
 */
function get_terms(array $args = array())
{
	return array();
}

/** @return mixed */
function term_exists(int|string $term, string $taxonomy = '', ?int $parent_term = null)
{
	return array('term_id' => 1, 'term_taxonomy_id' => 1);
}

/**
 * @param int|array<int,int|string>|string $terms
 * @return mixed
 */
function wp_set_object_terms(int $object_id, int|array|string $terms, string $taxonomy, bool $append = false)
{
	return array();
}

/** @return int|false */
function update_post_meta(int $post_id, string $meta_key, mixed $meta_value, mixed $prev_value = '')
{
	return 1;
}

/** @return bool */
function delete_post_meta(int $post_id, string $meta_key, mixed $meta_value = '')
{
	return true;
}

function metadata_exists(string $meta_type, int $object_id, string $meta_key): bool
{
	return false;
}

/** @return object|false|null */
function get_post(int $post_id)
{
	return null;
}

/**
 * @param array<string,mixed> $args
 * @return mixed
 */
function wp_get_object_terms(int|array $object_ids, string|array $taxonomies, array $args = array())
{
	return array();
}
