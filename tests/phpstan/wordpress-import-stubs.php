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
