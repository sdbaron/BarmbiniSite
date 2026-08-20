<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Activator {
	public static function activate() {
		self::create_log_table();
		self::create_queue_table();
		add_rewrite_endpoint( 'abonnements', EP_ROOT | EP_PAGES );
		flush_rewrite_rules();
	}

	protected static function create_log_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'barmbini_notification_log';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			event_type varchar(50) NOT NULL,
			event_key varchar(191) NOT NULL,
			object_id bigint(20) unsigned DEFAULT 0,
			object_type varchar(50) NOT NULL,
			delivery_mode varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			sent_at datetime DEFAULT NULL,
			error_message text DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_delivery (user_id, delivery_mode),
			KEY event_lookup (event_type, event_key),
			KEY object_lookup (object_type, object_id)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	protected static function create_queue_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'barmbini_notification_queue';
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			event_type varchar(50) NOT NULL,
			event_key varchar(191) NOT NULL,
			object_id bigint(20) unsigned DEFAULT 0,
			object_type varchar(50) NOT NULL,
			subscription_type varchar(50) NOT NULL,
			frequency varchar(20) NOT NULL,
			scheduled_for datetime NOT NULL,
			status varchar(20) NOT NULL,
			payload longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			processed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_frequency (user_id, frequency, status),
			KEY event_lookup (event_type, event_key),
			KEY scheduled_lookup (scheduled_for, status)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}