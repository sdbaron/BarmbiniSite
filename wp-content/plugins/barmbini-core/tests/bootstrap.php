<?php
/**
 * PHPUnit Bootstrap – Mockt WordPress-Kernfunktionen,
 * damit die barmbini-core Tests ohne laufende WP-Installation
 * ausgefuehrt werden koennen.
 *
 * @package Barmbini_Core
 */

// ABSPATH – wird von allen Plugin-Dateien auf Existenz geprueft
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 4 ) . '/' );
}

// Plugin-Konstanten
define( 'BARMBINI_CORE_VERSION', '0.1.0' );
define( 'BARMBINI_CORE_FILE', dirname( __DIR__ ) . '/barmbini-core.php' );
define( 'BARMBINI_CORE_PATH', dirname( __DIR__ ) . '/' );
define( 'BARMBINI_CORE_URL', 'http://barmbini.local/wp-content/plugins/barmbini-core/' );

// =====================================================================
// WordPress-Funktions-Mocks
// =====================================================================

/**
 * Globaler Options-Speicher fuer get_option() / update_option().
 */
$GLOBALS['__wp_options'] = array();

/**
 * Gespeicherte Shortcodes: [ tag => callback ]
 */
$GLOBALS['__wp_shortcodes'] = array();

/**
 * Registrierte Actions und Filter.
 */
$GLOBALS['__wp_actions']  = array();
$GLOBALS['__wp_filters']  = array();

/**
 * Registrierte Widgets.
 */
$GLOBALS['__wp_widgets'] = array();

// -------------------------------------------------------------------
// Options-API
// -------------------------------------------------------------------

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $key, $default = array() ) {
		return $GLOBALS['__wp_options'][ $key ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $key, $value ) {
		$GLOBALS['__wp_options'][ $key ] = $value;
		return true;
	}
}

// -------------------------------------------------------------------
// Shortcode-API
// -------------------------------------------------------------------

if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['__wp_shortcodes'][ $tag ] = $callback;
	}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	/**
	 * Simuliert das echte shortcode_atts() Verhalten:
	 *   - Nur Schluessel aus $pairs kommen ins Ergebnis
	 *   - Unbekannte Schluessel werden verworfen!
	 */
	function shortcode_atts( $pairs, $atts ) {
		$atts   = (array) $atts;
		$result = array();

		foreach ( $pairs as $key => $default ) {
			if ( array_key_exists( $key, $atts ) ) {
				$result[ $key ] = $atts[ $key ];
			} else {
				$result[ $key ] = $default;
			}
		}

		return $result;
	}
}

if ( ! function_exists( 'do_shortcode' ) ) {
	function do_shortcode( $content ) {
		// Minimal-Implementierung: durchlaeuft registrierte Shortcodes
		foreach ( $GLOBALS['__wp_shortcodes'] as $tag => $callback ) {
			$pattern = '/\[' . preg_quote( $tag, '/' ) . '(?:\s+([^\]]*))?\]/';
			if ( preg_match( $pattern, $content, $m ) ) {
				$atts = array();
				if ( ! empty( $m[1] ) ) {
					$atts = shortcode_parse_atts( $m[1] );
				}
				$content = preg_replace( $pattern, call_user_func( $callback, $atts, '' ), $content, 1 );
			}
		}
		return $content;
	}
}

if ( ! function_exists( 'shortcode_parse_atts' ) ) {
	function shortcode_parse_atts( $text ) {
		$atts   = array();
		$text   = trim( $text );
		$actual = preg_match_all(
			'/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|(\S+)(?:\s|$)/',
			$text,
			$matches,
			PREG_SET_ORDER
		);
		foreach ( $matches as $m ) {
			if ( ! empty( $m[1] ) ) {
				$atts[ $m[1] ] = $m[2];
			} elseif ( ! empty( $m[3] ) ) {
				$atts[ $m[3] ] = $m[4];
			} elseif ( ! empty( $m[5] ) ) {
				$atts[ $m[5] ] = $m[6];
			}
		}
		return $atts;
	}
}

