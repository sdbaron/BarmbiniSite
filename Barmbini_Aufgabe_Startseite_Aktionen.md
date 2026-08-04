# Detaillierte Aufgabe: Aktionen auf der Startseite anzeigen

## Ziel

Auf der Startseite der Website Sozialkaufhaus Barmbini soll ein Block eingefügt werden, der aktuell gültige Aktionen anzeigt.

Jede Aktion hat ein Start- und ein Enddatum. Nur Aktionen, deren Zeitraum das heutige Datum umfasst, werden auf der Startseite eingeblendet. Sobald das Enddatum überschritten ist, verschwindet die Aktion automatisch – ohne dass ein Redakteur manuell eingreifen muss.

Die Aktionen werden im WordPress-Admin unter einem eigenen Menüpunkt verwaltet. Redakteure können dort Aktionen mit Titel, Beschreibung, einem Flyer-Bild, einem Link und einem klar definierten Gültigkeitszeitraum anlegen. Auf der Startseite erscheint die Komponente über einen Shortcode im Gutenberg-Editor.

In der Regel gibt es eine Aktion – gelegentlich aber auch zwei oder drei gleichzeitig.

## Quellenbasis

Die Aufgabe basiert auf:

- `Barmbini_Technisches_Konzept_v2.5.md` — insbesondere §7.1 (Startseite: Konzept sieht „3 Teaser-Blöcke" und „Bildergalerie" vor)
- `Barmbini_Plugin_Architektur_barmbini-core.md` — Zielstruktur des Plugins, Modulzuschnitt Catalog
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` — verifizierter Ist-Stand und Einbauorte
- dem bestehenden Plugin `wp-content/plugins/barmbini-core/`
- dem bestehenden Shortcode-Muster `[barmbini_latest_news]` in `includes/catalog/class-latest-news-shortcode.php`
- dem bestehenden Registrierungsmuster aus `barmbini-core.php` und `includes/class-plugin.php`

## Fachliche Leitplanken

Die Umsetzung muss zu den bestehenden Projektgrundsätzen passen:

- Die Website ist einsprachig deutsch.
- WooCommerce dient als Produktkatalog, nicht als klassischer Shop mit Checkout.
- Das Projekt folgt dem Minimalprinzip bei Plugins.
- Neue Fachlogik gehört in das Plugin `barmbini-core`, nicht in `themes/kadence/functions.php`.
- Für reine Template- oder CSS-Anpassungen wäre ein Child-Theme die richtige Stelle – diese Aufgabe betrifft aber einen eigenen Inhaltstyp mit Datenhaltung und gehört daher vollständig ins Plugin.
- Der Block wird als Shortcode bereitgestellt, damit er per Gutenberg-Shortcode-Block auf jeder beliebigen Seite eingefügt werden kann.
- Es werden keine externen Drittanbieter-Services oder Schriften geladen.

## Verbindliche Annahmen für diese Aufgabe

Damit die Aufgabe umsetzbar und prüfbar ist, gelten für diese Version folgende Annahmen:

1. „Aktion" ist ein eigener benutzerdefinierter Inhaltstyp (Custom Post Type, CPT) mit dem Slug `barmbini_aktion`.
2. Jede Aktion hat ein Start- und ein Enddatum, die als Post-Meta gespeichert werden.
3. Auf der Startseite werden nur Aktionen angezeigt, deren `start_date ≤ heute ≤ end_date` gilt.
4. Sind mehrere Aktionen gleichzeitig gültig, werden alle angezeigt – üblicherweise 1, maximal etwa 3.
5. Die Sortierung im Frontend erfolgt absteigend nach Startdatum (neueste zuerst).
6. Jede Aktion kann ein Beitragsbild (Flyer) haben, das über die WordPress-Standardfunktion „Beitragsbild" gesetzt wird.
7. Jede Aktion kann einen optionalen Link enthalten (z. B. auf die Sortiment-Seite, ein bestimmtes Produkt oder eine externe URL).
8. Die Komponente ist rein darstellend — sie löst keine Benachrichtigungen aus und interagiert nicht mit dem Abonnementsystem.
9. Der CPT wird im Admin unter einem eigenen Menüpunkt „Aktionen" geführt, getrennt von Beiträgen und WooCommerce.
10. Redakteure (`editor`) dürfen Aktionen anlegen und bearbeiten. Das wird über WordPress-Capabilities gesteuert.

## Nicht Bestandteil dieser Aufgabe

Folgende Punkte gehören ausdrücklich nicht zum Umfang:

- Archivseite oder Einzelansicht für Aktionen (der CPT hat kein öffentliches Single-Template)
- Benachrichtigungs- oder E-Mail-Versand bei neuen Aktionen
- Verknüpfung mit dem Rabatt-System der Abonnements
- Kategorien oder Schlagworte für Aktionen (Taxonomien)
- Wiederkehrende oder automatisch verlängerbare Aktionen
- Mehrsprachigkeit der Aktionen
- Admin-Einstellungsseite für den Shortcode
- Änderungen am Theme oder an `functions.php`

## Umzusetzender Funktionsumfang

Die Lösung muss die folgenden fachlichen Fähigkeiten abdecken:

1. Ein **Custom Post Type `barmbini_aktion`**, der im Admin unter einem eigenen Menüpunkt erscheint.
2. **Metaboxen** für Startdatum, Enddatum und optionalen Link.
3. Ein **Shortcode `[barmbini_promotion]`**, der alle aktuell gültigen Aktionen ausgibt.
4. Der Shortcode ist im **Gutenberg-Editor** über den Shortcode-Block einfügbar.
5. Pro Aktion werden dargestellt: **Flyer-Bild** (Beitragsbild), **Titel**, **Beschreibung**, **Gültigkeitszeitraum** und ein optionaler **Link-Button**.
6. Nur Aktionen, deren Zeitraum das **heutige Datum** umfasst, erscheinen.
7. Bei mehreren gleichzeitig gültigen Aktionen werden **alle** angezeigt.
8. Das erzeugte HTML ist **semantisch korrekt, barrierearm und responsive**.
9. Die Komponente ist mit einer separaten **CSS-Datei** gestaltbar, überschreibt aber kein Theme-CSS unspezifisch.
10. Redakteure können Aktionen ohne technische Kenntnisse anlegen – die Oberfläche entspricht der gewohnten WordPress-Post-Ansicht.

---

## Aufgabe

### 1. Custom Post Type registrieren

**Ziel:** Ein neuer Inhaltstyp `barmbini_aktion` wird im WordPress-Admin sichtbar und ist für Redakteure nutzbar.

**Neue Datei:** `wp-content/plugins/barmbini-core/includes/catalog/class-promotion-post-type.php`

**Name der Klasse:** `Barmbini_Core_Promotion_Post_Type`

**Struktur der Klasse (orientiert am Muster `class-address-shortcode.php`):**

- Eine öffentliche Methode `register()`, die alle WordPress-Hooks für den CPT setzt.
- Eine geschützte Methode `register_post_type()`, die `register_post_type( 'barmbini_aktion', $args )` aufruft.
- Eine geschützte Methode `add_meta_boxes()`, die die Datums- und Link-Metaboxen hinzufügt.
- Eine geschützte Methode `render_start_date_metabox()`, die das Startdatum-Feld rendert.
- Eine geschützte Methode `render_end_date_metabox()`, die das Enddatum-Feld rendert.
- Eine geschützte Methode `render_link_metabox()`, die das Link-Feld rendert.
- Eine geschützte Methode `save_metaboxes( $post_id )`, die die Metabox-Daten beim Speichern sichert.

**`register_post_type()` – Parameter im Detail:**

| Parameter | Wert | Begründung |
|-----------|------|------------|
| `labels.name` | `'Aktionen'` | Menüname |
| `labels.singular_name` | `'Aktion'` | Einzahl |
| `labels.add_new` | `'Neue Aktion'` | Button-Text |
| `labels.add_new_item` | `'Neue Aktion erstellen'` | Überschrift im Editor |
| `labels.edit_item` | `'Aktion bearbeiten'` | |
| `labels.featured_image` | `'Flyer-Bild'` | Ersetzt das generische „Beitragsbild" |
| `labels.set_featured_image` | `'Flyer-Bild auswählen'` | |
| `labels.remove_featured_image` | `'Flyer-Bild entfernen'` | |
| `labels.use_featured_image` | `'Als Flyer-Bild verwenden'` | |
| `public` | `true` | Muss true sein, damit der Gutenberg-Editor nutzbar ist |
| `publicly_queryable` | `false` | Keine Einzelansicht (Kein Frontend-Template nötig) |
| `has_archive` | `false` | Keine Archivseite |
| `show_in_menu` | `true` | Eigenes Menü |
| `menu_position` | `25` | Unter „Kommentare", über „Werkzeuge" |
| `menu_icon` | `'dashicons-megaphone'` | Passendes Icon |
| `supports` | `array( 'title', 'editor', 'thumbnail' )` | Titel, Beschreibung, Flyer-Bild |
| `show_in_rest` | `true` | Gutenberg-Editor-Unterstützung |
| `capability_type` | `array( 'barmbini_aktion', 'barmbini_aktions' )` | Eigene Capabilities |
| `map_meta_cap` | `true` | WordPress löst Cap-Check automatisch auf |

**Hinweis zu Capabilities:** Der CPT verwendet einen eigenen `capability_type`, damit die Berechtigungen sauber zwischen Administrator und Redakteur getrennt werden können. Der Redakteur-Rolle müssen die neuen Capabilities nach der CPT-Registrierung zugewiesen werden (siehe Aufgabe 2).

**Registrierung der Hooks in `register()`:**

```php
public function register() {
    add_action( 'init', array( $this, 'register_post_type' ) );
    add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
    add_action( 'save_post_barmbini_aktion', array( $this, 'save_metaboxes' ), 10, 1 );
}
```

Wichtig: `save_post_barmbini_aktion` ist der spezifische Hook für genau diesen Post-Type – das verhindert, dass die Speicherlogik bei anderen Inhaltstypen läuft.

**Abnahmekriterium:**

- Der Menüpunkt „Aktionen" erscheint im WordPress-Admin.
- Ein Klick auf „Neue Aktion" öffnet den Gutenberg-Editor mit Titel-, Editor- und Beitragsbild-Feld.
- Der Beitragsbild-Bereich zeigt „Flyer-Bild" statt „Beitragsbild".

---

### 2. Redakteur-Rolle mit CPT-Capabilities ausstatten

**Ziel:** Redakteure dürfen Aktionen anlegen, bearbeiten und löschen – aber keine anderen CPT-Einstellungen ändern.

**Umsetzung in derselben Klasse** (`class-promotion-post-type.php`), als Teil der `register()`-Methode:

```php
add_action( 'init', array( $this, 'add_editor_capabilities' ), 11 );
// Priority 11 → läuft NACH register_post_type (Priority 10)
```

**Methode `add_editor_capabilities()`:**

1. Die Rolle `editor` über `get_role( 'editor' )` holen.
2. Wenn die Rolle existiert, folgende Capabilities hinzufügen:

| Capability | Beschreibung |
|------------|-------------|
| `edit_barmbini_aktion` | Aktion bearbeiten |
| `read_barmbini_aktion` | Aktion lesen |
| `delete_barmbini_aktion` | Aktion löschen |
| `edit_barmbini_aktions` | Alle Aktionen bearbeiten |
| `edit_others_barmbini_aktions` | Aktionen anderer Benutzer bearbeiten |
| `publish_barmbini_aktions` | Aktionen veröffentlichen |
| `read_private_barmbini_aktions` | Private Aktionen lesen |
| `delete_barmbini_aktions` | Alle Aktionen löschen |
| `delete_private_barmbini_aktions` | Private Aktionen löschen |
| `delete_published_barmbini_aktions` | Veröffentlichte Aktionen löschen |
| `delete_others_barmbini_aktions` | Aktionen anderer Benutzer löschen |
| `edit_private_barmbini_aktions` | Private Aktionen bearbeiten |
| `edit_published_barmbini_aktions` | Veröffentlichte Aktionen bearbeiten |

3. Die Administrator-Rolle (`administrator`) bekommt dieselben Capabilities (für den Fall, dass ein Admin keine Aktionen sehen kann).

**Alternativer pragmatischer Ansatz (empfohlen für die Erstumsetzung):**

Falls der obige Capability-Ansatz zu komplex ist, kann stattdessen `capability_type` auf `'post'` gesetzt werden. Dann gelten die Standard-Post-Berechtigungen und Redakteure können Aktionen automatisch verwalten. Nachteil: Die Capabilities sind dann nicht granular von normalen Beiträgen trennbar. Für die Erstumsetzung ist das akzeptabel.

**Abnahmekriterium:**

- Ein Benutzer mit der Rolle „Redakteur" kann Aktionen erstellen, bearbeiten und löschen.

---

### 3. Metaboxen für Startdatum, Enddatum und Link

**Ziel:** Im Aktions-Editor erscheinen Felder für den Gültigkeitszeitraum und einen optionalen Link.

**Alle Metaboxen in `add_meta_boxes()` registrieren:**

```php
protected function add_meta_boxes() {
    add_meta_box(
        'barmbini_promotion_dates',
        'Gültigkeitszeitraum',
        array( $this, 'render_dates_metabox' ),
        'barmbini_aktion',
        'side',
        'default'
    );

    add_meta_box(
        'barmbini_promotion_link',
        'Link',
        array( $this, 'render_link_metabox' ),
        'barmbini_aktion',
        'side',
        'default'
    );
}
```

Hinweis: Start- und Enddatum werden in einer gemeinsamen Metabox (`render_dates_metabox`) dargestellt, weil sie fachlich zusammengehören. Die Link-Metabox ist getrennt.

#### 3a. Datums-Metabox (`render_dates_metabox`)

**Feldtypen:** HTML `<input type="date">`

**Meta-Keys (in `wp_postmeta`):**
- `_barmbini_promotion_start_date` – Startdatum im Format `Y-m-d`
- `_barmbini_promotion_end_date` – Enddatum im Format `Y-m-d`

**Pflichtfelder:** Beide Felder sind optional. Ist kein Startdatum gesetzt, gilt die Aktion ab Veröffentlichungsdatum. Ist kein Enddatum gesetzt, läuft die Aktion unbegrenzt (was im Frontend nie passieren sollte, aber technisch möglich ist).

**Empfohlenes Rendering:**

```html
<p>
    <label for="barmbini_promotion_start_date">Startdatum</label>
    <input type="date" id="barmbini_promotion_start_date"
           name="barmbini_promotion_start_date"
           value="[gespeicherter Wert]">
