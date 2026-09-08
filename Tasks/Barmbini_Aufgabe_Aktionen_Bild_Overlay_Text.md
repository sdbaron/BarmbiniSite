# Detaillierte Aufgabe: Overlay-Text auf dem Aktions-Flyer-Bild

**Zielgruppe:** Mid-Level-Entwickler (WordPress/PHP/CSS)  
**Geschätzter Umfang:** ca. 0,5–1 Personentag inkl. Tests und Doku  
**Plugin-Version nach Umsetzung:** `barmbini-core` **0.10.1** (umgesetzt)

## Umsetzungsstatus

**Umgesetzt** in `barmbini-core` 0.10.1: Metabox, Meta-Keys, gemeinsame Render-Hilfe, CSS (9 Positionen), Shortcode + Einzelansicht, Admin-Anleitung und Architektur-/Vorbereitungsdoku.

## Ziel

Redakteure sollen pro Aktion einen optionalen **Kurztext** pflegen können, der **auf dem Flyer-Bild** (Beitragsbild) als Overlay erscheint – mit einstellbarer **Farbe**, **Schriftgröße (px)** sowie **vertikaler** und **horizontaler Position**.

Wenn kein Overlay-Text gesetzt ist, bleibt die Darstellung unverändert (wie heute). Der Overlay-Text ist **kein Ersatz** für Titel oder Beschreibung; er dient als kurzer Störer/Badge (z. B. „Nur diese Woche“, „50 %“).

## Quellenbasis

- `Tasks/Barmbini_Aufgabe_Startseite_Aktionen.md` – CPT und Shortcode-Grundlage
- `Tasks/Barmbini_Aufgabe_Aktionen_Einzelansicht.md` – Einzelansicht `/aktion/{slug}/`
- `Barmbini_Plugin_Architektur_barmbini-core.md` – Catalog-Modul, Promotion-Klassen
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` – Kapitel „Aktionen (Startseite)“
- `Docs/Barmbini_Anleitung_Aktionen_Admin.md` – Redakteurs-Anleitung
- Code-Ist-Stand:
  - `includes/catalog/class-promotion-post-type.php`
  - `includes/catalog/class-promotion-shortcode.php`
  - `assets/css/promotion.css`
  - Einzelansicht: `add_promotion_meta_to_content()` (Flyer via `the_content`-Filter)

## Fachliche Leitplanken

- Fachlogik ausschließlich in `barmbini-core`, nicht in `themes/kadence/functions.php`.
- Keine neuen Plugins, keine Composer-Abhängigkeiten, keine externen Schriften/Dienste.
- Keine neuen Datenbank-Tabellen – nur Post-Meta am CPT `barmbini_aktion`.
- Website einsprachig deutsch; Admin-Labels auf Deutsch.
- Deploy später per **Modus B** (nur Code; Live-Daten bleiben erhalten).
- Barrierearm: Overlay-Text muss im DOM als lesbarer Text vorhanden sein (kein reines Hintergrundbild); ausreichender Kontrast liegt in der Verantwortung des Redakteurs, aber Defaults sollen lesbar sein.

## Verbindliche Annahmen

1. **„Vor dem Bild“ = Overlay auf dem Flyer**, nicht Text oberhalb des Bildes im Dokumentfluss. Der Text liegt visuell über dem Bild (CSS `position: absolute` in einem `position: relative`-Wrapper).
2. Overlay wird nur gerendert, wenn **beide** Bedingungen gelten:
   - Overlay-Text ist nach Trim nicht leer
   - Die Aktion hat ein Flyer-Bild (`has_post_thumbnail()`)
3. Ohne Flyer-Bild wird der Overlay-Text **nicht** anderweitig angezeigt (kein Fallback unter dem Titel).
4. Gleiches Overlay-Verhalten auf:
   - Startseite / Shortcode `[barmbini_promotion]` (wenn `show_image` aktiv und Bild vorhanden)
   - Einzelansicht `/aktion/{slug}/` (Bildblock in `add_promotion_meta_to_content()`)
5. Titel, Beschreibung, Gültigkeitszeitraum und die Checkbox „Beschreibung auf der Startseite anzeigen“ bleiben unverändert.
6. Overlay-Text ist **Plaintext** (eine Zeile bzw. kurze Phrase); kein HTML, keine Shortcodes im Feld.
7. Positionen sind ein festes 3×3-Raster:

| Vertikal \\ Horizontal | `left` | `middle` | `right` |
|------------------------|--------|----------|---------|
| `top` | oben links | oben Mitte | oben rechts |
| `middle` | Mitte links | Mitte Mitte | Mitte rechts |
| `bottom` | unten links | unten Mitte | unten rechts |

8. Defaults, wenn Felder leer/ungültig und Text gesetzt ist:
   - Farbe: `#ffffff`
   - Größe: `24` (px)
   - Vertikal: `top`
   - Horizontal: `left`
