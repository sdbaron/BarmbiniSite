<?php
/**
 * Barmbini Core – Adressblock-Shortcode
 *
 * Stellt die Adressdaten als wiederverwendbare Komponente bereit.
 * Verwendung: [barmbini_address]
 *
 * Die Daten werden zentral in WordPress-Options gespeichert und
 * koennen unter Einstellungen > Barmbini Adresse bearbeitet werden.
 *
 * @package Barmbini_Core
 * @since 0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Address_Shortcode {

	/**
	 * Options-Key für die Adressdaten.
	 */
	const OPTION_KEY = 'barmbini_address_data';

	/**
	 * Standard-Adressdaten – entsprechen dem Format auf /barrierefreiheit/.
	 */
	public static function get_defaults() {
		return array(
			'shortname' => 'Barmbini',
			'name'      => 'Sozialkaufhaus Barmbek',
			'street'    => 'Alter Teichweg 11',
			'address2'  => 'Im Hinterhof',
			'zip'       => '22081',
			'city'      => 'Hamburg',
			'phone'     => '040 / 4294 5339',
			'email'     => 'info@barmbini.de',
		);
	}

	/**
	 * Registriert den Shortcode.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'barmbini_address', array( $this, 'render' ) );
	}

	/**
	 * Gibt die gespeicherten Adressdaten zurueck.
	 *
	 * @return array
	 */
	public function get_data() {
		$saved = get_option( self::OPTION_KEY, array() );

		return wp_parse_args( $saved, self::get_defaults() );
	}

	/**
	 * Rendert den Adressblock – identisch zum Format auf /barrierefreiheit/.
	 *
	 * @param array  $atts    Shortcode-Attribute (ungenutzt).
	 * @param string $content Eingeschlossener Inhalt (ungenutzt).
	 * @return string HTML des Adressblocks.
	 */
	public function render( $atts = array(), $content = '' ) {
		$data = $this->get_data();

		$lines = array();

		// Zeile 1: Barmbini (fett) + Name
		$line1 = '';
		if ( ! empty( $data['shortname'] ) ) {
			$line1 .= '<strong>' . esc_html( $data['shortname'] ) . '</strong>';
		}
		if ( ! empty( $data['name'] ) ) {
			$line1 .= '&nbsp;' . esc_html( $data['name'] );
		}
		if ( $line1 !== '' ) {
			$lines[] = $line1;
		}

		// Leerzeile
		$lines[] = '';

		// Strasse
		if ( ! empty( $data['street'] ) ) {
			$lines[] = esc_html( $data['street'] );
		}

		// Zusatzzeile (z. B. "Im Hinterhof")
		if ( ! empty( $data['address2'] ) ) {
			$lines[] = esc_html( $data['address2'] );
		}

		// PLZ + Stadt
		$city_line = trim( ( $data['zip'] ?? '' ) . ' ' . ( $data['city'] ?? '' ) );
		if ( $city_line !== '' ) {
			$lines[] = esc_html( $city_line );
		}

		// Telefon
		if ( ! empty( $data['phone'] ) ) {
			$lines[] = '📞 ' . esc_html( $data['phone'] );
		}

		// E-Mail
		if ( ! empty( $data['email'] ) ) {
			$lines[] = '✉️&nbsp;<a href="mailto:' . esc_attr( $data['email'] ) . '">' . esc_html( $data['email'] ) . '</a>';
		}

		$inner = '<strong>' . implode( '<br>', $lines ) . '</strong>';

		return '<p class="wp-block-paragraph barmbini-address-block">' . $inner . '</p>';
	}
}