// -------------------------------------------------------------------
// Escaping / Sanitizing
// -------------------------------------------------------------------

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Simuliert WordPress sanitize_text_field():
	 *   - Entfernt HTML-Tags
	 *   - Entfernt Null-Bytes
	 *   - Trimmt Whitespace
	 */
	function sanitize_text_field( $text ) {
		$text = (string) $text;
		$text = str_replace( "\0", '', $text );
		$text = strip_tags( $text );
		return trim( $text );
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	/**
	 * Simuliert WordPress wp_kses():
	 *   - Erlaubt nur die in $allowed_html definierten Tags
	 *   - Bei leerem Array werden alle HTML-Tags entfernt
	 *
	 * @param string $string       Eingabetext.
	 * @param array  $allowed_html Erlaubte HTML-Tags (wp_kses-Format).
	 * @param array  $protocols    Erlaubte Protokolle (ungenutzt im Mock).
	 * @return string Bereinigter Text.
	 */
	function wp_kses( $string, $allowed_html = array(), $protocols = array() ) {
		$string = (string) $string;
		if ( empty( $allowed_html ) ) {
			// Keine Tags erlaubt → alle strippen
			return trim( strip_tags( $string ) );
		}
		// Vereinfacht: wenn Tags erlaubt sind, nur strip_tags mit erlaubten Tags
		$allowed_tags = array();
		foreach ( $allowed_html as $tag => $attrs ) {
			$allowed_tags[] = $tag;
		}
		return trim( strip_tags( $string, '<' . implode( '><', $allowed_tags ) . '>' ) );
	}
}

// -------------------------------------------------------------------
// I18n
// -------------------------------------------------------------------

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = '' ) {
		return $text;
	}
}

// -------------------------------------------------------------------
// Hooks (minimal)
// -------------------------------------------------------------------

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['__wp_actions'][ $hook ][] = compact( 'callback', 'priority', 'args' );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		if ( ! isset( $GLOBALS['__wp_filters'][ $hook ] ) ) {
			return $value;
		}
		foreach ( $GLOBALS['__wp_filters'][ $hook ] as $filter ) {
			$value = call_user_func( $filter['callback'], $value );
		}
		return $value;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $args = 1 ) {
		$GLOBALS['__wp_filters'][ $hook ][] = compact( 'callback', 'priority', 'args' );
	}
}

// -------------------------------------------------------------------
// Sonstiges
// -------------------------------------------------------------------

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults ) {
		if ( is_object( $args ) ) {
			$args = get_object_vars( $args );
		}
		if ( ! is_array( $defaults ) ) {
			return $args;
		}
		return array_merge( $defaults, (array) $args );
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return trailingslashit( dirname( $file ) );
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		return 'http://barmbini.local/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $path ) {
		return rtrim( $path, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( $file, $callback ) {}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( $file, $callback ) {}
}

if ( ! function_exists( 'register_widget' ) ) {
	function register_widget( $class ) {
		$GLOBALS['__wp_widgets'][] = $class;
	}
}

// -------------------------------------------------------------------
// WP_Widget Basisklasse (Mock)
// -------------------------------------------------------------------

if ( ! class_exists( 'WP_Widget' ) ) {
	class WP_Widget {
		protected $id_base;
		protected $name;
		protected $widget_options;

		public function __construct( $id_base, $name, $options = array() ) {
			$this->id_base         = $id_base;
			$this->name            = $name;
			$this->widget_options  = $options;
		}

		public function get_field_id( $id ) {
			return 'widget-' . $this->id_base . '-' . $id;
		}

		public function get_field_name( $id ) {
			return 'widget-' . $this->id_base . '[' . $id . ']';
		}
	}
}

// =====================================================================
// Test-Helfer
// =====================================================================

/**
 * Setzt den globalen Options-Speicher zurueck.
 */
function _test_reset_options() {
	$GLOBALS['__wp_options'] = array();
}

/**
 * Setzt die gespeicherten Shortcodes zurueck.
 */
function _test_reset_shortcodes() {
	$GLOBALS['__wp_shortcodes'] = array();
}

/**
 * Setzt alles zurueck.
 */
function _test_reset_all() {
	_test_reset_options();
	_test_reset_shortcodes();
	$GLOBALS['__wp_actions']  = array();
	$GLOBALS['__wp_filters']  = array();
	$GLOBALS['__wp_widgets']  = array();
}
