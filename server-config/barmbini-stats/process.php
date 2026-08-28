#!/usr/bin/env php
<?php
/**
 * Barmbini Besucherstatistik – verarbeitet das rotierte nginx-Zugriffslog.
 *
 * Liest das von logrotate erzeugte Zugriffslog des Vortags (Standard:
 * /var/log/nginx/barmbini_access.log.1), filtert die HTML-Seitenaufrufe,
 * anonymisiert und schreibt ausschließlich aggregierte Werte als JSON pro Tag.
 *
 * Es werden NIEMALS IP-Adressen oder vollständige Referrer-URLs gespeichert.
 * Es wird kein Cookie gesetzt, keine externen Dienste aufgerufen.
 *
 * Ausgabe:  /var/lib/barmbini-stats/stats/stats-YYYY-MM-DD.json
 * Lauf-Log: /var/log/barmbini-stats.log
 *
 * Konfiguration über Umgebungsvariablen:
 *   BARMBINI_LOG_INPUT        Eingabedatei (Standard: /var/log/nginx/barmbini_access.log.1)
 *   BARMBINI_STATS_DIR        Zielverzeichnis (Standard: /var/lib/barmbini-stats/stats)
 *   BARMBINI_RUN_LOG          Lauf-Log (Standard: /var/log/barmbini-stats.log)
 *   BARMBINI_RETENTION_DAYS   Aufbewahrung der Aggregate (Standard: 90)
 *   BARMBINI_TOP_N            Länge der Top-Listen (Standard: 10)
 *   BARMBINI_BOT_FILTER       1 = Bot-Filter aktiv (Standard: 1)
 *   BARMBINI_EXCLUDED_IPS_FILE  Ausschlussliste (Standard: /var/lib/barmbini-stats/excluded-ips.conf)
 *
 * @package Barmbini_Server
 */

$input          = getenv( 'BARMBINI_LOG_INPUT' ) ?: '/var/log/nginx/barmbini_access.log.1';
$stats_dir      = getenv( 'BARMBINI_STATS_DIR' ) ?: '/var/lib/barmbini-stats/stats';
$run_log        = getenv( 'BARMBINI_RUN_LOG' ) ?: '/var/log/barmbini-stats.log';
$retention_days = (int) ( getenv( 'BARMBINI_RETENTION_DAYS' ) ?: 90 );
$top_n          = (int) ( getenv( 'BARMBINI_TOP_N' ) ?: 10 );
$bot_filter     = ( getenv( 'BARMBINI_BOT_FILTER' ) ?: '1' ) === '1';
$excluded_ips_file = getenv( 'BARMBINI_EXCLUDED_IPS_FILE' ) ?: '/var/lib/barmbini-stats/excluded-ips.conf';

$internal_hosts = array(
	'barmbini.de', 'www.barmbini.de', 'barmbini.local', 'localhost',
	// Eigene Server-IP und deren PTR-Name (IONOS): Zugriffe/Scanner ueber die IP
	// sollen nicht als externe Referrer erscheinen.
	'217.160.74.128', 'ip217-160-74-128.pbiaas.com',
);

function barmbini_log( $run_log, $msg ) {
	file_put_contents( $run_log, '[' . date( 'Y-m-d H:i:s' ) . '] ' . $msg . PHP_EOL, FILE_APPEND );
}

function barmbini_parse_date( $time ) {
	// Format: [19/Aug/2026:12:00:00 +0200]
	$d = DateTime::createFromFormat( 'd/M/Y:H:i:s O', $time );
	return $d ? $d->format( 'Y-m-d' ) : '';
}

function barmbini_is_excluded( $uri ) {
	$path = parse_url( $uri, PHP_URL_PATH );
	if ( null === $path || '' === $path ) {
		$path = $uri;
	}
	// Statische Ressourcen und Punkt-Endungen.
	if ( preg_match( '/\.(png|jpe?g|gif|webp|svg|css|js|woff2?|ttf|eot|ico|map|webmanifest|txt)$/i', $path ) ) {
		return true;
	}
	$excluded = array(
		'/wp-admin',
		'/wp-login.php',
		'/wp-json/',
		'/wp-cron.php',
		'/xmlrpc.php',
		'/wp-includes/',
		'/wp-content/',
		'/feed',
		'/sitemap',
		'/robots.txt',
		'/favicon.ico',
	);
	foreach ( $excluded as $prefix ) {
		if ( 0 === strpos( $path, $prefix ) ) {
			return true;
		}
	}
	return false;
}