</p>
<p>
    <label for="barmbini_promotion_end_date">Enddatum</label>
    <input type="date" id="barmbini_promotion_end_date"
           name="barmbini_promotion_end_date"
           value="[gespeicherter Wert]">
</p>
```

Der gespeicherte Wert wird mit `get_post_meta( $post->ID, '_barmbini_promotion_start_date', true )` ausgelesen.

#### 3b. Link-Metabox (`render_link_metabox`)

**Feldtyp:** HTML `<input type="url">`

**Meta-Key:** `_barmbini_promotion_link_url`

**Feld ist optional.** Leeres Feld = kein Link im Frontend.

```html
<p>
    <label for="barmbini_promotion_link_url">Ziel-URL</label>
    <input type="url" id="barmbini_promotion_link_url"
           name="barmbini_promotion_link_url"
           class="widefat"
           placeholder="https://..."
           value="[gespeicherter Wert]">
</p>
<p class="description">
    Optional. Wenn gesetzt, wird die Aktion im Frontend mit einem Link versehen.
</p>
```

#### 3c. Speicherlogik (`save_metaboxes`)

**Pflicht-Checks vor dem Speichern:**

1. `wp_verify_nonce` – Sicherstellen, dass die Anfrage vom eigenen Formular kommt. Dazu muss in die Metabox eine `wp_nonce_field( 'barmbini_promotion_meta', 'barmbini_promotion_nonce' )` eingefügt werden.
2. `defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE` – Autosave überspringen.
3. `current_user_can( 'edit_post', $post_id )` – Berechtigungsprüfung.

**Speichervorgang:**

| Feld | Sanitizing | update_post_meta |
|------|-----------|------------------|
| Startdatum | `sanitize_text_field` | `update_post_meta( $post_id, '_barmbini_promotion_start_date', $value )` |
| Enddatum | `sanitize_text_field` | `update_post_meta( $post_id, '_barmbini_promotion_end_date', $value )` |
| Link-URL | `esc_url_raw` | `update_post_meta( $post_id, '_barmbini_promotion_link_url', $value )` |

**Wichtig:** `esc_url_raw` für die URL verwenden, nicht `esc_url`. `esc_url_raw` bereitet die URL für die Datenbank vor, `esc_url` für die Ausgabe. In der Datenbank muss der Rohwert liegen.

**Abnahmekriterium:**

- Datumsfelder und Link-Feld erscheinen in der Seitenleiste des Aktions-Editors.
- Nach dem Speichern sind die eingegebenen Werte beim erneuten Öffnen erhalten.
- Die Nonce-Prüfung verhindert CSRF-Angriffe.

---

### 4. Shortcode-Klasse anlegen

**Ziel:** Eine neue PHP-Klasse im `catalog/`-Modul, die den Shortcode `[barmbini_promotion]` registriert und das Rendering übernimmt.

**Neue Datei:** `wp-content/plugins/barmbini-core/includes/catalog/class-promotion-shortcode.php`

**Name der Klasse:** `Barmbini_Core_Promotion_Shortcode`

Die Klasse folgt exakt dem Muster von `Barmbini_Core_Latest_News_Shortcode`:

- Eine öffentliche Methode `register()` → `add_shortcode( 'barmbini_promotion', array( $this, 'render' ) )`
- Eine öffentliche Methode `render( $atts )` → HTML-Ausgabe

#### 4a. Shortcode-Attribute

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|-------------|
| `show_image` | `bool` | `true` | Flyer-Bild anzeigen (`1`, `true`, `yes`) |
| `show_date` | `bool` | `true` | Gültigkeitszeitraum anzeigen |
| `show_description` | `bool` | `true` | Beschreibung (den Inhalt des Editors) anzeigen |
| `empty_message` | `string` | `''` | Text, der bei keinen gültigen Aktionen erscheint |

#### 4b. Kernabfrage in `render()`

Der zentrale `WP_Query`-Aufruf muss ALLE aktuell gültigen Aktionen finden.

```php
$today = current_time( 'Y-m-d' );

