/**
 * Barmbini Core – Login-Formular-Sicherheit
 *
 * Verhindert, dass nach dem Abmelden alte Zugangsdaten aus dem
 * Browser-Autofill in das Login-Formular auf /mein-konto/ eingefüllt
 * werden.
 *
 * Maßnahmen:
 * 1. autocomplete="new-password" auf dem Passwortfeld – das stärkste
 *    Signal an Browser/Passwortmanager, NICHT automatisch auszufüllen.
 * 2. autocomplete="off" auf dem Benutzernamen- und dem Formularfeld.
 * 3. Mehrfaches Leeren der Felder (sofort, nach load und zeitversetzt),
 *    weil Browser-Autofill häufig NACH DOMContentLoaded stattfindet.
 * 4. Behandlung der bfcache-Rücknavigation (pageshow), die den zuvor
 *    gefüllten Zustand sonst wiederherstellt.
 *
 * @package Barmbini_Core
 * @since 0.5.1
 */

(function () {
	'use strict';

	/**
	 * Liefert das WooCommerce-Login-Formular.
	 *
	 * @return {Element|null}
	 */
	function getLoginForm() {
		return document.querySelector('.woocommerce-form-login')
			|| document.querySelector('form.login');
	}

	/**
	 * Leert die Login-Felder und setzt die Autocomplete-Attribute.
	 *
	 * @return {void}
	 */
	function clearLoginFields() {
		var form = getLoginForm();
		if (!form) {
			return;
		}

		var userField = form.querySelector('#username');
		if (userField) {
			userField.value = '';
			userField.setAttribute('autocomplete', 'off');
		}

		var passField = form.querySelector('#password');
		if (passField) {
			passField.value = '';
			passField.setAttribute('autocomplete', 'new-password');
		}

		form.setAttribute('autocomplete', 'off');
	}

	/**
	 * Führt clearLoginFields nach einer Verzögerung aus.
	 *
	 * @param {number} ms Verzögerung in Millisekunden.
	 * @return {void}
	 */
	function clearLater(ms) {
		window.setTimeout(clearLoginFields, ms);
	}

	// 1) Sofort: entweder direkt (DOM fertig) oder bei DOMContentLoaded.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', clearLoginFields);
	} else {
		clearLoginFields();
	}

	// 2) Nach window.load und zeitversetzt: Browser-Autofill greift oft
	//    erst hier oder sogar noch später.
	window.addEventListener('load', function () {
		[0, 100, 300, 700, 1500, 3000].forEach(clearLater);
	});

	// 3) bfcache: "Zurück"-Navigation stellt den vorher gefüllten Zustand
	//    wieder her – hier erneut leeren.
	window.addEventListener('pageshow', function (e) {
		if (e.persisted) {
			clearLoginFields();
			clearLater(100);
			clearLater(500);
		}
	});
})();