9. Schriftgröße: Ganzzahl, erlaubt **12–72** px (außerhalb → auf Bereich klemmen bzw. Default).
10. Farbe: nur Hex `#RGB` oder `#RRGGBB` (case-insensitive); ungültige Werte → Default.

## Nicht Bestandteil dieser Aufgabe

- Mehrere Overlay-Texte pro Aktion
- Hintergrund-Badge, Schatten-Designer, Schriftart-Auswahl, Rotation, Animation
- Drag-and-Drop-Positionierung oder freie Pixel-Koordinaten (x/y)
- Overlay auf Archivseite `/aktion/` (Kartenlayout dort unverändert lassen, sofern kein eigenes Flyer-Rendering existiert)
- Änderung der Benachrichtigungs-/Abo-Logik
- Theme-Anpassungen oder Child-Theme
- Live-Deploy (nur lokale Umsetzung + Doku; Deploy ist Folgeauftrag)

---

## Umzusetzender Funktionsumfang

1. Neue Post-Meta-Felder für Overlay-Text, Farbe, Größe, Vertikal- und Horizontal-Position.
2. Metabox (oder Erweiterung der Box „Startseiten-Anzeige“) im Aktions-Editor.
3. Speichern mit Nonce, Capability-Check, Sanitizing und Validierung.
4. Gemeinsame Render-Hilfe für Shortcode und Einzelansicht (kein dupliziertes Markup).
5. CSS für Wrapper + 9 Positionsklassen; Farbe/Größe als sichere Inline-Styles am Text-Element.
6. Admin-Anleitung und Architektur-/Vorbereitungsdoku aktualisieren.
7. Manuelle Abnahmetests gemäß Checkliste unten.

---

## Aufgabe

### 1. Meta-Keys und Konstanten definieren

**Datei:** `includes/catalog/class-promotion-post-type.php`

Neue Konstanten (Namen verbindlich):

| Konstante | Meta-Key | Typ / Werte |
|-----------|----------|-------------|
| `META_OVERLAY_TEXT` | `_barmbini_promotion_overlay_text` | string (Plaintext) |
| `META_OVERLAY_COLOR` | `_barmbini_promotion_overlay_color` | string Hex |
| `META_OVERLAY_SIZE` | `_barmbini_promotion_overlay_size` | int (px) |
| `META_OVERLAY_POSITION_V` | `_barmbini_promotion_overlay_position_v` | `top` \| `middle` \| `bottom` |
| `META_OVERLAY_POSITION_H` | `_barmbini_promotion_overlay_position_h` | `left` \| `middle` \| `right` |

**Abnahmekriterium:** Konstanten sind zentral definiert; keine Magic Strings an Speicher-/Render-Stellen.

---

### 2. Admin-UI: Metabox erweitern oder neue Box anlegen

**Empfehlung:** Neue Metabox **„Overlay auf dem Flyer“** (Context `side`, Priority `default`), damit die bestehende Box „Startseiten-Anzeige“ schlank bleibt.

**Felder:**

