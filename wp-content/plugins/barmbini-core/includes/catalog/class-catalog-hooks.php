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
		add_action( 'woocommerce_before_shop_loop_item_title', array( $this, 'render_example_badge' ), 10 );
		add_action( 'woocommerce_before_single_product_summary', array( $this, 'render_example_badge' ), 10 );
	}

	/**
	 * Gibt das Badge "Beispiel" auf Produktfotos aus.
	 *
	 * Nutzt dieselbe Mechanik wie das WooCommerce-Sale-Badge ("Angebot!",
	 * .onsale): ein per Hook injiziertes <span>-Element. Das Badge erscheint
	 * nur, wenn das Produkt das Produkt-Schlagwort "Beispiel" (product_tag,
	 * Slug "beispiel") trägt. Kategoriebilder (.product-category) werden
	 * nicht erfasst.
	 *
	 * Die Klasse "onsale" wird nur für identische Größen-/Schriftwerte
	 * geerbt; Position (oben links) und Farben überschreibt
	 * .barmbini-example-badge.
	 *
	 * @return void
	 */
	public function render_example_badge() {
		global $product;

		if ( ! $product ) {
			return;
		}

		if ( ! has_term( 'beispiel', 'product_tag', $product->get_id() ) ) {
			return;
		}

		echo '<span class="barmbini-example-badge onsale">Beispiel</span>';
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
				'.woocommerce-product-gallery__image img:not(.zoomImg) { width: 200px !important; height: 200px !important; object-fit: cover; object-position: center; }',
				'.barmbini-example-notice { background: #eef7f1; border-left: 4px solid #2d6a4f; padding: 0.75rem 1rem; margin: 0 0 1.5rem; font-size: 0.95rem; }',
				'.barmbini-example-badge { left: 6px !important; right: auto !important; top: 6px !important; background: #2d6a4f !important; color: #fff !important; z-index: 9; pointer-events: none; }',
				'.single-product .barmbini-example-badge { top: 44px !important; left: 8px !important; }',
			)
		);
	}
}