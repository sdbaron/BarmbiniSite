<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Digest_Scheduler {
	protected $queue_repository;

	protected $settings;

	protected $delivery_service;

	public function __construct( Barmbini_Core_Queue_Repository $queue_repository, Barmbini_Core_Subscription_Settings $settings, Barmbini_Core_Delivery_Service $delivery_service ) {
		$this->queue_repository = $queue_repository;
		$this->settings         = $settings;
		$this->delivery_service = $delivery_service;
	}

	public function register_schedules( $schedules ) {
		if ( ! isset( $schedules['weekly'] ) ) {
			$schedules['weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display'  => 'Once Weekly',
			);
		}

		return $schedules;
	}

	public function schedule_events() {
		if ( ! wp_next_scheduled( 'barmbini_core_daily_digest' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'barmbini_core_daily_digest' );
		}

		if ( ! wp_next_scheduled( 'barmbini_core_weekly_digest' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'barmbini_core_weekly_digest' );
		}
	}

	public function run_daily_digest() {
		$this->run_digest( 'täglich' );
	}

	public function run_weekly_digest() {
		$this->run_digest( 'wöchentlich' );
	}

	protected function run_digest( $frequency ) {
		$items = $this->queue_repository->get_due_items( $frequency );

		if ( empty( $items ) ) {
			return;
		}

		$grouped_items = array();

		foreach ( $items as $item ) {
			$grouped_items[ $item['user_id'] ][] = $item;
		}

		foreach ( $grouped_items as $user_id => $user_items ) {
			$deliverable = array();
			$cancelled   = array();
			$settings    = $this->settings->get_user_settings( $user_id );

			foreach ( $user_items as $item ) {
				if ( $this->is_item_deliverable( $item, $settings ) ) {
					$deliverable[] = $item;
				} else {
					$cancelled[] = $item['id'];
				}
			}

			if ( ! empty( $cancelled ) ) {
				$this->queue_repository->mark_items( $cancelled, 'cancelled' );
			}

			if ( empty( $deliverable ) ) {
				continue;
			}

			$sent = $this->delivery_service->deliver_digest( $user_id, $frequency, $deliverable );
			$this->queue_repository->mark_items( wp_list_pluck( $deliverable, 'id' ), $sent ? 'sent' : 'failed' );
		}
	}

	protected function is_item_deliverable( $item, $settings ) {
		switch ( $item['event_type'] ) {
			case 'news':
				return ! empty( $settings['news_enabled'] ) && $item['frequency'] === $settings['news_frequency'];
			case 'discount':
				return ! empty( $settings['discount_enabled'] ) && $item['frequency'] === $settings['discount_frequency'];
			case 'category_product':
				$category_terms = array_map( 'absint', (array) ( $item['payload']['category_terms'] ?? array() ) );
				return ! empty( $settings['category_enabled'] )
					&& ! empty( $settings['category_terms'] )
					&& $item['frequency'] === $settings['category_frequency']
					&& array_intersect( $category_terms, $settings['category_terms'] );
			default:
				return false;
		}
	}
}