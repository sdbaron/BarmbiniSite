<?php
/**
 * Barmbini Core – Shortcode für aktuelle Aktionen
 *
 * Stellt alle aktuell gültigen Aktionen des CPT "barmbini_aktion"
 * als Shortcode bereit.
 * Verwendung: [barmbini_promotion]
 *
 * @package Barmbini_Core
 * @since 0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Promotion_Shortcode {

	/**
	 * Registriert den Shortcode.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'barmbini_promotion', array( $this, 'render' ) );
	}

	/**
	 * Rendert die Liste der aktuell gültigen Aktionen.
	 *
	 * Attribute:
	 *   show_image       – Flyer-Bild anzeigen (Standard: true)
	 *   show_date        – Gültigkeitszeitraum anzeigen (Standard: true)
	 *   show_description – Beschreibung anzeigen (Standard: true)
	 *   empty_message    – Text bei keiner gültigen Aktion (Standard: '')
	 *
	 * @param array  $atts    Shortcode-Attribute.
	 * @param string $content Eingeschlossener Inhalt (ungenutzt).
	 * @return string HTML der Aktionen-Liste.
	 */
	public function render( $atts = array(), $content = '' ) {
		$atts = shortcode_atts( array(
			'show_image'       => true,
			'show_date'        => true,
			'show_description' => true,
			'empty_message'    => '',
		), $atts, 'barmbini_promotion' );

		$show_image       = $this->is_truthy( $atts['show_image'] );
		$show_date        = $this->is_truthy( $atts['show_date'] );
		$show_description = $this->is_truthy( $atts['show_description'] );
		$empty_message    = trim( $atts['empty_message'] );

		// CSS nur einbinden, wenn der Shortcode tatsächlich gerendert wird.
		$this->enqueue_styles();

		$today = current_time( 'Y-m-d' );

		$query_args = array(
			'post_type'      => Barmbini_Core_Promotion_Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'meta_value',
			'meta_key'       => Barmbini_Core_Promotion_Post_Type::META_START_DATE,
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => Barmbini_Core_Promotion_Post_Type::META_START_DATE,
					'value'   => $today,
					'compare' => '<=',
					'type'    => 'DATE',
				),
				array(
					'key'     => Barmbini_Core_Promotion_Post_Type::META_END_DATE,
					'value'   => $today,
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
		);

		$query = new WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();

			if ( '' === $empty_message ) {
				return '';
			}

			return sprintf(
				'<div class="barmbini-promotions barmbini-promotions--empty"><p>%s</p></div>',
				esc_html( $empty_message )
			);
		}

		$output = '<div class="barmbini-promotions">';

		while ( $query->have_posts() ) {
			$query->the_post();
			$output .= $this->render_item( $show_image, $show_date, $show_description );
		}

		wp_reset_postdata();

		$output .= '</div>';

		return $output;
	}

	/**
	 * Rendert eine einzelne Aktion.
	 *
	 * @param bool $show_image       Flyer-Bild anzeigen.
	 * @param bool $show_date        Zeitraum anzeigen.
	 * @param bool $show_description Beschreibung anzeigen.
	 * @return string HTML der Aktion.
	 */
	protected function render_item( $show_image, $show_date, $show_description ) {
		$post_id = get_the_ID();

		$output = '<article class="barmbini-promotion-item">';

		if ( $show_image && has_post_thumbnail( $post_id ) ) {
			$output .= $this->render_image( $post_id );
		}

		$title = sprintf(
			'<a href="%s">%s</a>',
			esc_url( get_permalink() ),
			esc_html( get_the_title() )
		);

		$output .= sprintf( '<h3 class="barmbini-promotion-title">%s</h3>', $title );

		if ( $show_date ) {
			$output .= $this->render_dates( $post_id );
		}

		if ( $show_description ) {
			$output .= $this->render_description();
		}

		$output .= '</article>';

		return $output;
	}

	/**
	 * Rendert das Flyer-Bild.
	 *
	 * @param int $post_id Beitrags-ID.
	 * @return string
	 */
	protected function render_image( $post_id ) {
		$image_id  = get_post_thumbnail_id( $post_id );
		$image_url = get_the_post_thumbnail_url( $post_id, 'large' );
		$image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );

		if ( '' === $image_alt ) {
			$image_alt = get_the_title( $post_id );
		}

		return sprintf(
			'<a href="%s" class="barmbini-promotion-image-link"><img class="barmbini-promotion-image" src="%s" alt="%s"></a>',
			esc_url( get_permalink( $post_id ) ),
			esc_url( $image_url ),
			esc_attr( $image_alt )
		);
	}

	/**
	 * Rendert den Gültigkeitszeitraum.
	 *
	 * @param int $post_id Beitrags-ID.
	 * @return string
	 */
	protected function render_dates( $post_id ) {
		$start_date = get_post_meta( $post_id, Barmbini_Core_Promotion_Post_Type::META_START_DATE, true );
		$end_date   = get_post_meta( $post_id, Barmbini_Core_Promotion_Post_Type::META_END_DATE, true );

		if ( ! $start_date || ! $end_date ) {
			return '';
		}

		return sprintf(
			'<p class="barmbini-promotion-dates">Gültig vom %s bis zum %s</p>',
			esc_html( date_i18n( 'j. F Y', strtotime( $start_date ) ) ),
			esc_html( date_i18n( 'j. F Y', strtotime( $end_date ) ) )
		);
	}

	/**
	 * Rendert die Beschreibung (Editor-Inhalt) der Aktion.
	 *
	 * Gibt einen leeren String zurück, wenn der Inhalt nur leer ist.
	 *
	 * @return string
	 */
	protected function render_description() {
		$content = apply_filters( 'the_content', get_the_content() );

		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return '';
		}

		return '<div class="barmbini-promotion-description">' . $content . '</div>';
	}

	/**
	 * Bindet die CSS-Datei ein.
	 *
	 * @return void
	 */
	protected function enqueue_styles() {
		wp_enqueue_style(
			'barmbini-core-promotions',
			BARMBINI_CORE_URL . 'assets/css/promotion.css',
			array(),
			BARMBINI_CORE_VERSION
		);
	}

	/**
	 * Interpretiert einen Shortcode-Wert als boolesch.
	 *
	 * @param mixed $value Wert.
	 * @return bool
	 */
	protected function is_truthy( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$value = strtolower( (string) $value );

		return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
	}
}
