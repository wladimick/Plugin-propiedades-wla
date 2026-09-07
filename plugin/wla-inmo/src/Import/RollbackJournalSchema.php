<?php

namespace WLA\Inmo\Import;

final class RollbackJournalSchema
{
	public const DB_VERSION = '1';
	public const DB_VERSION_OPTION = 'wla_inmo_import_rollback_db_version';
	public const TABLE_SUFFIX = 'wla_import_rollback_journal';

	public static function tableName(mixed $wpdb): string
	{
		return (string) $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function sql(mixed $wpdb): string
	{
		$table = self::tableName($wpdb);
		$charsetCollate = (string) $wpdb->get_charset_collate();

		return "CREATE TABLE {$table} (\nid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\nbatch_uuid char(36) NOT NULL,\nrow_number int(10) unsigned NOT NULL,\nproperty_id bigint(20) unsigned NOT NULL DEFAULT 0,\noriginal_action varchar(16) NOT NULL,\ntargets_json longtext NOT NULL,\nbefore_json longtext NULL,\nafter_json longtext NULL,\nafter_hash char(64) NOT NULL DEFAULT '',\ncreated_object_hash char(64) NOT NULL DEFAULT '',\njournal_state varchar(16) NOT NULL DEFAULT 'prepared',\nrollback_status varchar(16) NOT NULL DEFAULT 'pending',\nrollback_reason varchar(64) NOT NULL DEFAULT '',\ncreated_at datetime NOT NULL,\nupdated_at datetime NOT NULL,\nrolled_back_at datetime NULL,\nPRIMARY KEY  (id),\nUNIQUE KEY batch_row (batch_uuid,row_number),\nKEY batch_uuid (batch_uuid),\nKEY property_id (property_id),\nKEY journal_state (journal_state),\nKEY rollback_status (rollback_status)\n) {$charsetCollate};";
	}
}
