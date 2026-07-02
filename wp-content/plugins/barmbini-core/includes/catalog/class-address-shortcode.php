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
	 * Parameter (alle optional):
	 *   shortname, name, street, address2, zip, city, phone, email
	 *     -> überschreiben den zentral gespeicherten Wert (immer, auch bei hide)
	 *   show="phone,email" -> nur diese Felder anzeigen
	 *   hide="address2"    -> diese Felder ausblenden
	 *   Einzelparameter gewinnen immer gegen show/hide.
	 *
	 * Beispiele:
	 *   [barmbini_address hide="name,shortname" shortname="Adresse"]
	 *   -> zeigt "Adresse" an (shortname= gewinnt gegen hide)
	 *   [barmbini_address show="phone,email" phone="030 / 123"]
	 *   -> zeigt nur Telefon mit überschriebener Nummer
	 *
	 * @param array  $atts    Shortcode-Attribute.
	 * @param string $content Eingeschlossener Inhalt (ungenutzt).
	 * @return string HTML des Adressblocks.
	 */
	public function render( $atts = array(), $content = '' ) {
		$atts = shortcode_atts( array(
			'show' => '',
			'hide' => '',
		), $atts );

		$data = $this->get_data();

		// 1. show/hide filtern (zuerst, damit Overrides gewinnen)
		$show = $atts['show'] ? array_map( 'trim', explode( ',', $atts['show'] ) ) : array();
		$hide = $atts['hide'] ? array_map( 'trim', explode( ',', $atts['hide'] ) ) : array();

		if ( ! empty( $show ) ) {
			$filtered = array();
			foreach ( $show as $key ) {
				if ( array_key_exists( $key, $data ) ) {
					$filtered[ $key ] = $data[ $key ];
				}
			}
			$data = $filtered;
		}

		if ( ! empty( $hide ) ) {
			foreach ( $hide as $key ) {
				unset( $data[ $key ] );
			}
		}

		// 2. Einzelne Felder aus den Attributen überschreiben
		//    (gewinnt immer – auch gegen show/hide)
		foreach ( array_keys( self::get_defaults() ) as $key ) {
			if ( isset( $atts[ $key ] ) && $atts[ $key ] !== '' ) {
				$data[ $key ] = sanitize_text_field( $atts[ $key ] );
			}
		}

		return self::render_html( $data );
	}

	/**
	 * Baut das HTML für einen Adressblock aus einem Daten-Array.
	 *
	 * Wird vom Shortcode UND vom Widget verwendet.
	 *
	 * @param array $data Adressdaten.
	 * @return string HTML.
	 */
	public static function render_html( $data ) {
		$lines = array();

		// Kopfzeile (nur wenn Name/Shortname vorhanden)
		if ( ! empty( $data['shortname'] ) || ! empty( $data['name'] ) ) {
			$line1 = '';
			if ( ! empty( $data['shortname'] ) ) {
				$line1 .= '<strong>' . esc_html( $data['shortname'] ) . '</strong>';
			}
			if ( ! empty( $data['name'] ) ) {
				$line1 .= ( $line1 !== '' ? '&nbsp;' : '' ) . esc_html( $data['name'] );
			}
			if ( $line1 !== '' ) {
				$lines[] = $line1;
				$lines[] = ''; // Leerzeile nach Kopf
			}
		}

		// Adresszeilen
		if ( ! empty( $data['street'] ) ) {
			$lines[] = esc_html( $data['street'] );
		}
		if ( ! empty( $data['address2'] ) ) {
			$lines[] = esc_html( $data['address2'] );
		}
		$city_line = trim( ( $data['zip'] ?? '' ) . ' ' . ( $data['city'] ?? '' ) );
		if ( $city_line !== '' ) {
			$lines[] = esc_html( $city_line );
		}

		// Kontaktdaten
		if ( ! empty( $data['phone'] ) ) {
			$lines[] = '📞 ' . esc_html( $data['phone'] );
		}
		if ( ! empty( $data['email'] ) ) {
			$lines[] = '✉️&nbsp;<a href="mailto:' . esc_attr( $data['email'] ) . '">' . esc_html( $data['email'] ) . '</a>';
		}

		// Falls alle Felder ausgeblendet wurden – nichts rendern
		if ( empty( $lines ) ) {
			return '';
		}

		return '<p class="wp-block-paragraph barmbini-address-block">' . implode( '<br>', $lines ) . '</p>';
	}
}
