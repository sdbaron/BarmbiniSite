<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Privacy_Exporter {
	protected $settings;

	protected $log_repository;

	protected $queue_repository;

	public function __construct( Barmbini_Core_Subscription_Settings $settings, Barmbini_Core_Log_Repository $log_repository, Barmbini_Core_Queue_Repository $queue_repository ) {
		$this->settings         = $settings;
		$this->log_repository   = $log_repository;
		$this->queue_repository = $queue_repository;
	}

	public function register_exporter( $exporters ) {
		$exporters['barmbini-notifications'] = array(
			'exporter_friendly_name' => 'Barmbini Benachrichtigungen',
			'callback'               => array( $this, 'export_personal_data' ),
		);

		return $exporters;
	}

	public function register_eraser( $erasers ) {
		$erasers['barmbini-notifications'] = array(
			'eraser_friendly_name' => 'Barmbini Benachrichtigungen',
			'callback'             => array( $this, 'erase_personal_data' ),
		);

		return $erasers;
	}

	public function export_personal_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$settings    = $this->settings->get_user_settings( $user->ID );
		$logs        = $this->log_repository->get_logs_for_user( $user->ID, 100, ( max( 1, absint( $page ) ) - 1 ) * 100 );
		$queue_items = $this->queue_repository->get_items_for_user( $user->ID, 100, ( max( 1, absint( $page ) ) - 1 ) * 100 );
		$data        = array();

		$data[] = array(
			'group_id'    => 'barmbini-subscriptions',
			'group_label' => 'Barmbini Abonnements',
			'item_id'     => 'barmbini-subscriptions-' . $user->ID,
			'data'        => $this->build_subscription_export_rows( $settings ),
		);

		foreach ( $logs as $log ) {
			$data[] = array(
				'group_id'    => 'barmbini-notification-log',
				'group_label' => 'Barmbini Versandlog',
				'item_id'     => 'barmbini-log-' . $log['id'],
				'data'        => array(
					array( 'name' => 'Ereignis', 'value' => $log['event_type'] ),
					array( 'name' => 'Modus', 'value' => $log['delivery_mode'] ),
					array( 'name' => 'Status', 'value' => $log['status'] ),
					array( 'name' => 'Gesendet am', 'value' => $log['sent_at'] ?: '' ),
				),
			);
		}

		foreach ( $queue_items as $queue_item ) {
			$data[] = array(
				'group_id'    => 'barmbini-notification-queue',
				'group_label' => 'Barmbini Benachrichtigungsqueue',
				'item_id'     => 'barmbini-queue-' . $queue_item['id'],
				'data'        => array(
					array( 'name' => 'Ereignis', 'value' => $queue_item['event_type'] ),
					array( 'name' => 'Frequenz', 'value' => $queue_item['frequency'] ),
					array( 'name' => 'Status', 'value' => $queue_item['status'] ),
					array( 'name' => 'Geplant für', 'value' => $queue_item['scheduled_for'] ),
				),
			);
		}

		$done = count( $logs ) < 100 && count( $queue_items ) < 100;

		return array(
			'data' => $data,
			'done' => $done,
		);
	}

	public function erase_personal_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );

		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		update_user_meta( $user->ID, Barmbini_Core_Subscription_Settings::NEWS_ENABLED, '0' );
		update_user_meta( $user->ID, Barmbini_Core_Subscription_Settings::DISCOUNT_ENABLED, '0' );
		update_user_meta( $user->ID, Barmbini_Core_Subscription_Settings::CATEGORY_ENABLED, '0' );
		update_user_meta( $user->ID, Barmbini_Core_Subscription_Settings::CATEGORY_TERMS, array() );
		delete_user_meta( $user->ID, Barmbini_Core_Subscription_Settings::CONSENT_AT );
		delete_user_meta( $user->ID, Barmbini_Core_Subscription_Settings::CONSENT_SOURCE );
		delete_user_meta( $user->ID, Barmbini_Core_Subscription_Settings::SUBSCRIPTION_UPDATED_AT );
		delete_user_meta( $user->ID, Barmbini_Core_Subscription_Settings::UNSUBSCRIBE_TOKEN_HASH );

		$this->log_repository->delete_logs_for_user( $user->ID );
		$this->queue_repository->delete_items_for_user( $user->ID );

		return array(
			'items_removed'  => true,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	protected function build_subscription_export_rows( $settings ) {
		return array(
			array( 'name' => 'Neuigkeiten', 'value' => ! empty( $settings['news_enabled'] ) ? 'aktiv' : 'inaktiv' ),
			array( 'name' => 'Neuigkeiten Frequenz', 'value' => $settings['news_frequency'] ),
			array( 'name' => 'Rabatte', 'value' => ! empty( $settings['discount_enabled'] ) ? 'aktiv' : 'inaktiv' ),
			array( 'name' => 'Rabatte Frequenz', 'value' => $settings['discount_frequency'] ),
			array( 'name' => 'Produktkategorien', 'value' => ! empty( $settings['category_enabled'] ) ? implode( ', ', $this->term_names( $settings['category_terms'] ) ) : 'inaktiv' ),
			array( 'name' => 'Produktkategorien Frequenz', 'value' => $settings['category_frequency'] ),
			array( 'name' => 'Einwilligung erfasst am', 'value' => $settings['consent_at'] ),
			array( 'name' => 'Einwilligungsquelle', 'value' => $settings['consent_source'] ),
			array( 'name' => 'Zuletzt aktualisiert am', 'value' => $settings['updated_at'] ),
		);
	}

	protected function term_names( $term_ids ) {
		$names = array();

		foreach ( array_filter( array_map( 'absint', (array) $term_ids ) ) as $term_id ) {
			$term = get_term( $term_id, 'product_cat' );

			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}

		return $names;
	}
}