<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Queue_Repository {
	public function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'barmbini_notification_queue';
	}

	public function enqueue( $data ) {
		global $wpdb;

		$defaults = array(
			'user_id'           => 0,
			'event_type'        => '',
			'event_key'         => '',
			'object_id'         => 0,
			'object_type'       => '',
			'subscription_type' => '',
			'frequency'         => 'täglich',
			'payload'           => array(),
		);

		$data = wp_parse_args( $data, $defaults );
		$data['frequency'] = Barmbini_Core_Subscription_Settings::normalize_frequency_value( $data['frequency'] );

		if ( $this->has_queue_item( $data['user_id'], $data['event_type'], $data['event_key'], $data['frequency'] ) ) {
			return false;
		}

		return false !== $wpdb->insert(
			$this->table_name(),
			array(
				'user_id'           => absint( $data['user_id'] ),
				'event_type'        => sanitize_key( $data['event_type'] ),
				'event_key'         => sanitize_text_field( $data['event_key'] ),
				'object_id'         => absint( $data['object_id'] ),
				'object_type'       => sanitize_key( $data['object_type'] ),
				'subscription_type' => sanitize_key( $data['subscription_type'] ),
				'frequency'         => $data['frequency'],
				'scheduled_for'     => $this->calculate_scheduled_for( $data['frequency'] ),
				'status'            => 'queued',
				'payload'           => wp_json_encode( $data['payload'] ),
				'created_at'        => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public function get_due_items( $frequency, $limit = 200 ) {
		global $wpdb;
		$frequencies  = Barmbini_Core_Subscription_Settings::get_frequency_aliases( $frequency );
		$placeholders = implode( ', ', array_fill( 0, count( $frequencies ), '%s' ) );
		$params       = array_merge( $frequencies, array( current_time( 'mysql', true ), absint( $limit ) ) );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} WHERE frequency IN ({$placeholders}) AND status = 'queued' AND scheduled_for <= %s ORDER BY scheduled_for ASC, id ASC LIMIT %d",
				$params
			),
			ARRAY_A
		);

		return $this->hydrate_items( is_array( $results ) ? $results : array() );
	}

	public function mark_items( $item_ids, $status ) {
		global $wpdb;

		foreach ( array_filter( array_map( 'absint', (array) $item_ids ) ) as $item_id ) {
			$wpdb->update(
				$this->table_name(),
				array(
					'status'       => sanitize_key( $status ),
					'processed_at' => current_time( 'mysql', true ),
				),
				array( 'id' => $item_id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}
	}

	public function cancel_pending_for_user( $user_id, $scope = 'all' ) {
		global $wpdb;

		foreach ( $this->get_scope_event_types( $scope ) as $event_type ) {
			$wpdb->update(
				$this->table_name(),
				array(
					'status'       => 'cancelled',
					'processed_at' => current_time( 'mysql', true ),
				),
				array(
					'user_id'     => absint( $user_id ),
					'event_type'  => $event_type,
					'status'      => 'queued',
				),
				array( '%s', '%s' ),
				array( '%d', '%s', '%s' )
			);
		}
	}

	public function cancel_stale_for_user( $user_id, $settings ) {
		foreach ( $this->get_pending_items_for_user( $user_id ) as $item ) {
			if ( ! $this->is_item_still_valid( $item, $settings ) ) {
				$this->mark_items( array( $item['id'] ), 'cancelled' );
			}
		}
	}

	public function get_recent_items( $limit = 50 ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} ORDER BY created_at DESC, id DESC LIMIT %d",
				absint( $limit )
			),
			ARRAY_A
		);

		return $this->hydrate_items( is_array( $results ) ? $results : array() );
	}

	public function get_items_for_user( $user_id, $limit = 100, $offset = 0 ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT %d OFFSET %d",
				absint( $user_id ),
				absint( $limit ),
				absint( $offset )
			),
			ARRAY_A
		);

		return $this->hydrate_items( is_array( $results ) ? $results : array() );
	}

	public function delete_items_for_user( $user_id ) {
		global $wpdb;

		$wpdb->delete( $this->table_name(), array( 'user_id' => absint( $user_id ) ), array( '%d' ) );
	}

	protected function has_queue_item( $user_id, $event_type, $event_key, $frequency ) {
		global $wpdb;
		$frequencies  = Barmbini_Core_Subscription_Settings::get_frequency_aliases( $frequency );
		$placeholders = implode( ', ', array_fill( 0, count( $frequencies ), '%s' ) );
		$params       = array_merge(
			array(
				absint( $user_id ),
				sanitize_key( $event_type ),
				sanitize_text_field( $event_key ),
			),
			$frequencies
		);

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->table_name()} WHERE user_id = %d AND event_type = %s AND event_key = %s AND frequency IN ({$placeholders}) AND status IN ('queued', 'processing', 'sent') LIMIT 1",
				$params
			)
		);

		return ! empty( $existing );
	}

	protected function calculate_scheduled_for( $frequency ) {
		$timezone = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $timezone );
		$frequency = Barmbini_Core_Subscription_Settings::normalize_frequency_value( $frequency );

		if ( 'wöchentlich' === $frequency ) {
			$scheduled = $now->modify( 'monday this week' )->setTime( 6, 0 );

			if ( $scheduled <= $now ) {
				$scheduled = $scheduled->modify( '+1 week' );
			}

			return get_gmt_from_date( $scheduled->format( 'Y-m-d H:i:s' ) );
		}

		$scheduled = $now->setTime( 18, 0 );

		if ( $scheduled <= $now ) {
			$scheduled = $scheduled->modify( '+1 day' );
		}

		return get_gmt_from_date( $scheduled->format( 'Y-m-d H:i:s' ) );
	}

	protected function hydrate_items( $items ) {
		foreach ( $items as &$item ) {
			$item['id']       = absint( $item['id'] );
			$item['user_id']  = absint( $item['user_id'] );
			$item['object_id'] = absint( $item['object_id'] );
			$item['frequency'] = Barmbini_Core_Subscription_Settings::normalize_frequency_value( $item['frequency'] ?? '' );
			$item['payload']  = json_decode( (string) $item['payload'], true );
			$item['payload']  = is_array( $item['payload'] ) ? $item['payload'] : array();
		}

		return $items;
	}

	protected function get_scope_event_types( $scope ) {
		switch ( $scope ) {
			case 'news':
				return array( 'news' );
			case 'discount':
				return array( 'discount' );
			case 'category':
				return array( 'category_product' );
			case 'all':
			default:
				return array( 'news', 'discount', 'category_product' );
		}
	}

	protected function get_pending_items_for_user( $user_id ) {
		global $wpdb;

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name()} WHERE user_id = %d AND status = 'queued' ORDER BY created_at DESC",
				absint( $user_id )
			),
			ARRAY_A
		);

		return $this->hydrate_items( is_array( $results ) ? $results : array() );
	}

	protected function is_item_still_valid( $item, $settings ) {
		switch ( $item['event_type'] ) {
			case 'news':
				return ! empty( $settings['news_enabled'] ) && $item['frequency'] === $settings['news_frequency'];
			case 'discount':
				return ! empty( $settings['discount_enabled'] ) && $item['frequency'] === $settings['discount_frequency'];
			case 'category_product':
				$item_terms = array_map( 'absint', (array) ( $item['payload']['category_terms'] ?? array() ) );
				return ! empty( $settings['category_enabled'] )
					&& ! empty( $settings['category_terms'] )
					&& $item['frequency'] === $settings['category_frequency']
					&& array_intersect( $item_terms, $settings['category_terms'] );
			default:
				return false;
		}
	}
}