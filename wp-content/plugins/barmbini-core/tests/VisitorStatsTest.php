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

require_once __DIR__ . '/../includes/roles/class-roles.php';
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
		// Local setzt sys_temp_dir teils auf C:\Windows\TEMP (VirtualStore,
		// dort sieht glob() die Dateien nicht). Den echten Benutzer-Temp
		// bevorzugen, damit die Fixtures auch unter Windows lesbar sind.
		$base = getenv( 'TEMP' ) ? getenv( 'TEMP' ) : sys_get_temp_dir();
		$dir  = rtrim( $base, '/\\' ) . DIRECTORY_SEPARATOR . 'barmbini-stats-test-' . uniqid();
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

	public function test_capability_granted_to_admin_and_shop_manager_only(): void {
		add_role( 'shop_manager', 'Shop Manager', array() );
		add_role( 'administrator', 'Administrator', array( 'manage_options' => true ) );
		add_role( 'editor', 'Editor', array( 'edit_posts' => true ) );
		add_role( 'subscriber', 'Subscriber', array( 'read' => true ) );

		$this->stats->ensure_capabilities();

		$this->assertTrue( get_role( 'administrator' )->has_cap( 'barmbini_view_stats' ) );
		$this->assertTrue( get_role( 'shop_manager' )->has_cap( 'barmbini_view_stats' ) );
		$this->assertFalse( get_role( 'editor' )->has_cap( 'barmbini_view_stats' ) );
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
	// get_stats_dir_status() – Diagnose
	// =================================================================

	public function test_stats_dir_status_missing_when_dir_absent(): void {
		$this->set_stats_dir( sys_get_temp_dir() . '/barmbini-does-not-exist-' . uniqid() );
		$this->assertSame( 'missing', $this->stats->get_stats_dir_status() );
	}

	public function test_stats_dir_status_ok_when_dir_exists(): void {
		$dir = $this->make_fixture_dir();
		$this->set_stats_dir( $dir );
		$this->assertSame( 'ok', $this->stats->get_stats_dir_status() );
	}

	public function test_render_block_empty_mentions_missing_dir(): void {
		$this->set_stats_dir( sys_get_temp_dir() . '/barmbini-does-not-exist-' . uniqid() );
		$html = $this->stats->render_block( null, 30 );
		$this->assertStringContainsString( 'Für diesen Zeitraum liegen noch keine Daten vor.', $html );
		$this->assertStringContainsString( 'Verzeichnis fehlt', $html );
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
			'bots'            => 2,
			'devices'         => array( 'mobile' => 6, 'tablet' => 1, 'desktop' => 3 ),
			'top_pages'       => array( array( 'path' => '/sortiment/', 'views' => 5 ), array( 'path' => '/', 'views' => 5 ) ),
			'top_referrers'   => array( array( 'domain' => 'google.de', 'views' => 3 ) ),
		) ) );
		$this->write_fixture( $dir, $d2, json_encode( array(
			'date'            => $d2,
			'views'           => 20,
			'unique_visitors' => 7,
			'bots'            => 3,
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
		$this->assertSame( 5, $totals['bots'] );
		// Beliebteste Seite: /sortiment/ mit 5 + 12 = 17.
		$this->assertSame( 17, $totals['top_pages']['/sortiment/'] );
		$this->assertSame( 8, $totals['top_pages']['/kontakt/'] );
		// Referrer summiert.
		$this->assertSame( 7, $totals['top_referrers']['google.de'] );
		$this->assertSame( 2, $totals['top_referrers']['facebook.com'] );
		// Tagesliste absteigend (neueste zuerst) sortiert.
		$this->assertSame( array( $d1, $d2 ), array_keys( $totals['days'] ) );
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

	public function test_read_aggregates_uses_full_daily_counts_when_present(): void {
		$dir = $this->make_fixture_dir();
		$d1  = date( 'Y-m-d', time() - 2 * 86400 );
		$d2  = date( 'Y-m-d', time() - 3 * 86400 );

		$this->write_fixture( $dir, $d1, json_encode( array(
			'date'            => $d1,
			'views'           => 10,
			'unique_visitors' => 4,
			'devices'         => array( 'mobile' => 6, 'tablet' => 1, 'desktop' => 3 ),
			'top_pages'       => array( array( 'path' => '/sortiment/', 'views' => 5 ) ),
			'pages'           => array( '/sortiment/' => 5, '/kontakt/' => 5 ),
			'top_referrers'   => array( array( 'domain' => 'google.de', 'views' => 3 ) ),
			'referrers'       => array( 'google.de' => 3, 'facebook.com' => 2 ),
		) ) );
		$this->write_fixture( $dir, $d2, json_encode( array(
			'date'            => $d2,
			'views'           => 20,
			'unique_visitors' => 7,
			'devices'         => array( 'mobile' => 10, 'tablet' => 2, 'desktop' => 8 ),
			'top_pages'       => array( array( 'path' => '/sortiment/', 'views' => 12 ) ),
			'pages'           => array( '/sortiment/' => 12, '/kontakt/' => 8 ),
			'top_referrers'   => array( array( 'domain' => 'google.de', 'views' => 4 ) ),
			'referrers'       => array( 'google.de' => 4 ),
		) ) );
		$this->set_stats_dir( $dir );

		$totals = $this->stats->read_aggregates( 30 );

		$this->assertSame( 17, $totals['top_pages']['/sortiment/'] );
		$this->assertSame( 13, $totals['top_pages']['/kontakt/'] );
		$this->assertSame( 7, $totals['top_referrers']['google.de'] );
		$this->assertSame( 2, $totals['top_referrers']['facebook.com'] );
	}

	public function test_average_daily_visitors(): void {
		$totals = array(
			'views'           => 30,
			'unique_visitors' => 11,
			'days'            => array(
				'2026-08-20' => array( 'views' => 10, 'unique_visitors' => 4 ),
				'2026-08-21' => array( 'views' => 20, 'unique_visitors' => 7 ),
			),
		);

		$this->assertSame( 6, $this->stats->get_average_daily_visitors( $totals ) );
		$this->assertSame( 0, $this->stats->get_average_daily_visitors( array( 'unique_visitors' => 0, 'days' => array() ) ) );
	}

	public function test_is_valid_ip_or_cidr(): void {
		$this->assertTrue( $this->stats->is_valid_ip_or_cidr( '203.0.113.10' ) );
		$this->assertTrue( $this->stats->is_valid_ip_or_cidr( '2001:db8::1' ) );
		$this->assertTrue( $this->stats->is_valid_ip_or_cidr( '192.168.0.0/16' ) );
		$this->assertTrue( $this->stats->is_valid_ip_or_cidr( '2001:db8::/32' ) );

		$this->assertFalse( $this->stats->is_valid_ip_or_cidr( 'nicht-eine-ip' ) );
		$this->assertFalse( $this->stats->is_valid_ip_or_cidr( '192.168.0.0/33' ) );
		$this->assertFalse( $this->stats->is_valid_ip_or_cidr( '2001:db8::/129' ) );
		$this->assertFalse( $this->stats->is_valid_ip_or_cidr( '' ) );
	}

	public function test_sanitize_excluded_ips(): void {
		$raw = "203.0.113.10\n# Kommentar\n\n198.51.100.25  # inline\n192.168.0.0/16\nungueltig\n203.0.113.10\n";
		$out = $this->stats->sanitize_excluded_ips( $raw );

		$this->assertSame( array( '203.0.113.10', '198.51.100.25', '192.168.0.0/16' ), $out );
	}

	public function test_get_and_save_excluded_ips(): void {
		$base = getenv( 'TEMP' ) ? getenv( 'TEMP' ) : sys_get_temp_dir();
		$file = rtrim( $base, '/\\' ) . DIRECTORY_SEPARATOR . 'barmbini-ips-' . uniqid() . '.conf';
		add_filter( 'barmbini_stats_excluded_ips_file', function () use ( $file ) {
			return $file;
		} );

		$this->assertSame( array(), $this->stats->get_excluded_ips() );

		$this->assertTrue( $this->stats->save_excluded_ips( "203.0.113.10\n192.168.0.0/16\n" ) );
		$this->assertSame( array( '203.0.113.10', '192.168.0.0/16' ), $this->stats->get_excluded_ips() );

		@unlink( $file );
	}

	public function test_recalc_flag_file_uses_filter(): void {
		$this->assertSame( '/var/lib/barmbini-stats/run/recalc.flag', $this->stats->get_recalc_flag_file() );

		add_filter( 'barmbini_stats_recalc_flag_file', function () {
			return '/tmp/recalc.flag';
		} );
		$this->assertSame( '/tmp/recalc.flag', $this->stats->get_recalc_flag_file() );
	}

	// =================================================================
	// render_shortcode() – Gating
	// =================================================================

	public function test_shortcode_renders_for_user_with_cap(): void {
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
