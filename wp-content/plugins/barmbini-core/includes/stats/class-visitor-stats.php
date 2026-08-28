<?php
/**
 * Barmbini Core – Besucherstatistik (nginx-Logs, Option B)
 *
 * Zeigt die anonymisierten Aggregate der Server-Verarbeitung an
 * (`/var/lib/barmbini-stats/stats/stats-*.json`). Sichtbar nur für
 * Benutzer mit der Capability `barmbini_view_stats` (Administrator +
 * Shop Manager).
 *
 * - Admin-Seite „Statistiken" (Zeitraum 7/30/90 Tage)
 * - Shortcode `[barmbini_visitor_stats]` für die Frontend-Anzeige
 *   (rendert nur für Admin/Shop Manager, sonst leerer String)
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
	const DEFAULT_EXCLUDED_IPS_FILE = '/var/lib/barmbini-stats/excluded-ips.conf';
	const AGG_CACHE_VERSION = '2';

	/**
	 * Registriert die Hooks des Statistik-Moduls.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'ensure_capabilities' ) );
		add_action( 'admin_init', array( $this, 'handle_excluded_ips_save' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_styles' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
	}

	/**
	 * Vergibt die Statistik-Capability idempotent an Administrator + Shop Manager.
	 *
	 * @return void
	 */
	public function ensure_capabilities() {
		foreach ( array( 'administrator', 'shop_manager' ) as $slug ) {
			$role = get_role( $slug );
			if ( $role && ! $role->has_cap( self::CAP ) ) {
				$role->add_cap( self::CAP );
			}
		}
	}

	/**
	 * Lädt das Statistik-CSS im Admin (nur auf der Statistik-Seite).
	 *
	 * @param string $hook_suffix Aktueller Admin-Seiten-Hook.
	 * @return void
	 */
	public function enqueue_admin_styles( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, self::MENU_SLUG ) ) {
			return;
		}
		$this->enqueue_style();
	}

	/**
	 * Lädt das Statistik-CSS im Frontend (nur für berechtigte Rollen).
	 *
	 * @return void
	 */
	public function enqueue_frontend_styles() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$this->enqueue_style();
	}

	/**
	 * Lädt das ausgelagerte Stylesheet `assets/css/visitor-stats.css`.
	 *
	 * @return void
	 */
	protected function enqueue_style() {
		$version = defined( 'BARMBINI_CORE_VERSION' ) ? BARMBINI_CORE_VERSION : false;
		wp_enqueue_style(
			'barmbini-visitor-stats',
			BARMBINI_CORE_URL . 'assets/css/visitor-stats.css',
			array(),
			$version
		);
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
	 * Prüft den Zustand des Statistik-Verzeichnisses für die Diagnose.
	 *
	 * @return string 'missing' | 'unreadable' | 'ok'
	 */
	public function get_stats_dir_status() {
		$dir = trailingslashit( $this->get_stats_dir() );
		if ( ! is_dir( $dir ) ) {
			return 'missing';
		}
		if ( ! is_readable( $dir ) ) {
			return 'unreadable';
		}

		return 'ok';
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
	 * Liefert den Pfad zur Ausschlussliste der IP-Adressen.
	 *
	 * Per Filter `barmbini_stats_excluded_ips_file` überschreibbar (Tests/Local).
	 *
	 * @return string
	 */
	public function get_excluded_ips_file() {
		return apply_filters( 'barmbini_stats_excluded_ips_file', self::DEFAULT_EXCLUDED_IPS_FILE );
	}

	/**
	 * Prüft, ob ein Eintrag eine gültige IP oder ein gültiges CIDR-Netz ist.
	 *
	 * @param string $entry Einzelner Eintrag.
	 * @return bool
	 */
	public function is_valid_ip_or_cidr( $entry ) {
		$entry = trim( $entry );
		if ( false === strpos( $entry, '/' ) ) {
			return (bool) filter_var( $entry, FILTER_VALIDATE_IP );
		}

		$parts = explode( '/', $entry, 2 );
		if ( 2 !== count( $parts ) || ! filter_var( trim( $parts[0] ), FILTER_VALIDATE_IP ) || ! ctype_digit( trim( $parts[1] ) ) ) {
			return false;
		}
		$bits = (int) trim( $parts[1] );
		$max  = ( false !== filter_var( trim( $parts[0] ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) ? 128 : 32;

		return $bits >= 0 && $bits <= $max;
	}

	/**
	 * Bereinigt und validiert die Ausschlussliste (eine IP/CIDR pro Zeile).
	 *
	 * @param string $raw Roheingabe (zeilenweise).
	 * @return string[] Gültige, deduplizierte Einträge.
	 */
	public function sanitize_excluded_ips( $raw ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $raw );
		$out   = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}
			$line = trim( preg_replace( '/#.*$/', '', $line ) );
			if ( '' === $line || ! $this->is_valid_ip_or_cidr( $line ) ) {
				continue;
			}
			$out[] = $line;
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Liest die aktuell konfigurierten ausgeschlossenen IP-Adressen.
	 *
	 * @return string[]
	 */
	public function get_excluded_ips() {
		$file = $this->get_excluded_ips_file();
		if ( ! is_file( $file ) ) {
			return array();
		}
		$lines = @file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		if ( ! is_array( $lines ) ) {
			return array();
		}
		$out = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}
			$line = trim( preg_replace( '/#.*$/', '', $line ) );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}
		return $out;
	}

	/**
	 * Schreibt die bereinigte Ausschlussliste in die Konfigurationsdatei.
	 *
	 * @param string $raw Roheingabe (zeilenweise).
	 * @return bool Erfolg.
	 */
	public function save_excluded_ips( $raw ) {
		$file    = $this->get_excluded_ips_file();
		$entries = $this->sanitize_excluded_ips( $raw );
		$content = empty( $entries ) ? '' : implode( PHP_EOL, $entries ) . PHP_EOL;

		if ( is_file( $file ) && ! is_writable( $file ) ) {
			return false;
		}
		if ( ! is_file( $file ) && ! is_writable( dirname( $file ) ) ) {
			return false;
		}

		return false !== @file_put_contents( $file, $content );
	}

	/**
	 * Behandelt das Speichern der Ausschlussliste (nur Administratoren).
	 *
	 * @return void
	 */
	public function handle_excluded_ips_save() {
		if ( ! isset( $_POST['barmbini_stats_ips_save'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! isset( $_POST['barmbini_stats_ips_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['barmbini_stats_ips_nonce'] ) ), 'barmbini_stats_ips' ) ) {
			return;
		}

		$raw   = isset( $_POST['barmbini_stats_ips'] ) ? (string) wp_unslash( $_POST['barmbini_stats_ips'] ) : '';
		$saved = $this->save_excluded_ips( $raw );

		wp_safe_redirect( add_query_arg(
			array( 'page' => self::MENU_SLUG, 'barmbini_ips_saved' => $saved ? '1' : '0' ),
			admin_url( 'admin.php' )
		) );
		exit;
	}

	/**
	 * Rendert das Bearbeitungsformular für die Ausschlussliste.
	 *
	 * @return string
	 */
	public function render_excluded_ips_form() {
		$ips      = $this->get_excluded_ips();
		$file     = $this->get_excluded_ips_file();
		$writable = is_file( $file ) ? is_writable( $file ) : is_writable( dirname( $file ) );
		$saved    = isset( $_GET['barmbini_ips_saved'] ) ? (int) $_GET['barmbini_ips_saved'] : null;
		$count    = count( $ips );
		$mtime    = is_file( $file ) ? @filemtime( $file ) : false;
		$modified = $mtime ? date_i18n( 'd.m.Y H:i', $mtime ) : '—';

		ob_start();
		?>
		<hr />
		<h2><?php echo esc_html( 'Ausgeschlossene IP-Adressen' ); ?></h2>
		<p class="description">
			<?php echo esc_html( 'Eine IP oder ein CIDR-Bereich pro Zeile (z. B. 203.0.113.10 oder 192.168.0.0/16). Diese Adressen werden in der Besucherstatistik nicht gezählt.' ); ?>
		</p>
		<p class="barmbini-stats-ips-status">
			<?php
			echo esc_html(
				sprintf(
					'Aktiv: %1$d Adresse(n) · Datei: %2$s · Zuletzt geändert: %3$s',
					$count,
					$file,
					$modified
				)
			);
			?>
		</p>
		<p class="description">
			<?php echo esc_html( 'Die Liste wird bei der täglichen Server-Auswertung (Cron, 07:15 Uhr) angewendet. Änderungen wirken ab dem nächsten Lauf; bereits ausgewertete Tage werden nicht rückwirkend gefiltert. Treffer ausgeschlossener Adressen erscheinen oben als „Ausgeschlossene IPs (Treffer)“. ' ); ?>
		</p>
		<?php if ( 1 === $saved ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( 'Ausschlussliste gespeichert.' ); ?></p></div>
		<?php elseif ( 0 === $saved ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( 'Konnte nicht speichern (Datei nicht beschreibbar).' ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! $writable ) : ?>
			<div class="notice notice-warning"><p><?php echo esc_html( 'Die Konfigurationsdatei ist für den Webserver nicht beschreibbar: ' . $file ); ?></p></div>
		<?php endif; ?>
		<form method="post">
			<input type="hidden" name="barmbini_stats_ips_nonce" value="<?php echo esc_attr( wp_create_nonce( 'barmbini_stats_ips' ) ); ?>" />
			<textarea name="barmbini_stats_ips" rows="8" cols="50" class="large-text code"<?php echo $writable ? '' : ' disabled="disabled"'; ?>><?php echo esc_textarea( implode( "\n", $ips ) ); ?></textarea>
			<?php
			if ( $writable ) {
				submit_button( 'Speichern', 'primary', 'barmbini_stats_ips_save' );
			}
			?>
		</form>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Liest und aggregiert die Tages-Dateien der letzten N Tage.
	 *
	 * Das Ergebnis wird per Transient (1 Stunde) zwischengespeichert; der
	 * Cache-Schlüssel enthält einen Fingerabdruck aus Dateiname + mtime
	 * sowie eine Cache-Version, damit neue Tagesdateien und Änderungen an
	 * der Aggregations-/Sortierlogik den Cache automatisch entwerten.
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

		// Fingerabdruck aus Pfad + Änderungszeit → invalidiert bei neuen/geänderten Dateien.
		$fingerprint = '';
		foreach ( $files as $file ) {
			$fingerprint .= $file . ':' . (string) @filemtime( $file ) . ';';
		}
		// Cache-Version im Schlüssel: Änderungen an der Aggregations-/Sortierlogik entwerten alte Transients.
		$cache_key = 'barmbini_stats_agg_v' . self::AGG_CACHE_VERSION . '_' . (int) $days . '_' . md5( $fingerprint );

		$cached = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$cutoff = date( 'Y-m-d', time() - $days * 86400 );

		$totals = array(
			'views'           => 0,
			'unique_visitors' => 0,
			'devices'         => array( 'mobile' => 0, 'tablet' => 0, 'desktop' => 0 ),
			'bots'            => 0,
			'excluded_ip_hits' => 0,
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
			$totals['bots']            += isset( $data['bots'] ) ? (int) $data['bots'] : 0;
			$totals['excluded_ip_hits'] += isset( $data['excluded_ip_hits'] ) ? (int) $data['excluded_ip_hits'] : 0;

			foreach ( array( 'mobile', 'tablet', 'desktop' ) as $d ) {
				if ( isset( $data['devices'][ $d ] ) ) {
					$totals['devices'][ $d ] += (int) $data['devices'][ $d ];
				}
			}

			// Exakte Aggregation: vollständige Tages-Zähler bevorzugen, sonst
			// auf die Top-N-Listen zurückfallen (ältere Tages-Dateien).
			if ( isset( $data['pages'] ) && is_array( $data['pages'] ) ) {
				foreach ( $data['pages'] as $path => $views ) {
					$totals['top_pages'][ $path ] = ( isset( $totals['top_pages'][ $path ] ) ? $totals['top_pages'][ $path ] : 0 ) + (int) $views;
				}
			} else {
				foreach ( (array) ( isset( $data['top_pages'] ) ? $data['top_pages'] : array() ) as $p ) {
					if ( isset( $p['path'], $p['views'] ) ) {
						$key = $p['path'];
						$totals['top_pages'][ $key ] = ( isset( $totals['top_pages'][ $key ] ) ? $totals['top_pages'][ $key ] : 0 ) + (int) $p['views'];
					}
				}
			}

			if ( isset( $data['referrers'] ) && is_array( $data['referrers'] ) ) {
				foreach ( $data['referrers'] as $domain => $views ) {
					$totals['top_referrers'][ $domain ] = ( isset( $totals['top_referrers'][ $domain ] ) ? $totals['top_referrers'][ $domain ] : 0 ) + (int) $views;
				}
			} else {
				foreach ( (array) ( isset( $data['top_referrers'] ) ? $data['top_referrers'] : array() ) as $r ) {
					if ( isset( $r['domain'], $r['views'] ) ) {
						$key = $r['domain'];
						$totals['top_referrers'][ $key ] = ( isset( $totals['top_referrers'][ $key ] ) ? $totals['top_referrers'][ $key ] : 0 ) + (int) $r['views'];
					}
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

		krsort( $totals['days'] );

		set_transient( $cache_key, $totals, HOUR_IN_SECONDS );

		return $totals;
	}

	/**
	 * Liefert die durchschnittliche Anzahl eindeutiger Besucher pro Tag.
	 *
	 * Die Tages-Dateien enthalten nur Tages-Uniques (keine Besucher-IDs),
	 * daher ist die Summe über einen Zeitraum keine eindeutige Besucherzahl.
	 * Der Tagesdurchschnitt ist die ehrliche Kennzahl für einen Zeitraum.
	 *
	 * @param array $totals Aggregierte Werte inkl. 'unique_visitors' und 'days'.
	 * @return int
	 */
	public function get_average_daily_visitors( $totals ) {
		$days = ( isset( $totals['days'] ) && is_array( $totals['days'] ) ) ? count( $totals['days'] ) : 0;
		if ( $days < 1 ) {
			return 0;
		}

		return (int) round( (int) $totals['unique_visitors'] / $days );
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
			$notice = '';
			$status = $this->get_stats_dir_status();
			if ( 'missing' === $status ) {
				$notice = ' · Statistik-Verzeichnis fehlt: ' . $this->get_stats_dir();
			} elseif ( 'unreadable' === $status ) {
				$notice = ' · Statistik-Verzeichnis ist für den Webserver nicht lesbar (Berechtigung)';
			}

			return '<p class="barmbini-stats-empty">' . esc_html( 'Für diesen Zeitraum liegen noch keine Daten vor.' . $notice ) . '</p>';
		}

		ob_start();
		?>
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
					<div class="barmbini-stats-label"><?php echo esc_html( 'Besucher/Tag (Ø)' ); ?></div>
					<div class="barmbini-stats-value"><?php echo (int) $this->get_average_daily_visitors( $totals ); ?></div>
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
				<div class="barmbini-stats-kpi">
					<div class="barmbini-stats-label"><?php echo esc_html( 'Bots / Scanner (gefiltert)' ); ?></div>
					<div class="barmbini-stats-value"><?php echo (int) $totals['bots']; ?></div>
				</div>
				<div class="barmbini-stats-kpi">
					<div class="barmbini-stats-label"><?php echo esc_html( 'Ausgeschlossene IPs (Treffer)' ); ?></div>
					<div class="barmbini-stats-value"><?php echo (int) $totals['excluded_ip_hits']; ?></div>
				</div>
			</div>

			<?php echo $this->render_daily_trend( $totals ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML-Output gekapselt. ?>

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
	 * Rendert den Tagesverlauf (Views + Besucher pro Tag) als Tabelle
	 * mit relativen Balken.
	 *
	 * @param array $totals Aggregierte Werte inkl. 'days'.
	 * @return string
	 */
	public function render_daily_trend( $totals ) {
		if ( empty( $totals['days'] ) ) {
			return '';
		}

		$max = 1;
		foreach ( $totals['days'] as $day ) {
			$max = max( $max, (int) $day['views'] );
		}

		ob_start();
		?>
		<h3><?php echo esc_html( 'Tagesverlauf' ); ?></h3>
		<table>
			<thead>
				<tr>
					<th><?php echo esc_html( 'Datum' ); ?></th>
					<th><?php echo esc_html( 'Views' ); ?></th>
					<th><?php echo esc_html( 'Besucher (ca.)' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $totals['days'] as $date => $day ) : ?>
				<?php
				$views   = (int) $day['views'];
				$uniques = (int) $day['unique_visitors'];
				$percent = (int) round( $views / $max * 100 );
				?>
				<tr>
					<td><?php echo esc_html( $date ); ?></td>
					<td>
						<span class="barmbini-stats-bar"><span style="width:<?php echo (int) $percent; ?>%"></span></span>
						<?php echo (int) $views; ?>
					</td>
					<td><?php echo (int) $uniques; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Shortcode-Handler: rendert nur für Admin/Shop Manager.
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

		if ( current_user_can( 'manage_options' ) ) {
			echo $this->render_excluded_ips_form(); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML-Output gekapselt.
		}
		echo '</div>';
	}
}
