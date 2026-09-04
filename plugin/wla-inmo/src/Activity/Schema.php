<?php

namespace WLA\Inmo\Activity;

final class Schema
{
	public const DB_VERSION = '1.0.0';
	public const DB_VERSION_OPTION = 'wla_inmo_activity_db_version';

	public static function tableName($wpdb = null): string
	{
		if ($wpdb === null) {
			global $wpdb;
		}

		return isset($wpdb->prefix) ? $wpdb->prefix . 'wla_inmo_activity' : 'wp_wla_inmo_activity';
	}

	public static function sql($wpdb): string
	{
		$table = self::tableName($wpdb);
		$charsetCollate = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';

		return "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			event_type varchar(80) NOT NULL,
			object_type varchar(40) NOT NULL DEFAULT '',
			object_id bigint(20) unsigned DEFAULT NULL,
			actor_user_id bigint(20) unsigned DEFAULT NULL,
			summary varchar(255) NOT NULL DEFAULT '',
			context longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY object_timeline (object_type, object_id, created_at),
			KEY event_timeline (event_type, created_at)
		) {$charsetCollate};";
	}
}
