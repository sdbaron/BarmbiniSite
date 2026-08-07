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
		add_action( 'woocommerce_before_main_content', array( $this, 'render_example_notice' ), 30 );
	}

	/**
	 * Gibt den Beispiel-Hinweis auf der Sortiment-Seite und auf allen
	 * Produktkategorie-Seiten aus.
	 *
	 * @return void
	 */
	public function render_example_notice() {
		if ( ! is_shop() && ! is_product_category() ) {
			return;
		}

		echo '<p class="barmbini-example-notice">Die gezeigten Artikel dienen als Beispiele. Das aktuelle Sortiment finden Sie direkt im Laden.</p>';
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
				'.woocommerce nav.woocommerce-breadcrumb { padding: 15px 0 0 15px !important; }',
				'.woocommerce-product-gallery .wp-post-image { width: 200px !important; height: 200px !important; object-fit: cover; object-position: center; }',
				'.barmbini-example-notice { background: #eef7f1; border-left: 4px solid #2d6a4f; padding: 0.75rem 1rem; margin: 0 0 1.5rem; font-size: 0.95rem; }',
				'.woocommerce-loop-product__link, .product-category a, .woocommerce-product-gallery__image { position: relative; }',
				'.woocommerce-loop-product__link::before, .product-category a::before, .woocommerce-product-gallery__image::before { content: "Beispiel"; position: absolute; top: 8px; left: 8px; z-index: 2; background: #2d6a4f; color: #fff; font-size: 0.7rem; line-height: 1; padding: 4px 8px; border-radius: 3px; pointer-events: none; }',
			)
		);
	}
}