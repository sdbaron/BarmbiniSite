<?php
/**
 * Tests für Barmbini_Core_Visitor_Stats
 *
 * Deckt die Capability-Vergabe, das Lesen/Aggregieren der Tages-Dateien,
 * den Pfad-Filter und das Shortcode-Gating (nur Admin/Redakteur) ab.
 *
 * @package Barmbini_Core
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/roles/class-seller-role.php';
require_once __DIR__ . '/../includes/stats/class-visitor-stats.php';

class VisitorStatsTest extends TestCase {

	/** @var Barmbini_Core_Visitor_Stats */
	private $stats;

	/** @var array<int,string> */
	private $tmp_dirs = array();

	protected function setUp(): void {
		_test_reset_all();
		$this->stats = new Barmbini_Core_Visitor_Stats();
	}

	protected function tearDown(): void {
		foreach ( $this->tmp_dirs as $dir ) {
			foreach ( (array) glob( $dir . '/stats-*.json' ) as $f ) {
				@unlink( $f );
			}
			@rmdir( $dir );
		}
		$this->tmp_dirs = array();
	}

	private function make_fixture_dir() {
		$dir = sys_get_temp_dir() . '/barmbini-stats-test-' . uniqid();
		mkdir( $dir, 0700, true );
		$this->tmp_dirs[] = $dir;
		return $dir;
	}

	private function write_fixture( $dir, $date, $json ) {
		file_put_contents( $dir . '/stats-' . $date . '.json', $json );
	}

	private function set_stats_dir( $dir ) {
		add_filter( 'barmbini_stats_dir', function () use ( $dir ) {
			return $dir;
		} );
	}

	// =================================================================
	// ensure_capabilities()
	// =================================================================

	public function test_capability_granted_to_admin_and_editor_only(): void {
		Barmbini_Core_Seller_Role::maybe_create_role();
		add_role( 'administrator', 'Administrator', array( 'manage_options' => true ) );
		add_role( 'editor', 'Editor', array( 'edit_posts' => true ) );
		add_role( 'subscriber', 'Subscriber', array( 'read' => true ) );

		$this->stats->ensure_capabilities();

		$this->assertTrue( get_role( 'administrator' )->has_cap( 'barmbini_view_stats' ) );
		$this->assertTrue( get_role( 'editor' )->has_cap( 'barmbini_view_stats' ) );
		$this->assertFalse( get_role( 'barmbini_verkaeufer' )->has_cap( 'barmbini_view_stats' ) );
		$this->assertFalse( get_role( 'subscriber' )->has_cap( 'barmbini_view_stats' ) );
	}

	// =================================================================
	// get_stats_dir() – Filter
	// =================================================================

	public function test_stats_dir_uses_filter(): void {
		$this->set_stats_dir( '/custom/stats' );
		$this->assertSame( '/custom/stats', $this->stats->get_stats_dir() );
	}

	public function test_stats_dir_default_when_no_filter(): void {
		$this->assertSame( '/var/lib/barmbini-stats/stats/', $this->stats->get_stats_dir() );
	}

	// =================================================================
	// read_aggregates()
	// =================================================================

	public function test_read_aggregates_returns_null_without_dir(): void {
		$this->set_stats_dir( sys_get_temp_dir() . '/barmbini-does-not-exist-' . uniqid() );
		$this->assertNull( $this->stats->read_aggregates( 30 ) );
	}

	public function test_read_aggregates_sums_days(): void {
		$dir = $this->make_fixture_dir();
		$d1  = date( 'Y-m-d', time() - 2 * 86400 );
		$d2  = date( 'Y-m-d', time() - 3 * 86400 );

		$this->write_fixture( $dir, $d1, json_encode( array(
			'date'            => $d1,
			'views'           => 10,
			'unique_visitors' => 4,
			'devices'         => array( 'mobile' => 6, 'tablet' => 1, 'desktop' => 3 ),
			'top_pages'       => array( array( 'path' => '/sortiment/', 'views' => 5 ), array( 'path' => '/', 'views' => 5 ) ),
			'top_referrers'   => array( array( 'domain' => 'google.de', 'views' => 3 ) ),
		) ) );
		$this->write_fixture( $dir, $d2, json_encode( array(
			'date'            => $d2,
			'views'           => 20,
			'unique_visitors' => 7,
			'devices'         => array( 'mobile' => 10, 'tablet' => 2, 'desktop' => 8 ),
			'top_pages'       => array( array( 'path' => '/sortiment/', 'views' => 12 ), array( 'path' => '/kontakt/', 'views' => 8 ) ),
			'top_referrers'   => array( array( 'domain' => 'google.de', 'views' => 4 ), array( 'domain' => 'facebook.com', 'views' => 2 ) ),
		) ) );
		$this->set_stats_dir( $dir );

		$totals = $this->stats->read_aggregates( 30 );

		$this->assertNotNull( $totals );
		$this->assertSame( 30, $totals['views'] );
		$this->assertSame( 11, $totals['unique_visitors'] );
		$this->assertSame( 16, $totals['devices']['mobile'] );
		$this->assertSame( 3, $totals['devices']['tablet'] );
		$this->assertSame( 11, $totals['devices']['desktop'] );
		// Beliebteste Seite: /sortiment/ mit 5 + 12 = 17.
		$this->assertSame( 17, $totals['top_pages']['/sortiment/'] );
		$this->assertSame( 8, $totals['top_pages']['/kontakt/'] );
		// Referrer summiert.
		$this->assertSame( 7, $totals['top_referrers']['google.de'] );
		$this->assertSame( 2, $totals['top_referrers']['facebook.com'] );
		// Tagesliste sortiert.
		$this->assertSame( array( $d2, $d1 ), array_keys( $totals['days'] ) );
	}

	public function test_read_aggregates_ignores_days_outside_period(): void {
		$dir = $this->make_fixture_dir();
		$recent = date( 'Y-m-d', time() - 2 * 86400 );
		$old    = date( 'Y-m-d', time() - 120 * 86400 );

		$this->write_fixture( $dir, $recent, json_encode( array(
			'date' => $recent, 'views' => 5, 'unique_visitors' => 2,
			'devices' => array( 'mobile' => 3, 'tablet' => 0, 'desktop' => 2 ),
			'top_pages' => array(), 'top_referrers' => array(),
		) ) );
		$this->write_fixture( $dir, $old, json_encode( array(
			'date' => $old, 'views' => 999, 'unique_visitors' => 500,
			'devices' => array( 'mobile' => 0, 'tablet' => 0, 'desktop' => 999 ),
			'top_pages' => array(), 'top_referrers' => array(),
		) ) );
		$this->set_stats_dir( $dir );

		$totals = $this->stats->read_aggregates( 30 );

		$this->assertSame( 5, $totals['views'] );
		$this->assertSame( 2, $totals['unique_visitors'] );
		$this->assertCount( 1, $totals['days'] );
		$this->assertArrayHasKey( $recent, $totals['days'] );
	}

	public function test_read_aggregates_skips_invalid_json(): void {
		$dir = $this->make_fixture_dir();
		$d1  = date( 'Y-m-d', time() - 1 * 86400 );
		$this->write_fixture( $dir, $d1, '{ungültig' );
		$this->set_stats_dir( $dir );

		$totals = $this->stats->read_aggregates( 30 );

		$this->assertNotNull( $totals );
		$this->assertSame( 0, $totals['views'] );
	}

	// =================================================================
	// render_shortcode() – Gating
	// =================================================================

	public function test_shortcode_renders_for_admin_or_editor(): void {
		$dir = $this->make_fixture_dir();
		$d1  = date( 'Y-m-d', time() - 1 * 86400 );
		$this->write_fixture( $dir, $d1, json_encode( array(
			'date' => $d1, 'views' => 12, 'unique_visitors' => 5,
			'devices' => array( 'mobile' => 6, 'tablet' => 1, 'desktop' => 5 ),
			'top_pages' => array( array( 'path' => '/sortiment/', 'views' => 12 ) ),
			'top_referrers' => array(),
		) ) );
		$this->set_stats_dir( $dir );

		$GLOBALS['__wp_current_user'] = 2;
		$GLOBALS['__wp_user_caps'][2] = array( 'barmbini_view_stats' );

		$html = $this->stats->render_shortcode();

		$this->assertStringContainsString( 'Besucherstatistik', $html );
		$this->assertStringContainsString( '12', $html );
		$this->assertStringContainsString( '/sortiment/', $html );
	}

	public function test_shortcode_empty_for_other_roles(): void {
		$this->set_stats_dir( $this->make_fixture_dir() );

		$GLOBALS['__wp_current_user'] = 3;
		$GLOBALS['__wp_user_caps'][3] = array( 'read' );

		$this->assertSame( '', $this->stats->render_shortcode() );
	}

	public function test_shortcode_empty_for_logged_out(): void {
		$this->set_stats_dir( $this->make_fixture_dir() );
		$GLOBALS['__wp_current_user'] = 0;

		$this->assertSame( '', $this->stats->render_shortcode() );
	}
}