$query_args = array(
    'post_type'      => 'barmbini_aktion',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'meta_value',
    'meta_key'       => '_barmbini_promotion_start_date',
    'order'          => 'DESC',
    'no_found_rows'  => true,
    'meta_query'     => array(
        'relation' => 'AND',
        array(
            'key'     => '_barmbini_promotion_start_date',
            'value'   => $today,
            'compare' => '<=',
            'type'    => 'DATE',
        ),
        array(
            'key'     => '_barmbini_promotion_end_date',
            'value'   => $today,
            'compare' => '>=',
            'type'    => 'DATE',
        ),
    ),
);
```

**Erklärung der `meta_query`:**

- `start_date <= today` — Die Aktion hat bereits begonnen (oder beginnt heute).
- `end_date >= today` — Die Aktion ist noch nicht abgelaufen (oder endet heute).
- `type => 'DATE'` — MySQL vergleicht die Werte als Datum, nicht als String. Das ist entscheidend für korrekte Ergebnisse.
- `relation => 'AND'` — Beide Bedingungen müssen erfüllt sein.

**Achtung bei Aktionen ohne Enddatum:** Die `meta_query` erwartet, dass das Enddatum gesetzt ist. Wenn eine Aktion kein Enddatum hat, wird sie mit dieser Query nicht gefunden. Das ist gewollt – Aktionen sollen immer ein Enddatum haben. Falls doch eine Aktion ohne Enddatum angelegt wird, erscheint sie technisch nie im Frontend. Das ist als Feature zu betrachten, nicht als Bug.

**Hinweis zur Performance:** `posts_per_page => -1` ist hier unproblematisch, weil realistisch nie mehr als 5 Aktionen gleichzeitig gültig sind.

#### 4c. Rendering pro Aktion

**Reihenfolge der Ausgabe für jede Aktion:**

1. Flyer-Bild (Beitragsbild) — wenn `show_image` aktiv und ein Bild gesetzt ist
2. Titel — als `<h3>`, optional verlinkt wenn Link-URL gesetzt
3. Gültigkeitszeitraum — wenn `show_date` aktiv: „Gültig vom [Start] bis zum [Ende]"
4. Beschreibung (der Editor-Inhalt) — wenn `show_description` aktiv
5. Link-Button — wenn Link-URL gesetzt und `show_description` deaktiviert ist (als Ersatz-Call-to-Action)

**HTML-Struktur:**

```html
<div class="barmbini-promotions">
    <article class="barmbini-promotion-item">
        <img class="barmbini-promotion-image"
             src="[thumbnail_url]" alt="[title]">
        <h3 class="barmbini-promotion-title">
            <a href="[link_url]">[title]</a>
            <!-- oder ohne Link: nur [title] -->
        </h3>
        <p class="barmbini-promotion-dates">
            Gültig vom [start_date] bis zum [end_date]
        </p>
        <div class="barmbini-promotion-description">
            [content]
        </div>
        <a class="barmbini-promotion-link button" href="[link_url]">
            Mehr erfahren
        </a>
    </article>
