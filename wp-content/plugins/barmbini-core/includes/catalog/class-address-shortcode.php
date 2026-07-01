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
	 * Standard-Adressdaten.
	 */
	public static function get_defaults() {
		return array(
			'name'     => 'Sozialkaufhaus Barmbini',
			'street'   => 'Alter Teichweg 11',
			'zip'      => '22081',
			'city'     => 'Hamburg',
			'phone'    => '040 / 42945339',
			'email'    => 'info@barmbini.de',
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
	 * Rendert den Adressblock.
	 *
	 * @param array  $atts    Shortcode-Attribute (ungenutzt).
	 * @param string $content Eingeschlossener Inhalt (ungenutzt).
	 * @return string HTML des Adressblocks.
	 */
	public function render( $atts = array(), $content = '' ) {
		$data = $this->get_data();

		$full_address = trim( $data['street'] . ', ' . $data['zip'] . ' ' . $data['city'] );

		ob_start();
		?>
		<div class="barmbini-address-block">
			<h3 class="barmbini-address-block__heading"><?php echo esc_html__( 'Adresse', 'barmbini-core' ); ?></h3>
			<p class="barmbini-address-block__address">
				<strong><?php echo esc_html( $data['name'] ); ?></strong>
				<br>
				<?php echo esc_html( $full_address ); ?>
			</p>

			<h3 class="barmbini-address-block__heading"><?php echo esc_html__( 'Kontakt', 'barmbini-core' ); ?></h3>
			<p class="barmbini-address-block__contact">
				<?php if ( ! empty( $data['phone'] ) ) : ?>
					📞 <?php echo esc_html( $data['phone'] ); ?>
					<?php if ( ! empty( $data['email'] ) ) : ?>
						&ensp;✉️
						<a href="mailto:<?php echo esc_attr( $data['email'] ); ?>"><?php echo esc_html( $data['email'] ); ?></a>
					<?php endif; ?>
				<?php endif; ?>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}
}
