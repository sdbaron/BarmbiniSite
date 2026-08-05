<?php
/**
 * Barmbini Core – Shortcode für Top-Produktkategorien
 *
 * Stellt alle Top-Level-Produktkategorien als gruppiertes Grid dar.
 * Jede Kategorie wird mit ihren Kindkategorien in einer Sektion
 * mit Überschrift und Trenner gerendert.
 *
 * Verwendung: [barmbini_top_product_categories]
 *
 * @package Barmbini_Core
 * @since 0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Top_Product_Categories_Shortcode {

	/**
	 * Registriert den Shortcode.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'barmbini_top_product_categories', array( $this, 'render' ) );
	}

	/**
	 * Rendert die Top-Level-Produktkategorien als gruppiertes Grid.
	 *
	 * Attribute:
	 *   columns    – Anzahl Spalten im Grid (Standard: 4)
	 *   hide_empty – Leere Kategorien ausblenden (Standard: 0)
	 *   exclude    – Komma-separierte IDs auszuschließender Kategorien (Standard: 60)
	 *   move_last  – Komma-separierte Slugs, die ans Ende sortiert werden (Standard: babybedarf)
	 *   parent     – ID der Elternkategorie, 0 = Top-Level (Standard: 0)
	 *   orderby    – Sortierkriterium für get_terms (Standard: menu_order)
	 *   order      – Sortierrichtung ASC oder DESC (Standard: ASC)
	 *
	 * @param array  $atts    Shortcode-Attribute.
	 * @param string $content Eingeschlossener Inhalt (ungenutzt).
	 * @return string HTML der gruppierten Kategorien-Grids.
	 */
	public function render( $atts = array(), $content = '' ) {
		if ( ! function_exists( 'get_terms' ) || ! class_exists( 'WooCommerce' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'columns'    => '4',
				'hide_empty' => '0',
				'exclude'    => '60',
				'move_last'  => 'babybedarf',
				'parent'     => '0',
				'orderby'    => 'menu_order',
				'order'      => 'ASC',
			),
			$atts,
			'barmbini_top_product_categories'
		);

		$exclude    = array_filter( array_map( 'absint', array_map( 'trim', explode( ',', (string) $atts['exclude'] ) ) ) );
		$hide_empty = $this->parse_bool_attr( $atts['hide_empty'] );
		$parent     = absint( $atts['parent'] );

		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => $parent,
				'hide_empty' => $hide_empty,
				'exclude'    => $exclude,
				'orderby'    => sanitize_key( $atts['orderby'] ),
				'order'      => strtoupper( (string) $atts['order'] ) === 'DESC' ? 'DESC' : 'ASC',
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		$terms = $this->move_terms_to_end( $terms, $atts['move_last'] );

		$sections      = array();
		$total_terms   = count( $terms );

		foreach ( $terms as $index => $term ) {
			$children = get_terms(
				array(
					'taxonomy'   => 'product_cat',
					'parent'     => (int) $term->term_id,
					'hide_empty' => $hide_empty,
					'exclude'    => $exclude,
					'orderby'    => sanitize_key( $atts['orderby'] ),
					'order'      => strtoupper( (string) $atts['order'] ) === 'DESC' ? 'DESC' : 'ASC',
				)
			);

			if ( is_wp_error( $children ) ) {
				continue;
			}

			$section_ids = ! empty( $children )
				? wp_list_pluck( $children, 'term_id' )
				: array( (int) $term->term_id );

			$section_content = $this->render_category_grid( $section_ids, $atts['columns'], $hide_empty );

			if ( '' === trim( $section_content ) ) {
				continue;
			}

			$sections[] = $this->render_section(
				$term->name,
				$section_content,
				$index < ( $total_terms - 1 )
			);
		}

		return implode( "\n", array_filter( $sections ) );
	}

	/**
	 * Konvertiert Shortcode-Attribute in echte Booleans.
	 *
	 * @param mixed $value Attributwert.
	 * @return bool
	 */
	private function parse_bool_attr( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$value = strtolower( (string) $value );

		return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * Rendert ein Kategorie-Grid via WooCommerce-Shortcode.
	 *
	 * @param array|int $ids         Kategorie-IDs.
	 * @param int       $columns     Anzahl Spalten.
	 * @param bool      $hide_empty  Leere Kategorien ausblenden.
	 * @return string HTML des Kategorie-Grids.
	 */
	private function render_category_grid( $ids, $columns, $hide_empty ) {
		$ids = array_filter( array_map( 'absint', (array) $ids ) );

		if ( empty( $ids ) ) {
			return '';
		}

		return do_shortcode(
			sprintf(
				'[product_categories ids="%s" columns="%d" hide_empty="%d"]',
				esc_attr( implode( ',', $ids ) ),
				max( 1, absint( $columns ) ),
				$hide_empty ? 1 : 0
			)
		);
	}

	/**
	 * Rendert eine Sektion mit Überschrift und optionalem Trenner.
	 *
	 * @param string $title        Überschrift.
	 * @param string $content      Sektionsinhalt.
	 * @param bool   $show_divider Trennlinie anzeigen.
	 * @return string HTML der Sektion.
	 */
	private function render_section( $title, $content, $show_divider ) {
		if ( '' === trim( $content ) ) {
			return '';
		}

		$section = sprintf(
			'<div class="wp-block-group barmbini-sortiment-section"><h2 class="wp-block-heading">%s</h2>%s</div>',
			esc_html( html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ),
			$content
		);

		if ( $show_divider ) {
			$section .= '<hr class="wp-block-separator has-alpha-channel-opacity" />';
		}

		return $section;
	}

	/**
	 * Verschiebt Terms mit bestimmten Slugs ans Ende der Liste.
	 *
	 * @param array  $terms Term-Objekte.
	 * @param string $slugs Komma-separierte Slugs.
	 * @return array Sortierte Terms.
	 */
	private function move_terms_to_end( $terms, $slugs ) {
		$slugs = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', (string) $slugs ) ) ) );

		if ( empty( $slugs ) || empty( $terms ) ) {
			return $terms;
		}

		$sorted_terms = array();
		$last_terms   = array();

		foreach ( $terms as $term ) {
			if ( in_array( $term->slug, $slugs, true ) ) {
				$last_terms[] = $term;
				continue;
			}

			$sorted_terms[] = $term;
		}

		return array_merge( $sorted_terms, $last_terms );
	}
}
