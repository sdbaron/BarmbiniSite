<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Event_Collector {
	protected $settings;

	protected $delivery_service;

	public function __construct( Barmbini_Core_Subscription_Settings $settings, Barmbini_Core_Delivery_Service $delivery_service ) {
		$this->settings         = $settings;
		$this->delivery_service = $delivery_service;
	}

	public function handle_transition_post_status( $new_status, $old_status, $post ) {
		if ( 'publish' === $old_status || 'publish' !== $new_status || empty( $post ) ) {
			return;
		}

		if ( 'post' === $post->post_type && has_category( 'neuigkeiten', $post ) ) {
			$event = array(
				'event_type'  => 'news',
				'event_key'   => 'news-' . $post->ID,
				'object_id'   => $post->ID,
				'object_type' => 'post',
				'intro'       => 'Es gibt eine neue Neuigkeit bei Barmbini.',
				'title'       => get_the_title( $post ),
				'excerpt'     => has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( $post->post_content ), 40 ),
				'url'         => get_permalink( $post ),
			);

			foreach ( $this->get_enabled_users( Barmbini_Core_Subscription_Settings::NEWS_ENABLED ) as $user_id ) {
				$this->delivery_service->deliver( $user_id, 'news', $event );
			}
		}

		if ( 'product' === $post->post_type ) {
			$term_ids = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'ids' ) );
			$event    = array(
				'event_type'  => 'category_product',
				'event_key'   => 'product-' . $post->ID,
				'object_id'   => $post->ID,
				'object_type' => 'product',
				'intro'       => 'Es ist ein neuer Artikel in einer Ihrer abonnierten Kategorien erschienen.',
				'title'       => get_the_title( $post ),
				'excerpt'     => wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: $post->post_content ), 40 ),
				'url'         => get_permalink( $post ),
				'category_terms' => array_map( 'absint', $term_ids ),
			);

			foreach ( $this->get_category_subscribers( $term_ids ) as $user_id ) {
				$this->delivery_service->deliver( $user_id, 'category', $event );
			}
		}
	}

	public function handle_product_save( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || empty( $update ) ) {
			return;
		}

		if ( empty( $post ) || 'product' !== $post->post_type || ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$this->maybe_dispatch_discount_event( $post_id );
	}

	public function handle_scheduled_sales() {
		if ( ! function_exists( 'wc_get_product_ids_on_sale' ) ) {
			return;
		}

		foreach ( wc_get_product_ids_on_sale() as $product_id ) {
			$this->maybe_dispatch_discount_event( $product_id );
		}
	}

	protected function maybe_dispatch_discount_event( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return;
		}

		$is_on_sale = $product->is_on_sale();
		$state_key  = '_barmbini_discount_active_state';
		$state      = (string) get_post_meta( $product_id, $state_key, true );

		if ( ! $is_on_sale ) {
			update_post_meta( $product_id, $state_key, '0' );
			return;
		}

		if ( '1' === $state ) {
			return;
		}

		update_post_meta( $product_id, $state_key, '1' );

		$event = array(
			'event_type'  => 'discount',
			'event_key'   => 'discount-' . $product_id . '-' . gmdate( 'YmdHis' ),
			'object_id'   => $product_id,
			'object_type' => 'product',
			'intro'       => 'Für einen Artikel bei Barmbini ist ein neuer Rabatt aktiv.',
			'title'       => $product->get_name(),
			'excerpt'     => wp_strip_all_tags( $product->get_short_description() ?: $product->get_description() ),
			'url'         => get_permalink( $product_id ),
		);

		foreach ( $this->get_enabled_users( Barmbini_Core_Subscription_Settings::DISCOUNT_ENABLED ) as $user_id ) {
			$this->delivery_service->deliver( $user_id, 'discount', $event );
		}
	}

	protected function get_enabled_users( $meta_key ) {
		$users = get_users(
			array(
				'fields'     => 'ids',
				'meta_key'   => $meta_key,
				'meta_value' => '1',
			)
		);

		return array_map( 'absint', $users );
	}

	protected function get_category_subscribers( $term_ids ) {
		if ( empty( $term_ids ) ) {
			return array();
		}

		$users      = get_users(
			array(
				'fields'     => 'ids',
				'meta_key'   => Barmbini_Core_Subscription_Settings::CATEGORY_ENABLED,
				'meta_value' => '1',
			)
		);
		$recipients = array();

		foreach ( $users as $user_id ) {
			$user_settings = $this->settings->get_user_settings( $user_id );

			if ( array_intersect( $term_ids, $user_settings['category_terms'] ) ) {
				$recipients[] = absint( $user_id );
			}
		}

		return array_values( array_unique( $recipients ) );
	}
}