| Feld | HTML | Hinweise für Redakteure |
|------|------|-------------------------|
| Text | `input type="text"` oder `textarea` (1–2 Zeilen), `maxlength="80"` empfohlen | „Leer lassen = kein Overlay“ |
| Farbe | `input type="color"` **plus** optional Textfeld Hex, oder nur `type="text"` mit Placeholder `#ffffff` | Default weiß |
| Größe (px) | `input type="number"` min 12 max 72 step 1 | Default 24 |
| Vertikale Position | `select`: Oben / Mitte / Unten | Werte `top` / `middle` / `bottom` |
| Horizontale Position | `select`: Links / Mitte / Rechts | Werte `left` / `middle` / `right` |

Kurzbeschreibung unter den Feldern: Der Text erscheint **auf dem Flyer-Bild** (Startseite und Aktionsseite), nicht statt Titel/Beschreibung.

**Abnahmekriterium:** Redakteur sieht die Felder im Editor einer Aktion; Speichern ohne Overlay-Felder bleibt möglich.

---

### 3. Speichern und Validierung

In `save_metaboxes()` (gleiche Nonce-/Capability-/Autosave-Checks wie bestehende Felder):

1. Text: `sanitize_text_field`, trim; leer → Meta löschen oder leeren String speichern (konsistent wählen und dokumentieren; empfohlen: leeren String speichern oder `delete_post_meta` bei Leer – beides ok, solange Render „leer = kein Overlay“ prüft).
2. Farbe: gegen Regex `/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/` prüfen; sonst Default `#ffffff` (nur speichern, wenn Text gesetzt – optional, aber empfohlen Defaults nur zu persistieren wenn sinnvoll).
3. Größe: `(int)`, dann `max( 12, min( 72, $size ) )`; leerer Input → 24.
4. Position V/H: Whitelist der erlaubten Strings; sonst Default `top` / `left`.

**Hilfsmethoden** (geschützt in der CPT-Klasse oder einer kleinen Helper-Klasse im Catalog-Modul):

- `sanitize_overlay_color( $raw ): string`
- `sanitize_overlay_size( $raw ): int`
- `sanitize_overlay_position_v( $raw ): string`
- `sanitize_overlay_position_h( $raw ): string`

**Abnahmekriterium:** Ungültige POST-Werte erzeugen keine Notices; gespeicherte Werte sind immer whitelisted bzw. geklemmt.

---

### 4. Gemeinsame Render-Hilfe

**Ziel:** Ein Markup an einer Stelle, genutzt von Shortcode und Einzelansicht.

**Empfohlene Platzierung:** neue geschützte/statische Methode, z. B.:

- in `class-promotion-post-type.php`: `public static function render_flyer_with_overlay( $post_id, $image_html, $wrapper_class = '' )`
- **oder** kleine Klasse `class-promotion-overlay.php` mit `render( $post_id, $image_html )`

**Eingabe:** bereits fertiges Bild-HTML (z. B. `<img …>` oder `get_the_post_thumbnail(…)`), damit Shortcode weiter eigene `src`/`alt`-Logik behalten kann.

**Ausgabe-Logik:**

```
wenn overlay_text leer ODER kein Bild-Kontext:
    → image_html unverändert zurück (ggf. nur in bestehendem Link-Wrapper)
sonst:
    → <div class="barmbini-promotion-flyer barmbini-promotion-flyer--{v}-{h}">
         {image_html}
         <span class="barmbini-promotion-overlay"
               style="color:{color};font-size:{size}px">{text}</span>
       </div>
```

Klassenbeispiel: `barmbini-promotion-flyer--top-left`, `…--middle-middle`, `…--bottom-right`.

**Escaping:**

- Text: `esc_html`
- Farbe: nur nach Sanitize in `style` (zusätzlich `esc_attr`)
- Größe: nur Integer in `style`

**Shortcode** (`render_image()`):

- Bestehenden Link-Wrapper beibehalten:  
  `<a class="barmbini-promotion-image-link" href="…">` + Flyer-mit-Overlay + `</a>`
- Klasse `barmbini-promotion-image` am `<img>` beibehalten.

**Einzelansicht** (`add_promotion_meta_to_content()`):

