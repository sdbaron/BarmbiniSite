<?php
/**
 * Tests für Barmbini_Core_Roles
 *
 * Deckt die Migration der alten Seller-Rolle zu `shop_manager` ab.
 *
 * @package Barmbini_Core
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/roles/class-roles.php';

class RolesTest extends TestCase {

	/** @var Barmbini_Core_Roles */
	private $roles;

	protected function setUp(): void {
		_test_reset_all();
		$this->roles = new Barmbini_Core_Roles();
	}

	public function test_migrate_reassigns_users_and_removes_legacy_role(): void {
		// Alte Rolle + Shop Manager vorhanden.
		add_role( 'barmbini_verkaeufer', 'Seller', array( 'edit_products' => true ) );
		add_role( 'shop_manager', 'Shop Manager', array( 'manage_woocommerce' => true ) );

		$user1 = new WP_User( 1 );
		$user1->add_role( 'barmbini_verkaeufer' );
		$user2 = new WP_User( 2 );
		$user2->add_role( 'barmbini_verkaeufer' );
		$GLOBALS['__wp_users'] = array( $user1, $user2 );

		$this->roles->migrate_legacy_seller_role();

		// Nutzer haben jetzt die Shop-Manager-Rolle.
		$this->assertContains( 'shop_manager', $user1->roles );
		$this->assertContains( 'shop_manager', $user2->roles );
		$this->assertNotContains( 'barmbini_verkaeufer', $user1->roles );
		$this->assertNotContains( 'barmbini_verkaeufer', $user2->roles );
		// Alte Rolle entfernt.
		$this->assertNull( get_role( 'barmbini_verkaeufer' ) );
	}

	public function test_migrate_noop_when_legacy_role_absent(): void {
		add_role( 'shop_manager', 'Shop Manager', array() );

		$this->roles->migrate_legacy_seller_role();

		// Nichts passiert, keine Benutzer angefasst.
		$this->assertNotNull( get_role( 'shop_manager' ) );
	}

	public function test_migrate_noop_when_shop_manager_absent(): void {
		add_role( 'barmbini_verkaeufer', 'Seller', array() );

		$this->roles->migrate_legacy_seller_role();

		// Alte Rolle bleibt bestehen (defensiv).
		$this->assertNotNull( get_role( 'barmbini_verkaeufer' ) );
	}
}
