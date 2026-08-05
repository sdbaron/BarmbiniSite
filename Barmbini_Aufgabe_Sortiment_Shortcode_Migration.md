# Detaillierte Aufgabe: Sortiment-Shortcode ins Plugin migrieren

## Ziel

Der Shortcode `[barmbini_top_product_categories]` und seine Hilfsfunktionen sollen aus dem Must-Use-Plugin `mu-plugins/barmbini-sortiment-shortcodes.php` in das Plugin `barmbini-core` migriert werden.

Damit wird die Architekturregel „Fachlogik gehört ins Plugin, nicht ins Theme oder in MU-Plugins" für diesen Baustein umgesetzt und die Codebasis zentralisiert.

## Quellenbasis

Die Aufgabe basiert auf:

- `Barmbini_Technisches_Konzept_v2.5.md`
- `Barmbini_Plugin_Architektur_barmbini-core.md` — Zielstruktur, Modulzuschnitt Catalog
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` — verifizierter Ist-Stand und Einbauorte
- der Quelldatei: `D:\Local Sites\barmbini\app\public\wp-content\mu-plugins\barmbini-sortiment-shortcodes.php`
- dem bestehenden Plugin `wp-content/plugins/barmbini-core/`
- dem bestehenden Shortcode-Muster: `class-promotion-shortcode.php` und `class-latest-news-shortcode.php`
- dem bestehenden Registrierungsmuster aus `barmbini-core.php` und `includes/class-plugin.php`
- der Seite „Sortiment" (Post-ID 20), die den Shortcode verwendet:
  `[barmbini_top_product_categories columns="4" hide_empty="0" exclude="60"]`

## Fachliche Leitplanken

Die Umsetzung muss zu den bestehenden Projektgrundsätzen passen:

- Die Website ist einsprachig deutsch.
- WooCommerce dient als Produktkatalog, nicht als klassischer Shop mit Checkout.
- Neue Fachlogik gehört in das Plugin `barmbini-core`, nicht in Theme oder MU-Plugins.
- Das Projekt folgt dem Minimalprinzip bei Plugins.
- Die bestehende Seite „Sortiment" (Post-ID 20) muss nach der Migration unverändert funktionieren.
- Es werden keine externen Drittanbieter-Services oder Schriften geladen.

## Ist-Analyse: Was die MU-Plugin-Datei enthält

Die Datei `mu-plugins/barmbini-sortiment-shortcodes.php` (150 Zeilen) enthält:

| Zeilen | Funktion | Zweck |
|--------|----------|-------|
| 1–9 | `barmbini_parse_bool_shortcode_atts()` | Konvertiert Shortcode-Attribute (`'1'`, `'true'`, `'yes'`, `'on'`) in echte Booleans |
| 11–25 | `barmbini_render_product_category_grid()` | Rendert ein Kategorie-Grid via WooCommerce-Shortcode `[product_categories ids="..." columns="..." hide_empty="..."]` |
| 27–41 | `barmbini_render_sortiment_section()` | Rendert eine Sektion: `<h2>`-Überschrift + Inhalt + `<hr>`-Trenner |
| 43–63 | `barmbini_move_terms_to_end()` | Verschiebt Terms mit bestimmten Slugs (z. B. `babybedarf`) ans Ende der Liste |
| 70–148 | `barmbini_top_product_categories_shortcode()` | **Hauptfunktion**: Shortcode-Handler für `[barmbini_top_product_categories]` |
| 150 | `add_shortcode(...)` | Registrierung des Shortcodes |

### Funktionsweise des Shortcodes im Detail

1. Holt alle Top-Level-Produktkategorien (`parent=0`) via `get_terms('product_cat', ...)`
2. Schließt Kategorien per `exclude`-Attribut aus (Standard: `60`)
3. Sortiert nach `menu_order` ASC (Standard)
4. Verschiebt Kategorien mit Slug `babybedarf` ans Ende (`move_last`-Attribut)
5. Für jede Kategorie:
   - Holt deren Kindkategorien
   - Wenn Kinder existieren → Grid aus den Kindkategorien
   - Wenn keine Kinder → Grid nur mit der Kategorie selbst
6. Rendert alles in `<div class="wp-block-group barmbini-sortiment-section">` mit `<h2>`-Überschrift
7. Fügt `<hr class="wp-block-separator has-alpha-channel-opacity" />` zwischen den Sektionen ein

### Shortcode-Attribute (vollständig)

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|-------------|
| `columns` | `int` | `4` | Anzahl Spalten im Grid |
| `hide_empty` | `bool` | `0` | Leere Kategorien ausblenden (`1`, `true`, `yes`, `on`) |
| `exclude` | `string` | `60` | Komma-separierte IDs auszuschließender Kategorien |
| `move_last` | `string` | `babybedarf` | Komma-separierte Slugs, die ans Ende sortiert werden |
| `parent` | `int` | `0` | ID der Elternkategorie (`0` = Top-Level) |
| `orderby` | `string` | `menu_order` | Sortierkriterium für `get_terms` |
| `order` | `string` | `ASC` | Sortierrichtung (`ASC` oder `DESC`) |

## Nicht Bestandteil dieser Aufgabe

Folgende Punkte gehören ausdrücklich nicht zum Umfang:

- Änderung der Seite „Sortiment" (Post-ID 20) — sie verwendet den Shortcode bereits und muss nur unverändert weiter funktionieren
- Neue CSS-Datei oder Styling-Änderungen — die bestehenden Kadence-Blocks-Klassen (`wp-block-group`, `wp-block-heading`, `wp-block-separator`) werden beibehalten
- Anpassung des Shortcode-Verhaltens oder neue Features
- Entfernung der MU-Plugin-Datei aus dem Server — das ist Teil eines separaten Deployment-Schritts
- Änderungen am Theme oder an `functions.php`
- Unit-Tests für die migrierten Funktionen (kann ein Folge-Ticket sein)

## Umzusetzender Funktionsumfang

Die Lösung muss die folgenden fachlichen Fähigkeiten abdecken:

1. Der Shortcode `[barmbini_top_product_categories]` wird im Plugin `barmbini-core` registriert.
2. Alle 7 Attribute funktionieren identisch zur MU-Plugin-Version.
3. Die Seite „Sortiment" zeigt nach der Migration exakt dasselbe Ergebnis.
4. Der Code folgt den bestehenden Plugin-Patterns: Klasse mit `register()`-Methode, Namespace `Barmbini_Core_Catalog_*`.
5. Die Hilfsfunktionen werden als `private`-Methoden in der Klasse gekapselt.
6. Kein globaler Namespace wird verschmutzt — alle Funktionen wandern in die Klasse.
7. Guard-Clause: Wenn WooCommerce nicht aktiv ist, gibt der Shortcode einen leeren String zurück.
8. Das HTML ist semantisch korrekt, barrierearm und responsive (wie zuvor).

---

## Aufgabe

### 1. Neue Shortcode-Klasse anlegen

**Ziel:** Eine neue PHP-Klasse im `catalog/`-Modul, die den Shortcode `[barmbini_top_product_categories]` registriert und das Rendering übernimmt.

**Neue Datei:** `wp-content/plugins/barmbini-core/includes/catalog/class-top-product-categories-shortcode.php`

**Name der Klasse:** `Barmbini_Core_Top_Product_Categories_Shortcode`

Die Klasse folgt dem Muster von `Barmbini_Core_Promotion_Shortcode`:

- Eine öffentliche Methode `register()` → `add_shortcode( 'barmbini_top_product_categories', array( $this, 'render' ) )`
- Eine öffentliche Methode `render( $atts )` → HTML-Ausgabe
- Private Hilfsmethoden für die vier bisher globalen Funktionen

#### 1a. Methodenübersicht

| Methode | Sichtbarkeit | Entspricht bisher | Beschreibung |
|---------|-------------|-------------------|-------------|
| `register()` | `public` | `add_shortcode(...)` | Registriert den Shortcode bei WordPress |
| `render( $atts )` | `public` | `barmbini_top_product_categories_shortcode()` | Haupt-Rendering-Methode |
| `parse_bool_attr( $value )` | `private` | `barmbini_parse_bool_shortcode_atts()` | Boolean-Parsing |
| `render_category_grid( $ids, $columns, $hide_empty )` | `private` | `barmbini_render_product_category_grid()` | Kategorie-Grid via WooCommerce |
| `render_section( $title, $content, $show_divider )` | `private` | `barmbini_render_sortiment_section()` | Sektion mit Überschrift |
| `move_terms_to_end( $terms, $slugs )` | `private` | `barmbini_move_terms_to_end()` | Terms umsortieren |

#### 1b. Dateikopf

```php
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
```

#### 1c. `render()`-Methode im Detail

Die Methode muss:

1. Prüfen, ob WooCommerce aktiv ist (`class_exists('WooCommerce')` und `function_exists('get_terms')`)
2. Shortcode-Attribute mit `shortcode_atts()` parsen (alle 7 Attribute, siehe Tabelle oben)
3. `exclude` als Array von Integer-IDs parsen
4. Top-Level-Terms via `get_terms('product_cat', ...)` abrufen (mit `parent`, `hide_empty`, `exclude`, `orderby`, `order`)
5. Bei Fehler (`is_wp_error`) oder leeren Terms leeren String zurückgeben
6. Terms mit `move_terms_to_end()` umsortieren
7. Für jeden Term:
   - Kind-Terms via `get_terms()` mit `parent = term_id` abrufen
   - Wenn Kinder existieren → deren IDs als Grid-IDs verwenden
   - Wenn keine Kinder → `term_id` selbst als Grid-ID verwenden
   - Grid via `render_category_grid()` rendern
   - Wenn Grid-Inhalt nicht leer → in Sektion mit `render_section()` wrappen
8. Sektionen mit `implode("\n", ...)` zusammenführen und zurückgeben

#### 1d. `parse_bool_attr()` im Detail

```php
private function parse_bool_attr( $value ) {
    if ( is_bool( $value ) ) {
        return $value;
    }

    $value = strtolower( (string) $value );

    return in_array( $value, array( '1', 'true', 'yes', 'on' ), true );
}
```

(Code identisch zur MU-Plugin-Version, nur als private Methode.)

#### 1e. `render_category_grid()` im Detail

```php
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
```

(Code identisch zur MU-Plugin-Version, nur als private Methode.)

#### 1f. `render_section()` im Detail

```php
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
```

(Code identisch zur MU-Plugin-Version, nur als private Methode.)

#### 1g. `move_terms_to_end()` im Detail

```php
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
```

(Code identisch zur MU-Plugin-Version, nur als private Methode.)

**Abnahmekriterium:**

- Die Klasse existiert unter dem angegebenen Pfad.
- Die Klasse enthält alle 6 Methoden mit korrekter Sichtbarkeit.
- Der Dateikopf enthält Paket-Angabe und Since-Version.

---

### 2. Klasse im Plugin-Bootstrap registrieren

**Ziel:** Die neue Klasse wird im Plugin-Ladevorgang bekannt gemacht und instanziiert.

#### 2a. `require_once` in `barmbini-core.php`

In der Haupt-Plugin-Datei muss die neue Datei per `require_once` geladen werden — und zwar **vor** `class-plugin.php`, wie alle anderen Catalog-Klassen.

**Einzufügen nach** Zeile 27 (`class-promotion-shortcode.php`):

```php
require_once BARMBINI_CORE_PATH . 'includes/catalog/class-top-product-categories-shortcode.php';
```

Position in der alphabetisch sortierten Liste (nach `class-promotion-shortcode.php`):

```php
require_once BARMBINI_CORE_PATH . 'includes/catalog/class-promotion-shortcode.php';
require_once BARMBINI_CORE_PATH . 'includes/catalog/class-top-product-categories-shortcode.php';  // ← NEU
require_once BARMBINI_CORE_PATH . 'includes/catalog/class-address-widget.php';
```

#### 2b. Neue Registrierungsmethode in `class-plugin.php`

Im Konstruktor von `Barmbini_Core_Plugin` eine neue Zeile einfügen:

```php
$this->register_top_product_categories_module();
```

Und eine neue `protected`-Methode nach dem Muster von `register_promotion_module()` anlegen:

```php
/**
 * Registriert den Shortcode [barmbini_top_product_categories].
 *
 * @return void
 */
