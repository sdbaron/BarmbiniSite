<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Log_Repository {
	public function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'barmbini_notification_log';
	}

	public function has_log( $user_id, $event_type, $event_key, $delivery_mode ) {
		global $wpdb;

		$table_name = $this->table_name();

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE user_id = %d AND event_type = %s AND event_key = %s AND delivery_mode = %s LIMIT 1",
				$user_id,
				$event_type,
				$event_key,
				$delivery_mode
			)
		);

		return ! empty( $existing );
	}

	public function insert_log( $data ) {
		global $wpdb;

		$defaults = array(
			'user_id'       => 0,
			'event_type'    => '',
			'event_key'     => '',
			'object_id'     => 0,
			'object_type'   => '',
			'delivery_mode' => 'immediate',
			'status'        => 'sent',
			'sent_at'       => current_time( 'mysql', true ),
			'error_message' => '',
		);

		$data = wp_parse_args( $data, $defaults );

		$wpdb->insert(
			$this->table_name(),
			array(
				'user_id'       => absint( $data['user_id'] ),
				'event_type'    => sanitize_key( $data['event_type'] ),
				'event_key'     => sanitize_text_field( $data['event_key'] ),
				'object_id'     => absint( $data['object_id'] ),
				'object_type'   => sanitize_key( $data['object_type'] ),
				'delivery_mode' => sanitize_key( $data['delivery_mode'] ),
				'status'        => sanitize_key( $data['status'] ),
				'sent_at'       => $data['sent_at'],
				'error_message' => sanitize_textarea_field( $data['error_message'] ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public function get_recent_logs( $limit = 50 ) {
		global $wpdb;

		$table_name = $this->table_name();
		$results    = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY COALESCE(sent_at, '1970-01-01 00:00:00') DESC, id DESC LIMIT %d",
				absint( $limit )
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	public function get_logs_for_user( $user_id, $limit = 100, $offset = 0 ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} WHERE user_id = %d ORDER BY COALESCE(sent_at, '1970-01-01 00:00:00') DESC, id DESC LIMIT %d OFFSET %d",
				absint( $user_id ),
				absint( $limit ),
				absint( $offset )
			),
			ARRAY_A
		);

		return is_array( $results ) ? $results : array();
	}

	public function delete_logs_for_user( $user_id ) {
		global $wpdb;

		$wpdb->delete( $this->table_name(), array( 'user_id' => absint( $user_id ) ), array( '%d' ) );
	}
}