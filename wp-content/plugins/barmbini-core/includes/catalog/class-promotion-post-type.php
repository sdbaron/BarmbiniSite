<?php
/**
 * Barmbini Core – Custom Post Type "Aktion"
 *
 * Registriert den Inhaltstyp barmbini_aktion für zeitlich begrenzte
 * Aktionen mit Start-/Enddatum und Flyer-Bild.
 *
 * @package Barmbini_Core
 * @since 0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Promotion_Post_Type {

	/**
	 * Slug des Custom Post Type.
	 */
	const POST_TYPE = 'barmbini_aktion';

	/**
	 * Meta-Keys für Start-/Enddatum, Anzeigeoptionen und Flyer-Overlay.
	 */
	const META_START_DATE          = '_barmbini_promotion_start_date';
	const META_END_DATE            = '_barmbini_promotion_end_date';
	const META_SHOW_DESCRIPTION    = '_barmbini_promotion_show_description';
	const META_OVERLAY_TEXT        = '_barmbini_promotion_overlay_text';
	const META_OVERLAY_COLOR       = '_barmbini_promotion_overlay_color';
	const META_OVERLAY_SIZE        = '_barmbini_promotion_overlay_size';
	const META_OVERLAY_POSITION_V  = '_barmbini_promotion_overlay_position_v';
	const META_OVERLAY_POSITION_H  = '_barmbini_promotion_overlay_position_h';

	const OVERLAY_COLOR_DEFAULT      = '#ffffff';
	const OVERLAY_SIZE_DEFAULT       = 24;
	const OVERLAY_SIZE_MIN           = 12;
	const OVERLAY_SIZE_MAX           = 72;
	const OVERLAY_POSITION_V_DEFAULT = 'top';
	const OVERLAY_POSITION_H_DEFAULT = 'left';

	/**
	 * Registriert alle Hooks für den CPT.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta_fields' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_metaboxes' ), 10, 1 );
		add_filter( 'views_edit-' . self::POST_TYPE, array( $this, 'add_archive_views' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_admin_list' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_archive_for_visitors' ) );
		add_filter( 'the_content', array( $this, 'add_promotion_meta_to_content' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_singular_styles' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 20 );
		add_action( 'admin_init', array( $this, 'remove_legacy_category' ) );
	}

	/**
	 * Registriert den Custom Post Type.
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => 'Aktionen',
			'singular_name'         => 'Aktion',
			'menu_name'             => 'Aktionen',
			'add_new'               => 'Neue Aktion',
			'add_new_item'          => 'Neue Aktion erstellen',
			'edit_item'             => 'Aktion bearbeiten',
			'new_item'              => 'Neue Aktion',
			'view_item'             => 'Aktion ansehen',
			'search_items'          => 'Aktionen durchsuchen',
			'not_found'             => 'Keine Aktionen gefunden',
			'not_found_in_trash'    => 'Keine Aktionen im Papierkorb',
			'all_items'             => 'Alle Aktionen',
			'featured_image'        => 'Flyer-Bild',
			'set_featured_image'    => 'Flyer-Bild auswählen',
			'remove_featured_image' => 'Flyer-Bild entfernen',
			'use_featured_image'    => 'Als Flyer-Bild verwenden',
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'has_archive'        => true,
			'show_in_menu'       => true,
			'menu_position'      => 25,
			'menu_icon'          => 'dashicons-megaphone',
			'supports'           => array( 'title', 'editor', 'thumbnail' ),
			'show_in_rest'       => true,
			'capability_type'    => 'post',
			'rewrite'            => array( 'slug' => 'aktion' ),
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Registriert die Meta-Felder für die REST-API (Gutenberg-Kompatibilität).
	 *
	 * Ohne register_post_meta() sind die Felder im Block-Editor nicht sichtbar.
	 *
	 * @return void
	 */
	public function register_meta_fields() {
		register_post_meta(
			self::POST_TYPE,
			self::META_START_DATE,
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_END_DATE,
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_post_meta(
			self::POST_TYPE,
			self::META_SHOW_DESCRIPTION,
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'string',
				'default'       => '1',
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		$overlay_string_metas = array(
			self::META_OVERLAY_TEXT,
			self::META_OVERLAY_COLOR,
			self::META_OVERLAY_POSITION_V,
			self::META_OVERLAY_POSITION_H,
		);

		foreach ( $overlay_string_metas as $meta_key ) {
			register_post_meta(
				self::POST_TYPE,
				$meta_key,
				array(
					'show_in_rest'  => true,
					'single'        => true,
					'type'          => 'string',
					'auth_callback' => function () {
						return current_user_can( 'edit_posts' );
					},
				)
			);
		}

		register_post_meta(
			self::POST_TYPE,
			self::META_OVERLAY_SIZE,
			array(
				'show_in_rest'  => true,
				'single'        => true,
				'type'          => 'integer',
				'default'       => self::OVERLAY_SIZE_DEFAULT,
				'auth_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Registriert die Meta-Felder für die REST-API (Gutenberg-Kompatibilität).
	 *
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'barmbini_promotion_dates',
			'Gültigkeitszeitraum',
			array( $this, 'render_dates_metabox' ),
			self::POST_TYPE,
			'side',
			'default'
		);

		add_meta_box(
			'barmbini_promotion_display',
			'Startseiten-Anzeige',
			array( $this, 'render_display_metabox' ),
			self::POST_TYPE,
			'side',
			'default'
		);

		add_meta_box(
			'barmbini_promotion_overlay',
			'Overlay auf dem Flyer',
			array( $this, 'render_overlay_metabox' ),
			self::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Rendert die Datums-Metabox.
	 *
	 * @param WP_Post $post Aktueller Beitrag.
	 * @return void
	 */
	public function render_dates_metabox( $post ) {
		wp_nonce_field( 'barmbini_promotion_meta', 'barmbini_promotion_nonce' );

		$start_date = get_post_meta( $post->ID, self::META_START_DATE, true );
		$end_date   = get_post_meta( $post->ID, self::META_END_DATE, true );
		?>
		<p>
			<label for="barmbini_promotion_start_date">Startdatum</label>
			<input type="date" id="barmbini_promotion_start_date"
				name="barmbini_promotion_start_date"
				value="<?php echo esc_attr( $start_date ); ?>">
		</p>
		<p>
			<label for="barmbini_promotion_end_date">Enddatum</label>
			<input type="date" id="barmbini_promotion_end_date"
				name="barmbini_promotion_end_date"
				value="<?php echo esc_attr( $end_date ); ?>">
		</p>
		<p class="description">
			Nur Aktionen, deren Zeitraum das heutige Datum umfasst, werden auf der Startseite angezeigt.
		</p>
		<?php
	}

	/**
	 * Rendert die Checkbox "Beschreibung auf der Startseite anzeigen".
	 *
	 * @param WP_Post $post Aktueller Beitrag.
	 * @return void
	 */
	public function render_display_metabox( $post ) {
		$show_description = get_post_meta( $post->ID, self::META_SHOW_DESCRIPTION, true );

		// Standard: true (Beschreibung anzeigen).
		if ( '' === $show_description ) {
			$show_description = '1';
		}
		?>
		<label>
			<input type="checkbox" name="barmbini_promotion_show_description"
				value="1" <?php checked( '1', $show_description ); ?>>
			Beschreibung auf der Startseite anzeigen
		</label>
		<p class="description">
			Wenn aktiv, erscheint der Text der Aktion unter dem Flyer-Bild auf der Startseite.
		</p>
		<?php
	}

	/**
	 * Rendert die Metabox für den Overlay-Text auf dem Flyer-Bild.
	 *
	 * @param WP_Post $post Aktueller Beitrag.
	 * @return void
	 */
	public function render_overlay_metabox( $post ) {
		$text       = get_post_meta( $post->ID, self::META_OVERLAY_TEXT, true );
		$color      = get_post_meta( $post->ID, self::META_OVERLAY_COLOR, true );
		$size       = get_post_meta( $post->ID, self::META_OVERLAY_SIZE, true );
		$position_v = get_post_meta( $post->ID, self::META_OVERLAY_POSITION_V, true );
		$position_h = get_post_meta( $post->ID, self::META_OVERLAY_POSITION_H, true );

		if ( '' === $color ) {
			$color = self::OVERLAY_COLOR_DEFAULT;
		}
		if ( '' === $size ) {
			$size = self::OVERLAY_SIZE_DEFAULT;
		}
		if ( '' === $position_v ) {
			$position_v = self::OVERLAY_POSITION_V_DEFAULT;
		}
		if ( '' === $position_h ) {
			$position_h = self::OVERLAY_POSITION_H_DEFAULT;
		}

		$color_sanitized = self::sanitize_overlay_color( $color );
		$color_picker    = $color_sanitized;
		if ( 4 === strlen( $color_picker ) ) {
			$color_picker = '#' . $color_picker[1] . $color_picker[1] . $color_picker[2] . $color_picker[2] . $color_picker[3] . $color_picker[3];
		}

		$v_options = array(
			'top'    => 'Oben',
			'middle' => 'Mitte',
			'bottom' => 'Unten',
		);
		$h_options = array(
			'left'   => 'Links',
			'middle' => 'Mitte',
			'right'  => 'Rechts',
		);
		?>
		<p>
			<label for="barmbini_promotion_overlay_text">Text</label>
			<input type="text" id="barmbini_promotion_overlay_text"
				name="barmbini_promotion_overlay_text"
				value="<?php echo esc_attr( $text ); ?>"
				maxlength="80" class="widefat"
				placeholder="z. B. Nur diese Woche">
		</p>
		<p>
			<label for="barmbini_promotion_overlay_color">Farbe</label>
			<input type="color" id="barmbini_promotion_overlay_color_picker"
				value="<?php echo esc_attr( $color_picker ); ?>"
				style="vertical-align: middle; margin-right: 0.5rem;"
				oninput="document.getElementById('barmbini_promotion_overlay_color').value=this.value">
			<input type="text" id="barmbini_promotion_overlay_color"
				name="barmbini_promotion_overlay_color"
				value="<?php echo esc_attr( $color ); ?>"
				placeholder="#ffffff" style="width: 6.5em;">
		</p>
		<p>
			<label for="barmbini_promotion_overlay_size">Größe (px)</label>
			<input type="number" id="barmbini_promotion_overlay_size"
				name="barmbini_promotion_overlay_size"
				value="<?php echo esc_attr( (string) (int) $size ); ?>"
				min="<?php echo esc_attr( (string) self::OVERLAY_SIZE_MIN ); ?>"
				max="<?php echo esc_attr( (string) self::OVERLAY_SIZE_MAX ); ?>"
				step="1" class="small-text">
		</p>
		<p>
			<label for="barmbini_promotion_overlay_position_v">Vertikale Position</label>
			<select id="barmbini_promotion_overlay_position_v"
				name="barmbini_promotion_overlay_position_v" class="widefat">
				<?php foreach ( $v_options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $position_v, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="barmbini_promotion_overlay_position_h">Horizontale Position</label>
			<select id="barmbini_promotion_overlay_position_h"
				name="barmbini_promotion_overlay_position_h" class="widefat">
				<?php foreach ( $h_options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $position_h, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description">
			Der Text erscheint auf dem Flyer-Bild (Startseite und Aktionsseite), nicht statt Titel oder Beschreibung. Leer lassen = kein Overlay.
		</p>
		<?php
	}

	/**
	 * Speichert die Metabox-Daten.
	 *
	 * @param int $post_id Beitrags-ID.
	 * @return void
	 */
	public function save_metaboxes( $post_id ) {
		if ( ! isset( $_POST['barmbini_promotion_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( $_POST['barmbini_promotion_nonce'] ), 'barmbini_promotion_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$start_date = $this->sanitize_date( isset( $_POST['barmbini_promotion_start_date'] )
			? sanitize_text_field( wp_unslash( $_POST['barmbini_promotion_start_date'] ) )
			: '' );

		$end_date = $this->sanitize_date( isset( $_POST['barmbini_promotion_end_date'] )
			? sanitize_text_field( wp_unslash( $_POST['barmbini_promotion_end_date'] ) )
			: '' );

		$show_description = isset( $_POST['barmbini_promotion_show_description'] ) ? '1' : '0';

		$overlay_text = isset( $_POST['barmbini_promotion_overlay_text'] )
			? sanitize_text_field( wp_unslash( $_POST['barmbini_promotion_overlay_text'] ) )
			: '';
		$overlay_text = trim( $overlay_text );

		$overlay_color = self::sanitize_overlay_color(
			isset( $_POST['barmbini_promotion_overlay_color'] )
				? sanitize_text_field( wp_unslash( $_POST['barmbini_promotion_overlay_color'] ) )
				: ''
		);

		$overlay_size = self::sanitize_overlay_size(
			isset( $_POST['barmbini_promotion_overlay_size'] )
				? wp_unslash( $_POST['barmbini_promotion_overlay_size'] )
				: ''
		);

		$overlay_position_v = self::sanitize_overlay_position_v(
			isset( $_POST['barmbini_promotion_overlay_position_v'] )
				? sanitize_text_field( wp_unslash( $_POST['barmbini_promotion_overlay_position_v'] ) )
				: ''
		);

		$overlay_position_h = self::sanitize_overlay_position_h(
			isset( $_POST['barmbini_promotion_overlay_position_h'] )
				? sanitize_text_field( wp_unslash( $_POST['barmbini_promotion_overlay_position_h'] ) )
				: ''
		);

		$this->save_meta_value( $post_id, self::META_START_DATE, $start_date );
		$this->save_meta_value( $post_id, self::META_END_DATE, $end_date );
		update_post_meta( $post_id, self::META_SHOW_DESCRIPTION, $show_description );

		$this->save_meta_value( $post_id, self::META_OVERLAY_TEXT, $overlay_text );

		if ( '' === $overlay_text ) {
			delete_post_meta( $post_id, self::META_OVERLAY_COLOR );
			delete_post_meta( $post_id, self::META_OVERLAY_SIZE );
			delete_post_meta( $post_id, self::META_OVERLAY_POSITION_V );
			delete_post_meta( $post_id, self::META_OVERLAY_POSITION_H );
		} else {
			update_post_meta( $post_id, self::META_OVERLAY_COLOR, $overlay_color );
			update_post_meta( $post_id, self::META_OVERLAY_SIZE, $overlay_size );
			update_post_meta( $post_id, self::META_OVERLAY_POSITION_V, $overlay_position_v );
			update_post_meta( $post_id, self::META_OVERLAY_POSITION_H, $overlay_position_h );
		}
	}

	/**
	 * Normalisiert eine Datumseingabe auf das Format Y-m-d.
	 *
	 * Ungültige oder leere Werte ergeben einen leeren String.
	 *
	 * @param string $value Rohwert aus dem Formular.
	 * @return string
	 */
	protected function sanitize_date( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Validiert eine Overlay-Farbe (Hex #RGB oder #RRGGBB).
	 *
	 * @param string $raw Rohwert.
	 * @return string
	 */
	public static function sanitize_overlay_color( $raw ) {
		$raw = trim( (string) $raw );

		if ( preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $raw ) ) {
			return strtolower( $raw );
		}

		return self::OVERLAY_COLOR_DEFAULT;
	}

	/**
	 * Validiert die Overlay-Schriftgröße (12–72 px).
	 *
	 * @param mixed $raw Rohwert.
	 * @return int
	 */
	public static function sanitize_overlay_size( $raw ) {
		if ( '' === $raw || null === $raw ) {
			return self::OVERLAY_SIZE_DEFAULT;
		}

		$size = (int) $raw;

		if ( $size < self::OVERLAY_SIZE_MIN ) {
			return self::OVERLAY_SIZE_MIN;
		}

		if ( $size > self::OVERLAY_SIZE_MAX ) {
			return self::OVERLAY_SIZE_MAX;
		}

		return $size;
	}

	/**
	 * Validiert die vertikale Overlay-Position.
	 *
	 * @param string $raw Rohwert.
	 * @return string
	 */
	public static function sanitize_overlay_position_v( $raw ) {
		$raw = sanitize_key( (string) $raw );

		if ( in_array( $raw, array( 'top', 'middle', 'bottom' ), true ) ) {
			return $raw;
		}

		return self::OVERLAY_POSITION_V_DEFAULT;
	}

	/**
	 * Validiert die horizontale Overlay-Position.
	 *
	 * @param string $raw Rohwert.
	 * @return string
	 */
	public static function sanitize_overlay_position_h( $raw ) {
		$raw = sanitize_key( (string) $raw );

		if ( in_array( $raw, array( 'left', 'middle', 'right' ), true ) ) {
			return $raw;
		}

		return self::OVERLAY_POSITION_H_DEFAULT;
	}

	/**
	 * Hüllt Flyer-Bild-HTML in einen Overlay-Wrapper, falls Text gesetzt ist.
	 *
	 * @param int    $post_id    Beitrags-ID.
	 * @param string $image_html Fertiges Bild-HTML.
	 * @return string
	 */
	public static function render_flyer_with_overlay( $post_id, $image_html ) {
		$post_id    = (int) $post_id;
		$image_html = (string) $image_html;

		if ( '' === $image_html || $post_id <= 0 ) {
			return $image_html;
		}

		$text = trim( (string) get_post_meta( $post_id, self::META_OVERLAY_TEXT, true ) );

		if ( '' === $text ) {
			return $image_html;
		}

		$color      = self::sanitize_overlay_color( get_post_meta( $post_id, self::META_OVERLAY_COLOR, true ) );
		$size       = self::sanitize_overlay_size( get_post_meta( $post_id, self::META_OVERLAY_SIZE, true ) );
		$position_v = self::sanitize_overlay_position_v( get_post_meta( $post_id, self::META_OVERLAY_POSITION_V, true ) );
		$position_h = self::sanitize_overlay_position_h( get_post_meta( $post_id, self::META_OVERLAY_POSITION_H, true ) );

		$modifier = sprintf( 'barmbini-promotion-flyer--%s-%s', $position_v, $position_h );

		return sprintf(
			'<div class="barmbini-promotion-flyer %s">%s<span class="barmbini-promotion-overlay" style="color:%s;font-size:%dpx">%s</span></div>',
			esc_attr( $modifier ),
			$image_html,
			esc_attr( $color ),
			(int) $size,
			esc_html( $text )
		);
	}

	/**
	 * Speichert einen Meta-Wert oder entfernt ihn, wenn leer.
	 *
	 * @param int    $post_id  Beitrags-ID.
	 * @param string $meta_key Meta-Key.
	 * @param string $value    Wert.
	 * @return void
	 */
	protected function save_meta_value( $post_id, $meta_key, $value ) {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
		} else {
			update_post_meta( $post_id, $meta_key, $value );
		}
	}

	/**
	 * Fügt Filter-Links "Aktiv", "Archiv" und "Alle" über der Aktions-Tabelle ein.
	 *
	 * @param array $views Bestehende View-Links.
	 * @return array
	 */
	public function add_archive_views( $views ) {
		$counts    = $this->get_promotion_counts();
		$current   = isset( $_GET['promotion_view'] ) ? sanitize_key( $_GET['promotion_view'] ) : 'active';
		$base_url  = admin_url( 'edit.php?post_type=' . self::POST_TYPE );

		$new_views = array();

		// Aktiv
		$new_views['active'] = sprintf(
			'<a href="%s" class="%s">Aktiv <span class="count">(%d)</span></a>',
			esc_url( $base_url ),
			'active' === $current ? 'current' : '',
			$counts['active']
		);

		// Archiv
		$new_views['archived'] = sprintf(
			'<a href="%s" class="%s">Archiv <span class="count">(%d)</span></a>',
			esc_url( add_query_arg( 'promotion_view', 'archived', $base_url ) ),
			'archived' === $current ? 'current' : '',
			$counts['archived']
		);

		// Alle
		$new_views['all'] = sprintf(
			'<a href="%s" class="%s">Alle <span class="count">(%d)</span></a>',
			esc_url( add_query_arg( 'promotion_view', 'all', $base_url ) ),
			'all' === $current ? 'current' : '',
			$counts['all']
		);

		return $new_views;
	}

	/**
	 * Filtert die Admin-Liste nach dem gewählten Ansichtsmodus.
	 *
	 * Nur aktiv auf der CPT-Übersichtsseite im Backend.
	 *
	 * @param WP_Query $query Die aktuelle WP_Query.
	 * @return void
	 */
	public function filter_admin_list( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$view = isset( $_GET['promotion_view'] ) ? sanitize_key( $_GET['promotion_view'] ) : 'active';

		// "Alle": Keine Filterung.
		if ( 'all' === $view ) {
			return;
		}

		$today      = current_time( 'Y-m-d' );
		$meta_query = (array) $query->get( 'meta_query' );

		if ( 'archived' === $view ) {
			// Nur abgelaufene Aktionen (Enddatum < heute).
			$meta_query[] = array(
				'key'     => self::META_END_DATE,
				'value'   => $today,
				'compare' => '<',
				'type'    => 'DATE',
			);
		} else {
			// "Aktiv" (Standard): Nur laufende oder zukünftige Aktionen.
			// Bedingung: Enddatum >= heute ODER kein Enddatum gesetzt.
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => self::META_END_DATE,
					'value'   => $today,
					'compare' => '>=',
					'type'    => 'DATE',
				),
				array(
					'key'     => self::META_END_DATE,
					'compare' => 'NOT EXISTS',
				),
			);
		}

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Zählt Aktionen getrennt nach Aktiv, Archiviert und Gesamt.
	 *
	 * @return array{active:int, archived:int, all:int}
	 */
	protected function get_promotion_counts() {
		global $wpdb;

		$today = current_time( 'Y-m-d' );

		$all = (int) wp_count_posts( self::POST_TYPE )->publish;

		// Archiviert: Enddatum ist gesetzt UND liegt vor heute.
		$archived = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(DISTINCT p.ID)
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
			 WHERE p.post_type = %s
			 AND p.post_status = 'publish'
			 AND pm.meta_key = %s
			 AND pm.meta_value < %s",
			self::POST_TYPE,
			self::META_END_DATE,
			$today
		) );

		return array(
			'active'   => $all - $archived,
			'archived' => $archived,
			'all'      => $all,
		);
	}

	/**
	 * Blendet Gültigkeitszeitraum und Beendet-Hinweis im Content der Einzelansicht ein.
	 *
	 * Kadence rendert Layout, Header und Footer – dieser Filter ergänzt nur die
	 * aktionsspezifischen Metadaten am Anfang des Beitragsinhalts.
	 *
	 * @param string $content Der Original-Inhalt.
	 * @return string
	 */
	public function add_promotion_meta_to_content( $content ) {
		if ( ! is_singular( self::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$meta = '';

		if ( has_post_thumbnail() ) {
			$thumbnail = get_the_post_thumbnail( null, 'large' );
			$meta     .= '<div class="barmbini-single-promotion__image">'
				. self::render_flyer_with_overlay( get_the_ID(), $thumbnail )
				. '</div>';
		}

		$start = get_post_meta( get_the_ID(), self::META_START_DATE, true );
		$end   = get_post_meta( get_the_ID(), self::META_END_DATE, true );
		$today = current_time( 'Y-m-d' );

		if ( $start && $end ) {
			$meta .= sprintf(
				'<p class="barmbini-single-promotion__dates">Gültig vom %s bis zum %s</p>',
				esc_html( date_i18n( 'j. F Y', strtotime( $start ) ) ),
				esc_html( date_i18n( 'j. F Y', strtotime( $end ) ) )
			);
		}

		if ( $end && $end < $today ) {
			$meta .= '<div class="barmbini-single-promotion__expired"><strong>Hinweis:</strong> Diese Aktion ist beendet.</div>';
		}

		return $meta . $content;
	}

	/**
	 * Bindet promotion.css auf der Aktions-Einzelansicht ein.
	 *
	 * @return void
	 */
	public function enqueue_singular_styles() {
		if ( ! is_singular( self::POST_TYPE ) ) {
			return;
		}

		wp_enqueue_style(
			'barmbini-core-promotions',
			BARMBINI_CORE_URL . 'assets/css/promotion.css',
			array(),
			BARMBINI_CORE_VERSION
		);
	}

	/**
	 * Spült die Rewrite-Regeln einmalig, wenn nötig.
	 *
	 * Verhindert 404-Fehler nach Änderungen an publicly_queryable oder rewrite-Slug.
	 * Wird nur ausgeführt, wenn die gespeicherte Flush-Version nicht der
	 * aktuellen Plugin-Version entspricht.
	 *
	 * @return void
	 */
	public function maybe_flush_rewrite_rules() {
		$flushed_version = get_option( 'barmbini_promotion_rewrite_version', '' );

		if ( BARMBINI_CORE_VERSION !== $flushed_version ) {
			flush_rewrite_rules();
			update_option( 'barmbini_promotion_rewrite_version', BARMBINI_CORE_VERSION );
		}
	}

	/**
	 * Filter: pre_get_posts
	 *
	 * Blendet im Frontend-Archiv (\/aktion\/) für Besucher ohne
	 * Redakteurs- oder Admin-Rolle Aktionen mit zukünftigem Startdatum
	 * aus. Admins und Redakteure sehen weiterhin alle Aktionen.
	 *
	 * @param WP_Query $query Die aktuelle WP_Query.
	 * @return void
	 */
	public function filter_archive_for_visitors( $query ) {
		if ( is_admin() ) {
			return;
		}
		if ( ! $query->is_main_query() ) {
			return;
		}
		if ( ! $query->is_post_type_archive( self::POST_TYPE ) ) {
			return;
		}
		if ( current_user_can( 'edit_others_posts' ) ) {
			return;
		}

		$today       = current_time( 'Y-m-d' );
		$meta_query  = (array) $query->get( 'meta_query' );
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => self::META_START_DATE,
				'value'   => $today,
				'compare' => '<=',
				'type'    => 'DATE',
			),
			array(
				'key'     => self::META_START_DATE,
				'compare' => 'NOT EXISTS',
			),
		);
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Entfernt einmalig die Standard-Kategorie "Aktion", um Verwechslungen
	 * mit dem CPT barmbini_aktion zu vermeiden.
	 *
	 * @return void
	 */
	public function remove_legacy_category() {
		if ( get_option( 'barmbini_legacy_category_removed' ) ) {
			return;
		}

		$term = get_term_by( 'name', 'Aktion', 'category' );

		if ( $term && ! is_wp_error( $term ) ) {
			wp_delete_term( $term->term_id, 'category' );
		}

		update_option( 'barmbini_legacy_category_removed', '1' );
	}
}