function barmbini_classify_device( $ua ) {
	$ua = strtolower( $ua );
	// Tablets: iPad oder Android mit Tablet-Kennung bzw. Samsung-SM-T-Reihe.
	if ( false !== strpos( $ua, 'ipad' ) || ( false !== strpos( $ua, 'android' ) && ( false !== strpos( $ua, 'tablet' ) || false !== strpos( $ua, 'sm-t' ) ) ) ) {
		return 'tablet';
	}
	if ( false !== strpos( $ua, 'mobile' ) || false !== strpos( $ua, 'iphone' ) || false !== strpos( $ua, 'android' ) ) {
		return 'mobile';
	}
	return 'desktop';
}

function barmbini_referrer_domain( $referer, $internal_hosts ) {
	if ( '' === $referer || '-' === $referer ) {
		return '';
	}
	$host = parse_url( $referer, PHP_URL_HOST );
	if ( null === $host || '' === $host ) {
		return '';
	}
	$host = preg_replace( '/^www\./i', '', $host );
	$host_lower = strtolower( $host );
	// Eigene Hosts (Domain, IP, PTR-Name) und die IONOS-Infrastruktur (pbiaas.com) ausfiltern.
	if ( in_array( $host_lower, $internal_hosts, true ) || false !== strpos( $host_lower, '.pbiaas.com' ) ) {
		return '';
	}
	return $host_lower;
}

/**
 * Erkennt Zugriffe, die ueber die eigene Server-IP bzw. deren PTR-Name kommen.
 * Solche Zugriffe stammen praktisch immer von Scannern/Internetmessungen
 * (kein Besucher surft eine Website ueber die IP-Adresse).
 *
 * @param string $referer Referrer-Header.
 * @return bool
 */
function barmbini_is_ip_referrer( $referer ) {
	if ( '' === $referer || '-' === $referer ) {
		return false;
	}
	$host = parse_url( $referer, PHP_URL_HOST );
	if ( null === $host || '' === $host ) {
		return false;
	}
	$host_lower = strtolower( $host );

	return in_array( $host_lower, array( '217.160.74.128', 'ip217-160-74-128.pbiaas.com' ), true )
		|| false !== strpos( $host_lower, '.pbiaas.com' );
}

function barmbini_cleanup_old( $stats_dir, $days ) {
	$cutoff = date( 'Y-m-d', time() - $days * 86400 );
	$files  = glob( rtrim( $stats_dir, '/' ) . '/stats-*.json' );
	foreach ( (array) $files as $f ) {
		if ( preg_match( '/stats-(\d{4}-\d{2}-\d{2})\.json$/', $f, $m ) && strcmp( $m[1], $cutoff ) < 0 ) {
			@unlink( $f );
		}
	}
}

/**
 * Lädt die Ausschlussliste (eine IP oder ein CIDR pro Zeile, # = Kommentar).
 *
 * @param string $file Pfad zur Konfigurationsdatei.
 * @return string[] Bereinigte Einträge.
 */
function barmbini_load_excluded_ips( $file ) {
	if ( ! is_file( $file ) ) {
		return array();
	}
	$lines = @file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
	if ( ! is_array( $lines ) ) {
		return array();
	}
	$entries = array();
	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( '' === $line || 0 === strpos( $line, '#' ) ) {
			continue;
		}
		$line = trim( preg_replace( '/#.*$/', '', $line ) );
		if ( '' !== $line ) {
			$entries[] = $line;
		}
	}
	return array_values( array_unique( $entries ) );
}

/**
 * Normalisiert eine IP (z. B. ::ffff:1.2.3.4 → 1.2.3.4).
 *
 * @param string $ip IP-Adresse.
 * @return string
 */
function barmbini_normalize_ip( $ip ) {
	$ip = trim( $ip );
	if ( 0 === stripos( $ip, '::ffff:' ) ) {
		$ip = substr( $ip, 7 );
	}
	return $ip;
}

/**
 * Prüft, ob eine IP in der Liste enthalten ist (exakt oder per CIDR).
 *
 * @param string   $ip      Zu prüfende IP.
 * @param string[] $entries Ausschlussliste.
 * @return bool
 */
