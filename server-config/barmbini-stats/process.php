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
 *
 * @package Barmbini_Server
 */

$input          = getenv( 'BARMBINI_LOG_INPUT' ) ?: '/var/log/nginx/barmbini_access.log.1';
$stats_dir      = getenv( 'BARMBINI_STATS_DIR' ) ?: '/var/lib/barmbini-stats/stats';
$run_log        = getenv( 'BARMBINI_RUN_LOG' ) ?: '/var/log/barmbini-stats.log';
$retention_days = (int) ( getenv( 'BARMBINI_RETENTION_DAYS' ) ?: 90 );
$top_n          = (int) ( getenv( 'BARMBINI_TOP_N' ) ?: 10 );
$bot_filter     = ( getenv( 'BARMBINI_BOT_FILTER' ) ?: '1' ) === '1';

$internal_hosts = array( 'barmbini.de', 'www.barmbini.de', 'barmbini.local', 'localhost' );

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
	if ( in_array( strtolower( $host ), $internal_hosts, true ) ) {
		return '';
	}
	return strtolower( $host );
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

// ---------------------------------------------------------------------
if ( ! is_file( $input ) ) {
	barmbini_log( $run_log, "Input nicht gefunden: {$input} (übersprungen)" );
	exit( 0 );
}

$fh = @fopen( $input, 'r' );
if ( ! $fh ) {
	barmbini_log( $run_log, "Kann Input nicht öffnen: {$input}" );
	exit( 1 );
}

$pattern   = '/^(\S+) \S+ \S+ \[([^\]]+)\] "(\S+) ([^"]*)" (\d+) \S+ "([^"]*)" "([^"]*)"$/';
$bot_regex = '/(bot|spider|crawler|slurp|bingpreview|googlebot|bingbot|yandex|baiduspider|duckduckbot|facebookexternalhit|whatsapp|telegrambot|semrush|ahrefs|petalbot|dotbot|uptimerobot|pingdom|gptbot|chatgpt|ccbot|claudebot|amazonbot)/i';

$views        = 0;
$bots         = 0;
$unique_ips   = array();
$devices      = array( 'mobile' => 0, 'tablet' => 0, 'desktop' => 0 );
$pages        = array();
$referrers    = array();
$log_date     = '';

while ( ( $line = fgets( $fh ) ) !== false ) {
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
	if ( $bot_filter && preg_match( $bot_regex, $ua ) ) {
		$bots++;
		continue;
	}

	$views++;
	$unique_ips[ $ip ] = true;
	$devices[ barmbini_classify_device( $ua ) ]++;
	$pages[ $uri ]     = isset( $pages[ $uri ] ) ? $pages[ $uri ] + 1 : 1;

	$dom = barmbini_referrer_domain( $referer, $internal_hosts );
	if ( '' !== $dom ) {
		$referrers[ $dom ] = isset( $referrers[ $dom ] ) ? $referrers[ $dom ] + 1 : 1;
	}
}
fclose( $fh );

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
);
if ( $bot_filter ) {
	$data['bots'] = $bots;
}

if ( ! is_dir( $stats_dir ) ) {
	@mkdir( $stats_dir, 0750, true );
}
$out  = rtrim( $stats_dir, '/' ) . '/stats-' . $log_date . '.json';
$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

if ( false === file_put_contents( $out, $json . PHP_EOL ) ) {
	barmbini_log( $run_log, "Kann {$out} nicht schreiben" );
	exit( 1 );
}

barmbini_cleanup_old( $stats_dir, $retention_days );
barmbini_log( $run_log, "OK: {$log_date} views={$views} uniques=" . count( $unique_ips ) . " -> {$out}" );
echo "OK: {$log_date} views={$views} uniques=" . count( $unique_ips ) . PHP_EOL;
