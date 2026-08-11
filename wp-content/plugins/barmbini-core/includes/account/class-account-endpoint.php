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
		// Konto-Bereiche ohne Shop/Checkout ausblenden (reiner Katalog).
		unset( $items['orders'] );
		unset( $items['downloads'] );
		unset( $items['edit-address'] );
		unset( $items['payment-methods'] );

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
	 * Lädt das Login-Sicherheits-JavaScript nur auf der Account-Seite,
	 * wenn der Benutzer nicht angemeldet ist.
	 *
	 * Leert Login-Felder und setzt autocomplete="off", damit nach dem
	 * Abmelden keine alten Zugangsdaten im Browser-Autofill erscheinen.
	 *
	 * @return void
	 */
	public function enqueue_login_security() {
		if ( is_user_logged_in() || ! is_account_page() ) {
			return;
		}

		wp_enqueue_script(
			'barmbini-core-account-login',
			BARMBINI_CORE_URL . 'assets/js/account-login.js',
			array(),
			BARMBINI_CORE_VERSION,
			false
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

		// Login-Sicherheit: verhindert Browser-Autofill nach Abmeldung.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_login_security' ) );
		add_action( 'woocommerce_register_form', array( $this, 'render_privacy_consent_checkbox' ) );
		add_filter( 'woocommerce_registration_errors', array( $this, 'validate_privacy_consent' ), 10, 3 );
		add_action( 'woocommerce_created_customer', array( $this, 'save_privacy_consent' ), 10, 1 );

		// Admin-Benachrichtigung bei neuer Registrierung.
		add_action( 'woocommerce_created_customer', array( $this, 'notify_admin_new_user' ), 20, 1 );

		// Passwort-Anforderungen: mindestens 6 Zeichen (statt strikter
		// WooCommerce-Stärke-Anforderung).
		add_filter( 'password_hint', array( $this, 'password_hint' ) );
		add_filter( 'woocommerce_min_password_strength', array( $this, 'password_min_strength' ) );
		add_filter( 'user_profile_update_errors', array( $this, 'validate_password_length_wp' ), 10, 3 );
		add_action( 'woocommerce_save_account_details_errors', array( $this, 'validate_password_length_wc' ), 10, 2 );
		add_filter( 'woocommerce_registration_errors', array( $this, 'validate_password_length_registration' ), 20, 3 );

		// E-Mail-Absender für alle wp_mail()-Mails.
		add_filter( 'wp_mail_from', array( $this, 'custom_mail_from' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'custom_mail_from_name' ) );

		// Nach nativer WordPress-Registrierung auf die Account-Seite umleiten.
		add_filter( 'registration_redirect', array( $this, 'registration_redirect' ) );

		// Dashboard-Inhalt anpassen (Standard-Text ersetzen).
		remove_action( 'woocommerce_account_content', 'woocommerce_account_content' );
		add_action( 'woocommerce_account_content', array( $this, 'render_custom_dashboard' ) );
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

	/**
	 * Ersetzt den Standard-WooCommerce-Dashboard-Inhalt durch eine
	 * Barmbini-Variante (ohne Bestellungen/Adressen, da reiner Katalog).
	 *
	 * WICHTIG: Spezifische Account-Endpoints (z. B. `abonnements`,
	 * `edit-account`) werden weiterhin über ihre eigenen Renderer
	 * ausgelöst; nur die Dashboard-Seite (kein Endpoint aktiv) rendert
	 * den angepassten Text.
	 *
	 * @return void
	 */
	public function render_custom_dashboard() {
		global $wp;

		// Spezifische Endpoints weiterhin normal ausliefern.
		if ( ! empty( $wp->query_vars ) ) {
			foreach ( $wp->query_vars as $key => $value ) {
				if ( 'pagename' === $key ) {
					continue;
				}

				if ( has_action( 'woocommerce_account_' . $key . '_endpoint' ) ) {
					do_action( 'woocommerce_account_' . $key . '_endpoint', $value );
					return;
				}
			}
		}

		$current_user     = wp_get_current_user();
		$logout_url       = function_exists( 'wc_logout_url' ) ? wc_logout_url() : wp_logout_url();
		$edit_account_url = function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( 'edit-account' ) : home_url( '/mein-konto/edit-account/' );

		$allowed_html = array(
			'a' => array(
				'href' => array(),
			),
		);
		?>
		<p>
			<?php
			printf(
				wp_kses( __( 'Hallo %1$s (nicht %1$s? <a href="%2$s">Abmelden</a>)', 'barmbini-core' ), $allowed_html ),
				'<strong>' . esc_html( $current_user->display_name ) . '</strong>',
				esc_url( $logout_url )
			);
			?>
		</p>
		<p>
			<?php
			printf(
				wp_kses( __( 'Von Ihrem Konto-Dashboard aus können Sie Ihr <a href="%1$s">Passwort und Ihre Kontodetails</a> bearbeiten.', 'barmbini-core' ), $allowed_html ),
				esc_url( $edit_account_url )
			);
			?>
		</p>
		<?php

		/**
		 * WooCommerce-Dashboard-Hook weiterhin auslösen, damit
		 * zusätzliche Inhalte (z. B. Widgets) nicht verloren gehen.
		 */
		do_action( 'woocommerce_account_dashboard' );
	}

	/**
	 * Ersetzt den WordPress-Standard-Hinweis zur Passwort-Komplexität
	 * durch eine einfache Mindestlängen-Angabe (6 Zeichen).
	 *
	 * @param string $hint Aktueller Hinweis.
	 * @return string
	 */
	public function password_hint( $hint ) {
		return __( 'Tipp: Das Passwort muss mindestens 6 Zeichen lang sein.', 'barmbini-core' );
	}

	/**
	 * Setzt die minimale WooCommerce-Passwortstärke auf 0 (keine
	 * Stärke-Anforderung), damit nur die eigene 6-Zeichen-Regel gilt.
	 *
	 * @return int
	 */
	public function password_min_strength() {
		return 0;
	}

	/**
	 * Validiert die Mindestlänge des Passworts bei WordPress-Profil-Updates
	 * (z. B. Passwort setzen nach Registrierung, Passwort zurücksetzen).
	 *
	 * @param WP_Error $errors Fehlerobjekt.
	 * @param bool     $update Ob Update.
	 * @param WP_User  $user   Benutzer.
	 * @return WP_Error
	 */
	public function validate_password_length_wp( $errors, $update, $user ) {
		$password = $this->get_submitted_password();

		if ( '' !== $password && strlen( $password ) < 6 ) {
			$errors->add( 'barmbini_password_too_short', __( 'Das Passwort muss mindestens 6 Zeichen lang sein.', 'barmbini-core' ) );
		}

		return $errors;
	}

	/**
	 * Validiert die Mindestlänge des Passworts im WooCommerce-Kontodetails-Formular.
	 *
	 * @param WP_Error $errors Fehlerobjekt.
	 * @param WP_User  $user   Benutzer.
	 * @return void
	 */
	public function validate_password_length_wc( $errors, $user ) {
		$password = $this->get_submitted_password();

		if ( '' !== $password && strlen( $password ) < 6 ) {
			$errors->add( 'barmbini_password_too_short', __( 'Das Passwort muss mindestens 6 Zeichen lang sein.', 'barmbini-core' ) );
		}
	}

	/**
	 * Validiert die Mindestlänge des Passworts bei der Registrierung.
	 *
	 * @param WP_Error $errors   Fehlerobjekt.
	 * @param string   $username Benutzername.
	 * @param string   $email    E-Mail-Adresse.
	 * @return WP_Error
	 */
	public function validate_password_length_registration( $errors, $username, $email ) {
		$password = $this->get_submitted_password();

		if ( '' !== $password && strlen( $password ) < 6 ) {
			$errors->add( 'barmbini_password_too_short', __( 'Das Passwort muss mindestens 6 Zeichen lang sein.', 'barmbini-core' ) );
		}

		return $errors;
	}

	/**
	 * Liefert das aktuell übermittelte Passwort aus den üblichen
	 * Formular-Feldern zurück (leer, wenn keines gesetzt).
	 *
	 * @return string
	 */
	protected function get_submitted_password() {
		foreach ( array( 'password_1', 'pass1', 'reg_password', 'password' ) as $field ) {
			if ( isset( $_POST[ $field ] ) && '' !== (string) $_POST[ $field ] ) {
				return (string) $_POST[ $field ];
			}
		}

		return '';
	}

	protected function is_subscriptions_endpoint_request() {
		global $wp_query;

		return function_exists( 'is_account_page' )
			&& is_account_page()
			&& isset( $wp_query->query_vars['abonnements'] );
	}
}