<?php
/**
 * Barmbini Core – Login-Brute-Force-Schutz (Limit Login Attempts)
 *
 * Begrenzt fehlgeschlagene Anmeldeversuche pro IP, um Brute-Force-Angriffe
 * auf `/wp-login.php` zu erschweren. Nach N Fehlversuchen wird die IP für
 * eine Sperrzeit blockiert. Keine Cookies, keine externen Dienste.
 *
 * Zählung und Sperre erfolgen über Transients (Option `_transient_*`).
 *
 * @package Barmbini_Core
 * @since 0.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Login_Limiter {

	const MAX_ATTEMPTS    = 5;
	const LOCKOUT_MINUTES = 15;
	const ATTEMPT_WINDOW  = 15; // Minuten, in denen Fehlversuche gezählt werden.

	/**
	 * Registriert die Login-Schutz-Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_login_failed', array( $this, 'record_failed_attempt' ) );
		add_filter( 'authenticate', array( $this, 'check_lockout' ), 30, 3 );
		add_action( 'wp_login', array( $this, 'reset_attempts' ), 10, 2 );
	}

	/**
	 * Ermittelt die Client-IP (nur REMOTE_ADDR – zuverlässig, nicht spoofbar).
	 *
	 * @return string
	 */
	protected function client_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	/**
	 * Transient-Key für den Fehlversuchs-Zähler dieser IP.
	 *
	 * @return string
	 */
	protected function attempts_key() {
		return 'barmbini_login_failed_' . md5( $this->client_ip() );
	}

	/**
	 * Transient-Key für die Sperre dieser IP.
	 *
	 * @return string
	 */
	protected function lockout_key() {
		return 'barmbini_login_lockout_' . md5( $this->client_ip() );
	}

	/**
	 * Action: wp_login_failed – zählt einen Fehlversuch und sperrt ggf.
	 *
	 * @param string $username Eingegebener Benutzername.
	 * @return void
	 */
	public function record_failed_attempt( $username ) {
		if ( ! $this->client_ip() ) {
			return;
		}

		$attempts = (int) get_transient( $this->attempts_key() );
		$attempts++;

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			set_transient( $this->lockout_key(), 1, self::LOCKOUT_MINUTES * MINUTE_IN_SECONDS );
			delete_transient( $this->attempts_key() );
		} else {
			set_transient( $this->attempts_key(), $attempts, self::ATTEMPT_WINDOW * MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Filter: authenticate – blockt die Anmeldung, wenn die IP gesperrt ist.
	 *
	 * @param WP_User|WP_Error|null $user     Bisheriges Authentifizierungsergebnis.
	 * @param string                $username Eingegebener Benutzername.
	 * @param string                $password Eingegebenes Passwort.
	 * @return WP_User|WP_Error|null
	 */
	public function check_lockout( $user, $username, $password ) {
		if ( empty( $username ) || empty( $password ) ) {
			return $user;
		}

		if ( get_transient( $this->lockout_key() ) ) {
			return new WP_Error(
				'too_many_attempts',
				__( 'Zu viele fehlgeschlagene Anmeldeversuche. Bitte versuchen Sie es später erneut.', 'barmbini-core' )
			);
		}

		return $user;
	}

	/**
	 * Action: wp_login – setzt Zähler und Sperre nach erfolgreicher Anmeldung zurück.
	 *
	 * @param string  $user_login Benutzername.
	 * @param WP_User $user       Benutzerobjekt.
	 * @return void
	 */
	public function reset_attempts( $user_login, $user ) {
		delete_transient( $this->attempts_key() );
		delete_transient( $this->lockout_key() );
	}
}
