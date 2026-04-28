<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Account_Endpoint {
	protected $settings;

	protected $consent_recorder;

	protected $queue_repository;

	public function __construct( Barmbini_Core_Subscription_Settings $settings, Barmbini_Core_Consent_Recorder $consent_recorder, Barmbini_Core_Queue_Repository $queue_repository = null ) {
		$this->settings         = $settings;
		$this->consent_recorder = $consent_recorder;
		$this->queue_repository = $queue_repository;
	}

	public function register_endpoint() {
		add_rewrite_endpoint( 'abonnements', EP_ROOT | EP_PAGES );
	}

	public function add_menu_item( $items ) {
		$updated_items = array();

		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$updated_items['abonnements'] = 'Abonnements';
			}

			$updated_items[ $key ] = $label;
		}

		if ( ! isset( $updated_items['abonnements'] ) ) {
			$updated_items['abonnements'] = 'Abonnements';
		}

		return $updated_items;
	}

	public function render_content() {
		if ( ! is_user_logged_in() ) {
			echo '<p>Bitte melden Sie sich an, um Ihre Abonnements zu verwalten.</p>';
			return;
		}

		$user_id              = get_current_user_id();
		$settings             = $this->settings->get_user_settings( $user_id );
		$product_categories   = $this->settings->get_product_categories();
		$supported_frequencies = $this->settings->get_supported_frequencies();
		$template_path        = BARMBINI_CORE_PATH . 'templates/account/subscriptions.php';

		if ( file_exists( $template_path ) ) {
			require $template_path;
		}
	}

	public function handle_form_submission() {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		if ( ! $this->is_subscriptions_endpoint_request() || ! is_user_logged_in() ) {
			return;
		}

		check_admin_referer( 'barmbini_save_subscriptions', 'barmbini_subscriptions_nonce' );

		$user_id = get_current_user_id();
		$result  = $this->settings->save_user_settings( $user_id, wp_unslash( $_POST ) );

		$this->consent_recorder->record(
			$user_id,
			$result['current'],
			$result['new'],
			$result['ts'],
			$result['source']
		);

		if ( $this->queue_repository ) {
			$this->queue_repository->cancel_stale_for_user( $user_id, $result['new'] );
		}

		wc_add_notice( 'Ihre Abonnements wurden gespeichert.', 'success' );
		wp_safe_redirect( wc_get_account_endpoint_url( 'abonnements' ) );
		exit;
	}

	public function enqueue_styles() {
		if ( ! $this->is_subscriptions_endpoint_request() ) {
			return;
		}

		wp_enqueue_style(
			'barmbini-core-account-subscriptions',
			BARMBINI_CORE_URL . 'assets/css/account-subscriptions.css',
			array(),
			BARMBINI_CORE_VERSION
		);
	}

	protected function is_subscriptions_endpoint_request() {
		global $wp_query;

		return function_exists( 'is_account_page' )
			&& is_account_page()
			&& isset( $wp_query->query_vars['abonnements'] );
	}
}