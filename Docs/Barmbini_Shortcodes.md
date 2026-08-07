# Shortcode-Referenz – Sozialkaufhaus Barmbini

Alle projektspezifischen Shortcodes werden vom Plugin **`barmbini-core`** bereitgestellt und können über den Gutenberg-**Shortcode-Block** auf jeder Seite eingefügt werden. Es gibt aktuell **vier** Shortcodes:

| Shortcode | Zweck | Klasse | Modul |
|-----------|-------|--------|-------|
| `[barmbini_address]` | Adressblock (Kontakt) | `Barmbini_Core_Address_Shortcode` | `class-address-shortcode.php` |
| `[barmbini_latest_news]` | Letzte Neuigkeiten-Beiträge | `Barmbini_Core_Latest_News_Shortcode` | `class-latest-news-shortcode.php` |
| `[barmbini_promotion]` | Aktuell gültige Aktionen | `Barmbini_Core_Promotion_Shortcode` | `class-promotion-shortcode.php` |
| `[barmbini_top_product_categories]` | Sortiment-Kategorien als Grid | `Barmbini_Core_Top_Product_Categories_Shortcode` | `class-top-product-categories-shortcode.php` |

Alle Klassen liegen unter `wp-content/plugins/barmbini-core/includes/catalog/`.

---

## 1. `[barmbini_address]` – Adressblock

**Zweck:** Gibt einen formatierten Adressblock aus (identisch zum Format auf der Seite `/barrierefreiheit/`).

**Basissyntax:**

```
[barmbini_address]
```

**Datenquelle:** Die Adressdaten werden zentral in `wp_options` (`barmbini_address_data`) gespeichert und können im Admin unter **Einstellungen → Barmbini Adresse** bearbeitet werden.

**Verfügbare Felder:** `shortname`, `name`, `street`, `address2`, `zip`, `city`, `phone`, `email`

### Attribute

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|--------------|
| `show` | `string` | `''` | Komma-separierte Liste – nur diese Felder anzeigen |
| `hide` | `string` | `''` | Komma-separierte Liste – diese Felder ausblenden |
| `shortname`, `name`, `street`, `address2`, `zip`, `city`, `phone`, `email` | `string` | gespeicherter Wert | Überschreibt den zentral gespeicherten Wert für genau dieses Feld |

**Wichtige Regel:** Einzelparameter gewinnen **immer** gegen `show`/`hide`. Werte dürfen `<strong>` und `<br>` enthalten (werden sicher gefiltert).

### Beispiele

```
[barmbini_address]
```
→ Vollständiger Adressblock mit den zentral gespeicherten Daten.

```
[barmbini_address show="phone,email"]
```
→ Nur Telefon und E-Mail anzeigen.

```
[barmbini_address hide="address2"]
```
→ Adressblock ohne die Zeile „Im Hinterhof".

```
[barmbini_address shortname="Adresse" hide="name"]
```
→ Zeigt „Adresse" als Kurzname (Einzelparameter gewinnt gegen `hide`).

```
[barmbini_address phone="040 / 123 456"]
```
→ Telefonnummer im Block überschreiben.

---

## 2. `[barmbini_latest_news]` – Letzte Neuigkeiten

**Zweck:** Gibt die letzten veröffentlichten Beiträge aus der Kategorie **„Neuigkeiten"** aus, jeweils mit Titel (als Link), Datum und optional Kurztext.

**Basissyntax:**

```
[barmbini_latest_news]
```

### Attribute

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|--------------|
| `count` | `int` | `3` | Anzahl der Beiträge (min. 1, max. 10) |
| `show_excerpt` | `bool` | `true` | Kurztext anzeigen (`1`, `true`, `yes`, `on`) |
| `show_date` | `bool` | `true` | Datum anzeigen |
| `empty_message` | `string` | `''` | Text bei leerer Ergebnisliste |

### Beispiele

```
[barmbini_latest_news]
```
→ Die letzten 3 Neuigkeiten mit Datum und Kurztext.

```
[barmbini_latest_news count="5"]
```
→ 5 Beiträge anzeigen.

```
[barmbini_latest_news show_date="0"]
```
→ Datum ausblenden.

```
[barmbini_latest_news show_excerpt="0"]
```
→ Kurztext ausblenden.

```
[barmbini_latest_news empty_message="Aktuell gibt es keine Neuigkeiten."]
```
→ Platzhaltertext, wenn keine Beiträge vorhanden sind.

---

## 3. `[barmbini_promotion]` – Aktionen

**Zweck:** Gibt alle **aktuell gültigen** Aktionen des Custom Post Type `barmbini_aktion` aus. Eine Aktion ist gültig, wenn ihr Zeitraum (Start- bis Enddatum) das heutige Datum umfasst. Abgelaufene und noch nicht begonnene Aktionen erscheinen automatisch nicht.

**Basissyntax:**

```
[barmbini_promotion]
```

**Wichtiger Hinweis (Cache):** Da Aktionen rein durch Verstreichen des Datums ungültig werden, leert ein WP-Cron-Job (`barmbini_core_cache_maintenance`, alle 6 Stunden) den WP Fastest Cache, damit abgelaufene Aktionen zuverlässig von der Startseite verschwinden.

