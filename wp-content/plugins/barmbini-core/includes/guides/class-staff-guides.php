<?php
/**
 * Barmbini Core – Interne Anleitung für Redakteure
 *
 * Stellt eine ausführliche Anleitung für die Rolle „Redakteur“ bereit:
 *
 * - `/anleitung-redakteur/` – für die Rolle „Redakteur“ (Capability `barmbini_view_guide_redakteur`)
 *
 * Die Seite wird vom Plugin automatisch angelegt (idempotent) und ist
 * rollenabhängig sichtbar (Administrator und Redakteur).
 *
 * Besucher und andere Rollen werden umgeleitet; die Seite ist zusätzlich mit
 * `noindex` gegen Suchmaschinen-Indexierung markiert.
 *
 * Zusätzlich gibt es einen kurzen Admin-Menüpunkt „Anleitungen“ sowie einen
 * Link in der Admin-Bar, damit die berechtigten Rollen die Seite schnell finden.
 *
 * Seit 0.9.1 gibt es keine eigene Shop-Manager-Anleitung mehr: Die frühere
 * Seite `/anleitung-verkaeufer/` wird bei `admin_init` automatisch in den
 * Papierkorb verschoben und die veraltete Capability `barmbini_view_guide_verkaeufer`
 * wird entfernt.
 *
 * @package Barmbini_Core
 * @since 0.7.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Barmbini_Core_Staff_Guides {

	const PAGE_REDAKTEUR = 'anleitung-redakteur';
	const CAP_REDAKTEUR  = 'barmbini_view_guide_redakteur';
	const MENU_SLUG      = 'barmbini-anleitungen';

	/** @deprecated Seit 0.9.1 – nur noch für die Entfernung der Alt-Seite. */
	const PAGE_VERKAEUFER = 'anleitung-verkaeufer';

	/** @deprecated Seit 0.9.1 – wird aus allen Rollen entfernt. */
	const CAP_VERKAEUFER = 'barmbini_view_guide_verkaeufer';

	/**
	 * Registriert die Hooks des Anleitungs-Moduls.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'ensure_capabilities' ) );
		add_action( 'admin_init', array( $this, 'ensure_pages' ) );
		add_action( 'admin_init', array( $this, 'maybe_remove_obsolete_verkaeufer_page' ) );
		add_action( 'template_redirect', array( $this, 'gate_frontend_pages' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_bar_menu', array( $this, 'register_admin_bar_links' ), 90 );
		add_filter( 'wp_robots', array( $this, 'noindex_guide_pages' ) );
	}

	/**
	 * Liefert die Slugs der Anleitungsseiten.
	 *
	 * @return array<int,string>
	 */
	public static function get_guide_slugs() {
		return array( self::PAGE_REDAKTEUR );
	}

	/**
	 * Liefert die Rollen, die die Anleitung sehen dürfen.
	 *
	 * @return array<int,string>
	 */
	public static function role_slugs() {
		return array( 'administrator', 'editor' );
	}

	/**
	 * Vergibt die Anleitungs-Capability idempotent an die erlaubten Rollen.
	 *
	 * Administrator und Redakteur erhalten `barmbini_view_guide_redakteur`.
	 * Die veralteten Capabilities `barmbini_view_guide_verkaeufer` und
	 * `barmbini_view_guides` werden aus allen Rollen entfernt.
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
		}

		// Veraltete Capabilities entfernen (seit 0.9.1: keine Shop-Manager-Anleitung).
		foreach ( wp_roles()->roles as $slug => $_role ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}
			if ( $role->has_cap( self::CAP_VERKAEUFER ) ) {
				$role->remove_cap( self::CAP_VERKAEUFER );
			}
			if ( $role->has_cap( 'barmbini_view_guides' ) ) {
				$role->remove_cap( 'barmbini_view_guides' );
			}
		}
	}

	/**
	 * Legt die Anleitungsseite an, falls sie noch nicht existiert.
	 *
	 * @return void
	 */
	public function ensure_pages() {
		if ( get_page_by_path( self::PAGE_REDAKTEUR ) ) {
			return;
		}

		wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => __( 'Anleitung für Redakteure', 'barmbini-core' ),
			'post_name'    => self::PAGE_REDAKTEUR,
			'post_content' => self::redakteur_content(),
		) );
	}

	/**
	 * Entfernt die veraltete Shop-Manager-Anleitung (seit 0.9.1).
	 *
	 * Die Seite wird in den Papierkorb verschoben (reversibel), damit keine
	 * verwaiste, unberechtigte Anleitung mehr öffentlich erreichbar ist.
	 *
	 * @return void
	 */
	public function maybe_remove_obsolete_verkaeufer_page() {
		$page = get_page_by_path( self::PAGE_VERKAEUFER );
		if ( ! $page || empty( $page->ID ) ) {
			return;
		}

		wp_trash_post( $page->ID );
	}

	/**
	 * Prüft, ob die aktuelle Seite die Anleitungsseite ist.
	 *
	 * @return bool
	 */
	public function is_guide_page() {
		return is_page( self::get_guide_slugs() );
	}

	/**
	 * Prüft, ob der aktuelle Besucher die Anleitung sehen darf.
	 *
	 * @param string $slug Seiten-Slug der Anleitung.
	 * @return bool
	 */
	public function can_view_page( $slug ) {
		if ( self::PAGE_REDAKTEUR === $slug ) {
			return current_user_can( self::CAP_REDAKTEUR );
		}

		return false;
	}

	/**
	 * Prüft, ob der Besucher die Anleitung sehen darf.
	 *
	 * @return bool
	 */
	public function can_view_any() {
		return current_user_can( self::CAP_REDAKTEUR );
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
	 * Liefert die URL der für den Besucher zugänglichen Anleitung.
	 *
	 * @return string Leer, wenn keine Anleitung zugänglich ist.
	 */
	public function first_accessible_guide_url() {
		if ( current_user_can( self::CAP_REDAKTEUR ) ) {
			return home_url( '/' . self::PAGE_REDAKTEUR . '/' );
		}

		return '';
	}

	/**
	 * Schützt die Anleitungsseite vor nicht berechtigten Besuchern.
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
	 * Verhindert die Indexierung der Anleitungsseite durch Suchmaschinen.
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
			self::CAP_REDAKTEUR,
			self::MENU_SLUG,
			array( $this, 'render_admin_landing' ),
			'dashicons-welcome-learn-more',
			3
		);
	}

	/**
	 * Registriert einen Schnellzugriff-Link in der Admin-Bar.
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
				'title'  => __( 'Für Redakteure', 'barmbini-core' ),
				'href'   => home_url( '/' . self::PAGE_REDAKTEUR . '/' ),
			) );
		}
	}

	/**
	 * Rendert die Einstiegsseite im Admin mit Link zur Anleitung.
	 *
	 * @return void
	 */
	public function render_admin_landing() {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Interne Anleitung', 'barmbini-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Hier findest du die Schritt-für-Schritt-Anleitung für deine Aufgabe.', 'barmbini-core' ) . '</p>';
		echo '<div style="display:flex;gap:20px;margin-top:20px;flex-wrap:wrap;">';

		$cards = array(
			array(
				'slug'  => self::PAGE_REDAKTEUR,
				'title' => __( 'Anleitung für Redakteure', 'barmbini-core' ),
				'desc'  => __( 'Aktionen anlegen, Beiträge pflegen, Seiten bearbeiten, Produkte erstellen.', 'barmbini-core' ),
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
	 * Inhalt der Anleitung für Redakteure.
	 *
	 * @return string
	 */
	public static function redakteur_content() {
		return <<<HTML
<h2>1. Deine Rolle: Was du als Redakteur/in darfst</h2>
<p>Du bist <strong>Redakteur/in</strong> auf der Website des Sozialkaufhauses Barmbini. Deine Aufgabe ist es, Inhalte zu pflegen: Neuigkeiten schreiben, Aktionen anlegen, Seiten bearbeiten und Sortiment-Produkte erstellen und bearbeiten.</p>
<h3>Das kannst du tun</h3>
<ul>
<li><strong>Beiträge („Neuigkeiten“)</strong> erstellen, bearbeiten und veröffentlichen</li>
<li><strong>Seiten</strong> bearbeiten (Texte, Bilder, Abschnitte)</li>
<li><strong>Aktionen</strong> für die Startseite anlegen und pflegen</li>
<li><strong>Sortiment-Produkte (Artikel)</strong> erstellen und bearbeiten</li>
<li><strong>Medien</strong> hochladen und verwalten (Bilder)</li>
</ul>
<h3>Das kannst du nicht tun</h3>
<ul>
<li>Plugins, Theme oder Einstellungen verwalten</li>
<li>Benutzerkonten verwalten</li>
<li>Systemweite oder sicherheitsrelevante Änderungen</li>
</ul>
<p><strong>Grundprinzip:</strong> Alles, was du bearbeitest, wird zuerst als <strong>Entwurf</strong> gespeichert und erst sichtbar, wenn du auf <strong>Veröffentlichen</strong> oder <strong>Aktualisieren</strong> klickst. So kannst du in Ruhe arbeiten, ohne dass Besucher Zwischenstände sehen.</p>
<hr>
<h2>2. Eine Aktion erstellen</h2>
<p>Aktionen sind zeitlich begrenzte Hinweise (z.&nbsp;B. ein Sonderangebot, ein Flohmarkt oder ein Spendenaufruf), die auf der Startseite erscheinen. Eine Aktion hat immer ein <strong>Start- und Enddatum</strong>.</p>
<h3>So legst du eine Aktion an</h3>
<ol>
<li>Klicke im linken Menü auf <strong>Aktionen</strong> und danach auf <strong>Neu hinzufügen</strong>.</li>
<li>Vergib einen klaren <strong>Titel</strong>, z.&nbsp;B. „Sommer-Schlussverkauf“.</li>
<li>Schreibe in den Editor eine kurze <strong>Beschreibung</strong> (2–4 Sätze reichen: Was gibt es? Für wen? Wie lange?).</li>
<li>Setze unter <strong>Gültigkeitszeitraum</strong> das <strong>Start- und Enddatum</strong>.</li>
<li>Lade unter <strong>Flyer-Bild</strong> das passende Bild hoch (einmalig auf der Startseite).</li>
<li>Klicke rechts auf <strong>Veröffentlichen</strong>.</li>
</ol>
<h3>Das solltest du beachten</h3>
<ul>
<li>Aktionen mit <strong>zukünftigem Startdatum</strong> sind für Besucher noch nicht sichtbar – sie erscheinen automatisch ab dem Startdatum.</li>
<li>Nach dem <strong>Enddatum</strong> verschwindet die Aktion automatisch von der Startseite.</li>
<li>Ein gutes <strong>Flyer-Bild</strong> ist quer (z.&nbsp;B. 1600×900 px) und zeigt das Angebot auf einen Blick.</li>
<li>Du kannst eine Aktion jederzeit öffnen, das Enddatum in die Vergangenheit setzen und auf <strong>Aktualisieren</strong> klicken, um sie sofort zu beenden.</li>
</ul>
<h3>Typische Beispiele</h3>
<ul>
<li>„Kreativ-Aktion bis zu 70&nbsp;%“ → Start heute, Ende in zwei Wochen</li>
<li>„Spendenaufruf“ → dauerhaft bis auf Weiteres (Enddatum weit in der Zukunft setzen)</li>
<li>„Flohmarkt am 12. September“ → Start und Ende am selben Tag bzw. für den Aktionszeitraum</li>
</ul>
<hr>
<h2>3. Eine Neuigkeit (Blogbeitrag) schreiben</h2>
<p>Neuigkeiten erscheinen automatisch im Bereich <strong>„Letzte Neuigkeiten“</strong> auf der Startseite. Die neuesten Beiträge stehen oben.</p>
<h3>So legst du einen Beitrag an</h3>
<ol>
<li>Klicke im Menü auf <strong>Beiträge → Neu hinzufügen</strong>.</li>
<li>Vergib einen aussagekräftigen <strong>Titel</strong> (z.&nbsp;B. „Wir haben wieder tolle Kindersachen im Angebot“).</li>
<li>Schreibe den <strong>Text</strong>. Gliedere längere Texte mit Zwischenüberschriften (Absatz-Symbol → Überschrift) und kurzen Absätzen.</li>
<li>Füge bei Bedarf ein <strong>Beitragsbild</strong> hinzu (rechte Spalte → „Beitragsbild festlegen“). Es erscheint als Vorschaubild in der Neuigkeiten-Liste.</li>
<li>Wähle unter <strong>Kategorien</strong> die Kategorie <strong>Neuigkeiten</strong>.</li>
<li>Klicke auf <strong>Veröffentlichen</strong>.</li>
</ol>
<h3>Das solltest du beachten</h3>
<ul>
<li>Der <strong>Auszug</strong> (Text, der in der Übersicht gezeigt wird) kann unter „Auszug“ rechts separat gepflegt werden – sonst wird der Textanfang automatisch genutzt.</li>
<li>Beitragsbilder lädst du am besten vorher unter <strong>Medien → Neu hinzufügen</strong> hoch und wählst sie dann aus (siehe Abschnitt 6).</li>
<li>Du kannst Beiträge auch <strong>planen</strong>: Statt „Veröffentlichen“ auf den Pfeil daneben klicken → „Veröffentlichung planen“ → Datum/Uhrzeit wählen (siehe Abschnitt 7).</li>
<li>Ein Rechtschreib- und Blick-Check vor dem Veröffentlichen spart spätere Korrekturen.</li>
</ul>
<hr>
<h2>4. Eine Seite bearbeiten</h2>
<p>Seiten sind feste Inhaltsseiten (z.&nbsp;B. „Über uns“, „Kontakt“, „So funktioniert es“). Als Redakteur/in kannst du sie bearbeiten – bitte ändere nur Inhalte, keine technischen Einstellungen.</p>
<h3>So bearbeitest du eine Seite</h3>
<ol>
<li>Klicke im Menü auf <strong>Seiten</strong> und wähle die gewünschte Seite (oder „Alle Seiten“ und dann die Zeile anklicken).</li>
<li>Bearbeite die Inhalte direkt im <strong>Editor</strong>: Texte anklicken und ändern, Bilder über das <strong>+</strong>-Symbol einfügen („Bild“-Block).</li>
<li>Entferne nichts, was du nicht sicher ersetzt hast – lass unbekannte Blöcke stehen.</li>
<li>Klicke oben rechts auf <strong>Aktualisieren</strong>, um die Änderung zu speichern.</li>
</ol>
<h3>Das solltest du beachten</h3>
<ul>
<li>Änderungen an Seiten sind <strong>sofort live</strong>, sobald du auf „Aktualisieren“ klickst. Prüfe deshalb vorher die <strong>Vorschau</strong>.</li>
<li>Möchtest du einen größeren Umbau machen, arbeite in Ruhe und veröffentliche erst, wenn alles passt, oder nutze die <strong>Vorschau</strong> im neuen Tab.</li>
<li>Die <strong>Startseite</strong> und <strong>Sortiment</strong>-Seite sind zentral – ändere dort nur, wenn du dir ganz sicher bist.</li>
<li>Blöcke kannst du mit dem <strong>+</strong> unten oder oben einfügen und mit den Pfeilen nach links/rechts ziehen, um die Reihenfolge zu ändern.</li>
</ul>
<hr>
<h2>5. Ein Produkt (Artikel) anlegen oder bearbeiten</h2>
<p>Im Sortiment erscheinen alle Artikel als Produkte. Ein sauberer Artikel hat einen klaren Namen, eine ehrliche Beschreibung, einen Preis, ein Foto und eine passende Kategorie.</p>
<h3>So legst du einen Artikel an</h3>
<ol>
<li>Klicke im Menü auf <strong>Produkte → Neu hinzufügen</strong>.</li>
<li>Vergib den <strong>Namen</strong> (z.&nbsp;B. „Kinderjacke Größe 110“ – Größe/Zustand direkt in den Namen, das hilft beim Stöbern).</li>
<li>Schreibe eine kurze <strong>Beschreibung</strong>: Zustand, Größe, Material, Besonderheiten.</li>
<li>Setze im Bereich <strong>Produktdaten → Allgemein</strong> den <strong>Preis</strong> (in Euro, ohne Währungszeichen, z.&nbsp;B. 3,50).</li>
<li>Lade unter <strong>Produktbild</strong> ein Foto hoch (gutes Licht, Artikel zentriert).</li>
<li>Wähle die passende <strong>Kategorie</strong> (rechte Spalte, z.&nbsp;B. „Kleidung“).</li>
<li>Klicke auf <strong>Veröffentlichen</strong>.</li>
</ol>
<h3>So änderst du einen Preis</h3>
<ol>
<li>Öffne den Artikel unter <strong>Produkte → Alle Produkte</strong>.</li>
<li>Wechsle zu <strong>Produktdaten → Allgemein</strong> und ändere das Feld <strong>Preis</strong>.</li>
<li>Klicke auf <strong>Aktualisieren</strong>. Der neue Preis ist sofort im Sortiment sichtbar.</li>
</ol>
<h3>So markierst du einen Artikel als ausverkauft</h3>
<ol>
<li>Öffne den Artikel unter <strong>Produkte → Alle Produkte</strong>.</li>
<li>Wechsle zu <strong>Produktdaten → Lagerbestand</strong>.</li>
<li>Setze <strong>Lagerstatus</strong> auf <strong>Ausverkauft</strong>.</li>
<li>Klicke auf <strong>Aktualisieren</strong>. Der Artikel bleibt sichtbar, wird aber als nicht mehr verfügbar gekennzeichnet.</li>
</ol>
<h3>Das solltest du beachten</h3>
<ul>
<li>Beschreibe den <strong>Zustand</strong> ehrlich (z.&nbsp;B. „leichte Gebrauchsspuren“). Das schafft Vertrauen.</li>
<li>Ein <strong>gutes Foto</strong> ist das Wichtigste: hell, scharf, ohne Hintergrund-Chaos.</li>
<li>Lösche Artikel nie direkt – verschiebe sie in den <strong>Papierkorb</strong> (siehe FAQ).</li>
</ul>
<hr>
<h2>6. Medien: Bilder hochladen und einfügen</h2>
<p>Alle Bilder liegen zentral in der <strong>Mediathek</strong>. Du kannst sie dort hochladen und in Beiträgen, Seiten und Produkten wiederverwenden.</p>
<h3>So lädst du ein Bild hoch</h3>
<ol>
<li>Klicke im Menü auf <strong>Medien → Neu hinzufügen</strong>.</li>
<li>Ziehe die Datei ins Fenster oder klicke auf „Dateien auswählen“.</li>
<li>Wähle möglichst das <strong>Original</strong> (nicht verkleinert) – die Website erstellt automatisch passende Größen.</li>
<li>Nach dem Hochladen erscheint das Bild in der Mediathek. Klicke es an, um <strong>Titel</strong> und <strong>Alternativtext</strong> (Alt-Text) zu pflegen. Der Alt-Text beschreibt das Bild für Menschen mit Seheinschränkungen und hilft der Auffindbarkeit.</li>
</ol>
<h3>So fügst du ein Bild in einen Beitrag ein</h3>
<ol>
<li>Setze den Cursor an die Stelle im Text, an der das Bild erscheinen soll.</li>
<li>Klicke auf das <strong>+</strong>-Symbol und wähle den Block <strong>Bild</strong>.</li>
<li>Klicke im Block auf „Hochladen“ (neue Datei) oder „Mediathek“ (bestehendes Bild auswählen).</li>
<li>Nutze rechts das Bedienfeld, um die <strong>Ausrichtung</strong> (z.&nbsp;B. mittig) und die <strong>Größe</strong> einzustellen.</li>
</ol>
<p><strong>Tipp:</strong> Nutze bei Produkten das Feld <strong>Produktbild</strong> statt den Bild-Block – so erscheint das Bild automatisch korrekt in der Sortiments-Liste.</p>
<hr>
<h2>7. Veröffentlichen &amp; Planen</h2>
<p>Nicht jeder Inhalt muss sofort online. WordPress bietet dir dafür drei Möglichkeiten:</p>
<h3>Entwurf (noch nicht sichtbar)</h3>
<ul>
<li>Klicke auf <strong>Entwurf speichern</strong>, um den Stand zu sichern, ohne etwas zu veröffentlichen. Das ist ideal, wenn du später weitermachen willst.</li>
</ul>
<h3>Sofort veröffentlichen</h3>
<ul>
<li>Klicke auf <strong>Veröffentlichen</strong> (oder bei bestehenden Inhalten <strong>Aktualisieren</strong>). Der Inhalt ist sofort für alle sichtbar.</li>
</ul>
<h3>Planen (später automatisch veröffentlichen)</h3>
<ol>
<li>Klicke neben „Veröffentlichen“ auf den kleinen <strong>Pfeil nach unten</strong>.</li>
<li>Wähle <strong>Veröffentlichung planen</strong>.</li>
<li>Setze Datum und Uhrzeit und bestätige. WordPress veröffentlicht den Inhalt dann automatisch zum gewählten Zeitpunkt.</li>
</ol>
<p><strong>Hinweis:</strong> Der Zeitplan nutzt die Zeitzone der Website. Prüfe deshalb die angezeigte Zeit, wenn du für einen bestimmten Termin planst.</p>
<hr>
<h2>8. Tipps für die tägliche Arbeit</h2>
<ul>
<li>Speichere regelmäßig über <strong>Entwurf speichern</strong>, bevor du veröffentlichst – so geht nichts verloren.</li>
<li>Nutze die <strong>Vorschau</strong>, bevor du Inhalte live schaltest. Prüfe auch die Mobilgeräte-Ansicht (Symbol mit Handy), weil viele Besucher mit dem Handy kommen.</li>
<li>Verwende klare, kurze Texte – die Besucher kommen zum Stöbern, nicht zum Lesen. Wichtige Infos zuerst.</li>
<li>Prüfe deine Änderung einmal auf <strong>Rechtschreibung</strong> und ob alle Links und Bilder funktionieren.</li>
<li>Wenn du unsicher bist: Lege Änderungen als <strong>Entwurf</strong> an und lass sie vor der Veröffentlichung gegenprüfen.</li>
<li>Wiederhole dich nicht: Ein bestehender Beitrag/eine bestehende Aktion, die aktualisiert wird, ist oft besser als ein neuer, ähnlicher Inhalt.</li>
</ul>
<hr>
<h2>9. Häufige Fragen (FAQ)</h2>
<p><strong>Kann ich eine Aktion wieder beenden?</strong><br>Ja. Öffne die Aktion und ändere das Enddatum auf ein Datum in der Vergangenheit – sie verschwindet dann automatisch von der Startseite.</p>
<p><strong>Kann ich gelöschte Beiträge wiederherstellen?</strong><br>Ja. Gelöschte Inhalte landen zunächst im <strong>Papierkorb</strong> und können von dort wiederhergestellt werden (Beiträge → Papierkorb → „Wiederherstellen“).</p>
<p><strong>Warum sehe ich manche Menüpunkte nicht?</strong><br>Als Redakteur/in hast du bewusst keinen Zugriff auf Plugins, Theme, Einstellungen und Benutzer. Das schützt die Website.</p>
<p><strong>Kann ich einen Artikel endgültig löschen?</strong><br>Artikel verschiebst du am besten in den <strong>Papierkorb</strong>. Endgültig löschen ist im Normalfall nicht nötig – der Papierkorb hält das Sortiment sauber und erlaubt Korrekturen.</p>
<p><strong>Ich habe einen Fehler veröffentlicht. Was tun?</strong><br>Kein Problem: Öffne den Inhalt, korrigiere den Text und klicke auf <strong>Aktualisieren</strong>. Die Korrektur ist sofort live.</p>
<p><strong>Wie finde ich ein Bild in der Mediathek wieder?</strong><br>Nutze oben in der Mediathek das <strong>Suchfeld</strong> oder filtere nach Dateityp („Bilder“). Achte beim Hochladen auf sprechende Dateinamen.</p>
HTML;
	}
}
