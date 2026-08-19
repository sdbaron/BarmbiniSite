<?php
/**
 * Tests für Barmbini_Core_Seller_Role
 *
 * Deckt die Capability-Matrix, den Rollen-Slug, die idempotente Anlage
 * und das Blockieren des permanenten Löschens (nur Papierkorb) ab.
 *
 * @package Barmbini_Core
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/roles/class-seller-role.php';

class SellerRoleTest extends TestCase {

	/** @var Barmbini_Core_Seller_Role */
	private $role;

	protected function setUp(): void {
		_test_reset_all();
		$this->role = new Barmbini_Core_Seller_Role();
	}

	// =================================================================
	// get_role_slug()
	// =================================================================

	public function test_role_slug_is_barmbini_verkaeufer(): void {
		$this->assertSame( 'barmbini_verkaeufer', Barmbini_Core_Seller_Role::get_role_slug() );
	}

	// =================================================================
	// get_capabilities() – Produktverwaltung enthalten
	// =================================================================

	public function test_capabilities_include_product_management(): void {
		$caps = Barmbini_Core_Seller_Role::get_capabilities();

		$expected = array(
			'read',
			'upload_files',
			'edit_products',
			'edit_published_products',
			'edit_others_products',
			'publish_products',
			'delete_products',
			'delete_published_products',
			'delete_others_products',
			'assign_product_terms',
		);

		foreach ( $expected as $cap ) {
			$this->assertArrayHasKey( $cap, $caps, "Capability fehlt: $cap" );
			$this->assertTrue( $caps[ $cap ], "Capability ist false: $cap" );
		}
	}

	// =================================================================
	// get_capabilities() – keine Admin-/System-/Inhaltsrechte
	// =================================================================

	public function test_capabilities_exclude_admin_rights(): void {
		$caps = Barmbini_Core_Seller_Role::get_capabilities();

		$excluded = array(
			'manage_options',
			'edit_users',
			'list_users',
			'promote_users',
			'remove_users',
			'activate_plugins',
			'update_plugins',
			'install_plugins',
			'update_themes',
			'switch_themes',
			'edit_plugins',
			'edit_themes',
			'update_core',
			'manage_woocommerce',
			'manage_product_terms',
			'edit_product_terms',
			'delete_product_terms',
			'edit_posts',
			'edit_pages',
			'publish_posts',
			'delete_posts',
			'edit_others_posts',
			'read_private_products',
		);

		foreach ( $excluded as $cap ) {
			$this->assertArrayNotHasKey( $cap, $caps, "Unzulaessige Capability vergeben: $cap" );
		}
	}

	// =================================================================
	// maybe_create_role() – idempotente Anlage
	// =================================================================

	public function test_maybe_create_role_creates_role(): void {
		Barmbini_Core_Seller_Role::maybe_create_role();

		$role = get_role( 'barmbini_verkaeufer' );
		$this->assertNotNull( $role, 'Rolle wurde nicht angelegt' );
		$this->assertTrue( $role->has_cap( 'edit_products' ) );
		$this->assertTrue( $role->has_cap( 'delete_others_products' ) );
		$this->assertFalse( $role->has_cap( 'manage_options' ) );
		$this->assertTrue( $role->has_cap( 'barmbini_verkaeufer' ) );
	}

	public function test_maybe_create_role_is_idempotent(): void {
		Barmbini_Core_Seller_Role::maybe_create_role();
		Barmbini_Core_Seller_Role::maybe_create_role();

		$this->assertCount( 1, $GLOBALS['__wp_roles'], 'Rolle wurde mehrfach angelegt' );
		$this->assertNotNull( get_role( 'barmbini_verkaeufer' ) );
	}

	// =================================================================
	// prevent_permanent_delete() – nur Papierkorb, kein permanentes Löschen
	// =================================================================

	public function test_prevent_permanent_delete_blocks_trash_for_seller(): void {
		$GLOBALS['__wp_user_caps'][1] = array( 'barmbini_verkaeufer' );
		$GLOBALS['__wp_posts'][42]    = (object) array( 'ID' => 42, 'post_status' => 'trash' );

		$result = $this->role->prevent_permanent_delete( array( 'delete_products' ), 'delete_post', 1, array( 42 ) );

		$this->assertSame( array( 'do_not_allow' ), $result );
	}

	public function test_prevent_permanent_delete_allows_non_trash(): void {
		$GLOBALS['__wp_user_caps'][1] = array( 'barmbini_verkaeufer' );
		$GLOBALS['__wp_posts'][42]    = (object) array( 'ID' => 42, 'post_status' => 'publish' );

		$result = $this->role->prevent_permanent_delete( array( 'delete_products' ), 'delete_post', 1, array( 42 ) );

		$this->assertSame( array( 'delete_products' ), $result );
	}

	public function test_prevent_permanent_delete_ignores_other_caps(): void {
		$GLOBALS['__wp_user_caps'][1] = array( 'barmbini_verkaeufer' );
		$GLOBALS['__wp_posts'][42]    = (object) array( 'ID' => 42, 'post_status' => 'trash' );

		$result = $this->role->prevent_permanent_delete( array( 'edit_products' ), 'edit_post', 1, array( 42 ) );

		$this->assertSame( array( 'edit_products' ), $result );
	}

	// =================================================================
	// allow_admin_access() – WooCommerce-Admin-Blockade aufheben
	// =================================================================

	public function test_allow_admin_access_returns_false_for_seller(): void {
		$GLOBALS['__wp_user_caps'][1] = array( 'barmbini_verkaeufer' );
		$GLOBALS['__wp_current_user'] = 1;

		$this->assertFalse( $this->role->allow_admin_access( true ) );
	}

	public function test_allow_admin_access_returns_original_for_non_seller(): void {
		$GLOBALS['__wp_user_caps'][1] = array( 'edit_posts' );
		$GLOBALS['__wp_current_user'] = 1;

		$this->assertTrue( $this->role->allow_admin_access( true ) );
		$this->assertFalse( $this->role->allow_admin_access( false ) );
	}

	// =================================================================
	// redirect_to_admin() – Login-Umleitung auf /wp-admin/
	// =================================================================

	public function test_redirect_to_admin_for_seller(): void {
		$user = new WP_User( 1 );
		$GLOBALS['__wp_user_caps'][1] = array( 'barmbini_verkaeufer' );

		$result = $this->role->redirect_to_admin( 'http://example.test/mein-konto/', '', $user );

		$this->assertStringContainsString( '/wp-admin/', $result );
	}

	public function test_redirect_to_admin_returns_original_for_non_seller(): void {
		$user = new WP_User( 1 );
		$GLOBALS['__wp_user_caps'][1] = array( 'edit_posts' );

		$result = $this->role->redirect_to_admin( 'http://example.test/mein-konto/', '', $user );

		$this->assertSame( 'http://example.test/mein-konto/', $result );
	}

	// =================================================================
	// enable_admin_bar_for_seller() – Admin-Bar für Verkäufer aktivieren
	// =================================================================

	public function test_enable_admin_bar_for_seller_returns_false(): void {
		$GLOBALS['__wp_user_caps'][1] = array( 'barmbini_verkaeufer' );
		$GLOBALS['__wp_current_user'] = 1;

		$this->assertFalse( $this->role->enable_admin_bar_for_seller( true ) );
	}

	public function test_enable_admin_bar_for_seller_returns_original_for_non_seller(): void {
		$GLOBALS['__wp_user_caps'][1] = array( 'edit_posts' );
		$GLOBALS['__wp_current_user'] = 1;

		$this->assertTrue( $this->role->enable_admin_bar_for_seller( true ) );
		$this->assertFalse( $this->role->enable_admin_bar_for_seller( false ) );
	}
}