protected function register_top_product_categories_module() {
    $shortcode = new Barmbini_Core_Top_Product_Categories_Shortcode();
    $shortcode->register();
}
```

**Einzufügen nach** `register_promotion_module()`.

#### 2c. Architekturdokument aktualisieren

In `Barmbini_Plugin_Architektur_barmbini-core.md` die Zielverzeichnis-Übersicht ergänzen:

Unter `|   |   |-- class-promotion-shortcode.php` einfügen:

```text
|   |   `-- class-top-product-categories-shortcode.php
```

**Abnahmekriterium:**

- Die Datei wird via `require_once` in `barmbini-core.php` geladen.
- Der Shortcode wird in `class-plugin.php` registriert.
- Die Architekturdokumentation ist aktualisiert.

---

### 3. Abnahmetests durchführen

Die folgenden manuellen Tests MÜSSEN nach der Migration durchgeführt werden:

#### T1: Shortcode wird erkannt

**Vorgehen:** Im WordPress-Admin eine Testseite mit dem Shortcode-Block `[barmbini_top_product_categories]` anlegen und im Frontend aufrufen.

**Erwartet:** Der Shortcode wird als gerenderte HTML-Ausgabe dargestellt, nicht als Rohtext.

#### T2: Seite „Sortiment" funktioniert unverändert

