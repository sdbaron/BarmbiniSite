<?php
/**
 * Lokale URL-Umschreibung nach DB-Import: barmbini.de -> barmbini.local
 * Serialize-safe (unserialize/serialize) fuer Optionen, Posts und Metadaten.
 *
 * Wird von fetch.ps1 als Fallback genutzt, wenn WP-CLI nicht verfuegbar ist.
 *
 * Konfiguration ueber Umgebungsvariablen (Defaults = Local by Flywheel):
 *   BARMBINI_LOCAL_DB_HOST   Standard 127.0.0.1
 *   BARMBINI_LOCAL_DB_PORT   Standard 10006
 *   BARMBINI_LOCAL_DB_USER   Standard root
 *   BARMBINI_LOCAL_DB_PASS   Standard root
 *   BARMBINI_LOCAL_DB_NAME   Standard local
 *   BARMBINI_SITE_DOMAIN     Standard barmbini.de
 *   BARMBINI_LOCAL_DOMAIN    Standard barmbini.local
 */

$host = getenv( 'BARMBINI_LOCAL_DB_HOST' ) ?: '127.0.0.1';
$port = (int) ( getenv( 'BARMBINI_LOCAL_DB_PORT' ) ?: 10006 );
$user = getenv( 'BARMBINI_LOCAL_DB_USER' ) ?: 'root';
$pass = getenv( 'BARMBINI_LOCAL_DB_PASS' ) ?: 'root';
$db   = getenv( 'BARMBINI_LOCAL_DB_NAME' ) ?: 'local';
$domain = getenv( 'BARMBINI_SITE_DOMAIN' ) ?: 'barmbini.de';
$local  = getenv( 'BARMBINI_LOCAL_DOMAIN' ) ?: 'barmbini.local';

$from = array( "https://$domain", "http://$domain", "//$domain" );
$to   = array( "https://$local", "http://$local", "//$local" );

$mysqli = new mysqli( $host, $user, $pass, $db, $port );
if ( $mysqli->connect_error ) {
	fwrite( STDERR, "Connect-Fehler: " . $mysqli->connect_error . "\n" );
	exit( 1 );
}
$mysqli->set_charset( 'utf8mb4' );

function barmbini_is_serialized( $data ) {
	if ( ! is_string( $data ) ) {
		return false;
	}
	$data = trim( $data );
	if ( 'N;' === $data ) {
		return true;
	}
	if ( strlen( $data ) < 4 ) {
		return false;
	}
	if ( ':' !== $data[1] ) {
		return false;
	}
	$last = substr( $data, -1 );
	if ( ';' !== $last && '}' !== $last ) {
		return false;
	}
	return false !== @unserialize( $data );
}

function barmbini_replace_recursive( $value, $from, $to ) {
	if ( is_string( $value ) ) {
		if ( barmbini_is_serialized( $value ) ) {
			$data = @unserialize( $value );
			if ( false !== $data ) {
				return serialize( barmbini_replace_recursive( $data, $from, $to ) );
			}
		}
		return str_replace( $from, $to, $value );
	}
	if ( is_array( $value ) ) {
		foreach ( $value as $k => $v ) {
			$value[ $k ] = barmbini_replace_recursive( $v, $from, $to );
		}
		return $value;
	}
	if ( is_object( $value ) ) {
		foreach ( get_object_vars( $value ) as $k => $v ) {
			$value->{$k} = barmbini_replace_recursive( $v, $from, $to );
		}
		return $value;
	}
	return $value;
}

// Tabelle => [ Primärschlüssel, [ Spalten ] ]
$tables = array(
	'wp_options'  => array( 'option_id', array( 'option_value' ) ),
	'wp_posts'    => array( 'ID', array( 'post_content', 'post_excerpt', 'post_title' ) ),
	'wp_postmeta' => array( 'meta_id', array( 'meta_value' ) ),
);

$changed_total = 0;

foreach ( $tables as $table => $spec ) {
	list( $pk, $cols ) = $spec;
	$col_list = implode( ', ', $cols );
	$res = $mysqli->query( "SELECT `$pk`, $col_list FROM `$table`" );
	if ( ! $res ) {
		fwrite( STDERR, "SELECT-Fehler $table: " . $mysqli->error . "\n" );
		continue;
	}
	$updates = array();
	while ( $row = $res->fetch_assoc() ) {
		$id = $row[ $pk ];
		$any_changed = false;
		$sets = array();
		foreach ( $cols as $col ) {
			$old = $row[ $col ];
			if ( null === $old ) {
				continue;
			}
			$new = barmbini_replace_recursive( $old, $from, $to );
			if ( $new !== $old ) {
				$sets[] = "`$col` = '" . $mysqli->real_escape_string( $new ) . "'";
				$any_changed = true;
			}
		}
		if ( $any_changed ) {
			$sql = "UPDATE `$table` SET " . implode( ', ', $sets ) . " WHERE `$pk` = " . (int) $id;
			if ( ! $mysqli->query( $sql ) ) {
				fwrite( STDERR, "UPDATE-Fehler $table#$id: " . $mysqli->error . "\n" );
			} else {
				$changed_total++;
			}
		}
	}
	$res->free();
	echo "Tabelle $table: verarbeitet\n";
}

echo "Geaenderte Zeilen gesamt: $changed_total\n";
echo "FERTIG\n";
