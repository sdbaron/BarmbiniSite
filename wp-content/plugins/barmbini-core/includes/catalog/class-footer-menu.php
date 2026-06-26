<?php
/**
 * Footer Burger Menu – PHP-Integration
 *
 * Injiziert den Burger-Toggle-Button per PHP-Filter in Footer-Menüs,
 * exakt nach dem Muster, das Kadence beim Header-Menü verwendet.
 * CSS + JS ergänzen die responsive Steuerung.
 *
 * @package Barmbini_Core
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Footer_Menu {

	/**
	 * Registriert alle Hooks für das Footer-Burger-Menü.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'wp_nav_menu', array( $this, 'maybe_wrap_with_burger' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Filter: wp_nav_menu
	 *
	 * Wickelt Footer-Menüs in einen Burger-Container und fügt
	 * den Toggle-Button ein – analog zu Kadences Header-Menü.
	 *
	 * Identifiziert Footer-Menüs anhand von:
	 * - theme_location mit dem Wort "footer"
	 * - menu_class / menu_id mit dem Wort "footer"
	 * - Widget-Menüs innerhalb von #colophon (Fallback)
	 *
	 * @param string   $nav_menu Das fertige Menü-HTML.
	 * @param stdClass $args     wp_nav_menu-Argumente.
	 * @return string Modifiziertes Menü-HTML.
	 */
	public function maybe_wrap_with_burger( $nav_menu, $args ) {
		if ( is_admin() ) {
			return $nav_menu;
		}

		// Bereits gewrappte Menüs nicht doppelt behandeln.
		if ( false !== strpos( $nav_menu, 'barmbini-footer-burger-container' ) ) {
			return $nav_menu;
		}

		if ( ! $this->is_footer_menu( $args ) ) {
			return $nav_menu;
		}

		return $this->build_burger_menu( $nav_menu );
	}

	/**
	 * Prüft, ob das Menü im Footer-Kontext steht.
	 *
	 * @param stdClass $args wp_nav_menu-Argumente.
	 * @return bool
	 */
	protected function is_footer_menu( $args ) {
		$haystack = array();

		if ( ! empty( $args->theme_location ) ) {
			$haystack[] = $args->theme_location;
		}
		if ( ! empty( $args->menu_class ) ) {
			$haystack[] = $args->menu_class;
		}
		if ( ! empty( $args->menu_id ) ) {
			$haystack[] = $args->menu_id;
		}
		if ( ! empty( $args->container_class ) ) {
			$haystack[] = $args->container_class;
		}
		if ( ! empty( $args->container_id ) ) {
			$haystack[] = $args->container_id;
		}

		$haystack = implode( ' ', $haystack );

		return false !== stripos( $haystack, 'footer' );
	}

	/**
	 * Baut das Burger-gewrappte Menü-HTML.
	 *
	 * @param string $nav_menu Originales Menü-HTML.
	 * @return string
	 */
	protected function build_burger_menu( $nav_menu ) {
		$button  = '<button class="menu-toggle barmbini-footer-burger-toggle"';
		$button .= ' aria-expanded="false" aria-label="Footer-Menü öffnen" type="button">';
		$button .= '<span class="barmbini-footer-burger-icon" aria-hidden="true">';
		$button .= '<span></span><span></span><span></span>';
		$button .= '</span>';
		$button .= '</button>';

		return '<div class="barmbini-footer-burger-container">'
			. $button
			. $nav_menu
			. '</div>';
	}

	/**
	 * Enqueue CSS und JavaScript für das Footer-Burger-Menü.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		wp_enqueue_style(
			'barmbini-core-footer-burger-menu',
			BARMBINI_CORE_URL . 'assets/css/footer-burger-menu.css',
			array(),
			BARMBINI_CORE_VERSION
		);

		wp_enqueue_script(
			'barmbini-core-footer-burger-menu',
			BARMBINI_CORE_URL . 'assets/js/footer-burger-menu.js',
			array(),
			BARMBINI_CORE_VERSION,
			true
		);
	}
}
