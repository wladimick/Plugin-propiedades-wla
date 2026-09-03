<?php

namespace WLA\Inmo\Properties;

final class Capabilities
{
	public const EDIT_POST = 'edit_wla_property';
	public const READ_POST = 'read_wla_property';
	public const DELETE_POST = 'delete_wla_property';
	public const EDIT_POSTS = 'edit_wla_properties';
	public const EDIT_OTHERS_POSTS = 'edit_others_wla_properties';
	public const PUBLISH_POSTS = 'publish_wla_properties';
	public const READ_PRIVATE_POSTS = 'read_private_wla_properties';
	public const DELETE_POSTS = 'delete_wla_properties';
	public const DELETE_PRIVATE_POSTS = 'delete_private_wla_properties';
	public const DELETE_PUBLISHED_POSTS = 'delete_published_wla_properties';
	public const DELETE_OTHERS_POSTS = 'delete_others_wla_properties';
	public const EDIT_PRIVATE_POSTS = 'edit_private_wla_properties';
	public const EDIT_PUBLISHED_POSTS = 'edit_published_wla_properties';

	/**
	 * Explicit post type capability mapping.
	 *
	 * Roles receive these capabilities later in Phase 1 / PR 1.6. Defining
	 * the contract here avoids relying on generic post/product permissions.
	 *
	 * @return array<string, string>
	 */
	public static function postTypeMap(): array
	{
		return array(
			'edit_post'              => self::EDIT_POST,
			'read_post'              => self::READ_POST,
			'delete_post'            => self::DELETE_POST,
			'edit_posts'             => self::EDIT_POSTS,
			'edit_others_posts'      => self::EDIT_OTHERS_POSTS,
			'publish_posts'          => self::PUBLISH_POSTS,
			'read_private_posts'     => self::READ_PRIVATE_POSTS,
			'delete_posts'           => self::DELETE_POSTS,
			'delete_private_posts'   => self::DELETE_PRIVATE_POSTS,
			'delete_published_posts' => self::DELETE_PUBLISHED_POSTS,
			'delete_others_posts'    => self::DELETE_OTHERS_POSTS,
			'edit_private_posts'     => self::EDIT_PRIVATE_POSTS,
			'edit_published_posts'   => self::EDIT_PUBLISHED_POSTS,
			'create_posts'           => self::EDIT_POSTS,
		);
	}

	/**
	 * @return array<int, string>
	 */
	public static function all(): array
	{
		return array_values(array_unique(self::postTypeMap()));
	}
}
