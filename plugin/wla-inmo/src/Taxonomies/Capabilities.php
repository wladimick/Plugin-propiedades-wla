<?php

namespace WLA\Inmo\Taxonomies;

final class Capabilities
{
	public const MANAGE_TERMS = 'manage_wla_property_terms';
	public const EDIT_TERMS = 'edit_wla_property_terms';
	public const DELETE_TERMS = 'delete_wla_property_terms';
	public const ASSIGN_TERMS = 'assign_wla_property_terms';

	/**
	 * @return array<string, string>
	 */
	public static function map(): array
	{
		return array(
			'manage_terms' => self::MANAGE_TERMS,
			'edit_terms'   => self::EDIT_TERMS,
			'delete_terms' => self::DELETE_TERMS,
			'assign_terms' => self::ASSIGN_TERMS,
		);
	}

	/**
	 * @return array<int, string>
	 */
	public static function all(): array
	{
		return array_values(self::map());
	}
}
