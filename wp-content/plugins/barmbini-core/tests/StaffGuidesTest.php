<?php
/**
 * Tests für Barmbini_Core_Staff_Guides
 *
 * Deckt die Slugs der Anleitungsseite, die Berechtigung (Capability),
 * die idempotente Anlage der Seite, das Entfernen der veralteten
 * Shop-Manager-Anleitung und die Umleitungs-Entscheidung ab.
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

	public function test_get_guide_slugs_returns_only_redakteur_page(): void {
		$slugs = Barmbini_Core_Staff_Guides::get_guide_slugs();

		$this->assertContains( 'anleitung-redakteur', $slugs );
		$this->assertNotContains( 'anleitung-verkaeufer', $slugs );
		$this->assertCount( 1, $slugs );
	}

	// =================================================================
	// role_slugs()
	// =================================================================

	public function test_role_slugs_include_admin_and_editor_only(): void {
		$slugs = Barmbini_Core_Staff_Guides::role_slugs();

		$this->assertContains( 'administrator', $slugs );
		$this->assertContains( 'editor', $slugs );
		$this->assertNotContains( 'shop_manager', $slugs );
	}

	// =================================================================
	// ensure_capabilities() – Redakteur-Capability je Rolle
	// =================================================================

	public function test_ensure_capabilities_adds_cap_to_allowed_roles(): void {
		add_role( 'shop_manager', 'Shop Manager', array( 'barmbini_view_guide_verkaeufer' => true ) );
		// Standardrollen anlegen (administrator, editor, subscriber).
		add_role( 'administrator', 'Administrator', array( 'manage_options' => true ) );
		add_role( 'editor', 'Editor', array( 'edit_posts' => true ) );
		add_role( 'subscriber', 'Subscriber', array( 'read' => true ) );

		$this->guides->ensure_capabilities();

		// Administrator und Redakteur: Redakteur-Anleitung.
		$this->assertTrue( get_role( 'administrator' )->has_cap( 'barmbini_view_guide_redakteur' ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( 'barmbini_view_guide_redakteur' ) );
		// Shop Manager und Subscriber: keine.
		$this->assertFalse( get_role( 'shop_manager' )->has_cap( 'barmbini_view_guide_redakteur' ) );
		$this->assertFalse( get_role( 'subscriber' )->has_cap( 'barmbini_view_guide_redakteur' ) );
	}

	public function test_ensure_capabilities_removes_obsolete_capabilities(): void {
		add_role( 'shop_manager', 'Shop Manager', array( 'barmbini_view_guide_verkaeufer' => true ) );
		add_role( 'administrator', 'Administrator', array(
			'manage_options'          => true,
			'barmbini_view_guides'    => true,
			'barmbini_view_guide_verkaeufer' => true,
		) );

		$this->guides->ensure_capabilities();

		// Veraltete Shop-Manager-Guide-Capability wird entfernt.
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'barmbini_view_guide_verkaeufer' ) );
		$this->assertFalse( get_role( 'shop_manager' )->has_cap( 'barmbini_view_guide_verkaeufer' ) );
		// Veraltete Sammel-Capability wird entfernt.
		$this->assertFalse( get_role( 'administrator' )->has_cap( 'barmbini_view_guides' ) );
	}

	// =================================================================
	// ensure_pages() – idempotente Anlage der Redakteur-Seite
	// =================================================================

	public function test_ensure_pages_creates_only_redakteur_page(): void {
		$this->guides->ensure_pages();

		$slugs = array();
		foreach ( $GLOBALS['__wp_inserted_posts'] as $post ) {
			$slugs[] = $post['post_name'];
		}

		$this->assertContains( 'anleitung-redakteur', $slugs );
		$this->assertNotContains( 'anleitung-verkaeufer', $slugs );
		$this->assertCount( 1, $slugs );
	}

	public function test_ensure_pages_skips_existing_page(): void {
		$GLOBALS['__wp_pages_by_path']['anleitung-redakteur'] = (object) array( 'ID' => 594 );

		$this->guides->ensure_pages();

		$this->assertCount( 0, $GLOBALS['__wp_inserted_posts'] );
	}

	// =================================================================
	// maybe_remove_obsolete_verkaeufer_page() – Entfernung der Alt-Seite
	// =================================================================

	public function test_maybe_remove_obsolete_verkaeufer_page_trashes_existing_page(): void {
		$GLOBALS['__wp_pages_by_path']['anleitung-verkaeufer'] = (object) array( 'ID' => 596 );

		$this->guides->maybe_remove_obsolete_verkaeufer_page();

		$this->assertContains( 596, $GLOBALS['__wp_trashed_posts'] );
		$this->assertArrayNotHasKey( 'anleitung-verkaeufer', $GLOBALS['__wp_pages_by_path'] );
	}

	public function test_maybe_remove_obsolete_verkaeufer_page_noop_when_absent(): void {
		$this->guides->maybe_remove_obsolete_verkaeufer_page();

		$this->assertCount( 0, $GLOBALS['__wp_trashed_posts'] );
	}

	// =================================================================
	// guide content – detaillierte Redakteur-Anleitung
	// =================================================================

	public function test_guide_content_contains_role_description(): void {
		$content = Barmbini_Core_Staff_Guides::redakteur_content();

		$this->assertStringContainsString( 'Deine Rolle', $content );
		$this->assertStringContainsString( 'Redakteur/in', $content );
		$this->assertStringContainsString( 'Eine Aktion erstellen', $content );
	}

	public function test_guide_content_is_detailed(): void {
		$content = Barmbini_Core_Staff_Guides::redakteur_content();

		// Zentrale Abschnitte der detaillierten Anleitung.
		$this->assertStringContainsString( 'Eine Neuigkeit (Blogbeitrag) schreiben', $content );
		$this->assertStringContainsString( 'Eine Seite bearbeiten', $content );
		$this->assertStringContainsString( 'Ein Produkt (Artikel) anlegen oder bearbeiten', $content );
		$this->assertStringContainsString( 'Medien: Bilder hochladen und einfügen', $content );
		$this->assertStringContainsString( 'Veröffentlichen &amp; Planen', $content );
		$this->assertStringContainsString( 'Tipps für die tägliche Arbeit', $content );
		$this->assertStringContainsString( 'Häufige Fragen (FAQ)', $content );
	}

	// =================================================================
	// can_view_page() – Berechtigung
	// =================================================================

	public function test_can_view_page_redakteur_only_with_redakteur_cap(): void {
		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'barmbini_view_guide_redakteur' );

		$this->assertTrue( $this->guides->can_view_page( 'anleitung-redakteur' ) );
		$this->assertFalse( $this->guides->can_view_page( 'anleitung-verkaeufer' ) );
	}

	public function test_can_view_page_unknown_slug_returns_false(): void {
		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'barmbini_view_guide_redakteur' );

		$this->assertFalse( $this->guides->can_view_page( 'impressum' ) );
	}

	// =================================================================
	// should_redirect() – Gating
	// =================================================================

	public function test_should_redirect_on_guide_page_without_cap(): void {
		$GLOBALS['__wp_current_page'] = 'anleitung-redakteur';
		$GLOBALS['__wp_current_user'] = 2; // angemeldet, aber ohne Cap
		$GLOBALS['__wp_user_caps'][2] = array( 'read' );

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
