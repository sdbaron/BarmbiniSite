<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Catalog_Hooks {
	protected $breadcrumbs;

	protected $category_display;

	public function __construct( Barmbini_Core_Catalog_Breadcrumbs $breadcrumbs, Barmbini_Core_Catalog_Category_Display $category_display ) {
		$this->breadcrumbs      = $breadcrumbs;
		$this->category_display = $category_display;
	}

	public function register_runtime_hooks() {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return;
		}

		remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
		remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
		remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

		add_action( 'woocommerce_before_main_content', array( $this->breadcrumbs, 'render' ), 5 );
	}

	public function remove_subcategory_count() {
		return '';
	}

	public function enqueue_styles() {
		if ( ! function_exists( 'is_woocommerce' ) || ! ( is_woocommerce() || is_product_taxonomy() || is_shop() ) ) {
			return;
		}

		wp_register_style( 'barmbini-core-catalog', false, array(), BARMBINI_CORE_VERSION );
		wp_enqueue_style( 'barmbini-core-catalog' );
		wp_add_inline_style( 'barmbini-core-catalog', $this->get_inline_styles() );
	}

	protected function get_inline_styles() {
		return implode(
			"\n",
			array(
				'.product-category .woocommerce-loop-category__title { text-align: center; }',
				'.product-category .barmbini-category-description { display: none; text-align: center; font-size: 0.85em; padding: 5px; }',
				'.product-category:hover .barmbini-category-description { display: block; }',
				'.kadence-breadcrumbs { display: none; }',
			)
		);
	}
}