</div>
```

**Flyer-Bild abrufen:**

```php
$image_id  = get_post_thumbnail_id( $post_id );
$image_url = get_the_post_thumbnail_url( $post_id, 'large' );
$image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ?: get_the_title( $post_id );
```

Falls kein Beitragsbild gesetzt ist, das `<img>`-Tag komplett weglassen (kein Platzhalter).

**Datum formatieren:**

Die Rohdaten aus der Datenbank liegen im Format `Y-m-d` vor. Für die Anzeige im Frontend sollen sie in deutschem Format ausgegeben werden:

```php
$start_date = get_post_meta( $post_id, '_barmbini_promotion_start_date', true );
$formatted  = date_i18n( 'j. F Y', strtotime( $start_date ) );
```

`date_i18n` respektiert die in WordPress eingestellte Sprache (Deutsch) und gibt z. B. „15. Januar 2026" aus.

**Link-URL und Button:**

- Die URL wird mit `esc_url()` für die Ausgabe gesichert.
- Der Button-Text kann hartcodiert „Mehr erfahren" sein, oder als Shortcode-Attribut `link_text` konfigurierbar gemacht werden (optional für die Erstumsetzung).
- Ist eine Link-URL gesetzt, wird der Titel als Link dargestellt UND ein Button gerendert. Sind Titel und Button beide verlinkt? Nein – entweder der Titel ist der Link (wenn `show_description` aktiv ist) oder ein separater Button wird gerendert. Empfehlung: Beides – Titel als Link und ein zusätzlicher Button. Das erhöht die Klickrate und ist barrierearm.

**Leerer Zustand:**

Wenn keine gültige Aktion existiert, wird entweder nichts ausgegeben oder die konfigurierte `empty_message`:

```php
if ( ! $query->have_posts() ) {
    wp_reset_postdata();
    if ( '' === $empty_message ) {
        return '';
    }
    return '<div class="barmbini-promotions barmbini-promotions--empty"><p>'
           . esc_html( $empty_message ) . '</p></div>';
}
```

**CSS-Einbindung:**

Die CSS-Datei wird nur enqueued, wenn der Shortcode tatsächlich gerendert wird – nicht global auf jeder Seite:

```php
protected function enqueue_styles() {
    wp_enqueue_style(
        'barmbini-core-promotions',
        BARMBINI_CORE_URL . 'assets/css/promotion.css',
        array(),
        BARMBINI_CORE_VERSION
    );
}
```

Aufruf in `render()` als erstes, bevor das Markup zusammengebaut wird.

**Abnahmekriterium:**

- `[barmbini_promotion]` gibt alle aktuell gültigen Aktionen aus.
- Ohne gültige Aktionen erscheint nichts (oder die `empty_message`).
- Das Markup ist valide HTML5.

---

### 5. CSS-Datei für die Aktionen-Komponente

**Ziel:** Die Aktionen werden ansprechend und responsive dargestellt.

**Neue Datei:** `wp-content/plugins/barmbini-core/assets/css/promotion.css`

**CSS-Vorgaben:**

- Keine absoluten Schriftgrößen, `rem` oder theme-eigene Variablen verwenden.
- Ausreichende Kontraste sicherstellen (mindestens AA-Niveau gemäß WCAG 2.1).
- Keine `!important`-Regeln, die Theme-Styles überschreiben könnten.
- Responsive Darstellung: auf kleinen Bildschirmen (ab 320 px) untereinander, auf größeren (ab 768 px) nebeneinander im Grid.
- Das Flyer-Bild soll nicht breiter als sein Container werden (`max-width: 100%`).
- Der „Mehr erfahren"-Button soll sich optisch am Kadence-Theme orientieren.

**CSS-Klassenstruktur:**

| Klasse | Element |
|--------|---------|
| `.barmbini-promotions` | Äußerer Container (Grid) |
| `.barmbini-promotions--empty` | Container im Leerzustand |
| `.barmbini-promotion-item` | Einzelne Aktion (`<article>`) |
| `.barmbini-promotion-image` | Flyer-Bild |
| `.barmbini-promotion-title` | Aktions-Titel (`<h3>`) |
| `.barmbini-promotion-dates` | Gültigkeitszeitraum |
| `.barmbini-promotion-description` | Beschreibungstext |
| `.barmbini-promotion-link` | Link-Button |

**Empfohlenes Grid-Layout:**

```css
.barmbini-promotions {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
}

