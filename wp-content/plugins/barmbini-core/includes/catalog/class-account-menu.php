<?php
/**
 * Barmbini Core – Dynamischer Menüeintrag für "Ihr Konto" / "Anmelden"
 *
 * Ersetzt den Menüpunkt /mein-konto/ im Hauptmenü je nach Anmeldestatus:
 * - Nicht angemeldet → "Anmelden"
 * - Angemeldet      → Avatar + "Ihr Konto"
 *
 * @package Barmbini_Core
 * @since 0.5.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Account_Menu {

	/**
	 * Registriert alle Hooks für den dynamischen Account-Menüeintrag.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'wp_nav_menu_objects', array( $this, 'swap_account_menu_title' ), 10, 2 );
		add_filter( 'nav_menu_item_title', array( $this, 'allow_account_avatar_html' ), 10, 4 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Filter: wp_nav_menu_objects
	 *
	 * Findet Menüpunkte, die auf /mein-konto/ verlinken, und passt
	 * deren Titel je nach Anmeldestatus an.
	 *
	 * @param array    $items Menü-Objekte.
	 * @param stdClass $args  wp_nav_menu-Argumente.
	 * @return array
	 */
	public function swap_account_menu_title( $items, $args ) {
		if ( is_admin() ) {
			return $items;
		}

		$logged_in = is_user_logged_in();

		foreach ( $items as $item ) {
			if ( ! $this->is_account_item( $item ) ) {
				continue;
			}

			if ( $logged_in ) {
				$user   = wp_get_current_user();
				$avatar = get_avatar(
					$user->ID,
					28,
					'',
					'',
					array(
						'class'  => 'barmbini-account-avatar',
						'height' => 28,
						'width'  => 28,
					)
				);

				$item->title   = $avatar . ' <span class="barmbini-account-label">Ihr Konto</span>';
				$item->classes[] = 'barmbini-account-menu-item';
			} else {
				$item->title = 'Anmelden';
			}
		}

		return $items;
	}

	/**
	 * Filter: nav_menu_item_title
	 *
	 * Stellt sicher, dass der Avatar-HTML-Teil des Titels nicht von
	 * nachgelagerten Filtern (z. B. wptexturize in the_title) zerstört wird.
	 *
	 * @param string   $title Der Menüpunkt-Titel.
	 * @param WP_Post  $item  Das Menüpunkt-Post-Objekt.
	 * @param stdClass $args  wp_nav_menu-Argumente.
	 * @param int      $depth Menütiefe.
	 * @return string
	 */
	public function allow_account_avatar_html( $title, $item, $args, $depth ) {
		if ( in_array( 'barmbini-account-menu-item', $item->classes, true ) ) {
			return $title;
		}

		return $title;
	}

	/**
	 * Lädt das Avatar-Menü-Stylesheet nur im Frontend.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_enqueue_style(
			'barmbini-core-account-menu',
			BARMBINI_CORE_URL . 'assets/css/account-menu.css',
			array(),
			BARMBINI_CORE_VERSION
		);
	}

	/**
	 * Prüft, ob ein Menüpunkt auf die Account-Seite verweist.
	 *
	 * @param WP_Post $item Menüpunkt.
	 * @return bool
	 */
	private function is_account_item( $item ) {
		return false !== strpos( $item->url, '/mein-konto/' )
			|| trailingslashit( $item->url ) === trailingslashit( home_url( '/mein-konto/' ) );
	}
}
