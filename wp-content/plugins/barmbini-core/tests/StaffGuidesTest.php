<?php
/**
 * Tests für Barmbini_Core_Staff_Guides
 *
 * Deckt die Slugs der Anleitungsseiten, die Berechtigung (Capability),
 * die idempotente Anlage der Seiten und die Umleitungs-Entscheidung ab.
 *
 * @package Barmbini_Core
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/roles/class-seller-role.php';
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
		$this->assertContains( 'anleitung-verkaeufer', $slugs );
		$this->assertCount( 2, $slugs );
	}

	// =================================================================
	// role_slugs()
	// =================================================================

	public function test_role_slugs_include_admin_editor_seller(): void {
		$slugs = Barmbini_Core_Staff_Guides::role_slugs();

		$this->assertContains( 'administrator', $slugs );
		$this->assertContains( 'editor', $slugs );
		$this->assertContains( 'barmbini_verkaeufer', $slugs );
	}

	// =================================================================
	// ensure_capabilities() – Capability nur an erlaubte Rollen
	// =================================================================

	public function test_ensure_capabilities_adds_cap_to_allowed_roles(): void {
		Barmbini_Core_Seller_Role::maybe_create_role();
		// Standardrollen anlegen (administrator, editor, subscriber).
		add_role( 'administrator', 'Administrator', array( 'manage_options' => true ) );
		add_role( 'editor', 'Editor', array( 'edit_posts' => true ) );
		add_role( 'subscriber', 'Subscriber', array( 'read' => true ) );

		$this->guides->ensure_capabilities();

		$this->assertTrue( get_role( 'administrator' )->has_cap( 'barmbini_view_guides' ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( 'barmbini_view_guides' ) );
		$this->assertTrue( get_role( 'barmbini_verkaeufer' )->has_cap( 'barmbini_view_guides' ) );
		$this->assertFalse( get_role( 'subscriber' )->has_cap( 'barmbini_view_guides' ) );
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
		$this->assertContains( 'anleitung-verkaeufer', $slugs );
		$this->assertCount( 2, $slugs );
	}

	public function test_ensure_pages_skips_existing_pages(): void {
		$GLOBALS['__wp_pages_by_path']['anleitung-redakteur'] = (object) array( 'ID' => 1 );

		$this->guides->ensure_pages();

		$slugs = array();
		foreach ( $GLOBALS['__wp_inserted_posts'] as $post ) {
			$slugs[] = $post['post_name'];
		}

		$this->assertNotContains( 'anleitung-redakteur', $slugs );
		$this->assertContains( 'anleitung-verkaeufer', $slugs );
	}

	public function test_guide_content_contains_role_description(): void {
		$this->assertStringContainsString( 'Deine Rolle', Barmbini_Core_Staff_Guides::redakteur_content() );
		$this->assertStringContainsString( 'Deine Rolle', Barmbini_Core_Staff_Guides::verkaeufer_content() );
		$this->assertStringContainsString( 'Einen neuen Artikel anlegen', Barmbini_Core_Staff_Guides::verkaeufer_content() );
		$this->assertStringContainsString( 'Eine Aktion erstellen', Barmbini_Core_Staff_Guides::redakteur_content() );
	}

	// =================================================================
	// should_redirect() – Gating
	// =================================================================

	public function test_should_redirect_on_guide_page_without_cap(): void {
		$GLOBALS['__wp_current_page'] = 'anleitung-verkaeufer';
		$GLOBALS['__wp_current_user'] = 2; // angemeldet, aber ohne Cap
		$GLOBALS['__wp_user_caps'][2] = array( 'read' );

		$this->assertTrue( $this->guides->should_redirect() );
	}

	public function test_should_redirect_false_for_guide_page_with_cap(): void {
		$GLOBALS['__wp_current_page'] = 'anleitung-verkaeufer';
		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'barmbini_view_guides' );

		$this->assertFalse( $this->guides->should_redirect() );
	}

	public function test_should_redirect_false_for_other_page(): void {
		$GLOBALS['__wp_current_page'] = 'sortiment';
		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'read' );

		$this->assertFalse( $this->guides->should_redirect() );
	}
}
