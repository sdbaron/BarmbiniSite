<?php
/**
 * Barmbini Core – REST-API-Härtung
 *
 * Sperrt die Benutzer-Endpoints der REST-API für nicht berechtigte
 * Personen, damit Benutzernamen nicht öffentlich auslesbar sind
 * (GET /wp-json/wp/v2/users gab den Benutzernamen preis).
 *
 * Administratoren (list_users) behalten vollen Zugriff.
 *
 * @package Barmbini_Core
 * @since 0.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Rest_Api_Hardening {

	/**
	 * Registriert den rest_endpoints-Filter.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'rest_endpoints', array( $this, 'disable_user_endpoints_for_public' ) );
	}

	/**
	 * Filter: rest_endpoints
	 *
	 * Entfernt die Benutzer-Routen für Aufrufer ohne list_users-Berechtigung.
	 *
	 * @param array $endpoints Alle registrierten REST-Endpoints.
	 * @return array
	 */
	public function disable_user_endpoints_for_public( $endpoints ) {
		if ( $this->is_allowed() ) {
			return $endpoints;
		}

		unset( $endpoints['/wp/v2/users'] );
		unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		unset( $endpoints['/wp/v2/users/me'] );
		unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)/posts'] );

		return $endpoints;
	}

	/**
	 * Prüft, ob der Aufrufer die Benutzer-Liste abfragen darf.
	 *
	 * @return bool
	 */
	protected function is_allowed() {
		return is_user_logged_in() && current_user_can( 'list_users' );
	}
}
