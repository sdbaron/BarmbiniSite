/**
 * Progressive Background Images
 *
 * Lädt Hintergrundbilder erst bei Sichtbarkeit (IntersectionObserver).
 * Markup: Elemente mit data-bg-src (optional data-bg-src-sm/md, data-bg-lq).
 * Zusätzliche Ziele können per wp_localize_script (barmbiniProgressiveBg.targets) kommen.
 *
 * @since 0.10.2
 */
(function () {
	'use strict';

	var config = window.barmbiniProgressiveBg || {};
	var allowedHosts = config.allowedHosts || [];
	var reducedData = false;

	try {
		reducedData = window.matchMedia('(prefers-reduced-data: reduce)').matches;
	} catch (e) {
		reducedData = false;
	}

	/**
	 * Prüft, ob eine URL relativ oder vom erlaubten Host stammt.
	 *
	 * @param {string} url
	 * @return {boolean}
	 */
	function isAllowedUrl(url) {
		if (!url || typeof url !== 'string') {
			return false;
		}

		url = url.trim();

		if (url.indexOf('/') === 0 && url.indexOf('//') !== 0) {
			return true;
		}

		try {
			var parsed = new URL(url, window.location.href);
			if (parsed.protocol !== 'http:' && parsed.protocol !== 'https:') {
				return false;
			}
			if (parsed.host === window.location.host) {
				return true;
			}
			for (var i = 0; i < allowedHosts.length; i++) {
				if (parsed.host === allowedHosts[i]) {
					return true;
				}
			}
		} catch (err) {
			return false;
		}

		return false;
	}

	/**
	 * Wählt die passende Bild-URL je Viewport.
	 *
	 * @param {HTMLElement} el
	 * @return {string}
	 */
	function resolveSrc(el) {
		var sm = el.getAttribute('data-bg-src-sm') || '';
		var md = el.getAttribute('data-bg-src-md') || '';
		var src = el.getAttribute('data-bg-src') || '';
		var width = window.innerWidth || document.documentElement.clientWidth || 0;

		if (width < 768 && sm) {
			return sm;
		}
		if (width < 1025 && md) {
			return md;
		}
		return src || md || sm;
	}

	/**
	 * Setzt optional das LQ-Bild sofort (falls data-bg-lq gesetzt).
	 *
	 * @param {HTMLElement} el
	 * @return {void}
	 */
	function applyLq(el) {
		var lq = el.getAttribute('data-bg-lq') || '';
		if (!lq || !isAllowedUrl(lq)) {
			return;
		}
		el.style.backgroundImage = 'url("' + lq.replace(/"/g, '\\"') + '")';
	}

	/**
	 * Lädt das HQ-Bild und wechselt den Hintergrund.
	 *
	 * @param {HTMLElement} el
	 * @param {IntersectionObserver|null} observer
	 * @return {void}
	 */
	function loadHighQuality(el, observer) {
		if (el.getAttribute('data-bg-loading') === '1' || el.classList.contains('barmbini-progressive-bg--loaded')) {
			return;
		}

		var hq = resolveSrc(el);
		if (!hq || !isAllowedUrl(hq)) {
			el.classList.add('barmbini-progressive-bg--failed');
			return;
		}

		if (reducedData) {
			applyLq(el);
			el.classList.add('barmbini-progressive-bg--loaded');
			el.classList.add('barmbini-progressive-bg--reduced-data');
			if (observer) {
				observer.unobserve(el);
			}
			return;
		}

		el.setAttribute('data-bg-loading', '1');

		var img = new Image();
		img.decoding = 'async';

		img.onload = function () {
			el.style.backgroundImage = 'url("' + hq.replace(/"/g, '\\"') + '")';
			el.classList.add('barmbini-progressive-bg--loaded');
			el.removeAttribute('data-bg-loading');
			if (observer) {
				observer.unobserve(el);
			}
		};

		img.onerror = function () {
			el.classList.add('barmbini-progressive-bg--failed');
			el.removeAttribute('data-bg-loading');
			if (observer) {
				observer.unobserve(el);
			}
		};

		img.src = hq;
	}

	/**
	 * Übernimmt konfigurierte Targets (Selektor + URLs) in data-Attribute.
	 *
	 * @return {void}
	 */
	function applyConfiguredTargets() {
		var targets = config.targets || [];
		for (var i = 0; i < targets.length; i++) {
			var t = targets[i];
			if (!t || !t.selector) {
				continue;
			}
			var nodes = document.querySelectorAll(t.selector);
			Array.prototype.forEach.call(nodes, function (el) {
				if (t.src && !el.getAttribute('data-bg-src')) {
					el.setAttribute('data-bg-src', t.src);
				}
				if (t.srcMd && !el.getAttribute('data-bg-src-md')) {
					el.setAttribute('data-bg-src-md', t.srcMd);
				}
				if (t.srcSm && !el.getAttribute('data-bg-src-sm')) {
					el.setAttribute('data-bg-src-sm', t.srcSm);
				}
				if (t.lq && !el.getAttribute('data-bg-lq')) {
					el.setAttribute('data-bg-lq', t.lq);
				}
				el.classList.add('barmbini-progressive-bg');
			});
		}
	}

	/**
	 * Startet den Observer für alle markierten Elemente.
	 *
	 * @return {void}
	 */
	function init() {
		applyConfiguredTargets();

		var elements = document.querySelectorAll('[data-bg-src], [data-bg-src-md], [data-bg-src-sm]');
		if (!elements.length) {
			return;
		}

		Array.prototype.forEach.call(elements, function (el) {
			el.classList.add('barmbini-progressive-bg');
			applyLq(el);
		});

		if (!('IntersectionObserver' in window)) {
			Array.prototype.forEach.call(elements, function (el) {
				loadHighQuality(el, null);
			});
			return;
		}

		var observer = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						loadHighQuality(entry.target, observer);
					}
				});
			},
			{
				threshold: 0.1,
				rootMargin: '50px 0px',
			}
		);

		Array.prototype.forEach.call(elements, function (el) {
			observer.observe(el);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
