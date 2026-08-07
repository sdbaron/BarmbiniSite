# Detaillierte Aufgabe: Letzte Neuigkeiten auf der Startseite anzeigen

## Ziel

Auf der Startseite der Website Sozialkaufhaus Barmbini soll ein Block eingefügt werden, der die letzten drei Neuigkeiten-Beiträge anzeigt.

Der Block soll im Gutenberg-Editor auf der Startseite platzierbar sein und automatisch die aktuellsten veröffentlichten Beiträge aus der Kategorie „Neuigkeiten" darstellen. Jede angezeigte Neuigkeit soll aus Titel, Datum und einem kurzen Anrisstext bestehen und auf den vollständigen Beitrag verlinken.

## Quellenbasis

Die Aufgabe basiert auf:

- `Barmbini_Technisches_Konzept_v2.5.md` — insbesondere §7.1 (Startseite: „Letzte 3 Blogbeiträge")
- `Barmbini_Plugin_Architektur_barmbini-core.md` — Zielstruktur des Plugins
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` — verifizierter Ist-Stand und Einbauorte
- dem bestehenden Plugin `wp-content/plugins/barmbini-core/`
- dem bestehenden Shortcode-Muster `[barmbini_address]` in `includes/catalog/class-address-shortcode.php`

## Fachliche Leitplanken

Die Umsetzung muss zu den bestehenden Projektgrundsätzen passen:

- Die Website ist einsprachig deutsch.
- WooCommerce dient als Produktkatalog, nicht als klassischer Shop mit Checkout.
- Das Projekt folgt dem Minimalprinzip bei Plugins.
- Neue Fachlogik gehört in das Plugin `barmbini-core`, nicht in `themes/kadence/functions.php`.
- Für reine Template- oder CSS-Anpassungen wäre ein Child-Theme die richtige Stelle — diese Aufgabe betrifft aber eine wiederverwendbare Komponente und gehört daher ins Plugin.
- Der Block wird als Shortcode bereitgestellt, damit er per Gutenberg-Shortcode-Block auf jeder beliebigen Seite eingefügt werden kann.
- Es werden keine externen Drittanbieter-Services oder Schriften geladen.

## Verbindliche Annahmen für diese Aufgabe

Damit die Aufgabe umsetzbar und prüfbar ist, gelten für diese Version folgende Annahmen:

1. „Neuigkeiten" meint WordPress-Beiträge (`post_type=post`), die der Kategorie `Neuigkeiten` (Slug: `neuigkeiten`) zugeordnet sind.
2. Es werden nur veröffentlichte Beiträge (`publish`) angezeigt.
3. Die Sortierung erfolgt absteigend nach Veröffentlichungsdatum.
4. Die Standardanzahl beträgt 3 Beiträge und ist per Shortcode-Attribut überschreibbar.
5. Der Shortcode hat keine Abhängigkeit zu einem bestimmten Theme und funktioniert mit Kadence sowie anderen Themes.
6. Die Komponente ist rein darstellend — sie löst keine Benachrichtigungen aus und interagiert nicht mit dem Abonnementssystem.

## Nicht Bestandteil dieser Aufgabe

Folgende Punkte gehören ausdrücklich nicht zum Umfang:

- Benachrichtigungs- oder E-Mail-Versand
- Abonnements- oder Kundenkonto-Logik
- Admin-Einstellungsseiten für den Block
- Paginierung oder „Mehr laden"-Funktion
- Aufwändige Animationen oder Slider
- Änderungen am Theme oder an `functions.php`

## Umzusetzender Funktionsumfang

Die Lösung muss die folgenden fachlichen Fähigkeiten abdecken:

1. Ein Shortcode `[barmbini_latest_news]`, der die letzten Neuigkeiten-Beiträge ausgibt.
2. Der Shortcode ist im Gutenberg-Editor über den Shortcode-Block einfügbar.
3. Pro Beitrag werden dargestellt: Titel (als Link zum Beitrag), Veröffentlichungsdatum, Kurztext (Excerpt).
4. Die Anzahl der Beiträge ist per Attribut `count` steuerbar.
5. Datum und Kurztext sind einzeln per Attribut ein- und ausblendbar.
6. Ist kein Beitrag in der Kategorie vorhanden, erscheint eine konfigurierbare Leermeldung oder gar keine Ausgabe.
7. Das erzeugte HTML ist semantisch korrekt, barrierearm und responsive.
8. Die Komponente ist mit einer separaten CSS-Datei gestaltbar, überschreibt aber kein Theme-CSS unspezifisch.

## Aufgabe

### 1. Shortcode-Klasse im Plugin anlegen

Ziel: Eine neue PHP-Klasse im `catalog/`-Modul, die den Shortcode registriert und das Rendering übernimmt.

Arbeitsschritte:

1. Neue Datei anlegen: `wp-content/plugins/barmbini-core/includes/catalog/class-latest-news-shortcode.php`
2. Klasse `Barmbini_Core_Latest_News_Shortcode` nach dem Muster der bestehenden `Barmbini_Core_Address_Shortcode` aufbauen.
3. Eine öffentliche Methode `register()` implementieren, die `add_shortcode( 'barmbini_latest_news', array( $this, 'render' ) )` aufruft.
4. Eine öffentliche Methode `render( $atts )` implementieren.

Erwartete Struktur der `render()`-Methode:

- Shortcode-Attribute parsen: `count` (Standard: 3, Maximum: 10), `show_excerpt` (Standard: `true`), `show_date` (Standard: `true`), `empty_message` (Standard: leer).
- `WP_Query` mit `category_name=neuigkeiten`, `posts_per_page=$count`, `post_status=publish`, `orderby=date`, `order=DESC`, `no_found_rows=true` ausführen.
- Bei leerem Ergebnis: entweder nichts ausgeben oder die konfigurierte `empty_message`.
- Bei Treffern: ein `<div class="barmbini-latest-news">` ausgeben, darin pro Beitrag ein `<article>` mit:
  - `<h3 class="barmbini-news-title"><a href="...">Titel</a></h3>`
  - `<time class="barmbini-news-date" datetime="...">Formatierter Zeitstempel</time>` (nur wenn `show_date` aktiv)
  - `<p class="barmbini-news-excerpt">...</p>` (nur wenn `show_excerpt` aktiv)
- `wp_reset_postdata()` nicht vergessen.

Abnahmekriterium:

- Der Shortcode `[barmbini_latest_news]` gibt ohne Fehler die letzten drei Neuigkeiten-Beiträge aus.
- Die Klasse ist nach dem bestehenden Plugin-Architekturmuster aufgebaut.

### 2. Shortcode im Plugin-Bootstrap registrieren

Ziel: Die neue Klasse muss geladen und ihre `register()`-Methode aufgerufen werden.

Arbeitsschritte:

1. In `barmbini-core.php` die neue Datei per `require_once` laden (analog zu den anderen `catalog/`-Dateien).
2. In `includes/class-plugin.php` eine neue geschützte Methode `register_latest_news_module()` anlegen.
3. Darin die Klasse instanziieren und `register()` aufrufen.
4. Die neue Methode im Konstruktor von `Barmbini_Core_Plugin` aufrufen.
5. Optional: Eine CSS-Datei `assets/css/latest-news.css` anlegen und in der `render()`-Methode enqueuen.

Abnahmekriterium:

- Der Shortcode ist nach Plugin-Aktivierung nutzbar.
- Die Registrierung folgt exakt dem bestehenden Muster (z. B. `register_address_shortcode_module()`).

### 3. Semantisches und barrierearmes Markup sicherstellen

Ziel: Die HTML-Ausgabe muss den Projektansprüchen an Barrierefreiheit genügen.

Pflichtanforderungen:

1. Jeder Neuigkeiten-Eintrag ist ein eigenes `<article>`-Element.
2. Der Beitragstitel ist in einer sinnvollen Überschriftenebene (`<h3>`, da `<h1>` die Seitennummer und `<h2>` der Block-Titel sein können).
3. Das Datum verwendet das semantische `<time>`-Element mit gültigem `datetime`-Attribut (ISO 8601).
4. Alle Links haben aussagekräftige Linktexte (der Beitragstitel).
5. Die Beitragsbilder erhalten ein `alt`-Attribut (falls in einer späteren Version ergänzt).

CSS-Vorgaben:

- Keine absoluten Schriftgrößen, nach Möglichkeit `rem` oder theme-eigene Variablen verwenden.
- Ausreichende Kontraste sicherstellen (mindestens AA-Niveau).
- Keine `!important`-Regeln, die Theme-Styles überschreiben könnten.
- Responsive Darstellung: auf kleinen Bildschirmen untereinander, auf größeren nebeneinander oder als Liste.

Abnahmekriterium:

- Das Markup ist valide und verwendet semantisch korrekte HTML5-Elemente.
- Die Darstellung funktioniert auf Mobilgeräten (320 px Breite) ohne horizontales Scrollen.

### 4. Shortcode im Gutenberg-Editor testen

Ziel: Sicherstellen, dass Redakteure den Block ohne technische Kenntnisse auf der Startseite platzieren können.

Arbeitsschritte:

1. Im WordPress-Admin die Startseite im Gutenberg-Editor öffnen.
2. Einen Shortcode-Block hinzufügen.
3. `[barmbini_latest_news]` eintragen.
4. Vorschau und Frontend prüfen.

Optionale Attributvarianten testen:

- `[barmbini_latest_news count="5"]`
- `[barmbini_latest_news show_date="0"]`
- `[barmbini_latest_news show_excerpt="0"]`
- `[barmbini_latest_news empty_message="Aktuell gibt es keine Neuigkeiten."]`

Abnahmekriterium:

- Der Shortcode-Block rendert im Frontend korrekt.
- Die Attribute verändern die Ausgabe wie erwartet.

### 5. Lokale Validierung und Sync

Ziel: Die Änderungen müssen lokal getestet und mit dem bestehenden Deployment-Workflow synchronisierbar sein.

Arbeitsschritte:

1. Das Plugin `barmbini-core` in der lokalen WordPress-Installation (`D:\Local Sites\barmbini\app\public`) aktivieren.
2. Mindestens drei Beiträge in der Kategorie „Neuigkeiten" anlegen, falls noch nicht vorhanden.
3. Den Shortcode auf der Startseite einfügen.
4. Frontend in mehreren Browsern und Auflösungen prüfen.
5. `sync.ps1` ausführen, um die Plugin-Änderungen vom Workspace in die lokale Installation zu übernehmen (oder den Workspace-Stand direkt als Quelle verwenden, je nach eingerichtetem Workflow).

Abnahmekriterium:

- Die lokale Installation zeigt die letzten drei Neuigkeiten auf der Startseite korrekt an.
- `sync.ps1` überträgt die neuen Dateien erfolgreich.

### 6. Dokumentation aktualisieren

Ziel: Projektdokumentation um den neuen Shortcode ergänzen.

Arbeitsschritte:

1. In `Barmbini_Plugin_Architektur_barmbini-core.md` den neuen Shortcode unter dem Catalog-Modul ergänzen.
2. In `Docs/Barmbini_Seiteninhalte.md` unter „Startseite“ den Shortcode-Hinweis einfügen.
3. In `Barmbini_Vorbereitung_Features_und_Bugfixes.md` den verifizierten Ist-Stand um den neuen Shortcode erweitern.

Abnahmekriterium:

- Die Dokumentation beschreibt den neuen Shortcode mit seinen Attributen und dem Einsatzzweck.

## Technische Details

### Shortcode-Referenz

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|-------------|
| `count` | `int` | `3` | Anzahl der anzuzeigenden Beiträge (max. 10) |
| `show_excerpt` | `bool` | `true` | Kurztext unter dem Titel anzeigen (`1`, `true`, `yes`) |
| `show_date` | `bool` | `true` | Veröffentlichungsdatum anzeigen (`1`, `true`, `yes`) |
| `empty_message` | `string` | `''` | Text, der bei keinen Beiträgen erscheint |

### WP_Query-Parameter

```php
$query_args = array(
    'category_name'  => 'neuigkeiten',
    'posts_per_page' => $count,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
    'no_found_rows'  => true,
);
```

### CSS-Klasse-Struktur

| Klasse | Element |
|--------|---------|
| `.barmbini-latest-news` | Äußerer Container |
| `.barmbini-news-item` | Einzelner Beitrag (article) |
| `.barmbini-news-title` | Beitragstitel |
| `.barmbini-news-date` | Veröffentlichungsdatum |
| `.barmbini-news-excerpt` | Kurztext |
| `.barmbini-news-empty` | Leermeldung (optional) |

### Zielverzeichnisse

```
wp-content/plugins/barmbini-core/
├── includes/
│   └── catalog/
│       └── class-latest-news-shortcode.php   ← NEU
├── assets/
│   └── css/
│       └── latest-news.css                    ← NEU (optional)
├── includes/
│   └── class-plugin.php                       ← ÄNDERN
└── barmbini-core.php                          ← ÄNDERN
```

## Querverweise

- Bestehendes Shortcode-Muster: `includes/catalog/class-address-shortcode.php`
- Plugin-Bootstrap: `barmbini-core.php` (Zeilen 28–47 für require_once-Reihenfolge)
- Modulregistrierung: `includes/class-plugin.php` (`register_address_shortcode_module()` als Vorlage)
- Neuigkeiten-Kategorie: siehe `Barmbini_Technisches_Konzept_v2.5.md` §7.6
- Startseiten-Konzept: siehe `Barmbini_Technisches_Konzept_v2.5.md` §7.1