@media (min-width: 768px) {
    .barmbini-promotions {
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    }
}
```

Dieses Layout zeigt auf Mobilgeräten eine Spalte, auf Tablets und Desktops automatisch so viele Spalten wie Platz ist (maximal eine Aktion pro Spalte, mindestens 300 px breit).

**Abnahmekriterium:**

- Die Darstellung funktioniert auf Mobilgeräten (320 px Breite) ohne horizontales Scrollen.
- Flyer-Bilder skalieren korrekt und überlappen nicht.
- Kontraste erfüllen AA-Niveau.

---

### 6. Plugin-Bootstrap anpassen

**Ziel:** Die neuen Klassen werden im Plugin geladen und registriert.

#### 6a. `barmbini-core.php` — require_once ergänzen

Die neuen Dateien müssen vor `class-plugin.php` geladen werden, da sie dort instanziiert werden. Einzufügen im Block der `catalog/`-Requires, alphabetisch nach den bestehenden Einträgen:

```php
require_once BARMBINI_CORE_PATH . 'includes/catalog/class-promotion-post-type.php';
require_once BARMBINI_CORE_PATH . 'includes/catalog/class-promotion-shortcode.php';
```

Diese beiden Zeilen zwischen `class-latest-news-shortcode.php` und `class-address-widget.php` einfügen.

#### 6b. `includes/class-plugin.php` — Neue Registrierungsmethode

Nach dem Muster von `register_latest_news_module()` eine neue Methode anlegen:

```php
protected function register_promotion_module() {
    $post_type = new Barmbini_Core_Promotion_Post_Type();
    $post_type->register();

    $shortcode = new Barmbini_Core_Promotion_Shortcode();
    $shortcode->register();
}
```

Diese Methode im Konstruktor aufrufen, nach `$this->register_latest_news_module();`:

```php
protected function __construct() {
    $this->loader = new Barmbini_Core_Loader();
    $this->register_catalog_module();
    $this->register_footer_menu_module();
    $this->register_address_shortcode_module();
    $this->register_latest_news_module();
    $this->register_promotion_module();        // ← NEU
    $this->register_account_module();
    $this->register_notifications_module();
    $this->register_privacy_module();
}
```

**Wichtig:** Der Promotion-Post-Type muss NICHT über `$this->loader` registriert werden, weil der CPT eigene Hooks direkt in `register()` setzt. Der Loader ist nur für Aktionen und Filter, die das Plugin selbst an WordPress delegiert. CPT-Registrierung, Metaboxen und Save-Hooks sind eigenständig.

**Abnahmekriterium:**

- Nach Plugin-Aktivierung ist der Menüpunkt „Aktionen" sichtbar.
- Der Shortcode `[barmbini_promotion]` ist nutzbar.
- Keine PHP-Fehler beim Laden des Plugins.

---

### 7. Lokale Validierung und Sync

**Ziel:** Die Änderungen müssen lokal getestet und mit dem bestehenden Deployment-Workflow synchronisierbar sein.

**Arbeitsschritte:**

1. Das Plugin `barmbini-core` in der lokalen WordPress-Installation (`D:\Local Sites\barmbini\app\public`) aktivieren.
2. Unter „Aktionen → Neue Aktion" mindestens drei Aktionen anlegen:
   - **Aktion A:** Start gestern, Ende morgen → muss erscheinen
   - **Aktion B:** Start vor einer Woche, Ende gestern → darf nicht erscheinen
   - **Aktion C:** Start morgen, Ende nächste Woche → darf nicht erscheinen
3. Jeder Aktion ein Flyer-Bild (Beitragsbild) zuweisen.
4. Eine Aktion mit einem Link versehen.
5. Shortcode-Block `[barmbini_promotion]` auf der Startseite einfügen.
6. Frontend prüfen: Nur Aktion A erscheint. Flyer-Bild, Titel, Beschreibung und Link sind sichtbar.
7. Aktion B so umdatieren, dass sie heute umfasst. Frontend prüfen: Jetzt erscheinen beide.
8. Aktion B wieder auf „gestern" zurückdatieren. Frontend prüfen: Nur noch Aktion A.
9. `sync.ps1` ausführen, um die Plugin-Änderungen vom Workspace in die lokale Installation zu synchronisieren.

**Abnahmekriterium:**

- Die lokale Installation zeigt nur die im Zeitraum gültigen Aktionen an.
- Abgelaufene und zukünftige Aktionen erscheinen nicht.
- `sync.ps1` überträgt die neuen Dateien erfolgreich.
- Der Redakteur kann Aktionen eigenständig pflegen.

---

### 8. Dokumentation aktualisieren

**Ziel:** Projektdokumentation um den neuen CPT und Shortcode ergänzen.

**Arbeitsschritte:**

1. **`Barmbini_Plugin_Architektur_barmbini-core.md`:**
   - Im Catalog-Modul zwei neue Einträge ergänzen:
     - CPT `barmbini_aktion` in `class-promotion-post-type.php`
     - Shortcode `[barmbini_promotion]` in `class-promotion-shortcode.php`
   - Im Zielverzeichnisbaum die neuen Dateien ergänzen.

2. **`Barmbini_Seiteninhalte.md`:**
   - Unter „Startseite" einen neuen Abschnitt „Aktionen" einfügen, der den Shortcode und seine Attribute dokumentiert.

3. **`Barmbini_Vorbereitung_Features_und_Bugfixes.md`:**
   - Den verifizierten Ist-Stand um den CPT `barmbini_aktion` und den Shortcode `[barmbini_promotion]` erweitern.

4. **`Barmbini_Technisches_Konzept_v2.5.md`:**
   - In §7.1 (Startseite) den Punkt „3 Teaser-Blöcke" um den Verweis auf den Aktions-Shortcode ergänzen.

**Abnahmekriterium:**

- Die Dokumentation beschreibt den neuen CPT, den Shortcode und seine Attribute.
- Ein neuer Entwickler kann anhand der Dokumentation verstehen, wie Aktionen funktionieren.

---

## Technische Details

### Datenmodell

#### Post-Meta-Felder (`wp_postmeta`)

| Meta-Key | Typ | Format | Beispiel |
|----------|-----|--------|---------|
| `_barmbini_promotion_start_date` | `string` | `Y-m-d` | `2026-08-01` |
| `_barmbini_promotion_end_date` | `string` | `Y-m-d` | `2026-08-31` |
| `_barmbini_promotion_link_url` | `string` | URL | `https://barmbini.de/sortiment/` |

