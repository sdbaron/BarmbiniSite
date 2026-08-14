# Detaillierte Aufgabe: Aktionen – Einzelansicht und Entfernung des externen Links

## Ziel

Die Aktionen (CPT `barmbini_aktion`) sollen eine eigene Einzelansicht erhalten – wie normale WordPress-Beiträge. Wenn ein Besucher auf der Startseite auf den Flyer oder Titel einer Aktion tippt, gelangt er zur Einzelseite der Aktion mit allen Details.

Gleichzeitig wird das bisherige „Link"-Feld (externe URL) aus dem CPT entfernt. Es gab die Möglichkeit, eine beliebige externe URL zu hinterlegen. Diese wird nicht mehr benötigt – der natürliche Weg führt immer zur Einzelansicht der Aktion. Der „Mehr erfahren"-Button entfällt ebenfalls.

## Quellenbasis

Die Aufgabe basiert auf:

- `Tasks/Barmbini_Aufgabe_Startseite_Aktionen.md` – bestehende CPT- und Shortcode-Spezifikation
- `Barmbini_Plugin_Architektur_barmbini-core.md` – Zielstruktur des Plugins
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` – verifizierter Ist-Stand
- `class-promotion-post-type.php` – aktuelle CPT-Klasse (Stand 2026-08-04)
- `class-promotion-shortcode.php` – aktueller Shortcode (Stand 2026-08-04)
- `promotion.css` – aktuelle Styles (Stand 2026-08-04)
- `Docs/Barmbini_Anleitung_Aktionen_Admin.md` – Admin-Anleitung

## Fachliche Leitplanken

- Neue Fachlogik gehört ins Plugin `barmbini-core`, nicht ins Theme.
- Das Single-Template wird aus dem Plugin-Ordner `templates/` geladen, nicht aus dem Theme.
- Die Einzelansicht nutzt den Header und Footer des aktiven Themes (Kadence), nur der Inhaltsbereich wird vom Plugin bestimmt.
- Keine externen Drittanbieter-Services oder Schriften.
- Keine neuen Datenbank-Tabellen.

## Verbindliche Annahmen

1. Der CPT-Slug bleibt `barmbini_aktion`. Die Einzelansicht ist unter `/aktion/{slug}/` erreichbar.
2. `publicly_queryable` wird von `false` auf `true` geändert.
3. Das externe Link-Feld (`_barmbini_promotion_link_url`) wird **komplett entfernt** – Metabox, Speicherlogik, Meta-Key-Konstante.
4. Bestehende Daten in `_barmbini_promotion_link_url` werden **nicht** gelöscht (sie verbleiben in der Datenbank, werden aber nicht mehr verwendet). Ein Cleanup-Migration ist nicht Teil dieser Aufgabe.
5. Flyer-Bild und Titel auf der Startseite verlinken **immer** auf `get_permalink()` der Aktion.
6. Der „Mehr erfahren"-Button im Shortcode entfällt ersatzlos.
7. Abgelaufene Aktionen in der Einzelansicht sind weiterhin aufrufbar, erhalten aber einen Hinweis „Diese Aktion ist beendet".
8. Es wird **keine** Archiv-Übersichtsseite (`/aktionen/`) erstellt – nur die Einzelansicht.
9. Das Template wird via `template_include`-Filter geladen (Plugin-seitig, nicht theme-seitig).

## Nicht Bestandteil dieser Aufgabe

- Archiv-Übersichtsseite für Aktionen
- SEO-Metaboxen (Yoast greift automatisch, da `public => true`)
- Breadcrumb-Integration für die Einzelansicht
- Änderung der Rewrite-Slug-Struktur
- Migration oder Löschung bestehender Link-Meta-Daten
- Kommentarfunktion für Aktionen

---

## Umzusetzender Funktionsumfang

1. CPT `publicly_queryable` von `false` auf `true` ändern → Einzelansicht existiert.
2. Externes Link-Feld **komplett entfernen** (Metabox, Render-Methode, Speicherlogik, Konstante).
3. Single-Template `templates/single-barmbini_aktion.php` erstellen.
4. Template-Ladung via `template_include`-Filter in der CPT-Klasse.
5. Shortcode anpassen: Flyer und Titel verlinken auf `get_permalink()`, Button entfernen.
6. CSS bereinigen (Button-Stile entfernen, ggf. Hover für verlinktes Flyer-Bild).
7. Dokumentation aktualisieren.

---

## Aufgabe

### 1. CPT: `publicly_queryable` aktivieren

**Ziel:** Jede Aktion erhält eine öffentliche URL und eine Einzelansicht.

**Datei:** `includes/catalog/class-promotion-post-type.php`  
**Methode:** `register_post_type()`

**Änderung:** In den `$args` den Wert von `'publicly_queryable'` von `false` auf `true` setzen.

```php
'publicly_queryable' => true,
```

**Wichtig:** Damit die neuen Permalinks funktionieren, muss nach dieser Änderung einmalig `flush_rewrite_rules()` aufgerufen werden. Das geschieht am einfachsten durch einmaliges Speichern der Permalink-Einstellungen unter „Einstellungen → Permalinks" im Admin. Alternativ kann die `register_activation_hook` in `class-activator.php` um einen `flush_rewrite_rules()`-Aufruf erweitert werden – dies ist aber nicht Teil dieser Aufgabe.

**Abnahmekriterium:**

- Eine veröffentlichte Aktion ist unter `/aktion/{slug}/` erreichbar.
- WordPress liefert kein 404, sondern rendert die Seite (zunächst mit dem Fallback-Template des Themes).

---

### 2. Externes Link-Feld komplett entfernen

**Ziel:** Alle Code-Stellen, die mit dem externen Link (`_barmbini_promotion_link_url`) zu tun haben, werden entfernt.

**Datei:** `includes/catalog/class-promotion-post-type.php`

**Zu entfernende Bestandteile:**

| Was | Wo | Aktion |
|-----|-----|--------|
| Konstante `META_LINK_URL` | Zeile ~28 | **Zeile löschen** |
| Kommentar „Meta-Keys für Start-, Enddatum und Link" | Zeile ~24 | **Umformulieren** zu „Meta-Keys für Start- und Enddatum" |
| `add_meta_box( 'barmbini_promotion_link', … )` | In `add_meta_boxes()` | **Block löschen** |
| `render_link_metabox()` | Komplette Methode | **Methode löschen** |
| Link-Speicherung in `save_metaboxes()` | Die Zeilen für `$link_url = …` und `$this->save_meta_value( …, self::META_LINK_URL, … )` | **Beide Zeilen entfernen** |
| Datei-Kommentar „optionalem Link" | Zeile 5 | **Umformulieren** zu „Start-/Enddatum und Flyer-Bild" |

**Achtung bei `save_metaboxes()`:** Die Nonce-Prüfung, Autosave-Check und Capability-Check bleiben erhalten. Nur die Link-spezifischen Zeilen entfallen.

**Abnahmekriterium:**

- Im Aktions-Editor erscheint keine „Link"-Metabox mehr.
- Speichern einer Aktion löst keine PHP-Notices oder -Warnings aus.
- Die Seite `class-promotion-post-type.php` enthält kein Vorkommen von `META_LINK_URL` oder `link_url` mehr.

---

### 3. Single-Template erstellen

**Ziel:** Eine eigene Template-Datei für die Einzelansicht der Aktion.

**Neue Datei:** `templates/single-barmbini_aktion.php`

**Pfad:** `wp-content/plugins/barmbini-core/templates/single-barmbini_aktion.php`

Das Template wird innerhalb des Theme-Layouts gerendert (Header/Footer kommen vom Kadence-Theme). Es steuert nur den Inhaltsbereich.

**Inhalt des Templates:**

```php
<?php
/**
 * Single-Template für Aktionen (barmbini_aktion).
 *
 * Wird via template_include-Filter aus dem Plugin geladen.
 * Header und Footer kommen vom aktiven Theme (Kadence).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<main class="barmbini-single-promotion">
    <article <?php post_class( 'barmbini-single-promotion__article' ); ?>>

        <?php if ( has_post_thumbnail() ) : ?>
            <div class="barmbini-single-promotion__image">
                <?php the_post_thumbnail( 'large' ); ?>
            </div>
        <?php endif; ?>

        <h1 class="barmbini-single-promotion__title">
            <?php the_title(); ?>
        </h1>

        <?php
        $start_date = get_post_meta( get_the_ID(), '_barmbini_promotion_start_date', true );
        $end_date   = get_post_meta( get_the_ID(), '_barmbini_promotion_end_date', true );
        $today      = current_time( 'Y-m-d' );

        if ( $start_date && $end_date ) :
            ?>
            <p class="barmbini-single-promotion__dates">
                Gültig vom <?php echo esc_html( date_i18n( 'j. F Y', strtotime( $start_date ) ) ); ?>
                bis zum <?php echo esc_html( date_i18n( 'j. F Y', strtotime( $end_date ) ) ); ?>
            </p>
        <?php endif; ?>

        <?php if ( $end_date && $end_date < $today ) : ?>
            <div class="barmbini-single-promotion__expired">
                <strong>Hinweis:</strong> Diese Aktion ist beendet.
            </div>
        <?php endif; ?>

        <div class="barmbini-single-promotion__content">
            <?php
            while ( have_posts() ) :
                the_post();
                the_content();
            endwhile;
            ?>
        </div>

    </article>
</main>

<?php
get_footer();
```

**Design-Vorgaben für das Template:**

- Das Flyer-Bild wird in voller Breite (`large`) dargestellt, `max-width: 100%`.
- Titel als `<h1>` (ist die Hauptüberschrift der Seite).
- Datum & „Beendet"-Hinweis unter dem Titel, vor dem Inhalt.
- Der „Beendet"-Hinweis erscheint nur, wenn das Enddatum **vor** heute liegt.
- Der Editor-Inhalt wird mit `the_content()` ausgegeben (Gutenberg-Blöcke funktionieren).
- Keine Seitenleiste (Full-Width).

**Abnahmekriterium:**

- `/aktion/{slug}/` zeigt Flyer-Bild, Titel, Zeitraum und Beschreibung.
- Abgelaufene Aktionen zeigen den „Diese Aktion ist beendet"-Hinweis.
- Das Template integriert sich optisch ins Kadence-Theme (Header/Footer identisch).

---

### 4. Template-Ladung via `template_include`-Filter

**Ziel:** WordPress lädt das Plugin-Template automatisch, wenn eine Aktion aufgerufen wird.

**Datei:** `includes/catalog/class-promotion-post-type.php`

**Umsetzung:**

1. In der `register()`-Methode einen neuen Hook hinzufügen:

```php
add_filter( 'template_include', array( $this, 'load_single_template' ) );
```

2. Neue öffentliche Methode `load_single_template( $template )`:

```php
/**
 * Lädt das Plugin-eigene Single-Template für Aktionen.
 *
 * @param string $template Vom Theme vorgeschlagenes Template.
 * @return string
 */
