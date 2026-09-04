<?php

namespace WLA\Inmo\Import;

final class IdentitySchema
{
	public const DB_VERSION = '1';
	public const DB_VERSION_OPTION = 'wla_inmo_import_identity_db_version';
	public const TABLE_SUFFIX = 'wla_import_identity';

	public static function tableName(mixed $wpdb): string
	{
		return (string) $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function sql(mixed $wpdb): string
	{
		$table = self::tableName($wpdb);
		$charsetCollate = (string) $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (\nproperty_id bigint(20) unsigned NOT NULL,\nsource_key varchar(64) NULL,\nexternal_id varchar(191) NULL,\nproperty_code varchar(100) NULL,\nupdated_at datetime NOT NULL,\nPRIMARY KEY  (property_id),\nUNIQUE KEY property_code (property_code),\nUNIQUE KEY external_identity (source_key,external_id),\nKEY source_key (source_key),\nKEY external_id (external_id)\n) {$charsetCollate};";
	}
}
