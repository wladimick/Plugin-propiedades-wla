<?php

namespace WLA\Inmo\Import;

final class BatchSchema
{
	public const DB_VERSION = '1';
	public const DB_VERSION_OPTION = 'wla_inmo_import_batch_db_version';
	public const TABLE_SUFFIX = 'wla_import_batches';

	public static function tableName(mixed $wpdb): string
	{
		return (string) $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function sql(mixed $wpdb): string
	{
		$table = self::tableName($wpdb);
		$charsetCollate = (string) $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (\nid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\nbatch_uuid char(36) NOT NULL,\nsource_key varchar(64) NOT NULL,\nsource_hash char(64) NOT NULL,\nstatus varchar(32) NOT NULL,\nprofile_json longtext NOT NULL,\ncreated_by bigint(20) unsigned NOT NULL DEFAULT 0,\ntotal_rows int(10) unsigned NOT NULL DEFAULT 0,\ncursor_row int(10) unsigned NOT NULL DEFAULT 0,\nprocessed_rows int(10) unsigned NOT NULL DEFAULT 0,\ncreated_count int(10) unsigned NOT NULL DEFAULT 0,\nupdated_count int(10) unsigned NOT NULL DEFAULT 0,\nskipped_count int(10) unsigned NOT NULL DEFAULT 0,\nwarning_count int(10) unsigned NOT NULL DEFAULT 0,\nerror_count int(10) unsigned NOT NULL DEFAULT 0,\nrevision bigint(20) unsigned NOT NULL DEFAULT 0,\ncreated_at datetime NOT NULL,\nupdated_at datetime NOT NULL,\nstarted_at datetime NULL,\ncompleted_at datetime NULL,\nPRIMARY KEY  (id),\nUNIQUE KEY batch_uuid (batch_uuid),\nKEY status (status),\nKEY source_hash (source_hash),\nKEY created_by (created_by),\nKEY updated_at (updated_at)\n) {$charsetCollate};";
	}
}