#### CPT-Eigenschaften

| Eigenschaft | Wert |
|-------------|------|
| Slug | `barmbini_aktion` |
| Menü-Icon | `dashicons-megaphone` |
| Menü-Position | `25` |
| Supports | `title`, `editor`, `thumbnail` |
| REST-API | `true` (Gutenberg) |
| Public | `true` |
| Öffentliche Einzelansicht | Nein (`publicly_queryable=false`) |

### Shortcode-Referenz

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|-------------|
| `show_image` | `bool` | `true` | Flyer-Bild anzeigen |
| `show_date` | `bool` | `true` | Gültigkeitszeitraum anzeigen |
| `show_description` | `bool` | `true` | Beschreibungstext anzeigen |
| `empty_message` | `string` | `''` | Text bei keinen gültigen Aktionen |

### WP_Query-Parameter (Kernabfrage)

```php
$today = current_time( 'Y-m-d' );

$query_args = array(
    'post_type'      => 'barmbini_aktion',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'meta_value',
    'meta_key'       => '_barmbini_promotion_start_date',
    'order'          => 'DESC',
    'no_found_rows'  => true,
    'meta_query'     => array(
        'relation' => 'AND',
        array(
            'key'     => '_barmbini_promotion_start_date',
            'value'   => $today,
            'compare' => '<=',
            'type'    => 'DATE',
        ),
        array(
            'key'     => '_barmbini_promotion_end_date',
            'value'   => $today,
            'compare' => '>=',
            'type'    => 'DATE',
        ),
    ),
);
```