### Attribute

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|--------------|
| `show_image` | `bool` | `true` | Flyer-Bild (Beitragsbild) anzeigen |
| `show_date` | `bool` | `true` | Gültigkeitszeitraum anzeigen |
| `show_description` | `bool` | `true` | Beschreibung anzeigen (sofern pro Aktion freigeschaltet) |
| `empty_message` | `string` | `''` | Text bei keiner gültigen Aktion |

### Beispiele

```
[barmbini_promotion]
```
→ Alle aktuell gültigen Aktionen mit Bild, Zeitraum und Beschreibung.

```
[barmbini_promotion show_image="0"]
```
→ Ohne Flyer-Bild.

```
[barmbini_promotion show_description="0"]
```
→ Ohne Beschreibungstext.

```
[barmbini_promotion empty_message="Aktuell keine Aktionen."]
```
→ Platzhaltertext, wenn gerade keine Aktion läuft.

**Pflege:** Aktionen werden im Admin unter dem Menüpunkt **„Aktionen"** gepflegt (Titel, Beschreibung, Flyer-Bild, Start-/Enddatum). Details siehe `Barmbini_Anleitung_Aktionen_Admin.md`.

---

## 4. `[barmbini_top_product_categories]` – Sortiment-Kategorien

**Zweck:** Rendert die Top-Level-Produktkategorien des WooCommerce-Katalogs als gruppiertes Grid. Jede Hauptkategorie wird als Sektion mit Überschrift dargestellt; darin erscheinen ihre Unterkategorien (bzw. bei Kategorien ohne Unterkategorien die Kategorie selbst). Sektionen werden durch eine Trennlinie (`<hr>`) getrennt.

**Basissyntax (wie auf der Seite „Sortiment" verwendet):**

```
[barmbini_top_product_categories columns="4" hide_empty="0" exclude="60"]
```

**Intern:** Das Rendering nutzt den WooCommerce-Shortcode `[product_categories ids="…" columns="…" hide_empty="…"]`.

### Attribute

| Attribut | Typ | Standard | Beschreibung |
|----------|-----|----------|--------------|
| `columns` | `int` | `4` | Anzahl Spalten im Grid |
| `hide_empty` | `bool` | `0` | Leere Kategorien ausblenden (`1`, `true`, `yes`, `on`) |
| `exclude` | `string` | `60` | Komma-separierte Kategorie-IDs, die ausgeschlossen werden (60 = „Unkategorisiert") |
| `move_last` | `string` | `babybedarf` | Komma-separierte Slugs, die ans Ende der Liste sortiert werden |
| `parent` | `int` | `0` | ID der Elternkategorie (`0` = Top-Level) |
| `orderby` | `string` | `menu_order` | Sortierkriterium für `get_terms` |
| `order` | `string` | `ASC` | Sortierrichtung (`ASC` oder `DESC`) |

### Beispiele

```
[barmbini_top_product_categories]
```
→ Alle Top-Level-Kategorien, 4 Spalten, „Babybedarf" am Ende.

```
[barmbini_top_product_categories columns="3"]
```
→ Grid mit 3 Spalten.

```
[barmbini_top_product_categories hide_empty="1"]
```
→ Leere Kategorien ausblenden.

```
[barmbini_top_product_categories exclude=""]
```
→ Keine Kategorie ausschließen („Unkategorisiert" erscheint wieder).

```
[barmbini_top_product_categories move_last="babybedarf,spielwaren"]
```
→ Mehrere Slugs ans Ende verschieben.

```
[barmbini_top_product_categories parent="61"]
```
→ Nur Unterkategorien einer bestimmten Hauptkategorie anzeigen.

---

## Gemeinsame Regeln

- **Boolean-Attribute:** `1`, `true`, `yes`, `on` (case-insensitiv) gelten als wahr; alles andere als falsch.
- **Einbindung:** Im Gutenberg-Editor über den Block **„Shortcode"** einfügen.
- **CSS:** Die Ausgabe nutzt Projekt-Klassen (z. B. `barmbini-sortiment-section`, `barmbini-promotions`) und Kadence-Block-Klassen; es wird kein globales Theme-CSS überschrieben.
- **Mehrere Shortcodes** auf derselben Seite sind möglich.

## Technische Einordnung

| Shortcode | Registrierung | Sonstiges |
|-----------|---------------|-----------|
| `barmbini_address` | `register()` in `class-address-shortcode.php` | Daten in `wp_options`; Widget im Admin teilt die Daten |
| `barmbini_latest_news` | `register()` in `class-latest-news-shortcode.php` | Kategorie „Neuigkeiten" |
| `barmbini_promotion` | `register()` in `class-promotion-shortcode.php` | CPT `barmbini_aktion`; Datumsfilter `start ≤ heute ≤ end` |
| `barmbini_top_product_categories` | `register()` in `class-top-product-categories-shortcode.php` | Migriert aus dem MU-Plugin `barmbini-sortiment-shortcodes.php` |
