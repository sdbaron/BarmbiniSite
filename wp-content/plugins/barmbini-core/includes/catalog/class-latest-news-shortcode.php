<?php
/**
 * Barmbini Core – Shortcode für die letzten Neuigkeiten-Beiträge
 *
 * Stellt die letzten veröffentlichten Beiträge aus der Kategorie
 * "Neuigkeiten" als Shortcode bereit.
 * Verwendung: [barmbini_latest_news]
 *
 * @package Barmbini_Core
 * @since 0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Latest_News_Shortcode {

	/**
	 * Registriert den Shortcode.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'barmbini_latest_news', array( $this, 'render' ) );
	}

	/**
	 * Rendert die Liste der letzten Neuigkeiten-Beiträge.
	 *
	 * Attribute:
	 *   count         – Anzahl der Beiträge (Standard: 3, max: 10)
	 *   show_excerpt  – Kurztext anzeigen (Standard: true)
	 *   show_date     – Datum anzeigen (Standard: true)
	 *   empty_message – Text bei leerer Ergebnisliste (Standard: '')
	 *
	 * @param array  $atts    Shortcode-Attribute.
	 * @param string $content Eingeschlossener Inhalt (ungenutzt).
	 * @return string HTML der Neuigkeiten-Liste.
	 */
	public function render( $atts = array(), $content = '' ) {
		$atts = shortcode_atts( array(
			'count'         => 3,
			'show_excerpt'  => true,
			'show_date'     => true,
			'empty_message' => '',
		), $atts, 'barmbini_latest_news' );

		// Attribute normalisieren
		$count         = max( 1, min( 10, intval( $atts['count'] ) ) );
		$show_excerpt  = $this->is_truthy( $atts['show_excerpt'] );
		$show_date     = $this->is_truthy( $atts['show_date'] );
		$empty_message = trim( $atts['empty_message'] );

		// CSS einbinden
		$this->enqueue_styles();

		$query_args = array(
			'category_name'  => 'neuigkeiten',
			'posts_per_page' => $count,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		$query = new WP_Query( $query_args );

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();

			if ( '' === $empty_message ) {
				return '';
			}

			return sprintf(
				'<div class="barmbini-latest-news barmbini-latest-news--empty"><p class="barmbini-news-empty">%s</p></div>',
				esc_html( $empty_message )
			);
		}

		$output = '<div class="barmbini-latest-news">';

		while ( $query->have_posts() ) {
			$query->the_post();

			$output .= '<article class="barmbini-news-item">';
			$output .= sprintf(
				'<h3 class="barmbini-news-title"><a href="%s">%s</a></h3>',
				esc_url( get_permalink() ),
				esc_html( get_the_title() )
			);

			if ( $show_date ) {
				$output .= sprintf(
					'<time class="barmbini-news-date" datetime="%s">%s</time>',
					esc_attr( get_the_date( 'c' ) ),
					esc_html( get_the_date() )
				);
			}

			if ( $show_excerpt ) {
				$excerpt = $this->get_excerpt( get_the_ID() );
				if ( '' !== $excerpt ) {
					$output .= sprintf( '<p class="barmbini-news-excerpt">%s</p>', $excerpt );
				}
			}

			$output .= '</article>';
		}

		$output .= '</div>';

		wp_reset_postdata();

		return $output;
	}

	/**
	 * Bindet das Stylesheet für die Neuigkeiten-Liste ein.
	 *
	 * @return void
	 */
	private function enqueue_styles() {
		wp_enqueue_style(
			'barmbini-latest-news',
			BARMBINI_CORE_URL . 'assets/css/latest-news.css',
			array(),
			BARMBINI_CORE_VERSION
		);
	}

	/**
	 * Gibt den Excerpt eines Beitrags zurück – gefiltert und ohne HTML.
	 *
	 * @param int $post_id Beitrags-ID.
	 * @return string
	 */
	private function get_excerpt( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return '';
		}

		if ( post_password_required( $post ) ) {
			return '';
		}

		$excerpt = $post->post_excerpt;

		if ( '' === $excerpt ) {
			$excerpt = wp_trim_words(
				strip_shortcodes( $post->post_content ),
				20,
				'…'
			);
		}

		return wp_kses( $excerpt, array() );
	}

	/**
	 * Interpretiert einen Attributwert als booleschen Wert.
	 *
	 * Akzeptiert: true, 1, '1', 'true', 'yes' => true
	 * Alles andere => false
	 *
	 * @param mixed $value Attributwert.
	 * @return bool
	 */
	private function is_truthy( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_numeric( $value ) ) {
			return intval( $value ) === 1;
		}

		$value = strtolower( trim( (string) $value ) );

		return in_array( $value, array( 'true', 'yes', '1' ), true );
	}
}
