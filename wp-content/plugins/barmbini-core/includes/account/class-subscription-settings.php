<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Subscription_Settings {
	const NEWS_ENABLED            = 'barmbini_news_enabled';
	const NEWS_FREQUENCY          = 'barmbini_news_frequency';
	const DISCOUNT_ENABLED        = 'barmbini_discount_enabled';
	const DISCOUNT_FREQUENCY      = 'barmbini_discount_frequency';
	const CATEGORY_ENABLED        = 'barmbini_category_enabled';
	const CATEGORY_FREQUENCY      = 'barmbini_category_frequency';
	const CATEGORY_TERMS          = 'barmbini_category_terms';
	const SUBSCRIPTION_UPDATED_AT = 'barmbini_subscription_updated_at';
	const CONSENT_AT              = 'barmbini_consent_at';
	const CONSENT_SOURCE          = 'barmbini_consent_source';
	const UNSUBSCRIBE_TOKEN_HASH  = 'barmbini_unsubscribe_token_hash';

	public function get_supported_frequencies() {
		return array( 'sofort', 'taeglich', 'woechentlich' );
	}

	public function get_defaults() {
		return array(
			'news_enabled'       => false,
			'news_frequency'     => 'sofort',
			'discount_enabled'   => false,
			'discount_frequency' => 'sofort',
			'category_enabled'   => false,
			'category_frequency' => 'sofort',
			'category_terms'     => array(),
			'updated_at'         => '',
			'consent_at'         => '',
			'consent_source'     => '',
		);
	}

	public function get_user_settings( $user_id ) {
		$defaults = $this->get_defaults();

		$settings = array(
			'news_enabled'       => $this->get_bool_meta( $user_id, self::NEWS_ENABLED ),
			'news_frequency'     => $this->sanitize_frequency( get_user_meta( $user_id, self::NEWS_FREQUENCY, true ) ),
			'discount_enabled'   => $this->get_bool_meta( $user_id, self::DISCOUNT_ENABLED ),
			'discount_frequency' => $this->sanitize_frequency( get_user_meta( $user_id, self::DISCOUNT_FREQUENCY, true ) ),
			'category_enabled'   => $this->get_bool_meta( $user_id, self::CATEGORY_ENABLED ),
			'category_frequency' => $this->sanitize_frequency( get_user_meta( $user_id, self::CATEGORY_FREQUENCY, true ) ),
			'category_terms'     => $this->get_term_ids( $user_id ),
			'updated_at'         => (string) get_user_meta( $user_id, self::SUBSCRIPTION_UPDATED_AT, true ),
			'consent_at'         => (string) get_user_meta( $user_id, self::CONSENT_AT, true ),
			'consent_source'     => (string) get_user_meta( $user_id, self::CONSENT_SOURCE, true ),
		);

		return wp_parse_args( $settings, $defaults );
	}

	public function save_user_settings( $user_id, $request_data, $source = 'account_endpoint' ) {
		$current_settings = $this->get_user_settings( $user_id );
		$new_settings     = array(
			'news_enabled'       => ! empty( $request_data['news_enabled'] ),
			'news_frequency'     => $this->sanitize_frequency( $request_data['news_frequency'] ?? '' ),
			'discount_enabled'   => ! empty( $request_data['discount_enabled'] ),
			'discount_frequency' => $this->sanitize_frequency( $request_data['discount_frequency'] ?? '' ),
			'category_enabled'   => ! empty( $request_data['category_enabled'] ),
			'category_frequency' => $this->sanitize_frequency( $request_data['category_frequency'] ?? '' ),
			'category_terms'     => $this->sanitize_term_ids( $request_data['category_terms'] ?? array() ),
		);

		if ( empty( $new_settings['category_terms'] ) ) {
			$new_settings['category_enabled'] = false;
		}

		update_user_meta( $user_id, self::NEWS_ENABLED, $new_settings['news_enabled'] ? '1' : '0' );
		update_user_meta( $user_id, self::NEWS_FREQUENCY, $new_settings['news_frequency'] );
		update_user_meta( $user_id, self::DISCOUNT_ENABLED, $new_settings['discount_enabled'] ? '1' : '0' );
		update_user_meta( $user_id, self::DISCOUNT_FREQUENCY, $new_settings['discount_frequency'] );
		update_user_meta( $user_id, self::CATEGORY_ENABLED, $new_settings['category_enabled'] ? '1' : '0' );
		update_user_meta( $user_id, self::CATEGORY_FREQUENCY, $new_settings['category_frequency'] );
		update_user_meta( $user_id, self::CATEGORY_TERMS, $new_settings['category_terms'] );

		return array(
			'current' => $current_settings,
			'new'     => $new_settings,
			'source'  => sanitize_key( $source ),
			'ts'      => current_time( 'mysql', true ),
		);
	}

	public function has_any_subscription( $settings ) {
		return ! empty( $settings['news_enabled'] )
			|| ! empty( $settings['discount_enabled'] )
			|| ( ! empty( $settings['category_enabled'] ) && ! empty( $settings['category_terms'] ) );
	}

	public function get_product_categories() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		return $terms;
	}

	public function update_timestamp( $user_id, $timestamp ) {
		update_user_meta( $user_id, self::SUBSCRIPTION_UPDATED_AT, $timestamp );
	}

	public function update_consent( $user_id, $timestamp, $source ) {
		update_user_meta( $user_id, self::CONSENT_AT, $timestamp );
		update_user_meta( $user_id, self::CONSENT_SOURCE, sanitize_key( $source ) );
	}

	public function refresh_unsubscribe_seed( $user_id ) {
		$seed = implode(
			'|',
			array(
				(string) $user_id,
				(string) get_user_meta( $user_id, self::CONSENT_AT, true ),
				(string) get_user_meta( $user_id, self::SUBSCRIPTION_UPDATED_AT, true ),
			)
		);

		update_user_meta( $user_id, self::UNSUBSCRIBE_TOKEN_HASH, wp_hash( $seed ) );
	}

	protected function sanitize_frequency( $value ) {
		$value = sanitize_key( $value );

		if ( ! in_array( $value, $this->get_supported_frequencies(), true ) ) {
			return 'sofort';
		}

		return $value;
	}

	protected function sanitize_term_ids( $term_ids ) {
		$term_ids = is_array( $term_ids ) ? $term_ids : array( $term_ids );
		$term_ids = array_map( 'absint', $term_ids );
		$term_ids = array_filter( $term_ids );
		$term_ids = array_values( array_unique( $term_ids ) );

		return $term_ids;
	}

	protected function get_term_ids( $user_id ) {
		$term_ids = get_user_meta( $user_id, self::CATEGORY_TERMS, true );

		return $this->sanitize_term_ids( is_array( $term_ids ) ? $term_ids : array() );
	}

	protected function get_bool_meta( $user_id, $key ) {
		return '1' === (string) get_user_meta( $user_id, $key, true );
	}
}