- Den Block  
  `<div class="barmbini-single-promotion__image">…thumbnail…</div>`  
  so umbauen, dass das Thumbnail durch die Render-Hilfe läuft (Wrapper `barmbini-single-promotion__image` außen belassen oder als `$wrapper_class` übergeben).

**Abnahmekriterium:** Änderung der Overlay-Darstellung erfordert nur eine Code-Stelle; Shortcode und Single zeigen identisches Overlay-Verhalten.

---

### 5. CSS

**Datei:** `assets/css/promotion.css`

Mindestumfang:

```css
.barmbini-promotion-flyer {
	position: relative;
	display: block;
	width: 100%;
}

.barmbini-promotion-flyer .barmbini-promotion-image,
.barmbini-promotion-flyer img {
	display: block;
	width: 100%;
	height: auto;
}

.barmbini-promotion-overlay {
	position: absolute;
	z-index: 1;
	max-width: calc(100% - 1.5rem);
	line-height: 1.2;
	font-weight: 700;
	pointer-events: none; /* Klick geht zum Link darunter */
	/* leichter Schatten empfohlen für Lesbarkeit auf hellen Flyern */
	text-shadow: 0 1px 2px rgba(0, 0, 0, 0.45);
}
```

Positionierung über Flex oder Top/Left/Transform – verbindlich die 9 Modifier-Klassen abdecken, z. B.:

- `--top-left`: `top: 0.75rem; left: 0.75rem; text-align: left;`
- `--top-middle`: `top: 0.75rem; left: 50%; transform: translateX(-50%); text-align: center;`
- `--top-right`: `top: 0.75rem; right: 0.75rem; text-align: right;`
- `--middle-left`: `top: 50%; left: 0.75rem; transform: translateY(-50%);`
- `--middle-middle`: `top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;`
- `--middle-right`: `top: 50%; right: 0.75rem; transform: translateY(-50%); text-align: right;`
- `--bottom-*`: analog mit `bottom: 0.75rem` statt `top`

**Hinweise:**

- `pointer-events: none` am Overlay, damit der bestehende Permalink-Link auf dem Flyer weiter funktioniert.
- Bestehende Regeln für `.barmbini-promotion-image-link` / Hover nicht brechen (Margin bleibt am Link).
- Styles nur laden, wo sie schon geladen werden (Shortcode enqueued `promotion.css`; sicherstellen, dass Einzelansicht dieselbe Datei lädt – falls noch nicht global für Singular-Aktionen: in CPT-Klasse `wp_enqueue_scripts` für `is_singular( barmbini_aktion )` ergänzen).

**Abnahmekriterium:** Alle 9 Positionen sind visuell unterscheidbar; Overlay überdeckt das Bild, verschiebt das Layout darunter nicht.

---

### 6. Dokumentation aktualisieren

Im selben Arbeitspaket:

| Dokument | Änderung |
|----------|----------|
| `Docs/Barmbini_Anleitung_Aktionen_Admin.md` | Neuer Abschnitt „Overlay-Text auf dem Flyer“: Felder, Defaults, Hinweis „leer = aus“ |
| `Barmbini_Plugin_Architektur_barmbini-core.md` | Meta-Keys + Overlay-Render in Promotion-Beschreibung ergänzen |
| `Barmbini_Vorbereitung_Features_und_Bugfixes.md` | Kurz im Kapitel Aktionen: Overlay-Felder und Verhalten |
| Optional: Shop-Manager-/Staff-Guide nur falls dort Aktionen detailliert beschrieben sind und der Text sonst veraltet wäre | Nur wenn nötig; Redakteure sind Hauptzielgruppe |

**Abnahmekriterium:** Ein Redakteur kann das Feature allein anhand der Admin-Anleitung bedienen.

---

### 7. Version und Changelog-Hinweis

- `BARMBINI_CORE_VERSION` / Plugin-Header in `barmbini-core.php` erhöhen.
- Kurzer Eintrag in vorhandener Changelog-/Commit-Message-Praxis des Repos (kein neues Markdown erzwingen, falls keines existiert).

---

## Vorgeschlagenes Markup (Referenz)

