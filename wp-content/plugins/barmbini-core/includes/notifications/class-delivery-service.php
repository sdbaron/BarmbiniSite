<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Delivery_Service {
	protected $settings;

	protected $log_repository;

	protected $unsubscribe_service;

	protected $queue_repository;

	public function __construct( Barmbini_Core_Subscription_Settings $settings, Barmbini_Core_Log_Repository $log_repository, Barmbini_Core_Unsubscribe_Service $unsubscribe_service, Barmbini_Core_Queue_Repository $queue_repository = null ) {
		$this->settings            = $settings;
		$this->log_repository      = $log_repository;
		$this->unsubscribe_service = $unsubscribe_service;
		$this->queue_repository    = $queue_repository;
	}

	public function deliver( $user_id, $subscription_type, $event ) {
		$user_settings = $this->settings->get_user_settings( $user_id );
		$frequency     = $this->resolve_frequency( $subscription_type, $user_settings );

		if ( 'sofort' !== $frequency ) {
			return $this->queue_event( $user_id, $subscription_type, $frequency, $event );
		}

		if ( $this->log_repository->has_log( $user_id, $event['event_type'], $event['event_key'], 'immediate' ) ) {
			return false;
		}

		$user = get_userdata( $user_id );

		if ( ! $user || ! is_email( $user->user_email ) ) {
			return false;
		}

		$unsubscribe_url = $this->unsubscribe_service->get_unsubscribe_url( $user_id, $subscription_type );
		$subject         = $this->build_subject( $event );
		$message         = $this->build_message( $event, $unsubscribe_url );
		$headers         = array( 'Content-Type: text/plain; charset=UTF-8' );
		$sent            = wp_mail( $user->user_email, $subject, $message, $headers );

		$this->log_repository->insert_log(
			array(
				'user_id'       => $user_id,
				'event_type'    => $event['event_type'],
				'event_key'     => $event['event_key'],
				'object_id'     => $event['object_id'],
				'object_type'   => $event['object_type'],
				'delivery_mode' => 'immediate',
				'status'        => $sent ? 'sent' : 'failed',
				'error_message' => $sent ? '' : 'wp_mail returned false',
			)
		);

		return $sent;
	}

	public function deliver_digest( $user_id, $frequency, $queue_items ) {
		$user = get_userdata( $user_id );

		if ( ! $user || ! is_email( $user->user_email ) || empty( $queue_items ) ) {
			return false;
		}

		$unsubscribe_url = $this->unsubscribe_service->get_unsubscribe_url( $user_id, 'all' );
		$delivery_mode   = 'täglich' === $frequency ? 'daily_digest' : 'weekly_digest';
		$subject         = 'täglich' === $frequency ? 'Ihr täglicher Barmbini-Digest' : 'Ihr wöchentlicher Barmbini-Digest';
		$message         = $this->build_digest_message( $queue_items, $frequency, $unsubscribe_url );
		$headers         = array( 'Content-Type: text/plain; charset=UTF-8' );
		$sent            = wp_mail( $user->user_email, $subject, $message, $headers );

		foreach ( $queue_items as $item ) {
			$this->log_repository->insert_log(
				array(
					'user_id'       => $user_id,
					'event_type'    => $item['event_type'],
					'event_key'     => $item['event_key'],
					'object_id'     => $item['object_id'],
					'object_type'   => $item['object_type'],
					'delivery_mode' => $delivery_mode,
					'status'        => $sent ? 'sent' : 'failed',
					'error_message' => $sent ? '' : 'wp_mail returned false',
				)
			);
		}

		return $sent;
	}

	protected function resolve_frequency( $subscription_type, $settings ) {
		switch ( $subscription_type ) {
			case 'news':
				return $settings['news_frequency'];
			case 'discount':
				return $settings['discount_frequency'];
			case 'category':
			default:
				return $settings['category_frequency'];
		}
	}

	protected function build_subject( $event ) {
		switch ( $event['event_type'] ) {
			case 'news':
				return 'Neue Neuigkeit bei Barmbini';
			case 'discount':
				return 'Neuer Rabatt bei Barmbini';
			case 'category_product':
			default:
				return 'Neuer Artikel bei Barmbini';
		}
	}

	protected function build_message( $event, $unsubscribe_url ) {
		$lines   = array();
		$lines[] = 'Hallo,';
		$lines[] = '';
		$lines[] = $event['intro'];
		$lines[] = '';
		$lines[] = $event['title'];

		if ( ! empty( $event['excerpt'] ) ) {
			$lines[] = '';
			$lines[] = wp_strip_all_tags( $event['excerpt'] );
		}

		if ( ! empty( $event['url'] ) ) {
			$lines[] = '';
			$lines[] = 'Mehr erfahren: ' . esc_url_raw( $event['url'] );
		}

		$lines[] = '';
		$lines[] = 'Abmeldung: ' . esc_url_raw( $unsubscribe_url );

		return implode( "\n", $lines );
	}

	protected function queue_event( $user_id, $subscription_type, $frequency, $event ) {
		if ( ! $this->queue_repository ) {
			return false;
		}

		return $this->queue_repository->enqueue(
			array(
				'user_id'           => $user_id,
				'event_type'        => $event['event_type'],
				'event_key'         => $event['event_key'],
				'object_id'         => $event['object_id'],
				'object_type'       => $event['object_type'],
				'subscription_type' => $subscription_type,
				'frequency'         => $frequency,
				'payload'           => $event,
			)
		);
	}

	protected function build_digest_message( $queue_items, $frequency, $unsubscribe_url ) {
		$lines   = array();
		$lines[] = 'Hallo,';
		$lines[] = '';
		$lines[] = 'Hier ist Ihr ' . ( 'täglich' === $frequency ? 'täglicher' : 'wöchentlicher' ) . ' Überblick von Barmbini:';
		$lines[] = '';

		foreach ( $queue_items as $item ) {
			$payload  = is_array( $item['payload'] ) ? $item['payload'] : array();
			$title    = $payload['title'] ?? '';
			$intro    = $payload['intro'] ?? '';
			$url      = $payload['url'] ?? '';
			$excerpt  = $payload['excerpt'] ?? '';
			$lines[]  = '- ' . $title;

			if ( $intro ) {
				$lines[] = '  ' . wp_strip_all_tags( $intro );
			}

			if ( $excerpt ) {
				$lines[] = '  ' . wp_trim_words( wp_strip_all_tags( $excerpt ), 30 );
			}

			if ( $url ) {
				$lines[] = '  Mehr erfahren: ' . esc_url_raw( $url );
			}

			$lines[] = '';
		}

		$lines[] = 'Abmeldung: ' . esc_url_raw( $unsubscribe_url );

		return implode( "\n", $lines );
	}
}