public function load_single_template( $template ) {
    if ( ! is_singular( self::POST_TYPE ) ) {
        return $template;
    }

    $plugin_template = BARMBINI_CORE_PATH . 'templates/single-barmbini_aktion.php';

    if ( file_exists( $plugin_template ) ) {
        return $plugin_template;
    }

    return $template;
}
```

**Wichtig:** `BARMBINI_CORE_PATH` ist eine Konstante aus `barmbini-core.php`. Sie ist beim Zeitpunkt des `template_include`-Filters bereits definiert.

**Abnahmekriterium:**

- `/aktion/{slug}/` lädt das Template aus `wp-content/plugins/barmbini-core/templates/single-barmbini_aktion.php`.
- Andere Seiten (Beiträge, Seiten, Produkte) sind nicht betroffen.

---

### 5. Shortcode anpassen: Flyer & Titel auf Einzelansicht verlinken

**Ziel:** Auf der Startseite führen Klicks auf Flyer und Titel zur Einzelseite der Aktion – nicht mehr zu einer externen URL.

**Datei:** `includes/catalog/class-promotion-shortcode.php`

**Änderungen in der Methode `render_item()`:**

| Vorher | Nachher |
|--------|---------|
| `$link_url = get_post_meta( …, META_LINK_URL, true )` | **Zeile entfernen** |
| `if ( $link_url ) { … } else { $title = esc_html(…); }` | Titel **immer** als Link auf `get_permalink()` |
| `if ( $link_url ) { … button … }` | **Button-Block komplett entfernen** |

**Neue Logik für Flyer-Bild (`render_image()`):**

Das Bild wird in einen Link gewrappt, der auf die Einzelansicht zeigt:

```php
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
```

**Neue Logik für Titel (`render_item()`):**

```php
$title = sprintf(
    '<a href="%s">%s</a>',
    esc_url( get_permalink() ),
    esc_html( get_the_title() )
);
$output .= sprintf( '<h3 class="barmbini-promotion-title">%s</h3>', $title );
```

**Vereinfachte `render_item()`-Methode:**

Die Methode reduziert sich auf:

1. `<article>` öffnen
2. Flyer-Bild (jetzt verlinkt)
3. Titel (jetzt verlinkt)
4. Zeitraum (wenn `show_date`)
5. Beschreibung (wenn `show_description`)
6. `</article>` schließen

Kein `$link_url`-Bezug mehr. Der Parameter `$show_image, $show_date, $show_description` bleibt unverändert.

**Aktualisierung des DocBlocks der Klasse:**

Der Datei-Kommentar und die `render()`-DocBlock-Methode sollen nicht mehr von „optionalem Link" sprechen.

**Abnahmekriterium:**

- Klick auf Flyer oder Titel im Frontend führt zur Einzelseite der Aktion.
- Der Shortcode `[barmbini_promotion]` funktioniert ohne PHP-Errors.
- Alle bestehenden Shortcode-Attribute (`show_image`, `show_date`, `show_description`, `empty_message`) funktionieren unverändert.

---

### 6. CSS bereinigen und ergänzen

**Ziel:** Button-Stile entfernen, da der Button nicht mehr existiert. Hover-Effekt für das jetzt verlinkte Flyer-Bild ergänzen.

**Datei:** `assets/css/promotion.css`

**Zu entfernende Regeln:**

```css
.barmbini-promotion-link {
    display: inline-block;
    align-self: flex-start;
    margin-top: auto;
    padding: 0.5rem 1rem;
    font-size: 1rem;
    color: #fff;
    background-color: #2d6a4f;
    border-radius: 3px;
    text-decoration: none;
}

