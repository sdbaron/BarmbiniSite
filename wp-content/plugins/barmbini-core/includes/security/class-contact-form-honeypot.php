<?php
/**
 * Barmbini Core – CF7-Honeypot (Spam-Schutz Option A)
 *
 * Setzt einen minimalen, datenschutzfreundlichen Spam-Schutz für das
 * Contact Form 7-Kontaktformular um: Ein für Menschen per CSS verstecktes
 * Feld (`your-company`) wird von Spambots typischerweise ausgefüllt.
 * Wird es ausgefüllt, markiert der Filter `wpcf7_spam` die Einreichung
 * als Spam.
 *
 * Kein externer Dienst, keine Cookies, keine Datenübertragung an Dritte.
 *
 * @package Barmbini_Core
 * @since 0.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Contact_Form_Honeypot {

	/**
	 * Registriert die Anti-Spam-Filter und das CSS.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'wpcf7_spam', array( $this, 'check_honeypot' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Filter: wpcf7_spam
	 *
	 * Markiert die Einreichung als Spam, wenn das versteckte Honeypot-Feld
	 * `your-company` ausgefüllt wurde.
	 *
	 * @param bool                $spam       Bisheriger Spam-Status.
	 * @param WPCF7_Submission    $submission Die aktuelle CF7-Einreichung.
	 * @return bool
	 */
	public function check_honeypot( $spam, $submission ) {
		if ( isset( $_POST['your-company'] ) && '' !== trim( (string) $_POST['your-company'] ) ) {
			$spam = true;
		}

		return $spam;
	}

	/**
	 * Blendet das Honeypot-Feld für Menschen aus (Bots sehen es weiterhin).
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		wp_register_style( 'barmbini-core-cf7-honeypot', false, array(), BARMBINI_CORE_VERSION );
		wp_enqueue_style( 'barmbini-core-cf7-honeypot' );
		wp_add_inline_style(
			'barmbini-core-cf7-honeypot',
			'.barmbini-hp { position: absolute !important; left: -9999px !important; width: 1px; height: 1px; overflow: hidden; }'
		);
	}
}
