<?php
/**
 * Barmbini Core – Adressblock-Widget
 *
 * Ermöglicht das Platzieren des Adressblocks in einer Widget-Area
 * (Sidebar, Footer etc.) mit visueller Bearbeitung im Admin.
 *
 * Die Daten werden in derselben wp_option gespeichert wie der
 * Shortcode [barmbini_address] – eine Änderung wirkt überall.
 *
 * @package Barmbini_Core
 * @since 0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Address_Widget extends WP_Widget {

	/**
	 * Registriert das Widget.
	 */
	public function __construct() {
		parent::__construct(
			'barmbini_address_widget',
			__( 'Barmbini Adresse', 'barmbini-core' ),
			array(
				'description' => __( 'Adressblock mit Kontaktdaten – zentral pflegbar.', 'barmbini-core' ),
			)
		);
	}

	/**
	 * Gibt das Widget im Frontend aus.
	 *
	 * @param array $args     Widget-Argumente (before_widget, after_widget etc.).
	 * @param array $instance Gespeicherte Widget-Instanz-Daten.
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget'];

		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}

		$shortcode = new Barmbini_Core_Address_Shortcode();
		echo Barmbini_Core_Address_Shortcode::render_html( $shortcode->get_data() );

		echo $args['after_widget'];
	}

	/**
	 * Rendert das Admin-Formular zum Bearbeiten der Adressdaten.
	 *
	 * @param array $instance Aktuelle Widget-Instanz-Daten.
	 */
	public function form( $instance ) {
		$shortcode = new Barmbini_Core_Address_Shortcode();
		$data      = $shortcode->get_data();
		$title     = ! empty( $instance['title'] ) ? $instance['title'] : '';

		$fields = array(
			'title'     => array( 'label' => 'Überschrift',   'key' => 'title' ),
			'shortname' => array( 'label' => 'Kurzname (fett)', 'key' => 'shortname' ),
			'name'      => array( 'label' => 'Name',           'key' => 'name' ),
			'street'    => array( 'label' => 'Strasse',        'key' => 'street' ),
			'address2'  => array( 'label' => 'Adresszusatz',   'key' => 'address2' ),
			'zip'       => array( 'label' => 'PLZ',            'key' => 'zip' ),
			'city'      => array( 'label' => 'Stadt',          'key' => 'city' ),
			'phone'     => array( 'label' => 'Telefon',        'key' => 'phone' ),
			'email'     => array( 'label' => 'E-Mail',         'key' => 'email' ),
		);

		foreach ( $fields as $id => $f ) {
			$val = ( 'title' === $id ) ? $title : ( $data[ $id ] ?? '' );
			?>
			<p>
				<label for="<?php echo esc_attr( $this->get_field_id( $id ) ); ?>">
					<?php echo esc_html( $f['label'] ); ?>
				</label>
				<input class="widefat"
					id="<?php echo esc_attr( $this->get_field_id( $id ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( $id ) ); ?>"
					type="text"
					value="<?php echo esc_attr( $val ); ?>">
			</p>
			<?php
		}
	}

	/**
	 * Speichert die Widget-Daten.
	 *
	 * @param array $new_instance Neue Werte.
	 * @param array $old_instance Alte Werte.
	 * @return array Gespeicherte Werte.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = sanitize_text_field( $new_instance['title'] ?? '' );

		// Zentrale Adressdaten aktualisieren
		$shortcode = new Barmbini_Core_Address_Shortcode();
		$current   = $shortcode->get_data();
		$updated   = array();

		foreach ( array_keys( Barmbini_Core_Address_Shortcode::get_defaults() ) as $key ) {
			$updated[ $key ] = isset( $new_instance[ $key ] )
				? sanitize_text_field( $new_instance[ $key ] )
				: ( $current[ $key ] ?? '' );
		}

		update_option( Barmbini_Core_Address_Shortcode::OPTION_KEY, $updated );

		return $instance;
	}
}
