<?php

namespace WLA\Inmo\Search;

final class IndexSchema
{
	public const DB_VERSION = '2';
	public const DB_VERSION_OPTION = 'wla_inmo_db_version';
	public const TABLE_SUFFIX = 'wla_property_index';

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
property_code varchar(100) NULL,
external_id varchar(191) NULL,
status varchar(40) NULL,
operation_slug varchar(100) NULL,
type_slug varchar(100) NULL,
region_slug varchar(100) NULL,
commune_slug varchar(100) NULL,
sector_slug varchar(150) NULL,
price_clp bigint(20) unsigned NULL,
price_uf decimal(14,2) NULL,
price_usd decimal(14,2) NULL,
bedrooms smallint(5) unsigned NULL,
bathrooms smallint(5) unsigned NULL,
parking smallint(5) unsigned NULL,
land_area_m2 decimal(14,2) NULL,
built_area_m2 decimal(14,2) NULL,
latitude decimal(10,7) NULL,
longitude decimal(10,7) NULL,
featured tinyint(1) NOT NULL DEFAULT 0,
updated_at datetime NOT NULL,
PRIMARY KEY  (property_id),
UNIQUE KEY property_code (property_code),
KEY operation_status (operation_slug,status),
KEY type_slug (type_slug),
KEY region_slug (region_slug),
KEY commune_slug (commune_slug),
KEY sector_slug (sector_slug),
KEY status_featured (status,featured),
KEY price_clp (price_clp),
KEY featured_updated (featured,updated_at)
) {$charsetCollate};";
	}
}