**Vorgehen:** Die bestehende Seite `/sortiment/` (Post-ID 20, enthält `[barmbini_top_product_categories columns="4" hide_empty="0" exclude="60"]`) im Frontend aufrufen.

**Erwartet:** Die Seite sieht exakt gleich aus wie vor der Migration. Alle Kategorien erscheinen in ihren Sektionen, Babybedarf am Ende.

#### T3: Attribut `columns` funktioniert

**Vorgehen:** Shortcode mit `columns="3"` und `columns="6"` testen.

**Erwartet:** Das Grid zeigt die entsprechende Anzahl Spalten.

#### T4: Attribut `hide_empty` funktioniert

**Vorgehen:** Shortcode mit `hide_empty="1"` testen.

**Erwartet:** Leere Kategorien werden ausgeblendet.

#### T5: Attribut `exclude` funktioniert

**Vorgehen:** Shortcode mit `exclude="60,25"` (oder anderen vorhandenen Kategorie-IDs) testen.

**Erwartet:** Die angegebenen Kategorien werden nicht angezeigt.

#### T6: Attribut `move_last` funktioniert

**Vorgehen:** Shortcode mit `move_last="babybedarf,spielwaren"` testen (Slugs an tatsächliche Gegebenheiten anpassen).

**Erwartet:** Die genannten Kategorien erscheinen am Ende der Liste.

