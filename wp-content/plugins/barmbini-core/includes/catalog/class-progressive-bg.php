<?php
/**
 * Progressive Background Images
 *
 * Universelles Lazy-/Progressive-Loading für CSS-Hintergründe:
 * Elemente mit data-bg-src (optional data-bg-src-sm/md, data-bg-lq)
 * oder konfigurierte CSS-Selektoren (Filter barmbini_progressive_bg_targets).
 *
 * Anleitung: Docs/Barmbini_Anleitung_Progressive_Hintergrundbilder.md
 *
 * @package Barmbini_Core
 * @since 0.10.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Progressive_Bg {

	/**
	 * Registriert Frontend-Assets.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Lädt CSS/JS auf dem Frontend und übergibt erlaubte Hosts + Targets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		$targets = $this->get_targets();

		wp_enqueue_style(
			'barmbini-core-progressive-bg',
			BARMBINI_CORE_URL . 'assets/css/progressive-bg.css',
			array(),
			BARMBINI_CORE_VERSION
		);

		wp_enqueue_script(
			'barmbini-core-progressive-bg',
			BARMBINI_CORE_URL . 'assets/js/progressive-bg.js',
			array(),
			BARMBINI_CORE_VERSION,
			true
		);

		wp_localize_script(
			'barmbini-core-progressive-bg',
			'barmbiniProgressiveBg',
			array(
				'allowedHosts' => $this->get_allowed_hosts(),
				'targets'      => $targets,
			)
		);
	}

	/**
	 * Erlaubte Hosts für Hintergrund-URLs (neben dem aktuellen Host).
	 *
	 * @return string[]
	 */
	protected function get_allowed_hosts() {
		$hosts = array();

		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( is_string( $home_host ) && '' !== $home_host ) {
			$hosts[] = $home_host;
		}

		$site_host = wp_parse_url( site_url(), PHP_URL_HOST );
		if ( is_string( $site_host ) && '' !== $site_host ) {
			$hosts[] = $site_host;
		}

		/**
		 * Zusätzliche erlaubte Hosts für progressive Hintergrundbilder.
		 *
		 * @param string[] $hosts
		 */
		$hosts = apply_filters( 'barmbini_progressive_bg_allowed_hosts', $hosts );

		return array_values( array_unique( array_filter( array_map( 'strval', (array) $hosts ) ) ) );
	}

	/**
	 * Konfigurierte CSS-Ziele (Selektor + Bild-URLs).
	 *
	 * Standard: Startseiten-Hero-Column mit den Hintergrund-Varianten.
	 *
	 * @return array<int,array<string,string>>
	 */
	protected function get_targets() {
		$uploads = wp_upload_dir();
		$base    = trailingslashit( $uploads['baseurl'] ) . '2026/09/';

		$defaults = array();

		if ( is_front_page() ) {
			// Kein 'lq': vor DOMContentLoaded kein Bild via Progressive-JS.
			// Wichtig: Im Kadence-Block darf ebenfalls kein Hintergrundbild gesetzt sein,
			// sonst lädt der Browser die URL bereits über generiertes CSS (vor DOMContentLoaded).
			$defaults[] = array(
				'selector' => '.kadence-column13_dbd800-e9 > .kt-inside-inner-col',
				'src'      => $base . 'Hintergrund-1920.jpg',
				'srcMd'    => $base . 'Hintergrund-800.jpg',
				'srcSm'    => $base . 'Hintergrund-480.jpg',
			);
		}

		/**
		 * Progressive-Background-Ziele.
		 *
		 * Jeder Eintrag:
		 * - selector (CSS, Pflicht)
		 * - src (Desktop/HQ)
		 * - srcMd (optional, Tablet)
		 * - srcSm (optional, Mobile)
		 * - lq (optional, sofortiger Platzhalter)
		 *
		 * @param array<int,array<string,string>> $targets
		 */
		$targets = apply_filters( 'barmbini_progressive_bg_targets', $defaults );

		if ( ! is_array( $targets ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $targets as $target ) {
			if ( ! is_array( $target ) || empty( $target['selector'] ) ) {
				continue;
			}

			$item = array(
				'selector' => sanitize_text_field( $target['selector'] ),
			);

			foreach ( array( 'src', 'srcMd', 'srcSm', 'lq' ) as $key ) {
				if ( empty( $target[ $key ] ) || ! is_string( $target[ $key ] ) ) {
					continue;
				}
				$url = esc_url_raw( $target[ $key ] );
				if ( '' !== $url ) {
					$item[ $key ] = $url;
				}
			}

			if ( empty( $item['src'] ) && empty( $item['srcMd'] ) && empty( $item['srcSm'] ) ) {
				continue;
			}

			$sanitized[] = $item;
		}

		return $sanitized;
	}
}
