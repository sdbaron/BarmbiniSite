<?php
/**
 * Barmbini Core – Rollen-Pflege (Shop Manager statt eigener Rolle)
 *
 * Die ursprüngliche Projektrolle `barmbini_verkaeufer` („Seller“) wurde durch
 * die WooCommerce-Standardrolle `shop_manager` („Shop Manager“) ersetzt.
 *
 * Dieses Modul kümmert sich ausschließlich um die saubere Migration:
 * - weist Nutzer mit der alten Rolle der Rolle `shop_manager` zu
 * - entfernt die veraltete Rolle `barmbini_verkaeufer` danach
 *
 * Die WordPress-Standardrolle `editor` bleibt unangetastet (keine Umbenennung,
 * Anzeige „Redakteur“ über die deutsche Übersetzung).
 *
 * @package Barmbini_Core
 * @since 0.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Roles {

	const LEGACY_SELLER_SLUG = 'barmbini_verkaeufer';
	const SHOP_MANAGER_SLUG  = 'shop_manager';

	/**
	 * Registriert die Rollen-Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'migrate_legacy_seller_role' ) );
	}

	/**
	 * Migriert Nutzer der alten Rolle zu `shop_manager` und entfernt die Rolle.
	 *
	 * Idempotent und defensiv: Läuft nur, wenn die alte Rolle existiert UND die
	 * Zielrolle `shop_manager` verfügbar ist (von WooCommerce angelegt).
	 *
	 * @return void
	 */
	public function migrate_legacy_seller_role() {
		if ( ! get_role( self::LEGACY_SELLER_SLUG ) ) {
			return;
		}
		if ( ! get_role( self::SHOP_MANAGER_SLUG ) ) {
			return;
		}

		$users = get_users( array(
			'role'   => self::LEGACY_SELLER_SLUG,
			'number' => -1,
		) );

		foreach ( $users as $user ) {
			$user->add_role( self::SHOP_MANAGER_SLUG );
			$user->remove_role( self::LEGACY_SELLER_SLUG );
		}

		remove_role( self::LEGACY_SELLER_SLUG );
	}
}
