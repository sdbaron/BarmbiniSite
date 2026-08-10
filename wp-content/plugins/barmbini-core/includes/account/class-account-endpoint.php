<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Account_Endpoint {
	protected $settings;

	protected $consent_recorder;

	protected $queue_repository;

	public function __construct( Barmbini_Core_Subscription_Settings $settings, Barmbini_Core_Consent_Recorder $consent_recorder, Barmbini_Core_Queue_Repository $queue_repository = null ) {
		$this->settings         = $settings;
		$this->consent_recorder = $consent_recorder;
		$this->queue_repository = $queue_repository;
	}

	public function register_endpoint() {
		add_rewrite_endpoint( 'abonnements', EP_ROOT | EP_PAGES );
	}

	public function add_menu_item( $items ) {
		$updated_items = array();

		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$updated_items['abonnements'] = 'Abonnements';
			}

			$updated_items[ $key ] = $label;
		}

		if ( ! isset( $updated_items['abonnements'] ) ) {
			$updated_items['abonnements'] = 'Abonnements';
		}

		return $updated_items;
	}

	public function render_content() {
		if ( ! is_user_logged_in() ) {
			echo '<p>Bitte melden Sie sich an, um Ihre Abonnements zu verwalten.</p>';
			return;
		}

		$user_id              = get_current_user_id();
		$settings             = $this->settings->get_user_settings( $user_id );
		$product_categories   = $this->settings->get_product_categories();
		$supported_frequencies = $this->settings->get_supported_frequencies();
		$template_path        = BARMBINI_CORE_PATH . 'templates/account/subscriptions.php';

		if ( file_exists( $template_path ) ) {
			require $template_path;
		}
	}

	public function handle_form_submission() {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		if ( ! $this->is_subscriptions_endpoint_request() || ! is_user_logged_in() ) {
			return;
		}

		check_admin_referer( 'barmbini_save_subscriptions', 'barmbini_subscriptions_nonce' );

		$user_id = get_current_user_id();
		$result  = $this->settings->save_user_settings( $user_id, wp_unslash( $_POST ) );

		$this->consent_recorder->record(
			$user_id,
			$result['current'],
			$result['new'],
			$result['ts'],
			$result['source']
		);

		if ( $this->queue_repository ) {
			$this->queue_repository->cancel_stale_for_user( $user_id, $result['new'] );
		}

		wc_add_notice( 'Ihre Abonnements wurden gespeichert.', 'success' );
		wp_safe_redirect( wc_get_account_endpoint_url( 'abonnements' ) );
		exit;
	}

	public function enqueue_styles() {
		if ( ! $this->is_subscriptions_endpoint_request() ) {
			return;
		}

		wp_enqueue_style(
			'barmbini-core-account-subscriptions',
			BARMBINI_CORE_URL . 'assets/css/account-subscriptions.css',
			array(),
			BARMBINI_CORE_VERSION
		);
	}

	/**
	 * Registriert die Hooks für die Benutzerregistrierung und
	 * die Anpassung des E-Mail-Versands (DSGVO-Checkbox, Absender,
	 * Redirect nach Registrierung).
	 *
	 * @return void
	 */
	public function register_registration_features() {
		if ( ! function_exists( 'is_account_page' ) ) {
			return;
		}

		// DSGVO-Checkbox bei der Registrierung (WooCommerce).
		add_action( 'woocommerce_register_form', array( $this, 'render_privacy_consent_checkbox' ) );
		add_filter( 'woocommerce_registration_errors', array( $this, 'validate_privacy_consent' ), 10, 3 );
		add_action( 'woocommerce_created_customer', array( $this, 'save_privacy_consent' ), 10, 1 );

		// Admin-Benachrichtigung bei neuer Registrierung.
		add_action( 'woocommerce_created_customer', array( $this, 'notify_admin_new_user' ), 20, 1 );

		// E-Mail-Absender für alle wp_mail()-Mails.
		add_filter( 'wp_mail_from', array( $this, 'custom_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'custom_mail_from_name' ) );

		// Nach nativer WordPress-Registrierung auf die Account-Seite umleiten.
		add_filter( 'registration_redirect', array( $this, 'registration_redirect' ) );
	}

	/**
	 * Rendert die DSGVO-Pflicht-Checkbox im WooCommerce-Registrierungsformular.
	 *
	 * @return void
	 */
	public function render_privacy_consent_checkbox() {
		$privacy_url = function_exists( 'get_privacy_policy_url' ) && get_privacy_policy_url()
			? get_privacy_policy_url()
			: home_url( '/datenschutz/' );

		$checked = ! empty( $_POST['barmbini_privacy_consent'] ) ? ' checked="checked"' : '';
		?>
		<p class="form-row form-row-wide barmbini-privacy-consent">
			<label class="woocommerce-form__label woocommerce-form__label-for-checkbox">
				<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox" name="barmbini_privacy_consent" value="1"<?php echo $checked; // phpcs:ignore WordPress.Security.EscapeOutput ?> />
				<span>
					<?php
					printf(
						/* translators: %s: Link zur Datenschutzerklärung */
						wp_kses_post( __( 'Ich habe die %s gelesen und stimme der Verarbeitung meiner Daten zu. *', 'barmbini-core' ) ),
						'<a href="' . esc_url( $privacy_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Datenschutzerklärung', 'barmbini-core' ) . '</a>'
					);
					?>
				</span>
			</label>
		</p>
		<?php
	}

	/**
	 * Validiert die DSGVO-Checkbox bei der Registrierung.
	 *
	 * @param WP_Error $errors   Bestehende Fehler.
	 * @param string   $username Benutzername.
	 * @param string   $email    E-Mail-Adresse.
	 * @return WP_Error
	 */
	public function validate_privacy_consent( $errors, $username, $email ) {
		if ( empty( $_POST['barmbini_privacy_consent'] ) ) {
			$errors->add( 'barmbini_privacy_consent_error', __( 'Bitte stimmen Sie der Datenschutzerklärung zu.', 'barmbini-core' ) );
		}

		return $errors;
	}

	/**
	 * Speichert die Einwilligung nach erfolgreicher Registrierung.
	 *
	 * @param int $customer_id ID des neuen Benutzers.
	 * @return void
	 */
	public function save_privacy_consent( $customer_id ) {
		if ( ! $customer_id || empty( $_POST['barmbini_privacy_consent'] ) ) {
			return;
		}

		$this->settings->update_consent( $customer_id, current_time( 'mysql' ), 'registration' );
	}

	/**
	 * Setzt die Absender-Adresse aller Mails auf die Barmbini-Adresse.
	 *
	 * @param string $from_email Aktuelle Absender-Adresse.
	 * @return string
	 */
	public function custom_mail_from( $from_email ) {
		return 'info@barmbini.de';
	}

	/**
	 * Setzt den Absender-Namen aller Mails.
	 *
	 * @param string $from_name Aktueller Absender-Name.
	 * @return string
	 */
	public function custom_mail_from_name( $from_name ) {
		return 'Barmbini Sozialkaufhaus';
	}

	/**
	 * Leitet nach der nativen WordPress-Registrierung auf die
	 * WooCommerce-Account-Seite um.
	 *
	 * @param string $redirect Aktuelles Redirect-Ziel.
	 * @return string
	 */
	public function registration_redirect( $redirect ) {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			return wc_get_page_permalink( 'myaccount' );
		}

		return $redirect;
	}

	/**
	 * Benachrichtigt den Betreiber per E-Mail über eine neue
	 * Kundenregistrierung.
	 *
	 * @param int $customer_id ID des neuen Kunden.
	 * @return void
	 */
	public function notify_admin_new_user( $customer_id ) {
		if ( ! $customer_id ) {
			return;
		}

		$user = get_userdata( $customer_id );
		if ( ! $user || is_wp_error( $user ) ) {
			return;
		}

		$subject = 'Neues Kundenkonto auf der Website';
		$message = "Auf der Website wurde ein neues Kundenkonto angelegt:\n\n"
			. 'Benutzername: ' . $user->user_login . "\n"
			. 'E-Mail: ' . $user->user_email . "\n"
			. 'Zeitpunkt: ' . current_time( 'mysql' ) . "\n";

		wp_mail( 'info@barmbini.de', $subject, $message );
	}

	protected function is_subscriptions_endpoint_request() {
		global $wp_query;

		return function_exists( 'is_account_page' )
			&& is_account_page()
			&& isset( $wp_query->query_vars['abonnements'] );
	}
}