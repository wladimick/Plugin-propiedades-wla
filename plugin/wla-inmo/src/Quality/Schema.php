<?php

namespace WLA\Inmo\Quality;

final class Schema
{
	public const DB_VERSION = '1';
	public const DB_VERSION_OPTION = 'wla_inmo_quality_db_version';
	public const TABLE_SUFFIX = 'wla_property_quality';

	public static function tableName($wpdb): string
	{
		return (string) $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function sql($wpdb): string
	{
		$table = self::tableName($wpdb);
		$charsetCollate = method_exists($wpdb, 'get_charset_collate') ? (string) $wpdb->get_charset_collate() : '';

		return "CREATE TABLE {$table} (
property_id bigint(20) unsigned NOT NULL,
score tinyint(3) unsigned NOT NULL DEFAULT 0,
passed_checks smallint(5) unsigned NOT NULL DEFAULT 0,
total_checks smallint(5) unsigned NOT NULL DEFAULT 0,
is_complete tinyint(1) NOT NULL DEFAULT 0,
has_price tinyint(1) NOT NULL DEFAULT 0,
has_image tinyint(1) NOT NULL DEFAULT 0,
missing_codes varchar(255) NOT NULL DEFAULT '',
updated_at datetime NOT NULL,
PRIMARY KEY  (property_id),
KEY score (score),
KEY complete_score (is_complete,score),
KEY price_score (has_price,score),
KEY image_score (has_image,score),
KEY updated_at (updated_at)
) {$charsetCollate};";
	}
}
