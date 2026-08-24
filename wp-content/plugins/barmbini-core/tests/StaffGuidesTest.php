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

	public function test_get_guide_slugs_returns_both_pages(): void {
		$slugs = Barmbini_Core_Staff_Guides::get_guide_slugs();

		$this->assertContains( 'anleitung-redakteur', $slugs );
		$this->assertContains( 'anleitung-shop-manager', $slugs );
		$this->assertNotContains( 'anleitung-verkaeufer', $slugs );
		$this->assertCount( 2, $slugs );
	}

	// =================================================================
	// role_slugs()
	// =================================================================

	public function test_role_slugs_include_admin_editor_shop_manager(): void {
		$slugs = Barmbini_Core_Staff_Guides::role_slugs();

		$this->assertContains( 'administrator', $slugs );
		$this->assertContains( 'editor', $slugs );
		$this->assertContains( 'shop_manager', $slugs );
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

		// Administrator + Redakteur: beide Anleitungen.
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'barmbini_view_guide_redakteur' ) );
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'barmbini_view_guide_shop_manager' ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( 'barmbini_view_guide_redakteur' ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( 'barmbini_view_guide_shop_manager' ) );
		// Shop Manager: nur Shop-Manager-Anleitung.
		$this->assertFalse( get_role( 'shop_manager' )->has_cap( 'barmbini_view_guide_redakteur' ) );
		$this->assertTrue( get_role( 'shop_manager' )->has_cap( 'barmbini_view_guide_shop_manager' ) );
		// Subscriber: keine.
		$this->assertFalse( get_role( 'subscriber' )->has_cap( 'barmbini_view_guide_redakteur' ) );
		$this->assertFalse( get_role( 'subscriber' )->has_cap( 'barmbini_view_guide_shop_manager' ) );
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

	public function test_ensure_pages_creates_both_pages(): void {
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
	}

	// =================================================================
	// can_view_page() – Pro-Seite-Berechtigung
	// =================================================================

	public function test_can_view_page_redakteur_only_with_redakteur_cap(): void {
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

	public function test_should_redirect_redakteur_page_for_shop_manager(): void {
		// Shop Manager hat nur die Shop-Manager-Cap → Redakteur-Seite wird umgeleitet.
		$GLOBALS['__wp_current_page'] = 'anleitung-redakteur';
		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'barmbini_view_guide_shop_manager' );

		$this->assertTrue( $this->guides->should_redirect() );
	}

	public function test_should_redirect_false_for_redakteur_page_with_redakteur_cap(): void {
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
