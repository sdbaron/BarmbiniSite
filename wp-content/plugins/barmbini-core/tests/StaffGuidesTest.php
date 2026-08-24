<?php
/**
 * Tests für Barmbini_Core_Staff_Guides
 *
 * Deckt die Slugs der beiden Anleitungsseiten (Redakteur & Shop Manager),
 * die rollenbasierte Berechtigung (Capabilities), die idempotente Anlage,
 * den Cleanup der veralteten Verkäufer-Seite und die Umleitungs-Entscheidung ab.
 *
 * @package Barmbini_Core
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/roles/class-roles.php';
require_once __DIR__ . '/../includes/guides/class-staff-guides.php';

class StaffGuidesTest extends TestCase {

	/** @var Barmbini_Core_Staff_Guides */
	private $guides;

	protected function setUp(): void {
		_test_reset_all();
		$this->guides = new Barmbini_Core_Staff_Guides();
	}

	// =================================================================
	// get_guide_slugs()
	// =================================================================

	public function test_get_guide_slugs_defaults_to_shop_manager_only(): void {
		// Standard: Redakteur-Anleitung ausgeblendet.
		$slugs = Barmbini_Core_Staff_Guides::get_guide_slugs();

		$this->assertContains( 'anleitung-shop-manager', $slugs );
		$this->assertNotContains( 'anleitung-redakteur', $slugs );
		$this->assertNotContains( 'anleitung-verkaeufer', $slugs );
		$this->assertCount( 1, $slugs );
	}

	public function test_get_guide_slugs_includes_redakteur_when_enabled(): void {
		add_filter( 'barmbini_guide_redakteur_enabled', function () {
			return true;
		} );

		$slugs = Barmbini_Core_Staff_Guides::get_guide_slugs();

		$this->assertContains( 'anleitung-redakteur', $slugs );
		$this->assertContains( 'anleitung-shop-manager', $slugs );
		$this->assertCount( 2, $slugs );
	}

	// =================================================================
	// role_slugs()
	// =================================================================

	public function test_role_slugs_defaults_without_editor_role(): void {
		$slugs = Barmbini_Core_Staff_Guides::role_slugs();

		$this->assertContains( 'administrator', $slugs );
		$this->assertContains( 'shop_manager', $slugs );
		$this->assertNotContains( 'editor', $slugs );
	}

	public function test_role_slugs_include_editor_when_enabled(): void {
		add_filter( 'barmbini_guide_redakteur_enabled', function () {
			return true;
		} );

		$slugs = Barmbini_Core_Staff_Guides::role_slugs();

		$this->assertContains( 'editor', $slugs );
	}

	// =================================================================
	// ensure_capabilities() – Pro-Seite-Capabilities je Rolle
	// =================================================================

	public function test_ensure_capabilities_adds_cap_to_allowed_roles(): void {
		add_role( 'shop_manager', 'Shop Manager', array() );
		// Standardrollen anlegen (administrator, editor, subscriber).
		add_role( 'administrator', 'Administrator', array( 'manage_options' => true ) );
		add_role( 'editor', 'Editor', array( 'edit_posts' => true ) );
		add_role( 'subscriber', 'Subscriber', array( 'read' => true ) );

		$this->guides->ensure_capabilities();

		// Standard (Redakteur ausgeblendet): Admin behaelt Redakteur-Cap nicht automatisch,
		// Editor erhaelt keine Anleitungs-Caps, Shop Manager nur die Shop-Manager-Cap.
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'barmbini_view_guide_shop_manager' ) );
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'barmbini_view_guide_redakteur' ) );
		$this->assertFalse( get_role( 'editor' )->has_cap( 'barmbini_view_guide_redakteur' ) );
		$this->assertFalse( get_role( 'editor' )->has_cap( 'barmbini_view_guide_shop_manager' ) );
		// Shop Manager: nur Shop-Manager-Anleitung.
		$this->assertFalse( get_role( 'shop_manager' )->has_cap( 'barmbini_view_guide_redakteur' ) );
		$this->assertTrue( get_role( 'shop_manager' )->has_cap( 'barmbini_view_guide_shop_manager' ) );
		// Subscriber: keine.
		$this->assertFalse( get_role( 'subscriber' )->has_cap( 'barmbini_view_guide_redakteur' ) );
		$this->assertFalse( get_role( 'subscriber' )->has_cap( 'barmbini_view_guide_shop_manager' ) );
	}

	public function test_ensure_capabilities_grants_admin_both_when_redakteur_enabled(): void {
		add_filter( 'barmbini_guide_redakteur_enabled', function () {
			return true;
		} );
		add_role( 'administrator', 'Administrator', array( 'manage_options' => true ) );
		add_role( 'editor', 'Editor', array( 'edit_posts' => true ) );

		$this->guides->ensure_capabilities();

		$this->assertTrue( get_role( 'administrator' )->has_cap( 'barmbini_view_guide_redakteur' ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'barmbini_view_guide_shop_manager' ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( 'barmbini_view_guide_redakteur' ) );
	}

	public function test_ensure_capabilities_removes_obsolete_capabilities(): void {
		add_role( 'shop_manager', 'Shop Manager', array( 'barmbini_view_guide_verkaeufer' => true ) );
		add_role( 'administrator', 'Administrator', array(
			'manage_options'                => true,
			'barmbini_view_guides'          => true,
			'barmbini_view_guide_verkaeufer' => true,
		) );

		$this->guides->ensure_capabilities();

		// Veraltete Verkäufer-Capability wird entfernt.
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'barmbini_view_guide_verkaeufer' ) );
		$this->assertFalse( get_role( 'shop_manager' )->has_cap( 'barmbini_view_guide_verkaeufer' ) );
		// Veraltete Sammel-Capability wird entfernt.
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'barmbini_view_guides' ) );
	}

	// =================================================================
	// ensure_pages() – idempotente Anlage der Seiten
	// =================================================================

	public function test_ensure_pages_creates_only_shop_manager_by_default(): void {
		$this->guides->ensure_pages();

		$slugs = array();
		foreach ( $GLOBALS['__wp_inserted_posts'] as $post ) {
			$slugs[] = $post['post_name'];
		}

		$this->assertContains( 'anleitung-shop-manager', $slugs );
		$this->assertNotContains( 'anleitung-redakteur', $slugs );
		$this->assertCount( 1, $slugs );
	}

	public function test_ensure_pages_creates_both_when_redakteur_enabled(): void {
		add_filter( 'barmbini_guide_redakteur_enabled', function () {
			return true;
		} );

		$this->guides->ensure_pages();

		$slugs = array();
		foreach ( $GLOBALS['__wp_inserted_posts'] as $post ) {
			$slugs[] = $post['post_name'];
		}

		$this->assertContains( 'anleitung-redakteur', $slugs );
		$this->assertContains( 'anleitung-shop-manager', $slugs );
		$this->assertCount( 2, $slugs );
	}

	public function test_ensure_pages_skips_existing_pages(): void {
		$GLOBALS['__wp_pages_by_path']['anleitung-redakteur']    = (object) array( 'ID' => 594 );
		$GLOBALS['__wp_pages_by_path']['anleitung-shop-manager'] = (object) array( 'ID' => 597 );

		$this->guides->ensure_pages();

		$this->assertCount( 0, $GLOBALS['__wp_inserted_posts'] );
	}

	// =================================================================
	// maybe_cleanup_legacy_verkaeufer_page() – Cleanup der Alt-Seite
	// =================================================================

	public function test_maybe_cleanup_legacy_verkaeufer_page_deletes_existing_page(): void {
		$GLOBALS['__wp_pages_by_path']['anleitung-verkaeufer'] = (object) array( 'ID' => 596 );

		$this->guides->maybe_cleanup_legacy_verkaeufer_page();

		$this->assertContains( 596, $GLOBALS['__wp_deleted_posts'] );
		$this->assertArrayNotHasKey( 'anleitung-verkaeufer', $GLOBALS['__wp_pages_by_path'] );
	}

	public function test_maybe_cleanup_legacy_verkaeufer_page_noop_when_absent(): void {
		$this->guides->maybe_cleanup_legacy_verkaeufer_page();

		$this->assertCount( 0, $GLOBALS['__wp_deleted_posts'] );
	}

	// =================================================================
	// guide content – beide Anleitungen
	// =================================================================

	public function test_guide_content_contains_role_description(): void {
		$this->assertStringContainsString( 'Deine Rolle', Barmbini_Core_Staff_Guides::redakteur_content() );
		$this->assertStringContainsString( 'Deine Rolle', Barmbini_Core_Staff_Guides::shop_manager_content() );
		$this->assertStringContainsString( 'Redakteur/in', Barmbini_Core_Staff_Guides::redakteur_content() );
		$this->assertStringContainsString( 'Shop Manager/in', Barmbini_Core_Staff_Guides::shop_manager_content() );
		$this->assertStringContainsString( 'Eine Aktion erstellen', Barmbini_Core_Staff_Guides::redakteur_content() );
		$this->assertStringContainsString( 'Einen neuen Artikel anlegen', Barmbini_Core_Staff_Guides::shop_manager_content() );
	}

	public function test_shop_manager_guide_content_is_detailed(): void {
		$content = Barmbini_Core_Staff_Guides::shop_manager_content();

		// Zentrale Abschnitte der erweiterten Shop-Manager-Anleitung.
		$this->assertStringContainsString( 'So funktioniert das Sortiment', $content );
		$this->assertStringContainsString( 'Beispiel', $content );
		$this->assertStringContainsString( 'Einen Preis anpassen', $content );
		$this->assertStringContainsString( 'Einen Artikel als ausverkauft markieren', $content );
		$this->assertStringContainsString( 'Kategorien pflegen', $content );
		$this->assertStringContainsString( 'Bilder hochladen und zuordnen', $content );
		$this->assertStringContainsString( 'Einen Artikel entfernen', $content );
		$this->assertStringContainsString( 'Entwurf und Veröffentlichen', $content );
		$this->assertStringContainsString( 'Tipps für die tägliche Arbeit', $content );
		$this->assertStringContainsString( 'Häufige Fragen (FAQ)', $content );
		// Aktionen-Überblick (seit 0.9.5) auch in der Shop-Manager-Anleitung.
		$this->assertStringContainsString( 'Aktionen im Überblick', $content );
		$this->assertStringContainsString( 'Flyer-Bild', $content );
	}

	// =================================================================
	// can_view_page() – Pro-Seite-Berechtigung
	// =================================================================

	public function test_can_view_page_redakteur_false_by_default_even_with_cap(): void {
		// Standard: Redakteur-Anleitung ausgeblendet → Seite nie sichtbar.
		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'barmbini_view_guide_redakteur', 'barmbini_view_guide_shop_manager' );

		$this->assertFalse( $this->guides->can_view_page( 'anleitung-redakteur' ) );
		$this->assertTrue( $this->guides->can_view_page( 'anleitung-shop-manager' ) );
	}

	public function test_can_view_page_redakteur_when_enabled_with_redakteur_cap(): void {
		add_filter( 'barmbini_guide_redakteur_enabled', function () {
			return true;
		} );
		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'barmbini_view_guide_redakteur' );

		$this->assertTrue( $this->guides->can_view_page( 'anleitung-redakteur' ) );
		$this->assertFalse( $this->guides->can_view_page( 'anleitung-shop-manager' ) );
	}

	public function test_can_view_page_shop_manager_only_with_shop_manager_cap(): void {
		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'barmbini_view_guide_shop_manager' );

		$this->assertTrue( $this->guides->can_view_page( 'anleitung-shop-manager' ) );
		$this->assertFalse( $this->guides->can_view_page( 'anleitung-redakteur' ) );
	}

	public function test_can_view_page_unknown_slug_returns_false(): void {
		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'barmbini_view_guide_shop_manager' );

		$this->assertFalse( $this->guides->can_view_page( 'impressum' ) );
	}

	// =================================================================
	// should_redirect() – Gating
	// =================================================================

	public function test_should_redirect_on_guide_page_without_cap(): void {
		$GLOBALS['__wp_current_page'] = 'anleitung-shop-manager';
		$GLOBALS['__wp_current_user'] = 2; // angemeldet, aber ohne Cap
		$GLOBALS['__wp_user_caps'][2] = array( 'read' );

		$this->assertTrue( $this->guides->should_redirect() );
	}

	public function test_should_redirect_false_for_shop_manager_page_with_shop_manager_cap(): void {
		$GLOBALS['__wp_current_page'] = 'anleitung-shop-manager';
		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'barmbini_view_guide_shop_manager' );

		$this->assertFalse( $this->guides->should_redirect() );
	}

	public function test_should_redirect_redakteur_page_for_shop_manager_when_enabled(): void {
		// Nur relevant, wenn die Redakteur-Anleitung reaktiviert ist:
		// Shop Manager ohne Redakteur-Cap → Umleitung.
		add_filter( 'barmbini_guide_redakteur_enabled', function () {
			return true;
		} );
		$GLOBALS['__wp_current_page'] = 'anleitung-redakteur';
		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'barmbini_view_guide_shop_manager' );

		$this->assertTrue( $this->guides->should_redirect() );
	}

	public function test_should_redirect_false_for_redakteur_page_when_disabled(): void {
		// Standard: anleitung-redakteur ist keine geführte Anleitungsseite mehr →
		// keine Umleitung (Seite gilt als normale/unbekannte Seite).
		$GLOBALS['__wp_current_page'] = 'anleitung-redakteur';
		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'barmbini_view_guide_redakteur' );

		$this->assertFalse( $this->guides->should_redirect() );
	}

	public function test_should_redirect_false_for_redakteur_page_when_enabled_with_redakteur_cap(): void {
		add_filter( 'barmbini_guide_redakteur_enabled', function () {
			return true;
		} );
		$GLOBALS['__wp_current_page'] = 'anleitung-redakteur';
		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'barmbini_view_guide_redakteur' );

		$this->assertFalse( $this->guides->should_redirect() );
	}

	public function test_should_redirect_false_for_other_page(): void {
		$GLOBALS['__wp_current_page'] = 'sortiment';
		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'read' );

		$this->assertFalse( $this->guides->should_redirect() );
	}
}