.barmbini-promotion-link:hover,
.barmbini-promotion-link:focus {
    background-color: #1b4332;
    color: #fff;
    text-decoration: none;
}
```

**Neue Regel für das verlinkte Flyer-Bild:**

```css
.barmbini-promotion-image-link {
    display: block;
    margin-bottom: 1rem;
}

.barmbini-promotion-image-link:hover .barmbini-promotion-image,
.barmbini-promotion-image-link:focus .barmbini-promotion-image {
    opacity: 0.9;
}
```

**Zusätzliche CSS-Klassen für die Einzelansicht (in dieselbe Datei):**

Diese Styles greifen nur auf der Einzelseite (`single-barmbini_aktion`):

```css
/* Einzelansicht */
.barmbini-single-promotion {
    max-width: 800px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.barmbini-single-promotion__image img {
    display: block;
    width: 100%;
    max-width: 100%;
    height: auto;
    margin-bottom: 1.5rem;
}

.barmbini-single-promotion__title {
    margin: 0 0 0.75rem;
    font-size: 2rem;
    line-height: 1.2;
}

.barmbini-single-promotion__dates {
    margin: 0 0 1rem;
    font-size: 0.9rem;
    color: #555;
}

.barmbini-single-promotion__expired {
    margin: 0 0 1rem;
    padding: 0.75rem 1rem;
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 4px;
    font-size: 0.9rem;
}

.barmbini-single-promotion__content {
    font-size: 1rem;
    line-height: 1.6;
}
```

**Abnahmekriterium:**

- Keine CSS-Regeln für `.barmbini-promotion-link` mehr.
- Flyer-Bild hat einen dezenten Hover-Effekt.
- Einzelansicht ist zentriert und responsive (max. 800 px Inhalt).

---

### 7. Dokumentation aktualisieren

**Ziel:** Alle Dokumente spiegeln die Entfernung des Link-Felds und die neue Einzelansicht wider.

#### 7a. `Docs/Barmbini_Anleitung_Aktionen_Admin.md`

| Abschnitt | Änderung |
|-----------|----------|
| §2 (Felder im Editor) | Zeile für „Link" entfernen |
| §4 (Link) | **Komplett streichen** |
| §5 (Veröffentlichen) | „Link" aus der Checkliste entfernen |
| §6 (Shortcode) | Atttribut-Tabelle bleibt unverändert (kein `show_link`-Attribut nötig) |
| Kurzreferenz | Schritt „Link" entfernen |
| Neuer Abschnitt | **„Einzelansicht der Aktion"** – erklärt, dass jede Aktion unter `/aktion/{slug}/` aufrufbar ist und wie Besucher dorthin gelangen |
| Nummerierung | Nach Streichung von §4 neu durchnummerieren |

#### 7b. `Tasks/Barmbini_Aufgabe_Startseite_Aktionen.md`

- Annahme 7 („Jede Aktion kann einen optionalen Link enthalten") **streichen**.
- Im Shortcode-Referenz-Abschnitt die Spalte für Link-relevantes entfernen.
- Neuen Nachtrag `## Nachtrag: Einzelansicht (2026-08-04)` mit den Änderungen anfügen.

#### 7c. `Barmbini_Plugin_Architektur_barmbini-core.md`

- Im Catalog-Modul den Eintrag zu `class-promotion-post-type.php` aktualisieren (Link nicht mehr erwähnen).
- Im Catalog-Modul den Eintrag zu `class-promotion-shortcode.php` aktualisieren (kein Link mehr).
- Im Zielverzeichnisbaum `templates/single-barmbini_aktion.php` ergänzen.

#### 7d. `Barmbini_Vorbereitung_Features_und_Bugfixes.md`

- Verifizierten Ist-Stand um „Einzelansicht" und „Link entfernt" ergänzen.

#### 7e. `Docs/Barmbini_Seiteninhalte.md`

- Im Abschnitt „Aktionen" den Satz zur Link-URL streichen.
- Einzelansicht-URL erwähnen.

**Abnahmekriterium:**

- Kein Dokument erwähnt mehr die externe Link-URL als Feature.
- Alle Dokumente beschreiben die Einzelansicht.

---

## Technische Details

### Geänderte CPT-Argumente

| Parameter | Vorher | Nachher |
|-----------|--------|---------|
| `publicly_queryable` | `false` | `true` |

### Entfernte Meta-Keys

| Meta-Key | Aktion |
|----------|--------|
| `_barmbini_promotion_link_url` | Nicht mehr gelesen, nicht mehr gespeichert. Bestehende Werte in der DB bleiben unangetastet. |

### Template-Ladung

```
template_include-Filter
  → is_singular('barmbini_aktion')?
    → Ja: templates/single-barmbini_aktion.php
    → Nein: Theme-Template unverändert
```

### Shortcode-Vereinfachung

| Element | Vorher | Nachher |
|---------|--------|---------|
| Flyer-Bild | `<img>` ohne Link | `<a href="permalink"><img></a>` |
| Titel | Link auf externe URL ODER reiner Text | **Immer** Link auf `get_permalink()` |
| Button | „Mehr erfahren" → externe URL | **Entfernt** |

### CSS-Klassen (Einzelansicht)

| Klasse | Element |
|--------|---------|
| `.barmbini-single-promotion` | Äußerer Container (`<main>`) |
| `.barmbini-single-promotion__article` | Artikel (`<article>`) |
| `.barmbini-single-promotion__image` | Bild-Container |
| `.barmbini-single-promotion__title` | Titel (`<h1>`) |
| `.barmbini-single-promotion__dates` | Gültigkeitszeitraum |
| `.barmbini-single-promotion__expired` | „Aktion beendet"-Hinweis |
| `.barmbini-single-promotion__content` | Editor-Inhalt |

### Neue CSS-Klasse (Startseite)

| Klasse | Element |
|--------|---------|
| `.barmbini-promotion-image-link` | Link-Wrapper um das Flyer-Bild |

---

## Zielverzeichnisse (Änderungen)

```text
wp-content/plugins/barmbini-core/
├── includes/catalog/
│   ├── class-promotion-post-type.php   ← ÄNDERN (Link entfernen + template_include + publicly_queryable)
│   └── class-promotion-shortcode.php   ← ÄNDERN (Link-Logik entfernen, Flyer/Titel auf permalink)
├── templates/
│   └── single-barmbini_aktion.php      ← NEU
└── assets/css/
    └── promotion.css                   ← ÄNDERN (Button-Styles raus, Hover + Single-Styles rein)
```

---

## Testfälle

### T1: Einzelansicht erreichbar

| Schritt | Erwartet |
|---------|----------|
| Aktion veröffentlichen (Slug z. B. `sommer-aktion`) | `/aktion/sommer-aktion/` liefert 200 |
| Flyer-Bild in der Einzelansicht | Wird in voller Breite angezeigt |
| Titel in der Einzelansicht | Wird als `<h1>` angezeigt |
| Zeitraum in der Einzelansicht | „Gültig vom … bis zum …" |
| Beschreibung in der Einzelansicht | Editor-Inhalt wird vollständig gerendert |

### T2: Abgelaufene Aktion in Einzelansicht

| Schritt | Erwartet |
|---------|----------|
| Aktion mit Enddatum gestern | Seite ist erreichbar (kein 404) |
| „Beendet"-Hinweis | Gelber Kasten „Diese Aktion ist beendet" erscheint |

### T3: Shortcode – Flyer und Titel verlinkt

| Schritt | Erwartet |
|---------|----------|
| `[barmbini_promotion]` auf Startseite | Flyer-Bild ist auf `/aktion/{slug}/` verlinkt |
| Klick auf Titel | Führt zur Einzelansicht |
| Kein „Mehr erfahren"-Button | Button existiert nicht |

### T4: Shortcode-Attribute unverändert

| Shortcode | Erwartet |
|-----------|----------|
| `[barmbini_promotion show_image="0"]` | Kein Bild, Titel weiterhin verlinkt |
| `[barmbini_promotion show_date="0"]` | Kein Zeitraum |
| `[barmbini_promotion show_description="0"]` | Keine Beschreibung |
| `[barmbini_promotion empty_message="Keine Aktionen"]` | Text bei Leerzustand |

### T5: Link-Metabox entfernt

| Schritt | Erwartet |
|---------|----------|
| Aktion im Admin bearbeiten | Keine „Link"-Metabox in der Seitenleiste |
| Aktion speichern | Keine PHP-Errors im Debug-Log |
| `_barmbini_promotion_link_url` in DB | Wird nicht mehr neu geschrieben (alte Werte bleiben) |

### T6: Template-Ladung

| Schritt | Erwartet |
|---------|----------|
| `/aktion/{slug}/` aufrufen | Template aus `plugins/barmbini-core/templates/` wird geladen |
| `/2026/08/irgendein-beitrag/` aufrufen | Normales Theme-Template, nicht das Plugin-Template |
| `/sortiment/` aufrufen | Normales Theme-Template |

### T7: Responsive Einzelansicht

| Viewport | Erwartet |
|----------|----------|
| 320 px | Inhalt nutzt volle Breite, kein horizontales Scrollen |
| 768 px | Inhalt auf 800 px zentriert |
| 1024 px | Identisch zu 768 px |

### T8: CSS – keine toten Regeln

| Prüfung | Erwartet |
|---------|----------|
| `grep "promotion-link" promotion.css` | Kein Treffer (Button-Regeln entfernt) |
| `grep "image-link" promotion.css` | Neue Regel vorhanden |
| `grep "single-promotion" promotion.css` | Neue Regeln vorhanden |

---

## Querverweise

- CPT-Registrierung: `includes/catalog/class-promotion-post-type.php`
- Shortcode-Logik: `includes/catalog/class-promotion-shortcode.php`
- Bestehendes CSS: `assets/css/promotion.css`
- Plugin-Bootstrap: `barmbini-core.php` (keine Änderung nötig)
- Modulregistrierung: `includes/class-plugin.php` (keine Änderung nötig)
- Admin-Anleitung: `Docs/Barmbini_Anleitung_Aktionen_Admin.md`

---

## Zusammenfassung der zu ändernden/erstellenden Dateien

| Datei | Aktion | Inhalt |
|-------|--------|--------|
| `includes/catalog/class-promotion-post-type.php` | **ÄNDERN** | `publicly_queryable=true`, Link-Konstante/-Metabox/-Speicherung entfernen, `template_include`-Filter |
| `includes/catalog/class-promotion-shortcode.php` | **ÄNDERN** | Link-Logik entfernen, Flyer→Permalink, Titel→Permalink, Button entfernen |
| `templates/single-barmbini_aktion.php` | **NEU** | Einzelansicht-Template |
| `assets/css/promotion.css` | **ÄNDERN** | Button-Styles entfernen, Hover für Bild-Link, Single-Styles |
| `Docs/Barmbini_Anleitung_Aktionen_Admin.md` | **ÄNDERN** | §4 streichen, Einzelansicht erklären |
| `Tasks/Barmbini_Aufgabe_Startseite_Aktionen.md` | **ÄNDERN** | Nachtrag Einzelansicht |
| `Barmbini_Plugin_Architektur_barmbini-core.md` | **ÄNDERN** | Catalog-Modul-Einträge aktualisieren |
| `Barmbini_Vorbereitung_Features_und_Bugfixes.md` | **ÄNDERN** | Ist-Stand ergänzen |
| `Docs/Barmbini_Seiteninhalte.md` | **ÄNDERN** | Link-URL entfernen, Einzelansicht erwähnen |