function barmbini_ip_matches( $ip, $entries ) {
	$ip = barmbini_normalize_ip( $ip );
	if ( '' === $ip ) {
		return false;
	}
	foreach ( $entries as $entry ) {
		if ( false === strpos( $entry, '/' ) ) {
			if ( 0 === strcasecmp( $ip, barmbini_normalize_ip( $entry ) ) ) {
				return true;
			}
			continue;
		}
		if ( barmbini_cidr_match( $ip, $entry ) ) {
			return true;
		}
	}
	return false;
}

/**
 * CIDR-Abgleich (IPv4 und IPv6) über inet_pton + Bitmaske.
 *
 * @param string $ip   Zu prüfende IP.
 * @param string $cidr Netzwerk in CIDR-Notation (z. B. 192.168.0.0/16).
 * @return bool
 */
function barmbini_cidr_match( $ip, $cidr ) {
	$parts = explode( '/', $cidr, 2 );
	if ( 2 !== count( $parts ) || '' === trim( $parts[0] ) || ! ctype_digit( trim( $parts[1] ) ) ) {
		return false;
	}
	$net  = barmbini_normalize_ip( trim( $parts[0] ) );
	$bits = (int) trim( $parts[1] );

	$ip_bin  = @inet_pton( $ip );
	$net_bin = @inet_pton( $net );
	if ( false === $ip_bin || false === $net_bin || strlen( $ip_bin ) !== strlen( $net_bin ) ) {
		return false;
	}
	$max_bits = strlen( $net_bin ) * 8;
	if ( $bits < 0 || $bits > $max_bits ) {
		return false;
	}
	if ( 0 === $bits ) {
		return true;
	}

	$mask_len = (int) floor( $bits / 8 );
	$mask_rem = $bits % 8;
	if ( $mask_len > 0 && substr( $ip_bin, 0, $mask_len ) !== substr( $net_bin, 0, $mask_len ) ) {
		return false;
	}
	if ( $mask_rem > 0 ) {
		$mask = ( 0xFF << ( 8 - $mask_rem ) ) & 0xFF;
		if ( ( ord( $ip_bin[ $mask_len ] ) & $mask ) !== ( ord( $net_bin[ $mask_len ] ) & $mask ) ) {
			return false;
		}
	}
	return true;
}

// ---------------------------------------------------------------------
if ( ! is_file( $input ) ) {
	barmbini_log( $run_log, "Input nicht gefunden: {$input} (übersprungen)" );
	exit( 0 );
}

$is_gz = ( '.gz' === substr( $input, -3 ) );
if ( $is_gz && ! function_exists( 'gzopen' ) ) {
	barmbini_log( $run_log, "zlib-Erweiterung fehlt für {$input}" );
	exit( 1 );
}
$fh = $is_gz ? @gzopen( $input, 'r' ) : @fopen( $input, 'r' );
if ( ! $fh ) {
	barmbini_log( $run_log, "Kann Input nicht öffnen: {$input}" );
	exit( 1 );
}

$pattern   = '/^(\S+) \S+ \S+ \[([^\]]+)\] "(\S+) ([^"]*)" (\d+) \S+ "([^"]*)" "([^"]*)"$/';
$bot_regex = '/(bot|spider|crawler|slurp|bingpreview|googlebot|bingbot|yandex|baiduspider|duckduckbot|facebookexternalhit|whatsapp|telegrambot|semrush|ahrefs|petalbot|dotbot|uptimerobot|pingdom|gptbot|chatgpt|ccbot|claudebot|amazonbot|cyberconvoy|scout\/|modat|internetmeasurement|censys|zgrab)/i';

$views        = 0;
$bots         = 0;
$excluded_ip_hits = 0;
$unique_ips   = array();
$devices      = array( 'mobile' => 0, 'tablet' => 0, 'desktop' => 0 );
$pages        = array();
$referrers    = array();
$log_date     = '';

$excluded_ips = barmbini_load_excluded_ips( $excluded_ips_file );

$readline = $is_gz ? 'gzgets' : 'fgets';

