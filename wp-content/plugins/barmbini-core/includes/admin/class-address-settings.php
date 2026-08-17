<?php
/**
 * Barmbini Core – Adress-Einstellungen (WP-Admin)
 *
 * Stellt eine Einstellungsseite unter "Einstellungen > Barmbini Adresse"
 * bereit, über die die zentralen Adress- und Kontaktdaten (inkl. Telefon)
 * gepflegt werden. Die Werte werden in derselben Option
 * `barmbini_address_data` gespeichert, die der Shortcode
 * [barmbini_address] und das Widget verwenden – eine Änderung wirkt überall.
 *
 * @package Barmbini_Core
 * @since 0.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Address_Settings {

	const OPTION_GROUP = 'barmbini_address_group';
	const PAGE_SLUG    = 'barmbini-address';

	/**
	 * Registriert Menüpunkt und Einstellung.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_options_page' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
	}

	/**
	 * Fügt die Unterseite unter "Einstellungen" hinzu.
	 *
	 * @return void
	 */
	public function add_options_page() {
		add_options_page(
			'Barmbini Adresse',
			'Barmbini Adresse',
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Registriert die Option mit Sanitize-Callback.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			self::OPTION_GROUP,
			Barmbini_Core_Address_Shortcode::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Bereinigt die übermittelten Adressdaten.
	 *
	 * @param array $input Rohe Eingabedaten.
	 * @return array Bereinigte Daten.
	 */
	public function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$clean  = array();
		$fields = array_keys( Barmbini_Core_Address_Shortcode::get_defaults() );

		foreach ( $fields as $key ) {
			$clean[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
		}

		return $clean;
	}

	/**
	 * Rendert die Einstellungsseite.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$data = ( new Barmbini_Core_Address_Shortcode() )->get_data();

		$fields = array(
			'shortname' => 'Kurzname (fett)',
			'name'      => 'Name',
			'street'    => 'Straße',
			'address2'  => 'Adresszusatz',
			'zip'       => 'PLZ',
			'city'      => 'Stadt',
			'phone'     => 'Telefon',
			'email'     => 'E-Mail',
		);
		?>
		<div class="wrap">
			<h1>Barmbini Adresse</h1>
			<p>Diese Daten werden vom Shortcode <code>[barmbini_address]</code> und vom Widget „Barmbini Adresse“ verwendet. Eine Änderung wirkt überall.</p>
			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( $fields as $key => $label ) : ?>
						<tr>
							<th scope="row"><label for="barmbini-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td>
								<input
									type="text"
									class="regular-text"
									id="barmbini-<?php echo esc_attr( $key ); ?>"
									name="<?php echo esc_attr( Barmbini_Core_Address_Shortcode::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]"
									value="<?php echo esc_attr( $data[ $key ] ?? '' ); ?>">
								<?php if ( 'phone' === $key ) : ?>
									<p class="description">Wird auf der Website als anklickbarer <code>tel:</code>-Link ausgegeben (z.&nbsp;B. „040 / 4294 5339“).</p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
