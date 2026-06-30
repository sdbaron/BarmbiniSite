/**
 * Footer Burger Menu – Toggle-Funktionalität
 *
 * Reagiert ausschliesslich auf Klicks auf den bereits per PHP
 * im HTML vorhandenen Burger-Button (Klasse .menu-toggle).
 * Keine DOM-Manipulation – exakt dasselbe Prinzip wie Kadences
 * Header-Navigation.
 *
 * @since 0.1.0
 */

(function () {
	'use strict';

	/**
	 * Bindet Click-Event an alle Footer-Burger-Buttons.
	 *
	 * Die Buttons werden von PHP über den wp_nav_menu-Filter
	 * ausgegeben. Dieses Skript fügt keine Elemente hinzu,
	 * sondern steuert nur das Öffnen/Schliessen.
	 */
	function initFooterBurgerMenus() {
		var toggles = document.querySelectorAll('.barmbini-footer-burger-toggle');

		Array.prototype.forEach.call(toggles, function (toggleBtn) {
			// Bereits initialisiert? Dann überspringen.
			if (toggleBtn.dataset.barmbiniInit === '1') {
				return;
			}
			toggleBtn.dataset.barmbiniInit = '1';

			var container = toggleBtn.closest('.barmbini-footer-burger-container');
			if (!container) {
				return;
			}

			toggleBtn.addEventListener('click', function () {
				var isOpen = container.classList.contains('is-open');

				// Finde das umschliessende Grid-Column-Element (Kadence Footer-Section)
				var footerSection = toggleBtn.closest('.site-footer-section');

				if (isOpen) {
					container.classList.remove('is-open');
					toggleBtn.setAttribute('aria-expanded', 'false');
					toggleBtn.setAttribute('aria-label', 'Footer-Menü öffnen');
					if (footerSection) {
						footerSection.classList.remove('barmbini-footer-open');
					}
				} else {
					container.classList.add('is-open');
					toggleBtn.setAttribute('aria-expanded', 'true');
					toggleBtn.setAttribute('aria-label', 'Footer-Menü schliessen');
					if (footerSection) {
						footerSection.classList.add('barmbini-footer-open');
					}
				}
			});
		});
	}

	// ---------- Initialisierung ----------

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initFooterBurgerMenus);
	} else {
		initFooterBurgerMenus();
	}

	// Nach vollständigem Laden erneut initialisieren
	// (z. B. für Customizer-Vorschau oder verzögert geladene Widgets).
	window.addEventListener('load', initFooterBurgerMenus);
})();
