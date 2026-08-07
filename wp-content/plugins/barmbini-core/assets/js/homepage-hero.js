/**
 * Barmbini Core – Startseiten-Hero
 *
 * Blendet das Header-Logo auf der Startseite ein, sobald der Hero-Bereich
 * (der das große Logo zeigt) aus dem Blickfeld gescrollt ist. Solange der
 * Hero sichtbar ist, bleibt das Header-Logo ausgeblendet – das verhindert,
 * dass im oberen Bereich zwei Logos gleichzeitig erscheinen.
 *
 * Die Umsetzung nutzt IntersectionObserver auf dem Hero-Element und toggelt
 * die Klasse "barmbini-header-logo-visible" am .site-header (siehe CSS).
 */
(function () {
	'use strict';

	function init() {
		// Nur auf der Frontseite.
		if (!document.body.classList.contains('home')) {
			return;
		}

		var hero = document.querySelector('.kb-row-layout-id13_93d54b-9c');
		var header = document.querySelector('.site-header');

		if (!hero || !header) {
			return;
		}

		if (!('IntersectionObserver' in window)) {
			// Fallback ohne IntersectionObserver: Logo immer anzeigen.
			header.classList.add('barmbini-header-logo-visible');
			return;
		}

		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				// Hero nicht (mehr) sichtbar -> Header-Logo einblenden.
				header.classList.toggle('barmbini-header-logo-visible', !entry.isIntersecting);
			});
		}, {
			threshold: 0
		});

		observer.observe(hero);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