### Zielverzeichnisse

```text
wp-content/plugins/barmbini-core/
├── includes/
│   └── catalog/
│       ├── class-promotion-post-type.php     ← NEU
│       └── class-promotion-shortcode.php     ← NEU
├── assets/
│   └── css/
│       └── promotion.css                     ← NEU
├── includes/
│   └── class-plugin.php                     ← ÄNDERN
└── barmbini-core.php                        ← ÄNDERN
```

### CSS-Klassenstruktur

| Klasse | Element |
|--------|---------|
| `.barmbini-promotions` | Äußerer Container |
| `.barmbini-promotions--empty` | Container im Leerzustand |
| `.barmbini-promotion-item` | Einzelne Aktion (`<article>`) |
| `.barmbini-promotion-image` | Flyer-Bild |
| `.barmbini-promotion-title` | Aktions-Titel |
| `.barmbini-promotion-dates` | Gültigkeitszeitraum |
| `.barmbini-promotion-description` | Beschreibungstext |
| `.barmbini-promotion-link` | Link-Button |

---

## Testfälle

### T1: CPT-Registrierung

| Schritt | Erwartet |
|---------|----------|
| Plugin aktivieren | Menüpunkt „Aktionen" erscheint mit Dashicon-Megaphon |
| Auf „Aktionen" klicken | CPT-Übersichtstabelle erscheint |
| Auf „Neue Aktion" klicken | Gutenberg-Editor öffnet sich mit Titel, Editor, Beitragsbild |
| „Beitragsbild" prüfen | Label zeigt „Flyer-Bild" |

### T2: Redakteur-Zugriff

| Schritt | Erwartet |
|---------|----------|
| Als Redakteur anmelden | Menüpunkt „Aktionen" sichtbar |
| Neue Aktion erstellen | Speichern und Veröffentlichen möglich |
| Aktion bearbeiten | Änderungen werden gespeichert |
| Aktion löschen | Aktion wird in den Papierkorb verschoben |

### T3: Metaboxen speichern

| Schritt | Erwartet |
|---------|----------|
| Startdatum setzen: `2026-08-01` | Nach Speichern wieder `2026-08-01` |
| Enddatum setzen: `2026-08-31` | Nach Speichern wieder `2026-08-31` |
| Link-URL setzen: `https://example.com` | Nach Speichern wieder `https://example.com` |
| Alle Felder leer lassen | Keine Fehler, keine PHP-Warnings |

### T4: Nur gültige Aktionen erscheinen

| Aktion | Start | Ende | Soll erscheinen? |
|--------|-------|------|------------------|
| A | gestern | morgen | ✅ Ja |
| B | vor 7 Tagen | gestern | ❌ Nein |
| C | morgen | nächste Woche | ❌ Nein |
| D | heute | heute | ✅ Ja |

### T5: Mehrere gültige Aktionen

| Schritt | Erwartet |
|---------|----------|
| 2 Aktionen mit gültigem Zeitraum anlegen | Beide erscheinen im Frontend |
| Sortierung prüfen | Neuestes Startdatum zuerst |

### T6: Flyer-Bild

| Schritt | Erwartet |
|---------|----------|
| Aktion mit Flyer-Bild | Bild wird im Frontend angezeigt |
| Aktion ohne Flyer-Bild | Kein `<img>`-Tag, kein Platzhalter |
| `show_image="0"` | Bild wird nicht angezeigt, auch wenn gesetzt |

### T7: Link

| Schritt | Erwartet |
|---------|----------|
| Link-URL gesetzt | Titel ist verlinkt, Button erscheint |
| Keine Link-URL | Titel ist reiner Text, kein Button |

### T8: Leerer Zustand

| Schritt | Erwartet |
|---------|----------|
| Keine gültige Aktion | Nichts wird ausgegeben |
| `empty_message="Keine Aktionen"` | Text erscheint |

### T9: Shortcode-Attribute

| Shortcode | Erwartet |
|-----------|----------|
| `[barmbini_promotion]` | Bild, Datum, Beschreibung sichtbar |
| `[barmbini_promotion show_image="0"]` | Nur Titel, Datum, Beschreibung |
| `[barmbini_promotion show_date="0"]` | Nur Bild, Titel, Beschreibung |
| `[barmbini_promotion show_description="0"]` | Nur Bild, Titel, Datum |

### T10: Responsive Verhalten

| Viewport | Erwartet |
|----------|----------|
| 320 px | Aktionen untereinander, kein horizontales Scrollen |
| 768 px | Aktionen nebeneinander (2-spaltig wenn 2 Aktionen) |
| 1024 px | Grid mit max. 3 Spalten |
| 1440 px | Grid zentriert, Bilder nicht überdehnt |

### T11: Pflicht-Checks

