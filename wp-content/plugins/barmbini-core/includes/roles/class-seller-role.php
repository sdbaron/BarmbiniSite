<?php
/**
 * Barmbini Core – Benutzerrolle „Verkäufer"
 *
 * Definiert die Projektrolle `barmbini_verkaeufer` (Anzeigename „Verkäufer").
 * Der Verkäufer kann ausschließlich Sortiment-Produkte (WooCommerce) verwalten:
 * neue Artikel anlegen, Preise anpassen, als ausverkauft markieren (nativer
 * Lagerstatus) und Artikel in den Papierkorb verschieben.
 *
 * Keine Inhalte/Blog, keine Kategorien-Verwaltung, keine System- oder
 * Benutzerrechte. Optional wird das permanente Löschen aus dem Papierkorb
 * für diese Rolle blockiert (nur Papierkorb = reversibel).
 *
 * Die Rolle wird idempotent angelegt (Aktivierung + Selbstheilung über
 * `admin_init`) und wird nie automatisch entfernt – konsistent zur
 * Projektentscheidung in `uninstall.php`.
 *
 * @package Barmbini_Core
 * @since 0.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Seller_Role {

	const ROLE_SLUG = 'barmbini_verkaeufer';

	/**
	 * Registriert die Rollen-Hooks.
	 *
	 * Die Selbstheilung läuft auf `admin_init`, damit die Rolle auch nach
	 * einem reinen Code-Deploy (Plugin bereits aktiv) automatisch angelegt
	 * wird. Der Check erfolgt nur für Nutzer mit `manage_options`.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( 'Barmbini_Core_Seller_Role', 'maybe_create_role' ) );
		add_filter( 'map_meta_cap', array( $this, 'prevent_permanent_delete' ), 10, 4 );

		// WooCommerce leitet Nutzer ohne edit_posts/manage_woocommerce/manage_options
		// standardmäßig vom wp-admin auf die „Mein Konto“-Seite um. Für den Verkäufer
		// (der bewusst diese Caps nicht hat, aber Produkte verwalten soll) muss der
		// Admin-Zugriff wieder freigeschaltet werden.
		add_filter( 'woocommerce_prevent_admin_access', array( $this, 'allow_admin_access' ) );
		add_filter( 'login_redirect', array( $this, 'redirect_to_admin' ), 10, 3 );
	}

	/**
	 * Liefert den Rollen-Slug.
	 *
	 * @return string
	 */
	public static function get_role_slug() {
		return self::ROLE_SLUG;
	}

	/**
	 * Liefert die Capability-Matrix für den Verkäufer.
	 *
	 * Ein Ort der Wahrheit – wird zum Anlegen der Rolle und in Tests verwendet.
	 *
	 * @return array<string,bool>
	 */
	public static function get_capabilities() {
		return array(
			// Basis
			'read'                      => true,
			'upload_files'              => true, // Produktfotos
			// Produkte verwalten (alle Produkte)
			'edit_products'             => true,
			'edit_published_products'   => true, // Preise an veröffentlichten Artikeln
			'edit_others_products'      => true, // alle Produkte
			'publish_products'          => true,
			'delete_products'           => true,
			'delete_published_products' => true,
			'delete_others_products'    => true, // alle Produkte
			// Kategorien nur zuordnen, nicht verwalten
			'assign_product_terms'      => true,
		);
	}

	/**
	 * Legt die Rolle idempotent an, falls sie noch nicht existiert.
	 *
	 * Statisch, damit sie sowohl aus der Aktivierung (class-activator.php)
	 * als auch per WP-CLI auf dem Server aufgerufen werden kann.
	 *
	 * @return void
	 */
	public static function maybe_create_role() {
		if ( get_role( self::ROLE_SLUG ) ) {
			return;
		}

		add_role( self::ROLE_SLUG, __( 'Verkäufer', 'barmbini-core' ), self::get_capabilities() );
	}

	/**
	 * Hebt die WooCommerce-Admin-Blockade für die Verkäufer-Rolle auf.
	 *
	 * WooCommerce verhindert den wp-admin-Zugriff für Nutzer ohne
	 * `edit_posts`/`manage_woocommerce`/`manage_options`. Da der Verkäufer
	 * Produkte im Admin verwaltet, aber bewusst diese Caps nicht hat, wird
	 * die Blockade (Filter `woocommerce_prevent_admin_access`) für ihn
	 * deaktiviert. Der Nutzer erhält dadurch keinen zusätzlichen Capability-
	 * Zugriff – er darf nur in den Admin-Bereich.
	 *
	 * @param bool $prevent Aktueller Verhinderungs-Status aus WooCommerce.
	 * @return bool
	 */
	public function allow_admin_access( $prevent ) {
		if ( current_user_can( self::ROLE_SLUG ) ) {
			return false;
		}

		return $prevent;
	}

	/**
	 * Leitet den Verkäufer nach dem Login in den WordPress-Admin um.
	 *
	 * Ohne diesen Filter würde WooCommerce den Verkäufer nach dem Login auf
	 * die „Mein Konto“-Seite schicken, wo er keine Admin-Optionen sieht.
	 *
	 * @param string   $redirect_to           Zieldatum aus dem Login.
	 * @param string   $requested_redirect_to Ursprünglich angeforderte URL.
	 * @param WP_User  $user                  Eingeloggter Nutzer.
	 * @return string
	 */
	public function redirect_to_admin( $redirect_to, $requested_redirect_to, $user ) {
		if ( $user instanceof WP_User && user_can( $user, self::ROLE_SLUG ) ) {
			return admin_url();
		}

		return $redirect_to;
	}

	/**
	 * Blockiert das permanente Löschen für die Rolle „Verkäufer".
	 *
	 * Der Verkäufer darf Produkte nur in den Papierkorb verschieben
	 * (reversibel), aber nicht endgültig löschen. Dafür wird `delete_post`
	 * auf `do_not_allow` gesetzt, wenn das Produkt bereits im Papierkorb
	 * liegt und der Nutzer die Verkäufer-Rolle hat.
	 *
	 * @param array<int,string> $caps    Erkannte Capabilities.
	 * @param string            $cap     Anfrage-Capability (z. B. `delete_post`).
	 * @param int               $user_id Nutzer-ID.
	 * @param array<int,mixed>  $args    Weitere Argumente (ggf. Post-ID).
	 * @return array<int,string>
	 */
	public function prevent_permanent_delete( $caps, $cap, $user_id, $args ) {
		if ( 'delete_post' !== $cap ) {
			return $caps;
		}

		if ( ! user_can( $user_id, self::ROLE_SLUG ) ) {
			return $caps;
		}

		$post_id = isset( $args[0] ) ? (int) $args[0] : 0;
		if ( ! $post_id ) {
			return $caps;
		}

		$post = get_post( $post_id );
		if ( $post && 'trash' === $post->post_status ) {
			$caps = array( 'do_not_allow' );
		}

		return $caps;
	}
}
