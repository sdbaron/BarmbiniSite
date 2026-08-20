<?php
/**
 * Barmbini Core – Interne Anleitungen (Editor & Seller)
 *
 * Stellt zwei ausführliche Anleitungen für interne Mitarbeiter-Rollen bereit:
 *
 * - `/anleitung-redakteur/` – für die Rolle „Editor“
 * - `/anleitung-verkaeufer/` – für die Rolle „Seller“
 *
 * Die Seiten werden vom Plugin automatisch angelegt (idempotent) und sind
 * rollenabhängig sichtbar:
 *
 * - `/anleitung-redakteur/` – Administrator und Editor (Capability `barmbini_view_guide_redakteur`)
 * - `/anleitung-verkaeufer/` – Administrator, Editor und Seller (Capability `barmbini_view_guide_verkaeufer`)
 *
 * Besucher und andere Rollen werden umgeleitet; die Seiten sind zusätzlich mit
 * `noindex` gegen Suchmaschinen-Indexierung markiert.
 *
 * Zusätzlich gibt es einen kurzen Admin-Menüpunkt „Anleitungen“ sowie Links
 * in der Admin-Bar, damit die berechtigten Rollen die Seiten schnell finden.
 *
 * @package Barmbini_Core
 * @since 0.7.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Staff_Guides {

	const PAGE_REDAKTEUR  = 'anleitung-redakteur';
	const PAGE_VERKAEUFER = 'anleitung-verkaeufer';
	const CAP_REDAKTEUR   = 'barmbini_view_guide_redakteur';
	const CAP_VERKAEUFER  = 'barmbini_view_guide_verkaeufer';
	const MENU_SLUG       = 'barmbini-anleitungen';

	/**
	 * Registriert die Hooks des Anleitungs-Moduls.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'ensure_capabilities' ) );
		add_action( 'admin_init', array( $this, 'ensure_pages' ) );
		add_action( 'template_redirect', array( $this, 'gate_frontend_pages' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_bar_menu', array( $this, 'register_admin_bar_links' ), 90 );
		add_filter( 'wp_robots', array( $this, 'noindex_guide_pages' ) );
	}

	/**
	 * Liefert die Slugs der beiden Anleitungsseiten.
	 *
	 * @return array<int,string>
	 */
	public static function get_guide_slugs() {
		return array( self::PAGE_REDAKTEUR, self::PAGE_VERKAEUFER );
	}

	/**
	 * Liefert die Rollen, die die Anleitungen sehen dürfen.
	 *
	 * @return array<int,string>
	 */
	public static function role_slugs() {
		return array( 'administrator', 'editor', Barmbini_Core_Seller_Role::get_role_slug() );
	}

	/**
	 * Vergibt die Anleitungs-Capabilities idempotent an die erlaubten Rollen.
	 *
	 * Administrator und Editor sehen beide Anleitungen, der Seller nur
	 * die Seller-Anleitung. Die veraltete Sammel-Capability
	 * `barmbini_view_guides` wird dabei entfernt.
	 *
	 * @return void
	 */
	public function ensure_capabilities() {
		foreach ( array( 'administrator', 'editor' ) as $slug ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			if ( ! $role->has_cap( self::CAP_REDAKTEUR ) ) {
				$role->add_cap( self::CAP_REDAKTEUR );
			}
			if ( ! $role->has_cap( self::CAP_VERKAEUFER ) ) {
				$role->add_cap( self::CAP_VERKAEUFER );
			}
		}

		$seller = get_role( Barmbini_Core_Seller_Role::get_role_slug() );
		if ( $seller && ! $seller->has_cap( self::CAP_VERKAEUFER ) ) {
			$seller->add_cap( self::CAP_VERKAEUFER );
		}

		// Veraltete Sammel-Capability entfernen (seit 0.7.0).
		foreach ( array( 'administrator', 'editor', Barmbini_Core_Seller_Role::get_role_slug() ) as $slug ) {
			$role = get_role( $slug );
			if ( $role && $role->has_cap( 'barmbini_view_guides' ) ) {
				$role->remove_cap( 'barmbini_view_guides' );
			}
		}
	}

	/**
	 * Legt die beiden Anleitungsseiten an, falls sie noch nicht existieren.
	 *
	 * @return void
	 */
	public function ensure_pages() {
		$pages = array(
			self::PAGE_REDAKTEUR => array(
			'title'   => __( 'Anleitung für Editor', 'barmbini-core' ),
				'content' => self::redakteur_content(),
			),
			self::PAGE_VERKAEUFER => array(
			'title'   => __( 'Anleitung für Seller', 'barmbini-core' ),
				'content' => self::verkaeufer_content(),
			),
		);

		foreach ( $pages as $slug => $data ) {
			if ( get_page_by_path( $slug ) ) {
				continue;
			}

			wp_insert_post( array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $data['title'],
				'post_name'    => $slug,
				'post_content' => $data['content'],
			) );
		}
	}

	/**
	 * Prüft, ob die aktuelle Seite eine der Anleitungsseiten ist.
	 *
	 * @return bool
	 */
	public function is_guide_page() {
		return is_page( self::get_guide_slugs() );
	}

	/**
	 * Prüft, ob der aktuelle Besucher eine bestimmte Anleitung sehen darf.
	 *
	 * @param string $slug Seiten-Slug der Anleitung.
	 * @return bool
	 */
	public function can_view_page( $slug ) {
		if ( self::PAGE_REDAKTEUR === $slug ) {
			return current_user_can( self::CAP_REDAKTEUR );
		}
		if ( self::PAGE_VERKAEUFER === $slug ) {
			return current_user_can( self::CAP_VERKAEUFER );
		}

		return false;
	}

	/**
	 * Prüft, ob der Besucher mindestens eine Anleitung sehen darf.
	 *
	 * @return bool
	 */
	public function can_view_any() {
		return current_user_can( self::CAP_REDAKTEUR ) || current_user_can( self::CAP_VERKAEUFER );
	}

	/**
	 * Prüft, ob für die aktuelle Seite eine Umleitung nötig ist.
	 *
	 * @return bool
	 */
	public function should_redirect() {
		if ( ! $this->is_guide_page() ) {
			return false;
		}

		$post = get_queried_object();
		$slug = ( $post && isset( $post->post_name ) ) ? $post->post_name : '';

		return ! $this->can_view_page( $slug );
	}

	/**
	 * Liefert die URL der ersten für den Besucher zugänglichen Anleitung.
	 *
	 * @return string Leer, wenn keine Anleitung zugänglich ist.
	 */
	public function first_accessible_guide_url() {
		if ( current_user_can( self::CAP_REDAKTEUR ) ) {
			return home_url( '/' . self::PAGE_REDAKTEUR . '/' );
		}
		if ( current_user_can( self::CAP_VERKAEUFER ) ) {
			return home_url( '/' . self::PAGE_VERKAEUFER . '/' );
		}

		return '';
	}

	/**
	 * Schützt die Anleitungsseiten vor nicht berechtigten Besuchern.
	 *
	 * @return void
	 */
	public function gate_frontend_pages() {
		if ( ! $this->should_redirect() ) {
			return;
		}

		// Nicht angemeldet → Login-Seite.
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( get_permalink() ) );
			exit;
		}

		// Angemeldet, aber ohne Zugriff → zu einer zugänglichen Anleitung oder Startseite.
		$fallback = $this->first_accessible_guide_url();
		wp_safe_redirect( $fallback ? $fallback : home_url( '/' ) );
		exit;
	}

	/**
	 * Verhindert die Indexierung der Anleitungsseiten durch Suchmaschinen.
	 *
	 * @param array<string,bool> $robots Bestehende Robots-Direktiven.
	 * @return array<string,bool>
	 */
	public function noindex_guide_pages( $robots ) {
		if ( is_page( self::get_guide_slugs() ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}

		return $robots;
	}

	/**
	 * Registriert den Admin-Menüpunkt „Anleitungen" als Einstieg.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'Anleitungen', 'barmbini-core' ),
			__( 'Anleitungen', 'barmbini-core' ),
			self::CAP_VERKAEUFER,
			self::MENU_SLUG,
			array( $this, 'render_admin_landing' ),
			'dashicons-welcome-learn-more',
			3
		);
	}

	/**
	 * Registriert Schnellzugriff-Links in der Admin-Bar.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin-Bar-Objekt.
	 * @return void
	 */
	public function register_admin_bar_links( $wp_admin_bar ) {
		if ( ! $this->can_view_any() ) {
			return;
		}

		$wp_admin_bar->add_node( array(
			'id'    => 'barmbini-guides',
			'title' => __( 'Anleitungen', 'barmbini-core' ),
			'href'  => admin_url( 'admin.php?page=' . self::MENU_SLUG ),
		) );

		if ( current_user_can( self::CAP_REDAKTEUR ) ) {
			$wp_admin_bar->add_node( array(
				'id'     => 'barmbini-guide-redakteur',
				'parent' => 'barmbini-guides',
				'title'  => __( 'Für Editor', 'barmbini-core' ),
				'href'   => home_url( '/' . self::PAGE_REDAKTEUR . '/' ),
			) );
		}

		if ( current_user_can( self::CAP_VERKAEUFER ) ) {
			$wp_admin_bar->add_node( array(
				'id'     => 'barmbini-guide-verkaeufer',
				'parent' => 'barmbini-guides',
				'title'  => __( 'Für Seller', 'barmbini-core' ),
				'href'   => home_url( '/' . self::PAGE_VERKAEUFER . '/' ),
			) );
		}
	}

	/**
	 * Rendert die Einstiegsseite im Admin mit Links zu beiden Anleitungen.
	 *
	 * @return void
	 */
	public function render_admin_landing() {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Interne Anleitungen', 'barmbini-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Hier findest du die Schritt-für-Schritt-Anleitungen für deine Aufgabe.', 'barmbini-core' ) . '</p>';
		echo '<div style="display:flex;gap:20px;margin-top:20px;flex-wrap:wrap;">';

		$cards = array(
			array(
				'slug'  => self::PAGE_REDAKTEUR,
				'title' => __( 'Anleitung für Editor', 'barmbini-core' ),
				'desc'  => __( 'Aktionen anlegen, Beiträge pflegen, Produkte erstellen.', 'barmbini-core' ),
			),
			array(
				'slug'  => self::PAGE_VERKAEUFER,
				'title' => __( 'Anleitung für Seller', 'barmbini-core' ),
				'desc'  => __( 'Artikel anlegen, Preise ändern, ausverkauft markieren.', 'barmbini-core' ),
			),
		);

		foreach ( $cards as $card ) {
			if ( ! $this->can_view_page( $card['slug'] ) ) {
				continue;
			}

			$url = home_url( '/' . $card['slug'] . '/' );
			echo '<a href="' . esc_url( $url ) . '" style="display:block;width:320px;padding:20px;border:1px solid #ccd0d4;border-radius:8px;text-decoration:none;color:#1d2327;background:#fff;">';
			echo '<strong style="font-size:16px;">' . esc_html( $card['title'] ) . '</strong>';
			echo '<p style="margin-top:8px;">' . esc_html( $card['desc'] ) . '</p>';
			echo '<span style="color:#2271b1;">' . esc_html__( 'Anleitung öffnen →', 'barmbini-core' ) . '</span>';
			echo '</a>';
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Inhalt der Anleitung für Editor.
	 *
	 * @return string
	 */
	public static function redakteur_content() {
		return <<<HTML
<h2>1. Deine Rolle: Was du als Editor/in darfst</h2>
<p>Du bist <strong>Editor/in</strong> auf der Website des Sozialkaufhauses Barmbini. Deine Aufgabe ist es, Inhalte zu pflegen: Neuigkeiten schreiben, Aktionen anlegen und Sortiment-Produkte erstellen und bearbeiten.</p>
<h3>Das kannst du tun</h3>
<ul>
<li><strong>Beiträge („Neuigkeiten“)</strong> erstellen und bearbeiten</li>
<li><strong>Seiten</strong> bearbeiten</li>
<li><strong>Aktionen</strong> für die Startseite anlegen und pflegen</li>
<li><strong>Sortiment-Produkte (Artikel)</strong> erstellen und pflegen</li>
</ul>
<h3>Das kannst du nicht tun</h3>
<ul>
<li>Plugins, Theme oder Einstellungen verwalten</li>
<li>Benutzerkonten verwalten</li>
<li>Systemweite oder sicherheitsrelevante Änderungen</li>
</ul>
<hr>
<h2>2. Eine Aktion erstellen</h2>
<p>Aktionen sind zeitlich begrenzte Hinweise (z.&nbsp;B. ein Sonderangebot, ein Flohmarkt oder ein Spendenaufruf), die auf der Startseite erscheinen.</p>
<ol>
<li>Klicke im linken Menü auf <strong>Aktionen</strong> und danach auf <strong>Neu hinzufügen</strong>.</li>
<li>Vergib einen klaren <strong>Titel</strong>, z.&nbsp;B. „Sommer-Schlussverkauf“.</li>
<li>Schreibe in den Editor eine kurze <strong>Beschreibung</strong>.</li>
<li>Setze unter <strong>Gültigkeitszeitraum</strong> das <strong>Start- und Enddatum</strong>.</li>
<li>Lade unter <strong>Flyer-Bild</strong> das passende Bild hoch (einmalig auf der Startseite).</li>
<li>Klicke rechts auf <strong>Veröffentlichen</strong>.</li>
</ol>
<p><strong>Hinweis:</strong> Aktionen mit zukünftigem Startdatum sind für Besucher noch nicht sichtbar – erst ab dem Startdatum erscheinen sie automatisch.</p>
<h3>Typische Beispiele</h3>
<ul>
<li>„Kreativ-Aktion bis zu 70&nbsp;%“ → Start heute, Ende in zwei Wochen</li>
<li>„Spendenaufruf“ → dauerhaft bis auf Weiteres</li>
</ul>
<hr>
<h2>3. Eine Neuigkeit (Blogbeitrag) schreiben</h2>
<ol>
<li>Klicke im Menü auf <strong>Beiträge → Neu hinzufügen</strong>.</li>
<li>Vergib einen <strong>Titel</strong> und schreibe den Text.</li>
<li>Füge bei Bedarf ein <strong>Beitragsbild</strong> hinzu (rechte Spalte).</li>
<li>Wähle unter <strong>Kategorien</strong> die Kategorie <strong>Neuigkeiten</strong>.</li>
<li>Klicke auf <strong>Veröffentlichen</strong>.</li>
</ol>
<p>Neuigkeiten erscheinen automatisch im Bereich <strong>„Letzte Neuigkeiten“</strong> auf der Startseite.</p>
<hr>
<h2>4. Ein Produkt (Artikel) anlegen oder bearbeiten</h2>
<ol>
<li>Klicke im Menü auf <strong>Produkte → Alle Produkte</strong> oder <strong>Neu hinzufügen</strong>.</li>
<li>Vergib den <strong>Namen</strong> (z.&nbsp;B. „Kinderjacke“) und eine kurze <strong>Beschreibung</strong>.</li>
<li>Setze im Bereich <strong>Produktdaten → Allgemein</strong> den <strong>Preis</strong>.</li>
<li>Lade unter <strong>Produktbild</strong> ein Foto hoch.</li>
<li>Wähle die passende <strong>Kategorie</strong> (rechts).</li>
<li>Klicke auf <strong>Veröffentlichen</strong>.</li>
</ol>
<hr>
<h2>5. Tipps für die tägliche Arbeit</h2>
<ul>
<li>Speichere regelmäßig über <strong>Entwurf speichern</strong>, bevor du veröffentlichst.</li>
<li>Nutze die Vorschau (<strong>Vorschau</strong>), um das Ergebnis zu prüfen.</li>
<li>Verwende klare, kurze Texte – die Besucher kommen zum Stöbern, nicht zum Lesen.</li>
<li>Wenn du unsicher bist: Lege Änderungen als <strong>Entwurf</strong> an und lass sie vor der Veröffentlichung gegenprüfen.</li>
</ul>
<hr>
<h2>6. Häufige Fragen (FAQ)</h2>
<p><strong>Kann ich eine Aktion wieder beenden?</strong><br>Ja. Öffne die Aktion und ändere das Enddatum auf ein Datum in der Vergangenheit – sie verschwindet dann automatisch.</p>
<p><strong>Kann ich gelöschte Beiträge wiederherstellen?</strong><br>Ja. Gelöschte Inhalte landen zunächst im <strong>Papierkorb</strong> und können von dort wiederhergestellt werden.</p>
<p><strong>Warum sehe ich manche Menüpunkte nicht?</strong><br>Als Editor/in hast du bewusst keinen Zugriff auf Plugins, Theme, Einstellungen und Benutzer. Das schützt die Website.</p>
HTML;
	}

	/**
	 * Inhalt der Anleitung für Seller.
	 *
	 * @return string
	 */
	public static function verkaeufer_content() {
		return <<<HTML
<h2>1. Deine Rolle: Was du als Seller/in darfst</h2>
<p>Du bist <strong>Seller/in</strong> im Sozialkaufhaus Barmbini. Deine Aufgabe ist es, das <strong>Sortiment</strong> zu pflegen: neue Artikel einstellen, Preise anpassen, Artikel als ausverkauft markieren und nicht mehr benötigte Artikel zu entfernen.</p>
<h3>Das kannst du tun</h3>
<ul>
<li><strong>Neue Artikel</strong> (Produkte) anlegen und veröffentlichen</li>
<li><strong>Preise</strong> anpassen</li>
<li>Artikel als <strong>ausverkauft</strong> markieren</li>
<li>Artikel in den <strong>Papierkorb</strong> verschieben</li>
</ul>
<h3>Das kannst du nicht tun</h3>
<ul>
<li>Beiträge, Seiten oder Aktionen bearbeiten</li>
<li>Kategorien anlegen, umbenennen oder löschen (nur zuordnen)</li>
<li>Plugins, Theme, Einstellungen oder Benutzer verwalten</li>
<li>Artikel <strong>endgültig</strong> löschen – gelöschte Artikel landen im Papierkorb</li>
</ul>
<hr>
<h2>2. Einen neuen Artikel anlegen</h2>
<ol>
<li>Klicke im Menü auf <strong>Produkte → Neues Produkt hinzufügen</strong>.</li>
<li>Vergib einen klaren <strong>Namen</strong>, z.&nbsp;B. „Kinderjacke Größe 110“.</li>
<li>Schreibe eine kurze <strong>Beschreibung</strong> (Zustand, Größe, Besonderheiten).</li>
<li>Setze den <strong>Preis</strong> unter <strong>Produktdaten → Allgemein</strong>.</li>
<li>Lade unter <strong>Produktbild</strong> ein aussagekräftiges Foto hoch.</li>
<li>Ordne rechts die passende <strong>Kategorie</strong> zu (z.&nbsp;B. „Kleidung“).</li>
<li>Klicke auf <strong>Veröffentlichen</strong>.</li>
</ol>
<p><strong>Tipp:</strong> Ein gutes Foto und ein ehrlicher Zustandshinweis verkaufen sich am besten.</p>
<hr>
<h2>3. Einen Preis anpassen</h2>
<ol>
<li>Öffne unter <strong>Produkte → Alle Produkte</strong> den Artikel.</li>
<li>Wechsle zum Bereich <strong>Produktdaten → Allgemein</strong>.</li>
<li>Ändere das Feld <strong>Preis</strong>.</li>
<li>Klicke auf <strong>Aktualisieren</strong> (rechts oben).</li>
</ol>
<p>Der neue Preis ist sofort im Sortiment sichtbar.</p>
<hr>
<h2>4. Einen Artikel als ausverkauft markieren</h2>
<ol>
<li>Öffne den Artikel unter <strong>Produkte → Alle Produkte</strong>.</li>
<li>Wechsle zum Bereich <strong>Produktdaten → Lagerbestand</strong>.</li>
<li>Setze das Feld <strong>Lagerstatus</strong> auf <strong>Auf Lager / Ausverkauft</strong> – wähle <strong>Ausverkauft</strong>.</li>
<li>Klicke auf <strong>Aktualisieren</strong>.</li>
</ol>
<p>Der Artikel bleibt im Sortiment sichtbar, wird aber als nicht mehr verfügbar gekennzeichnet.</p>
<hr>
<h2>5. Einen Artikel entfernen (Papierkorb)</h2>
<ol>
<li>Öffne unter <strong>Produkte → Alle Produkte</strong> die Liste.</li>
<li>Fahre mit der Maus über den Artikel und klicke auf <strong>Papierkorb</strong> (oder öffne ihn und wähle <strong>In den Papierkorb verschieben</strong>).</li>
</ol>
<p><strong>Wichtig:</strong> Artikel werden nie endgültig gelöscht, sondern nur in den Papierkorb verschoben. So kannst du einen versehentlich entfernten Artikel wiederherstellen.</p>
<hr>
<h2>6. Tipps für die tägliche Arbeit</h2>
<ul>
<li>Speichere <strong>Entwürfe</strong> zwischendurch, bevor du veröffentlichst.</li>
<li>Prüfe die <strong>Vorschau</strong>, bevor du einen Artikel online stellst.</li>
<li>Beschreibe den <strong>Zustand</strong> ehrlich (z.&nbsp;B. „leichte Gebrauchsspuren“).</li>
<li>Für ein neues Foto: <strong>Medien → Neu hinzufügen</strong> zuerst hochladen, dann im Artikel zuordnen.</li>
</ul>
<hr>
<h2>7. Häufige Fragen (FAQ)</h2>
<p><strong>Ich habe einen falschen Preis gespeichert. Was tun?</strong><br>Öffne den Artikel und korrigiere den Preis unter <strong>Produktdaten → Allgemein</strong>. Danach auf <strong>Aktualisieren</strong> klicken.</p>
<p><strong>Ein Artikel ist wieder da, obwohl ich ihn entfernt habe?</strong><br>Entfernte Artikel landen im <strong>Papierkorb</strong>. Dort kannst du sie sehen und bei Bedarf wiederherstellen – oder der Papierkorb wird später geleert.</p>
<p><strong>Kann ich eine Kategorie neu anlegen?</strong><br>Nein. Als Seller/in kannst du nur bestehende Kategorien zuordnen. Neue Kategorien legt ein Administrator an.</p>
<p><strong>Warum sehe ich nicht alle Menüpunkte?</strong><br>Du hast bewusst nur Zugriff auf die Produktverwaltung. Das hält die Website sicher und übersichtlich.</p>
HTML;
	}
}