#### T7: WooCommerce-Inaktivität wird abgefangen

**Vorgehen:** (Optional, nur in Testumgebung) WooCommerce kurz deaktivieren und Seite mit Shortcode aufrufen.

**Erwartet:** Kein PHP-Fehler, kein Kurztext. Der Shortcode gibt einen leeren String zurück.

#### T8: Keine PHP-Fehler im Debug-Modus

**Vorgehen:** `WP_DEBUG` auf `true` setzen, Seite „Sortiment" aufrufen.

**Erwartet:** Keine PHP-Warnings, Notices oder Errors.

---

## Technische Notizen

### Kein CSS nötig

Der Shortcode nutzt ausschließlich Kadence-Blocks-CSS-Klassen (`wp-block-group`, `wp-block-heading`, `wp-block-separator`, `barmbini-sortiment-section`) und WooCommerce-Kern-Styling für `[product_categories]`. Es wird **keine neue CSS-Datei** benötigt.

### MU-Plugin nach Migration

Nach erfolgreicher Migration und bestandenen Abnahmetests muss die Datei `mu-plugins/barmbini-sortiment-shortcodes.php` aus der lokalen und der Server-Installation entfernt werden. Dies ist ein **separater Deployment-Schritt** und nicht Teil dieser Entwicklungsaufgabe.

Die Entfernung erfolgt in Modus B (nur Code), da keine Datenbankänderungen betroffen sind. Der Shortcode existiert bereits im Seiteninhalt und wird nach der Migration vom Plugin-Handler bedient.

### Keine `do_shortcode`-Kollision

Da der Shortcode-Name identisch bleibt (`barmbini_top_product_categories`), muss sichergestellt werden, dass der alte MU-Plugin-Handler **nicht mehr** geladen wird, bevor der neue Plugin-Handler aktiv ist. Die MU-Plugin-Datei muss daher unmittelbar nach der Code-Auslieferung gelöscht werden, um doppelte Shortcode-Registrierung zu vermeiden (WordPress würde einen `_doing_it_wrong`-Hinweis ausgeben).

---

## Zusammenfassung für den Entwickler

| Schritt | Was | Wo |
|---------|-----|-----|
| 1 | Neue Klasse `Barmbini_Core_Top_Product_Categories_Shortcode` erstellen | `includes/catalog/class-top-product-categories-shortcode.php` |
| 2 | `require_once` einfügen | `barmbini-core.php` (nach Zeile 27) |
| 3 | `register_top_product_categories_module()` einfügen | `includes/class-plugin.php` |
| 4 | Architekturdokument aktualisieren | `Barmbini_Plugin_Architektur_barmbini-core.md` |
| 5 | 8 Abnahmetests durchführen | Lokale WordPress-Installation |
| 6 | MU-Plugin-Datei entfernen (separater Schritt) | `mu-plugins/barmbini-sortiment-shortcodes.php` |

---

## Umsetzungs- und Deployment-Protokoll (2026-08-05)

### Stand der Umsetzung

