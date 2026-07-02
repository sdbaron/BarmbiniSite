<?php
/**
 * Tests für Barmbini_Core_Address_Widget
 *
 * Deckt Widget-Ausgabe, Admin-Formular und Daten-Speicherung ab.
 *
 * @package Barmbini_Core
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/catalog/class-address-shortcode.php';
require_once __DIR__ . '/../includes/catalog/class-address-widget.php';

class AddressWidgetTest extends TestCase {

	/** @var Barmbini_Core_Address_Widget */
	private $widget;

	protected function setUp(): void {
		_test_reset_all();
		$this->widget = new Barmbini_Core_Address_Widget();
	}

	// =================================================================
	// Konstruktor
	// =================================================================

	public function test_constructor_sets_widget_id(): void {
		// id_base ist protected – wir pruefen ueber get_field_id()
		$field_id = $this->widget->get_field_id( 'title' );
		$this->assertStringContainsString( 'barmbini_address_widget', $field_id );
	}

	public function test_constructor_sets_widget_name(): void {
		// name ist protected – ueber Output der form() pruefen
		ob_start();
		$this->widget->form( array() );
		$output = ob_get_clean();

		// Die Labels enthalten die uebersetzten Feldnamen
		$this->assertStringContainsString( 'Kurzname', $output );
		$this->assertStringContainsString( 'Strasse', $output );
	}

	// =================================================================
	// widget() – Frontend-Ausgabe
	// =================================================================

	public function test_widget_renders_address_html(): void {
		$args = array(
			'before_widget' => '<div class="widget">',
			'after_widget'  => '</div>',
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
		);

		ob_start();
		$this->widget->widget( $args, array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div class="widget">', $output );
		$this->assertStringContainsString( 'Alter Teichweg 11', $output );
		$this->assertStringContainsString( '</div>', $output );
	}

	public function test_widget_renders_title_when_set(): void {
		$args = array(
			'before_widget' => '<div>',
			'after_widget'  => '</div>',
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
		);

		ob_start();
		$this->widget->widget( $args, array( 'title' => 'Kontakt' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<h2>Kontakt</h2>', $output );
	}

	public function test_widget_no_title_when_not_set(): void {
		$args = array(
			'before_widget' => '<div>',
			'after_widget'  => '</div>',
			'before_title'  => '<h2>',
			'after_title'   => '</h2>',
		);

		ob_start();
		$this->widget->widget( $args, array() );
		$output = ob_get_clean();

		$this->assertStringNotContainsString( '<h2>', $output );
	}

	// =================================================================
	// form() – Admin-Formular
	// =================================================================

	public function test_form_contains_all_fields(): void {
		ob_start();
		$this->widget->form( array() );
		$output = ob_get_clean();

		$fields = array(
			'title', 'shortname', 'name', 'street',
			'address2', 'zip', 'city', 'phone', 'email',
		);

		foreach ( $fields as $field ) {
			$this->assertStringContainsString(
				'widget-barmbini_address_widget-' . $field,
				$output,
				"Formularfeld '$field' fehlt"
			);
		}
	}

	public function test_form_shows_saved_values(): void {
		// Adressdaten in der Option speichern
		$test_data = array(
			'shortname' => 'TestShop',
			'street'    => 'Testweg 1',
			'city'      => 'Teststadt',
		);
		update_option( 'barmbini_address_data', $test_data );

		ob_start();
		$this->widget->form( array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'value="TestShop"', $output );
		$this->assertStringContainsString( 'value="Testweg 1"', $output );
		$this->assertStringContainsString( 'value="Teststadt"', $output );
	}

	public function test_form_shows_saved_title(): void {
		ob_start();
		$this->widget->form( array( 'title' => 'Meine Adresse' ) );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'value="Meine Adresse"', $output );
	}

	// =================================================================
	// update() – Daten speichern
	// =================================================================

	public function test_update_saves_all_fields_to_option(): void {
		$new_instance = array(
			'title'     => 'Kontakt',
			'shortname' => 'Neu',
			'name'      => 'Neuer Name',
			'street'    => 'Neue Strasse',
			'address2'  => '',
			'zip'       => '12345',
			'city'      => 'Neustadt',
			'phone'     => '030 / 111',
			'email'     => 'neu@test.de',
		);

		$this->widget->update( $new_instance, array() );

		$saved = get_option( 'barmbini_address_data' );

		$this->assertEquals( 'Neu', $saved['shortname'] );
		$this->assertEquals( 'Neuer Name', $saved['name'] );
		$this->assertEquals( 'Neue Strasse', $saved['street'] );
		$this->assertEquals( '', $saved['address2'] );
		$this->assertEquals( '12345', $saved['zip'] );
		$this->assertEquals( 'Neustadt', $saved['city'] );
		$this->assertEquals( '030 / 111', $saved['phone'] );
		$this->assertEquals( 'neu@test.de', $saved['email'] );
	}

	public function test_update_returns_title_in_instance(): void {
		$result = $this->widget->update(
			array( 'title' => 'Mein Widget' ),
			array()
		);

		$this->assertEquals( 'Mein Widget', $result['title'] );
	}

	public function test_update_partial_data_preserves_existing(): void {
		// Erst alle Felder setzen
		$all_fields = array();
		foreach ( array_keys( Barmbini_Core_Address_Shortcode::get_defaults() ) as $key ) {
			$all_fields[ $key ] = 'original_' . $key;
		}
		$this->widget->update( $all_fields, array() );

		// Dann nur ein Feld aendern
		$this->widget->update( array( 'phone' => '040 / NEU' ), array() );

		$saved = get_option( 'barmbini_address_data' );

		$this->assertEquals( '040 / NEU', $saved['phone'] );
		$this->assertEquals( 'original_street', $saved['street'] );
		$this->assertEquals( 'original_email', $saved['email'] );
	}

	public function test_update_sanitizes_input(): void {
		$this->widget->update(
			array( 'title' => '<b>Fett</b>', 'shortname' => '<script>x</script>' ),
			array()
		);

		$saved = get_option( 'barmbini_address_data' );

		// sanitize_text_field entfernt HTML-Tags
		$this->assertStringNotContainsString( '<script>', $saved['shortname'] );
		$this->assertStringNotContainsString( '<b>', $saved['shortname'] );
	}

	// =================================================================
	// Integration: Widget nutzt dieselbe Option wie Shortcode
	// =================================================================

	public function test_widget_and_shortcode_share_data(): void {
		// Widget speichert
		$this->widget->update( array( 'street' => 'Geteilte Strasse' ), array() );

		// Shortcode liest
		$shortcode = new Barmbini_Core_Address_Shortcode();
		$data = $shortcode->get_data();

		$this->assertEquals( 'Geteilte Strasse', $data['street'] );
	}

	public function test_widget_output_uses_shortcode_saved_data(): void {
		// Daten ueber Widget speichern
		$this->widget->update(
			array( 'shortname' => 'WidgetShop', 'city' => 'WidgetCity' ),
			array()
		);

		// Widget rendern
		$args = array(
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '',
			'after_title'   => '',
		);

		ob_start();
		$this->widget->widget( $args, array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( '<strong>WidgetShop</strong>', $output );
		$this->assertStringContainsString( 'WidgetCity', $output );
	}
}
