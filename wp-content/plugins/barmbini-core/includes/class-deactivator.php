<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Deactivator {
	public static function deactivate() {
		wp_clear_scheduled_hook( 'barmbini_core_daily_digest' );
		wp_clear_scheduled_hook( 'barmbini_core_weekly_digest' );
		wp_clear_scheduled_hook( 'barmbini_core_cache_maintenance' );
		wp_clear_scheduled_hook( 'barmbini_core_action_start_notifier' );
		flush_rewrite_rules();
	}
}