| Test | Erwartet |
|------|----------|
| `wp_reset_postdata()` nach WP_Query | Keine Seiteneffekte auf andere Queries |
| Validität des HTML | W3C-Validator: keine Errors |
| Kontrast Text/Hintergrund | AA-Niveau (mind. 4.5:1 für Normaltext) |
| Tastatur-Navigation | Alle interaktiven Elemente per Tab erreichbar |

---

## Querverweise

- Bestehendes Shortcode-Muster: `includes/catalog/class-latest-news-shortcode.php`
- Plugin-Bootstrap: `barmbini-core.php` (Zeilen 28–47 für require_once-Reihenfolge)
- Modulregistrierung: `includes/class-plugin.php` (`register_latest_news_module()` als Vorlage)
- Startseiten-Konzept: `Barmbini_Technisches_Konzept_v2.5.md` §7.1
- Plugin-Architektur: `Barmbini_Plugin_Architektur_barmbini-core.md`
- Deployment-Workflow: `Barmbini_Aufgabe_Update_von_local_auf_Server.md`
- Admin-Anleitung: `Barmbini_Anleitung_Aktionen_Admin.md`

---

## Nachtrag: Einzelansicht und Entfernung des externen Links (2026-08-04)

Die Aktionen erhielten eine eigene Einzelansicht, erreichbar unter `/aktion/{slug}/`. Das externe Link-Feld wurde komplett entfernt.

### Änderungen

| Bereich | Vorher | Nachher |
|--------|--------|---------|
| `publicly_queryable` | `false` | `true` |
| Link-Metabox | Vorhanden | Entfernt |
| `META_LINK_URL`-Konstante | Vorhanden | Entfernt |
| Shortcode Flyer-Bild | `<img>` ohne Link | `<a href="permalink"><img></a>` |
| Shortcode Titel | Link auf externe URL _oder_ Text | Immer Link auf `get_permalink()` |
| Shortcode Button | „Mehr erfahren" | Entfernt |
| Template | Keines (Theme-Fallback) | `templates/single-barmbini_aktion.php` via `template_include` |
| Einzelansicht | 404 | Volle Seite mit Flyer, Titel, Datum, Inhalt, Beendet-Hinweis |

### Neue Dateien

- `templates/single-barmbini_aktion.php` – Einzelansicht-Template

### Neue Hooks in der CPT-Klasse

- `template_include`-Filter → `load_single_template()`

### Detaildokument

Siehe `Barmbini_Aufgabe_Aktionen_Einzelansicht.md`.

---

## Nachtrag: Pro-Aktion-Beschreibung + Archivseite + Gutenberg-Fix (2026-08-04)

| Änderung | Beschreibung |
|----------|-------------|
| Pro-Aktion-Checkbox | Metabox „Startseiten-Anzeige" mit ☑ „Beschreibung auf der Startseite anzeigen". Shortcode prüft `_barmbini_promotion_show_description` pro Aktion. Globaler `show_description`-Parameter wirkt als Override. |
| Archivseite | `has_archive` auf `true` → `/aktion/` als Übersichtsseite |
| Gutenberg-Fix | `capability_type` von Array auf `'post'` → Block-Editor funktioniert |
| Kadence-Layout | Eigenes Template entfernt, `the_content`-Filter blendet Flyer-Bild + Meta ein |
| Kategorie-Cleanup | `remove_legacy_category()` löscht Standard-Kategorie "Aktion" einmalig |

---

## Nachtrag: Archiv-Ansicht (2026-07-31)

Die Admin-Übersicht der Aktionen wurde um drei gefilterte Ansichten ergänzt:

| View | URL-Parameter | Filterlogik |
|------|--------------|-------------|
| **Aktiv** | (Standard) | `end_date >= heute` ODER kein Enddatum gesetzt |
| **Archiv** | `?promotion_view=archived` | `end_date < heute` |
| **Alle** | `?promotion_view=all` | Kein Filter |

Umsetzung:
- `views_edit-barmbini_aktion`-Filter für die Link-Leiste
- `pre_get_posts`-Action für die Meta-Query
- Direkte SQL-Zählung für die View-Counts
- Abgelaufene Aktionen bleiben `publish` — sie werden nicht in `draft` geändert

---

## Zusammenfassung der zu erstellenden/ändernden Dateien

| Datei | Aktion | Inhalt |
|-------|--------|--------|
| `includes/catalog/class-promotion-post-type.php` | **NEU** | CPT-Registrierung, Capabilities, Metaboxen (Start/End/Link), Speicherlogik |
| `includes/catalog/class-promotion-shortcode.php` | **NEU** | Shortcode `[barmbini_promotion]`, WP_Query mit Datumsfilter, Rendering |
| `assets/css/promotion.css` | **NEU** | Responsive Grid, Flyer-Bild, Button, Leerzustand |
| `barmbini-core.php` | **ÄNDERN** | Zwei `require_once`-Zeilen ergänzen |
| `includes/class-plugin.php` | **ÄNDERN** | `register_promotion_module()` ergänzen und im Konstruktor aufrufen |
| `Barmbini_Plugin_Architektur_barmbini-core.md` | **ÄNDERN** | Catalog-Modul + Verzeichnisbaum ergänzen |
| `Barmbini_Seiteninhalte.md` | **ÄNDERN** | Abschnitt „Aktionen" unter Startseite ergänzen |
| `Barmbini_Vorbereitung_Features_und_Bugfixes.md` | **ÄNDERN** | Verifizierten Ist-Stand ergänzen |
| `Barmbini_Technisches_Konzept_v2.5.md` | **ÄNDERN** | §7.1 ergänzen |