```html
<a href="/aktion/sommerverkauf/" class="barmbini-promotion-image-link">
  <div class="barmbini-promotion-flyer barmbini-promotion-flyer--top-right">
    <img class="barmbini-promotion-image" src="…/flyer.jpg" alt="Sommerschlussverkauf">
    <span class="barmbini-promotion-overlay" style="color:#ffffff;font-size:28px">Nur diese Woche</span>
  </div>
</a>
```

---

## Testplan (Abnahme)

### Admin

1. Neue Aktion ohne Overlay-Felder → speichern → Frontend ohne Overlay (Regression).
2. Text setzen, Farbe `#ff0000`, Größe `32`, Position oben/rechts → speichern → Meta korrekt in DB.
3. Ungültige Farbe `rot` und Größe `999` → nach Speichern Farbe Default bzw. Größe 72.
4. Text wieder leeren → Overlay verschwindet im Frontend.
5. Aktion **ohne** Flyer-Bild, aber mit Overlay-Text → kein Overlay irgendwo sichtbar.

### Frontend Shortcode

6. Startseite: Overlay liegt auf dem Bild, Position stimmt, Klick auf Bild öffnet weiterhin Einzelansicht.
7. `show_image="0"` am Shortcode → kein Bild, kein Overlay.
8. Mobile (~375 px Breite): Text bricht sinnvoll um / bleibt im Bild (`max-width`), Layout der Karte bricht nicht.

### Frontend Einzelansicht

9. `/aktion/{slug}/`: gleiches Overlay wie auf der Startseite.
10. Abgelaufene Aktion mit Overlay: Overlay weiterhin sichtbar (kein Bezug zur Datumsregel nötig); Beendet-Hinweis unverändert.

### Regression

11. Beschreibung-Checkbox, Zeitraum, Titel-Link verhalten sich wie zuvor.
12. Keine PHP-Notices; HTML valid/escapet (kein rohes User-HTML im Overlay).

---

## Risiken und offene Punkte

| Risiko | Mitigation |
|--------|------------|
| Schlechte Lesbarkeit auf bunten Flyern | `text-shadow` im CSS; Redakteure wählen Kontrastfarbe |
| Lange Texte sprengen das Bild | `maxlength="80"` + `max-width` + ggf. Zeilenumbruch |
| Einzelansicht lädt `promotion.css` nicht | Explizit für `is_singular( barmbini_aktion )` enqueuen |
| Doppeltes Margin (Link + Flyer) | Visuell prüfen; Margin nur am äußeren Link belassen |

**Keine offenen Produktentscheidungen** für den Start der Umsetzung: Overlay-Interpretation und 3×3-Raster sind durch diese Aufgabe festgelegt.

Falls später gewünscht (Folgeaufgaben, nicht hier): Hintergrundplatte hinter dem Text, zweite Zeile, freie Koordinaten.

---

## Definition of Done

- [ ] Meta-Felder, Metabox, Save/Sanitize implementiert
- [ ] Overlay-Rendering in Shortcode und Einzelansicht über gemeinsame Hilfe
- [ ] CSS für 9 Positionen + Lesbarkeits-Schatten
- [ ] Styles auf Singular-Aktion geladen
- [ ] Admin-Anleitung + Architektur-/Vorbereitungsdoku aktualisiert
- [ ] Plugin-Version erhöht
- [ ] Testplan manuell durchgelaufen und kurz im PR/Commit-Kommentar bestätigt
- [ ] Keine Theme-Änderungen; bereit für späteren Modus-B-Deploy

---

## Einstieg für den Entwickler (Reihenfolge)

1. Konstanten + Metabox + Save (ohne Frontend) lokal prüfen (Meta in DB).
2. Render-Hilfe + Shortcode anbinden + CSS.
3. Einzelansicht anbinden + CSS-Enqueue prüfen.
4. Tests 1–12.
5. Dokumentation und Version.

Referenzmuster im Plugin: bestehende Metabox „Startseiten-Anzeige“ und `render_image()` im Shortcode – daran Orientierung halten (Nonce, `esc_*`, Klassenpräfix `barmbini-`).
