<?php
/**
 * Frontend-Asset-Steuerung
 *
 * Reduziert unnötige Frontend-Skripte auf Katalogseiten:
 * - WooCommerce Order Attribution (Sourcebuster) dauerhaft aus
 * - Contact Form 7 CSS/JS nur auf Seiten mit Formular
 * - WooCommerce Cart-/Frontend-JS nur auf Shop-, Produkt- und Kontoseiten
 * - defer für eigene und ausgewählte Theme-/Plugin-Skripte
 *
 * @package Barmbini_Core
 * @since 0.10.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Frontend_Assets {

	/**
	 * Registriert Filter und Dequeue-Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'pre_option_woocommerce_feature_order_attribution_enabled', array( $this, 'disable_order_attribution_option' ) );
		add_filter( 'wc_order_attribution_allow_tracking', '__return_false' );
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_order_attribution_assets' ), 100 );

		add_filter( 'wpcf7_load_js', array( $this, 'should_load_cf7_assets' ) );
		add_filter( 'wpcf7_load_css', array( $this, 'should_load_cf7_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_cf7_assets_when_unused' ), 100 );

		add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_woocommerce_scripts_when_unused' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'apply_defer_strategy' ), 999 );
	}

	/**
	 * Erzwingt Order Attribution = aus (ohne DB-Schreiben).
	 *
	 * @param mixed $value Vorheriger Optionswert (wird ignoriert).
	 * @return string
	 */
	public function disable_order_attribution_option( $value ) {
		return 'no';
	}

	/**
	 * Entfernt Sourcebuster / Order-Attribution-Skripte falls doch enqueued.
	 *
	 * @return void
	 */
	public function dequeue_order_attribution_assets() {
		wp_dequeue_script( 'sourcebuster-js' );
		wp_deregister_script( 'sourcebuster-js' );
		wp_dequeue_script( 'wc-order-attribution' );
		wp_deregister_script( 'wc-order-attribution' );
	}

	/**
	 * Filter: wpcf7_load_js / wpcf7_load_css
	 *
	 * @param bool $load Bisheriger Status.
	 * @return bool
	 */
	public function should_load_cf7_assets( $load ) {
		if ( is_admin() ) {
			return $load;
		}

		return self::page_needs_cf7();
	}

	/**
	 * Falls CF7 trotzdem Assets gesetzt hat: wieder entfernen.
	 *
	 * @return void
	 */
	public function dequeue_cf7_assets_when_unused() {
		if ( self::page_needs_cf7() ) {
			return;
		}

		wp_dequeue_script( 'contact-form-7' );
		wp_dequeue_script( 'swv' );
		wp_dequeue_style( 'contact-form-7' );
	}

	/**
	 * Entfernt WooCommerce-Frontend-/Cart-JS außerhalb von Shop und Konto.
	 *
	 * @return void
	 */
	public function dequeue_woocommerce_scripts_when_unused() {
		if ( self::page_needs_woocommerce_scripts() ) {
			return;
		}

		$handles = array(
			'woocommerce',
			'wc-add-to-cart',
			'wc-add-to-cart-variation',
			'wc-cart-fragments',
			'wc-cart',
			'wc-checkout',
			'wc-single-product',
			'wc-jquery-blockui',
			'jquery-blockui',
			'wc-js-cookie',
			'js-cookie',
		);

		foreach ( $handles as $handle ) {
			wp_dequeue_script( $handle );
		}
	}

	/**
	 * Setzt loading strategy=defer für sichere Frontend-Handles.
	 *
	 * Nicht: jQuery (Inline/abhängige Plugins), CF7 (Inline-before),
	 * Kadence Navigation (Theme setzt bereits async).
	 *
	 * @return void
	 */
	public function apply_defer_strategy() {
		if ( is_admin() ) {
			return;
		}

		/**
		 * Filter: Script-Handles, die mit defer geladen werden sollen.
		 *
		 * @param string[] $handles Script-Handles.
		 */
		$handles = apply_filters(
			'barmbini_core_defer_script_handles',
			array(
				'barmbini-core-footer-burger-menu',
				'barmbini-core-progressive-bg',
			)
		);

		foreach ( $handles as $handle ) {
			$handle = (string) $handle;
			if ( '' === $handle ) {
				continue;
			}
			if ( wp_script_is( $handle, 'registered' ) || wp_script_is( $handle, 'enqueued' ) ) {
				wp_script_add_data( $handle, 'strategy', 'defer' );
			}
		}
	}

	/**
	 * Prüft, ob die aktuelle Frontend-Seite CF7-Assets braucht.
	 *
	 * Erkennung: Shortcode/Block im Inhalt, bekannte Kontakt-Slug, Filter.
	 *
	 * @return bool
	 */
	public static function page_needs_cf7() {
		$needs = false;

		if ( is_singular() ) {
			$post = get_post();
			if ( $post instanceof WP_Post ) {
				$content = (string) $post->post_content;
				if (
					has_shortcode( $content, 'contact-form-7' )
					|| false !== strpos( $content, '[contact-form-7' )
					|| ( function_exists( 'has_block' ) && has_block( 'contact-form-7/contact-form-selector', $post ) )
				) {
					$needs = true;
				}
			}
		}

		// Bekannte Kontaktseite (Kadence/Builder kann Shortcode außerhalb post_content halten).
		if ( ! $needs && is_page( array( 'kontakt', 'kontakt-anfahrt', 'contact' ) ) ) {
			$needs = true;
		}

		/**
		 * Filter: CF7-Assets auf dieser Anfrage laden?
		 *
		 * @param bool $needs Aktuelle Entscheidung.
		 */
		return (bool) apply_filters( 'barmbini_core_load_cf7_assets', $needs );
	}

	/**
	 * Prüft, ob WooCommerce-Frontend-JS auf dieser Seite nötig ist.
	 *
	 * Behalten auf Shop/Produkt/Taxonomie, Warenkorb, Kasse und Mein Konto.
	 *
	 * @return bool
	 */
	public static function page_needs_woocommerce_scripts() {
		$needs = false;

		if ( function_exists( 'is_woocommerce' ) ) {
			$needs = is_woocommerce()
				|| is_cart()
				|| is_checkout()
				|| is_account_page();
		}

		/**
		 * Filter: WooCommerce-Frontend-JS auf dieser Anfrage laden?
		 *
		 * @param bool $needs Aktuelle Entscheidung.
		 */
		return (bool) apply_filters( 'barmbini_core_load_woocommerce_scripts', $needs );
	}
}
