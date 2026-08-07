<?php
/**
 * Barmbini Core – Startseiten-Layout
 *
 * Bindet projektspezifische CSS-Anpassungen für die Startseite ein,
 * z. B. die Begrenzung der Hero-Spalten (2 Spalten bis 600px statt
 * Stapeln am Tablet-Breakpoint), damit das Hero-Logo nicht überbreit wird.
 *
 * @package Barmbini_Core
 * @since 0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Homepage_Layout {

	/**
	 * Registriert die Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Bindet das Startseiten-CSS ein – nur auf der Frontseite.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		if ( ! is_front_page() ) {
			return;
		}

		wp_enqueue_style(
			'barmbini-core-homepage',
			BARMBINI_CORE_URL . 'assets/css/homepage-hero.css',
			array(),
			BARMBINI_CORE_VERSION
		);
	}
}
