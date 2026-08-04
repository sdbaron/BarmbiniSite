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
	 * Meta-Keys für Start- und Enddatum.
	 */
	const META_START_DATE = '_barmbini_promotion_start_date';
	const META_END_DATE   = '_barmbini_promotion_end_date';

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
		add_filter( 'the_content', array( $this, 'add_promotion_meta_to_content' ) );
		add_action( 'init', array( $this, 'maybe_flush_rewrite_rules' ), 20 );
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

		$this->save_meta_value( $post_id, self::META_START_DATE, $start_date );
		$this->save_meta_value( $post_id, self::META_END_DATE, $end_date );
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

		$meta  = '';

		if ( has_post_thumbnail() ) {
			$meta .= '<div class="barmbini-single-promotion__image">'
				. get_the_post_thumbnail( null, 'large' )
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
}
