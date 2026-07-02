<?php
/**
 * Tests für Barmbini_Core_Address_Shortcode
 *
 * Deckt Rendering, Filter (show/hide), Feld-Overrides und
 * den shortcode_atts-Bug ab, der unbekannte Keys gelöscht hat.
 *
 * @package Barmbini_Core
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/catalog/class-address-shortcode.php';

class AddressShortcodeTest extends TestCase {

	/** @var Barmbini_Core_Address_Shortcode */
	private $shortcode;

	protected function setUp(): void {
		_test_reset_all();
		$this->shortcode = new Barmbini_Core_Address_Shortcode();
	}

	// =================================================================
	// get_defaults()
	// =================================================================

	public function test_get_defaults_returns_all_nine_fields(): void {
		$defaults = Barmbini_Core_Address_Shortcode::get_defaults();

		$expected_keys = array(
			'shortname', 'name', 'street', 'address2',
			'zip', 'city', 'phone', 'email',
		);
		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $defaults, "Default fehlt: $key" );
		}
	}

	public function test_get_defaults_are_non_empty_strings(): void {
		$defaults = Barmbini_Core_Address_Shortcode::get_defaults();

		foreach ( $defaults as $key => $value ) {
			$this->assertIsString( $value, "$key ist kein String" );
			$this->assertNotEmpty( $value, "$key ist leer" );
		}
	}

	// =================================================================
	// get_data()
	// =================================================================

	public function test_get_data_returns_defaults_when_no_option_saved(): void {
		$data = $this->shortcode->get_data();
		$this->assertEquals( 'Alter Teichweg 11', $data['street'] );
	}

	public function test_get_data_returns_saved_option(): void {
		update_option( 'barmbini_address_data', array(
			'street' => 'Musterstrasse 1',
			'city'   => 'Berlin',
		) );

		$data = $this->shortcode->get_data();

		// Gespeicherte Felder ueberschreiben
		$this->assertEquals( 'Musterstrasse 1', $data['street'] );
		$this->assertEquals( 'Berlin', $data['city'] );
		// Nicht gespeicherte Felder fallen auf Defaults zurueck
		$this->assertEquals( '040 / 4294 5339', $data['phone'] );
	}

	public function test_get_data_partial_save_merges_with_defaults(): void {
		update_option( 'barmbini_address_data', array( 'phone' => '030 / 111' ) );

		$data = $this->shortcode->get_data();

		$this->assertEquals( '030 / 111', $data['phone'] );
		$this->assertEquals( 'Alter Teichweg 11', $data['street'] );
		$this->assertEquals( 'info@barmbini.de', $data['email'] );
	}

	// =================================================================
	// render() – Standard (alle Felder)
	// =================================================================

	public function test_render_all_fields_present(): void {
		$html = $this->shortcode->render();

		$this->assertStringContainsString( '<strong>Barmbini</strong>', $html );
		$this->assertStringContainsString( 'Sozialkaufhaus Barmbek', $html );
		$this->assertStringContainsString( 'Alter Teichweg 11', $html );
		$this->assertStringContainsString( 'Im Hinterhof', $html );
		$this->assertStringContainsString( '22081 Hamburg', $html );
		$this->assertStringContainsString( '📞 040 / 4294 5339', $html );
		$this->assertStringContainsString( 'info@barmbini.de', $html );
		$this->assertStringContainsString( 'mailto:info@barmbini.de', $html );
	}

	public function test_render_output_wrapped_in_paragraph(): void {
		$html = $this->shortcode->render();

		$this->assertStringStartsWith( '<p class="wp-block-paragraph barmbini-address-block">', $html );
		$this->assertStringEndsWith( '</p>', $html );
	}

	public function test_render_uses_br_tags(): void {
		$html = $this->shortcode->render();

		$this->assertStringContainsString( '<br>', $html );
	}

	// =================================================================
	// render() – show-Parameter
	// =================================================================

	public function test_render_show_only_specified_fields(): void {
		$html = $this->shortcode->render( array( 'show' => 'phone,email' ) );

		// Diese Felder duerfen NICHT vorkommen
		$this->assertStringNotContainsString( 'Alter Teichweg 11', $html );
		$this->assertStringNotContainsString( 'Im Hinterhof', $html );
		$this->assertStringNotContainsString( '22081 Hamburg', $html );
		$this->assertStringNotContainsString( 'Barmbini', $html );

		// Diese Felder MUESSEN vorkommen
		$this->assertStringContainsString( '📞 040 / 4294 5339', $html );
		$this->assertStringContainsString( 'info@barmbini.de', $html );
	}

	public function test_render_show_with_spaces_in_list(): void {
		$html = $this->shortcode->render( array( 'show' => ' phone , email ' ) );

		$this->assertStringContainsString( '📞 040 / 4294 5339', $html );
		$this->assertStringContainsString( 'info@barmbini.de', $html );
		$this->assertStringNotContainsString( 'Alter Teichweg 11', $html );
	}

	public function test_render_show_single_field(): void {
		$html = $this->shortcode->render( array( 'show' => 'phone' ) );

		$this->assertStringContainsString( '📞 040 / 4294 5339', $html );
		$this->assertStringNotContainsString( 'info@barmbini.de', $html );
	}

	public function test_render_show_empty_string_shows_all(): void {
		$html = $this->shortcode->render( array( 'show' => '' ) );

		$this->assertStringContainsString( 'Alter Teichweg 11', $html );
		$this->assertStringContainsString( 'info@barmbini.de', $html );
	}

	public function test_render_show_unknown_field_ignored(): void {
		$html = $this->shortcode->render( array( 'show' => 'nonexistent' ) );

		// Da show="nonexistent" kein gueltiges Feld ist, bleibt $data leer
		// -> es wird gar nichts gerendert
		$this->assertEmpty( $html );
	}

	// =================================================================
	// render() – hide-Parameter
	// =================================================================

	public function test_render_hide_removes_specified_fields(): void {
		$html = $this->shortcode->render( array( 'hide' => 'address2,phone' ) );

		$this->assertStringNotContainsString( 'Im Hinterhof', $html );
		$this->assertStringNotContainsString( '📞', $html );
		// Diese muessen noch da sein
		$this->assertStringContainsString( 'Alter Teichweg 11', $html );
		$this->assertStringContainsString( 'info@barmbini.de', $html );
	}

	public function test_render_hide_name_and_shortname(): void {
		$html = $this->shortcode->render( array( 'hide' => 'name,shortname' ) );

		$this->assertStringNotContainsString( '<strong>', $html );
		$this->assertStringNotContainsString( 'Sozialkaufhaus Barmbek', $html );
		$this->assertStringContainsString( 'Alter Teichweg 11', $html );
	}

	public function test_render_hide_all_fields_returns_empty(): void {
		$html = $this->shortcode->render( array(
			'hide' => 'shortname,name,street,address2,zip,city,phone,email',
		) );

		$this->assertEmpty( $html );
	}

	// =================================================================
	// render() – Feld-Overrides (raw_atts → shortcode_atts Bug-Fix)
	// =================================================================

	public function test_render_field_override_shortname(): void {
		$html = $this->shortcode->render( array( 'shortname' => 'Adresse' ) );

		$this->assertStringContainsString( '<strong>Adresse</strong>', $html );
		$this->assertStringNotContainsString( 'Barmbini', $html );
	}

	public function test_render_field_override_multiple_fields(): void {
		$html = $this->shortcode->render( array(
			'shortname' => 'Kontakt',
			'phone'     => '030 / 123456',
		) );

		$this->assertStringContainsString( '<strong>Kontakt</strong>', $html );
		$this->assertStringContainsString( '📞 030 / 123456', $html );
		$this->assertStringNotContainsString( '040 / 4294 5339', $html );
	}

	public function test_render_field_override_with_empty_string_not_applied(): void {
		$html = $this->shortcode->render( array( 'shortname' => '' ) );

		// Leerer Override wird ignoriert → Default bleibt
		$this->assertStringContainsString( '<strong>Barmbini</strong>', $html );
	}

	// =================================================================
	// render() – hide + Override (der kritische Bug-Fall)
	// =================================================================

	public function test_render_hide_shortname_but_override_wins(): void {
		// Das ist DER Test, der den shortcode_atts-Bug abdeckt.
		// shortcode_atts loescht "shortname", weil es nicht in den
		// Defaults (show, hide) steht. raw_atts bewahrt es.
		$html = $this->shortcode->render( array(
			'hide'      => 'name,shortname',
			'shortname' => 'Adresse',
		) );

		// Individual-Override gewinnt IMMER gegen hide
		$this->assertStringContainsString( '<strong>Adresse</strong>', $html );
		$this->assertStringNotContainsString( 'Sozialkaufhaus Barmbek', $html );
	}

	public function test_render_hide_with_multiple_overrides(): void {
		$html = $this->shortcode->render( array(
			'hide'      => 'phone',
			'phone'     => '040 / 999',
		) );

		// phone-Override gewinnt gegen hide
		$this->assertStringContainsString( '📞 040 / 999', $html );
	}

	// =================================================================
	// render() – Edge Cases
	// =================================================================

	public function test_render_both_show_and_hide(): void {
		// show hat Vorrang (wird zuerst angewendet), danach hide
		$html = $this->shortcode->render( array(
			'show' => 'phone,email,street',
			'hide' => 'street',
		) );

		// street ist durch show drin, wird dann durch hide entfernt
		$this->assertStringNotContainsString( 'Alter Teichweg 11', $html );
		$this->assertStringContainsString( '📞', $html );
		$this->assertStringContainsString( 'info@barmbini.de', $html );
	}

	public function test_render_with_no_args(): void {
		$html = $this->shortcode->render( array() );
		$this->assertStringContainsString( 'Barmbini', $html );
	}

	public function test_render_with_null_atts(): void {
		$html = $this->shortcode->render( null );
		$this->assertStringContainsString( 'Barmbini', $html );
	}

	public function test_render_strips_disallowed_html(): void {
		// wp_kses() erlaubt nur <strong> und <br> – <script> wird entfernt
		$html = $this->shortcode->render( array( 'shortname' => '<script>alert(1)</script>' ) );

		// <script> Tag wurde entfernt
		$this->assertStringNotContainsString( '<script>', $html );
		// Reiner Text "alert(1)" bleibt erhalten
		$this->assertStringContainsString( 'alert(1)', $html );
	}

	public function test_render_preserves_strong_tag(): void {
		// <strong> ist in wp_kses ausdruecklich erlaubt
		$html = $this->shortcode->render( array( 'shortname' => '<strong>Wichtig</strong>' ) );

		$this->assertStringContainsString( '<strong>Wichtig</strong>', $html );
		// Aber das aeussere <strong> vom render_html kommt dazu → doppelt
		// (render_html wrappt shortname selbst in <strong>)
	}

	public function test_render_preserves_br_tag(): void {
		// <br> ist in wp_kses erlaubt – render_html escaped NICHT das name-Feld
		$html = $this->shortcode->render( array( 'name' => 'Zeile 1<br>Zeile 2' ) );

		// <br> ueberlebt wp_kses UND render_html (name wird nicht escaped)
		$this->assertStringContainsString( 'Zeile 1<br>Zeile 2', $html );
	}

	public function test_render_escapes_html_in_email_href(): void {
		// E-Mail im href muss escaped sein
		$html = $this->shortcode->render( array( 'email' => 'test"x@test.de' ) );

		$this->assertStringNotContainsString( '"x@test.de', $html );
		$this->assertStringContainsString( '&quot;', $html );
	}

	// =================================================================
	// Shortcode-Registrierung
	// =================================================================

	public function test_register_adds_shortcode(): void {
		_test_reset_shortcodes();

		$this->shortcode->register();

		$this->assertArrayHasKey(
			'barmbini_address',
			$GLOBALS['__wp_shortcodes'],
			'Shortcode [barmbini_address] wurde nicht registriert'
		);
	}

	public function test_register_callback_is_render_method(): void {
		_test_reset_shortcodes();

		$this->shortcode->register();

		$callback = $GLOBALS['__wp_shortcodes']['barmbini_address'];
		$this->assertSame( array( $this->shortcode, 'render' ), $callback );
	}

	// =================================================================
	// do_shortcode() Integration
	// =================================================================

	public function test_do_shortcode_with_defaults(): void {
		$this->shortcode->register();

		$html = do_shortcode( '[barmbini_address]' );

		$this->assertStringContainsString( 'Alter Teichweg 11', $html );
	}

	public function test_do_shortcode_with_show_param(): void {
		$this->shortcode->register();

		$html = do_shortcode( '[barmbini_address show="phone,email"]' );

		$this->assertStringNotContainsString( 'Alter Teichweg 11', $html );
		$this->assertStringContainsString( '📞', $html );
	}

	public function test_do_shortcode_with_override(): void {
		$this->shortcode->register();

		$html = do_shortcode( '[barmbini_address shortname="Test"]' );

		$this->assertStringContainsString( '<strong>Test</strong>', $html );
	}

	// =================================================================
	// render_html() – statische Methode
	// =================================================================

	public function test_render_html_with_empty_data(): void {
		$html = Barmbini_Core_Address_Shortcode::render_html( array() );
		$this->assertEmpty( $html );
	}

	public function test_render_html_with_only_phone(): void {
		$html = Barmbini_Core_Address_Shortcode::render_html( array(
			'phone' => '040 / 123',
		) );

		$this->assertStringContainsString( '📞 040 / 123', $html );
		$this->assertStringNotContainsString( '<strong>', $html );
		$this->assertStringNotContainsString( 'mailto:', $html );
	}

	public function test_render_html_with_only_shortname(): void {
		$html = Barmbini_Core_Address_Shortcode::render_html( array(
			'shortname' => 'Kurz',
		) );

		$this->assertStringContainsString( '<strong>Kurz</strong>', $html );
		// Nach der Kopfzeile kommt eine Leerzeile, aber keine Adressdaten
		$this->assertStringNotContainsString( 'mailto:', $html );
	}

	public function test_render_html_city_without_zip(): void {
		$html = Barmbini_Core_Address_Shortcode::render_html( array(
			'city' => 'Hamburg',
		) );

		$this->assertStringContainsString( 'Hamburg', $html );
		$this->assertStringNotContainsString( '22081', $html );
	}

	public function test_render_html_zip_without_city(): void {
		$html = Barmbini_Core_Address_Shortcode::render_html( array(
			'zip' => '22081',
		) );

		$this->assertStringContainsString( '22081', $html );
		$this->assertStringNotContainsString( 'Hamburg', $html );
	}
}