Alle Entwicklungsschritte (1–4) wurden im Workspace `d:\Dev\Website\wp-content\plugins\barmbini-core` umgesetzt:

- Neue Datei `includes/catalog/class-top-product-categories-shortcode.php` (Klasse `Barmbini_Core_Top_Product_Categories_Shortcode`)
- `require_once` in `barmbini-core.php` ergänzt
- `register_top_product_categories_module()` in `includes/class-plugin.php` ergänzt
- Architekturdokument `Barmbini_Plugin_Architektur_barmbini-core.md` aktualisiert

### Abnahmetests

Alle 8 Abnahmetests (T1–T8) wurden **bestanden**:

- **T1 (Shortcode erkannt):** `shortcode_exists()` = JA; kein Rohtext im HTML
- **T2 (Seite `/sortiment/` unverändert):** 5 Sektionen gerendert, Babybedarf am Ende
- **T3 (columns=3/6):** Grid-Reihen variieren korrekt
- **T4 (hide_empty=1/true):** leere Kategorie „Bücher" wird ausgeblendet (5→4 Sektionen); Boolean-Parsing korrekt
- **T5 (exclude=60 / leer):** mit Kategorie 60 wird „Unkategorisiert" ausgeschlossen; bei `exclude=""` erscheint sie wieder
- **T6 (move_last):** `move_last="babybedarf"` verschiebt Babybedarf ans Ende; leer ⇒ Babybedarf am Anfang
- **T8 (keine PHP-Fehler):** keine Warnings/Notices im Debug-Modus

Tests wurden im Browser (`http://barmbini.local/sortiment/`) und isoliert per `do_shortcode()`-Skript gegen die lokale WordPress-Installation durchgeführt. Das Testskript wurde nach Abschluss gelöscht.

### Deployment auf den Server (Modus B)

Am 2026-08-05 wurde das Deployment auf den Produktiv-Server `217.160.74.128` durchgeführt:

**Deployment-Modus:** B (nur Code, Live-Daten sicher) — keine Datenbankänderung.

**Ablauf:**
1. **Server-Backup** automatisch via `deploy.ps1` (SQL + wp-content-Archiv in `/root/barmbini-backup-*`)
2. **Code-Deployment** via `.\deploy.ps1 -NoBrowser` — Ergebnis `DEPLOY_OK`
3. **Sanity-Check:** Kadence-Theme OK · `barmbini-core` OK · WordPress-Index OK
4. **Cache geleert** (WP-Cache + `wp cache flush`)
5. **MU-Plugin-Entfernung:** Die alte Datei `/var/www/barmbini/wp-content/mu-plugins/barmbini-sortiment-shortcodes.php` (3813 B) wurde **nach** dem Code-Deployment mit Backup nach `/root/barmbini-muplugin-backup-2026-08-05-100340` verschoben (nicht gelöscht, Rollback möglich)

**Live-Verifikation** (`http://217.160.74.128/sortiment/`):
- Kein Rohtext-Shortcode
- Keine PHP-Fehler, keine doppelte Shortcode-Registrierung (`_doing_it_wrong` nicht aufgetreten)
- 5 Sortiment-Sektionen gerendert, Babybedarf am Ende

**Hinweis:** Die `deploy.ps1`-Modus-B-Archivliste enthält nur `languages`/`plugins`/`themes`/`index.php` — **nicht** `mu-plugins`. Server-MU-Plugins müssen daher manuell gepflegt werden. Dieses Deployment hat das manuell per Skript erledigt.

### Rollback-Pfade

| Ebene | Sicherungsort |
|-------|---------------|
| Live-MU-Plugin | `/root/barmbini-muplugin-backup-2026-08-05-100340` am 2026-08-05 |
| Live-wp-content (Deploy-Backup) | `/root/barmbini-backup-*` (erstellt von `deploy.ps1`) |
| Lokale Plugin-Dateien (vor Migration) | `D:\Local Sites\barmbini\app\public\wp-content\bak-migration-2026-08-05-115334` |
| Lokales MU-Plugin | `D:\Local Sites\barmbini\app\public\wp-content\mu-plugins\barmbini-sortiment-shortcodes.php.bak` |

### Offene / optionale Folgepunkte

- **HTTPS-Produktbild** `Piramidespielzeug.png` wird über `https://` mit `ERR_CONNECTION_REFUSED` geladen — unabhängig von dieser Migration; liegt an fehlender HTTPS-Konfiguration bzw. echtem Zertifikat.
- Die lokale `.bak`-Datei des MU-Plugins und das lokale Backup können nach einer Abkühl-/Beobachtungsphase entfernt werden.
