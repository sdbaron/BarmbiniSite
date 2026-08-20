<?php
/**
 * Barmbini Core – Besucherstatistik (nginx-Logs, Option B)
 *
 * Zeigt die anonymisierten Aggregate der Server-Verarbeitung an
 * (`/var/lib/barmbini-stats/stats/stats-*.json`). Sichtbar nur für
 * Benutzer mit der Capability `barmbini_view_stats` (Administrator +
 * Redakteur).
 *
 * - Admin-Seite „Statistiken" (Zeitraum 7/30/90 Tage)
 * - Shortcode `[barmbini_visitor_stats]` für die Frontend-Anzeige
 *   (rendert nur für Admin/Editor, sonst leerer String)
 *
 * Die Server-Seite erzeugt ausschließlich aggregierte, anonymisierte Werte
 * (keine IPs, keine vollen Referrer-URLs, keine Cookies). Siehe
 * `server-config/barmbini-stats/` und
 * `Tasks/Barmbini_Aufgabe_Besucherstatistik_nginx.md`.
 *
 * @package Barmbini_Core
 * @since 0.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Visitor_Stats {

	const CAP       = 'barmbini_view_stats';
	const MENU_SLUG = 'barmbini-visitor-stats';
	const SHORTCODE = 'barmbini_visitor_stats';
	const PERIODS   = array( 7, 30, 90 );
	const TOP_N     = 10;

	/**
	 * Registriert die Hooks des Statistik-Moduls.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'ensure_capabilities' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
	}

	/**
	 * Vergibt die Statistik-Capability idempotent an Administrator + Editor.
	 *
	 * @return void
	 */
	public function ensure_capabilities() {
		foreach ( array( 'administrator', 'editor' ) as $slug ) {
			$role = get_role( $slug );
			if ( $role && ! $role->has_cap( self::CAP ) ) {
				$role->add_cap( self::CAP );
			}
		}
	}

	/**
	 * Liefert das Verzeichnis mit den Aggregat-Dateien.
	 *
	 * Per Filter `barmbini_stats_dir` überschreibbar (z. B. für Tests/Local).
	 *
	 * @return string
	 */
	public function get_stats_dir() {
		return apply_filters( 'barmbini_stats_dir', '/var/lib/barmbini-stats/stats/' );
	}

	/**
	 * Liefert den aus der Admin-URL gewählten Zeitraum.
	 *
	 * @return int
	 */
	public function get_period() {
		$days = isset( $_GET['days'] ) ? (int) $_GET['days'] : 30;
		if ( ! in_array( $days, self::PERIODS, true ) ) {
			$days = 30;
		}

		return $days;
	}

	/**
	 * Liest und aggregiert die Tages-Dateien der letzten N Tage.
	 *
	 * @param int $days Anzahl Tage (7/30/90).
	 * @return array|null Aggregierte Werte oder null, wenn keine Daten.
	 */
	public function read_aggregates( $days = 30 ) {
		$dir = trailingslashit( $this->get_stats_dir() );
		if ( ! is_dir( $dir ) ) {
			return null;
		}

		$files = glob( $dir . 'stats-*.json' );
		if ( ! $files ) {
			return null;
		}

		$cutoff = date( 'Y-m-d', time() - $days * 86400 );

		$totals = array(
			'views'           => 0,
			'unique_visitors' => 0,
			'devices'         => array( 'mobile' => 0, 'tablet' => 0, 'desktop' => 0 ),
			'top_pages'       => array(),
			'top_referrers'   => array(),
			'days'            => array(),
		);

		foreach ( $files as $file ) {
			if ( ! preg_match( '/stats-(\d{4}-\d{2}-\d{2})\.json$/', $file, $m ) ) {
				continue;
			}
			$date = $m[1];
			if ( strcmp( $date, $cutoff ) < 0 ) {
				continue;
			}

			$data = json_decode( (string) file_get_contents( $file ), true );
			if ( ! is_array( $data ) ) {
				continue;
			}

			$totals['views']           += isset( $data['views'] ) ? (int) $data['views'] : 0;
			$totals['unique_visitors'] += isset( $data['unique_visitors'] ) ? (int) $data['unique_visitors'] : 0;

			foreach ( array( 'mobile', 'tablet', 'desktop' ) as $d ) {
				if ( isset( $data['devices'][ $d ] ) ) {
					$totals['devices'][ $d ] += (int) $data['devices'][ $d ];
				}
			}

			foreach ( (array) ( isset( $data['top_pages'] ) ? $data['top_pages'] : array() ) as $p ) {
				if ( isset( $p['path'], $p['views'] ) ) {
					$key = $p['path'];
					$totals['top_pages'][ $key ] = ( isset( $totals['top_pages'][ $key ] ) ? $totals['top_pages'][ $key ] : 0 ) + (int) $p['views'];
				}
			}

			foreach ( (array) ( isset( $data['top_referrers'] ) ? $data['top_referrers'] : array() ) as $r ) {
				if ( isset( $r['domain'], $r['views'] ) ) {
					$key = $r['domain'];
					$totals['top_referrers'][ $key ] = ( isset( $totals['top_referrers'][ $key ] ) ? $totals['top_referrers'][ $key ] : 0 ) + (int) $r['views'];
				}
			}

			$totals['days'][ $date ] = array(
				'views'           => isset( $data['views'] ) ? (int) $data['views'] : 0,
				'unique_visitors' => isset( $data['unique_visitors'] ) ? (int) $data['unique_visitors'] : 0,
			);
		}

		arsort( $totals['top_pages'] );
		$totals['top_pages'] = array_slice( $totals['top_pages'], 0, self::TOP_N, true );

		arsort( $totals['top_referrers'] );
		$totals['top_referrers'] = array_slice( $totals['top_referrers'], 0, self::TOP_N, true );

		ksort( $totals['days'] );

		return $totals;
	}

	/**
	 * Rendert den Statistik-Block (Admin-Seite und Shortcode).
	 *
	 * @param array|null $totals Aggregierte Werte oder null.
	 * @param int        $days   Angezeigter Zeitraum.
	 * @return string
	 */
	public function render_block( $totals, $days = 30 ) {
		if ( null === $totals ) {
			return '<p class="barmbini-stats-empty">' . esc_html( 'Für diesen Zeitraum liegen noch keine Daten vor.' ) . '</p>';
		}

		ob_start();
		?>
		<style>
			.barmbini-stats{max-width:960px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#1d2327;}
			.barmbini-stats h2{margin:0 0 .4em;}
			.barmbini-stats .barmbini-stats-muted{color:#646970;font-size:.9em;margin:.2em 0 1em;}
			.barmbini-stats .barmbini-stats-kpis{display:flex;gap:16px;flex-wrap:wrap;}
			.barmbini-stats .barmbini-stats-kpi{flex:1;min-width:150px;background:#f6f7f7;border:1px solid #dcdcde;border-radius:8px;padding:14px;}
			.barmbini-stats .barmbini-stats-kpi .barmbini-stats-label{font-size:.85em;color:#646970;}
			.barmbini-stats .barmbini-stats-kpi .barmbini-stats-value{font-size:1.6em;font-weight:700;margin-top:2px;}
			.barmbini-stats h3{margin:1.6em 0 .5em;}
			.barmbini-stats table{width:100%;border-collapse:collapse;}
			.barmbini-stats th,.barmbini-stats td{text-align:left;padding:6px 10px;border-bottom:1px solid #eee;font-size:.95em;}
			.barmbini-stats th{color:#646970;font-weight:600;}
		</style>
		<div class="barmbini-stats">
			<h2><?php echo esc_html( 'Besucherstatistik' ); ?></h2>
			<p class="barmbini-stats-muted">
				<?php
				echo esc_html(
					sprintf(
						'Zeitraum: %d Tag(e) · %d Tag(e) mit Daten · anonymisiert, ohne Cookies',
						(int) $days,
						count( $totals['days'] )
					)
				);
				?>
			</p>

			<div class="barmbini-stats-kpis">
				<div class="barmbini-stats-kpi">
					<div class="barmbini-stats-label"><?php echo esc_html( 'Seitenaufrufe (Views)' ); ?></div>
					<div class="barmbini-stats-value"><?php echo (int) $totals['views']; ?></div>
				</div>
				<div class="barmbini-stats-kpi">
					<div class="barmbini-stats-label"><?php echo esc_html( 'Besucher (ca.)' ); ?></div>
					<div class="barmbini-stats-value"><?php echo (int) $totals['unique_visitors']; ?></div>
				</div>
				<div class="barmbini-stats-kpi">
					<div class="barmbini-stats-label"><?php echo esc_html( 'Mobil' ); ?></div>
					<div class="barmbini-stats-value"><?php echo (int) $totals['devices']['mobile']; ?></div>
				</div>
				<div class="barmbini-stats-kpi">
					<div class="barmbini-stats-label"><?php echo esc_html( 'Tablet' ); ?></div>
					<div class="barmbini-stats-value"><?php echo (int) $totals['devices']['tablet']; ?></div>
				</div>
				<div class="barmbini-stats-kpi">
					<div class="barmbini-stats-label"><?php echo esc_html( 'Desktop' ); ?></div>
					<div class="barmbini-stats-value"><?php echo (int) $totals['devices']['desktop']; ?></div>
				</div>
			</div>

			<h3><?php echo esc_html( 'Beliebteste Seiten' ); ?></h3>
			<?php if ( empty( $totals['top_pages'] ) ) : ?>
				<p class="barmbini-stats-muted"><?php echo esc_html( 'Keine Daten.' ); ?></p>
			<?php else : ?>
				<table>
					<thead><tr><th><?php echo esc_html( 'Seite' ); ?></th><th><?php echo esc_html( 'Views' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $totals['top_pages'] as $path => $views ) : ?>
						<tr><td><code><?php echo esc_html( $path ); ?></code></td><td><?php echo (int) $views; ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h3><?php echo esc_html( 'Referrer' ); ?></h3>
			<?php if ( empty( $totals['top_referrers'] ) ) : ?>
				<p class="barmbini-stats-muted"><?php echo esc_html( 'Keine Daten.' ); ?></p>
			<?php else : ?>
				<table>
					<thead><tr><th><?php echo esc_html( 'Domain' ); ?></th><th><?php echo esc_html( 'Views' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $totals['top_referrers'] as $domain => $views ) : ?>
						<tr><td><?php echo esc_html( $domain ); ?></td><td><?php echo (int) $views; ?></td></tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode-Handler: rendert nur für Admin/Editor.
	 *
	 * @param array|string $atts Shortcode-Attribute (optional `days`).
	 * @return string
	 */
	public function render_shortcode( $atts = array() ) {
		if ( ! current_user_can( self::CAP ) ) {
			return '';
		}

		$atts = is_array( $atts ) ? $atts : array();
		$days = isset( $atts['days'] ) ? (int) $atts['days'] : 30;
		if ( ! in_array( $days, self::PERIODS, true ) ) {
			$days = 30;
		}

		return $this->render_block( $this->read_aggregates( $days ), $days );
	}

	/**
	 * Registriert den Admin-Menüpunkt „Statistiken“.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'Statistiken', 'barmbini-core' ),
			__( 'Statistiken', 'barmbini-core' ),
			self::CAP,
			self::MENU_SLUG,
			array( $this, 'render_admin_page' ),
			'dashicons-chart-bar',
			4
		);
	}

	/**
	 * Rendert die Admin-Seite „Statistiken“.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$days   = $this->get_period();
		$totals = $this->read_aggregates( $days );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( 'Besucherstatistik' ) . '</h1>';
		echo '<form method="get" style="margin:0 0 1em;">';
		echo '<input type="hidden" name="page" value="' . esc_attr( self::MENU_SLUG ) . '" />';
		echo '<label for="barmbini-days">' . esc_html( 'Zeitraum:' ) . '</label> ';
		echo '<select name="days" id="barmbini-days" onchange="this.form.submit()">';
		foreach ( self::PERIODS as $d ) {
			$sel = ( $d === $days ) ? ' selected="selected"' : '';
			echo '<option value="' . (int) $d . '"' . $sel . '>' . (int) $d . ' ' . esc_html( 'Tage' ) . '</option>';
		}
		echo '</select>';
		echo '</form>';
		echo $this->render_block( $totals, $days ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML-Output gekapselt.
		echo '</div>';
	}
}
