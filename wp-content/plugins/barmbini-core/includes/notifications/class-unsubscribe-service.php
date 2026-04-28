<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Unsubscribe_Service {
	protected $settings;

	protected $queue_repository;

	public function __construct( Barmbini_Core_Subscription_Settings $settings, Barmbini_Core_Queue_Repository $queue_repository = null ) {
		$this->settings = $settings;
		$this->queue_repository = $queue_repository;
	}

	public function get_unsubscribe_url( $user_id, $scope = 'all' ) {
		$token = $this->build_token( $user_id );

		return add_query_arg(
			array(
				'barmbini_unsubscribe' => '1',
				'user_id'              => absint( $user_id ),
				'scope'                => sanitize_key( $scope ),
				'token'                => rawurlencode( $token ),
			),
			home_url( '/' )
		);
	}

	public function handle_request() {
		if ( empty( $_GET['barmbini_unsubscribe'] ) || empty( $_GET['user_id'] ) || empty( $_GET['token'] ) ) {
			return;
		}

		$user_id = absint( wp_unslash( $_GET['user_id'] ) );
		$scope   = sanitize_key( wp_unslash( $_GET['scope'] ?? 'all' ) );
		$token   = sanitize_text_field( wp_unslash( $_GET['token'] ) );

		if ( ! $user_id || ! $this->is_valid_token( $user_id, $token ) ) {
			return;
		}

		switch ( $scope ) {
			case 'news':
				update_user_meta( $user_id, Barmbini_Core_Subscription_Settings::NEWS_ENABLED, '0' );
				break;
			case 'discount':
				update_user_meta( $user_id, Barmbini_Core_Subscription_Settings::DISCOUNT_ENABLED, '0' );
				break;
			case 'category':
				update_user_meta( $user_id, Barmbini_Core_Subscription_Settings::CATEGORY_ENABLED, '0' );
				update_user_meta( $user_id, Barmbini_Core_Subscription_Settings::CATEGORY_TERMS, array() );
				break;
			case 'all':
			default:
				update_user_meta( $user_id, Barmbini_Core_Subscription_Settings::NEWS_ENABLED, '0' );
				update_user_meta( $user_id, Barmbini_Core_Subscription_Settings::DISCOUNT_ENABLED, '0' );
				update_user_meta( $user_id, Barmbini_Core_Subscription_Settings::CATEGORY_ENABLED, '0' );
				update_user_meta( $user_id, Barmbini_Core_Subscription_Settings::CATEGORY_TERMS, array() );
				break;
		}

		$this->settings->update_timestamp( $user_id, current_time( 'mysql', true ) );
		$this->settings->refresh_unsubscribe_seed( $user_id );

		if ( $this->queue_repository ) {
			$this->queue_repository->cancel_pending_for_user( $user_id, $scope );
		}

		wp_safe_redirect( add_query_arg( 'barmbini_unsubscribed', '1', home_url( '/' ) ) );
		exit;
	}

	protected function is_valid_token( $user_id, $token ) {
		$expected_hash = (string) get_user_meta( $user_id, Barmbini_Core_Subscription_Settings::UNSUBSCRIBE_TOKEN_HASH, true );

		return ! empty( $expected_hash ) && hash_equals( $expected_hash, wp_hash( $this->build_seed( $user_id ) ) ) && hash_equals( $this->build_token( $user_id ), $token );
	}

	protected function build_token( $user_id ) {
		return hash_hmac( 'sha256', $this->build_seed( $user_id ), wp_salt( 'auth' ) );
	}

	protected function build_seed( $user_id ) {
		return implode(
			'|',
			array(
				(string) $user_id,
				(string) get_user_meta( $user_id, Barmbini_Core_Subscription_Settings::CONSENT_AT, true ),
				(string) get_user_meta( $user_id, Barmbini_Core_Subscription_Settings::SUBSCRIPTION_UPDATED_AT, true ),
			)
		);
	}
}