while ( ( $line = $readline( $fh ) ) !== false ) {
	if ( ! preg_match( $pattern, trim( $line ), $m ) ) {
		continue;
	}
	$ip      = $m[1];
	$time    = $m[2];
	$method  = $m[3];
	$uri     = explode( ' ', $m[4], 2 )[0];
	$status  = (int) $m[5];
	$referer = $m[6];
	$ua      = $m[7];

	if ( '' === $log_date ) {
		$log_date = barmbini_parse_date( $time );
	}
	if ( 'GET' !== $method || 200 !== $status || barmbini_is_excluded( $uri ) ) {
		continue;
	}
	// Konfigurierter IP-Ausschluss (z. B. eigene Büro-/Test-IPs).
	if ( ! empty( $excluded_ips ) && barmbini_ip_matches( $ip, $excluded_ips ) ) {
		$excluded_ip_hits++;
		continue;
	}
	if ( $bot_filter && preg_match( $bot_regex, $ua ) ) {
		$bots++;
		continue;
	}
	// Zugriffe ueber die eigene Server-IP / deren PTR sind Scanner → ausschliessen.
	if ( barmbini_is_ip_referrer( $referer ) ) {
		$bots++;
		continue;
	}

	$views++;
	$unique_ips[ $ip ] = true;
	$devices[ barmbini_classify_device( $ua ) ]++;

	// Pfad normalisieren (ohne Query-String), damit /sortiment/?page=2
	// und /sortiment/ als dieselbe Seite zählen.
	$page = parse_url( $uri, PHP_URL_PATH );
	if ( null === $page || '' === $page ) {
		$page = '/';
	}
	$pages[ $page ] = isset( $pages[ $page ] ) ? $pages[ $page ] + 1 : 1;

	$dom = barmbini_referrer_domain( $referer, $internal_hosts );
	if ( '' !== $dom ) {
		$referrers[ $dom ] = isset( $referrers[ $dom ] ) ? $referrers[ $dom ] + 1 : 1;
	}
}
if ( $is_gz ) {
	gzclose( $fh );
} else {
	fclose( $fh );
}

if ( '' === $log_date ) {
	$log_date = date( 'Y-m-d' );
}

arsort( $pages );
arsort( $referrers );

$top_pages = array();
foreach ( array_slice( $pages, 0, $top_n, true ) as $p => $c ) {
	$top_pages[] = array( 'path' => $p, 'views' => $c );
}
$top_referrers = array();
foreach ( array_slice( $referrers, 0, $top_n, true ) as $d => $c ) {
	$top_referrers[] = array( 'domain' => $d, 'views' => $c );
}

$data = array(
	'date'            => $log_date,
	'views'           => $views,
	'unique_visitors' => count( $unique_ips ),
	'devices'         => $devices,
	'top_pages'       => $top_pages,
	'top_referrers'   => $top_referrers,
	// Vollständige Tages-Zähler (assoziativ), damit die Plugin-Aggregation
	// über mehrere Tage exakt ist (Top-N allein wäre verlustbehaftet).
	'pages'           => $pages,
	'referrers'       => $referrers,
	'excluded_ip_hits' => $excluded_ip_hits,
);
if ( $bot_filter ) {
	$data['bots'] = $bots;
}

if ( ! is_dir( $stats_dir ) ) {
	// 0755: WordPress (www-data) muss die Aggregate fuer die Anzeige lesen koennen.
	@mkdir( $stats_dir, 0755, true );
}
$out  = rtrim( $stats_dir, '/' ) . '/stats-' . $log_date . '.json';
$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

// Atomar schreiben (temp + rename), damit parallele Läufe (Cron + Recalc)
// niemals eine halb geschriebene JSON-Datei hinterlassen.
$tmp = $out . '.tmp.' . getmypid();
if ( false === file_put_contents( $tmp, $json . PHP_EOL ) ) {
	barmbini_log( $run_log, "Kann {$out} nicht schreiben" );
	exit( 1 );
}
// 0644: Dateien fuer www-data (WordPress-Anzeige) lesbar halten.
@chmod( $tmp, 0644 );
if ( ! @rename( $tmp, $out ) ) {
	@unlink( $tmp );
	barmbini_log( $run_log, "Kann {$out} nicht ersetzen" );
	exit( 1 );
}

barmbini_cleanup_old( $stats_dir, $retention_days );
barmbini_log( $run_log, "OK: {$log_date} views={$views} uniques=" . count( $unique_ips ) . " excluded={$excluded_ip_hits} -> {$out}" );
echo "OK: {$log_date} views={$views} uniques=" . count( $unique_ips ) . " excluded={$excluded_ip_hits}" . PHP_EOL;
