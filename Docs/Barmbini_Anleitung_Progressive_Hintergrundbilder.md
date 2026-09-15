# Anleitung: Progressive Hintergrundbilder

Diese Anleitung beschreibt, wie das **progressive Laden** von CSS-Hintergrundbildern im Plugin `barmbini-core` (ab Version **0.10.2**) funktioniert und wie Sie es anpassen.

Zielgruppe: Administratoren und Entwickler, die Hintergründe (z. B. Kadence-Sections) schneller und stufenweise laden wollen.

---

## 1. Was macht das Feature?

Statt ein großes Hintergrundbild sofort zu laden, gilt:

1. **Sofort sichtbar (CSS/Kadence):** grauer Platzhalter und/oder ein kleines Bild im Block.
2. **Nach `DOMContentLoaded`:** das Skript startet erst dann (kein HQ-Nachladen während des HTML-Parsings).
3. **Wenn das Element in den Viewport kommt:** das passende höherwertige Bild wird nachgeladen.
4. **Übergang:** leichter Blur nur über dem Hintergrund (Text und Buttons bleiben scharf).

Das gilt für echte CSS-`background-image`-Flächen (Kadence Section/Column), **nicht** für normale Bild-Blöcke mit `<img>` (dort nutzt WordPress bereits `srcset`).

---

## 2. Zwei Wege zur Anpassung

| Weg | Wann geeignet? | Wer |
|-----|----------------|-----|
| **A) HTML-Attribute** `data-bg-src…` | Einzelnes Element im Markup / Custom-HTML | Redaktion mit HTML-Kenntnis |
| **B) PHP-Filter** `barmbini_progressive_bg_targets` | Bestehende Kadence-Blöcke ohne Markup-Änderung | Entwickler (Plugin/MU-Plugin) |

Beide Wege können parallel genutzt werden. Das Skript sucht alle Elemente mit `data-bg-*` und wendet zusätzlich die konfigurierten CSS-Selektoren an.

---

## 3. Weg A: Attribute am Element

Markieren Sie den Container, der den Hintergrund tragen soll:

```html
<div
  class="barmbini-progressive-bg"
  data-bg-lq="https://barmbini.de/wp-content/uploads/2026/09/Hintergrund-480.jpg"
  data-bg-src-sm="https://barmbini.de/wp-content/uploads/2026/09/Hintergrund-480.jpg"
  data-bg-src-md="https://barmbini.de/wp-content/uploads/2026/09/Hintergrund-800.jpg"
  data-bg-src="https://barmbini.de/wp-content/uploads/2026/09/Hintergrund-1920.jpg"
>
  <!-- Inhalt der Section -->
</div>
```

### Bedeutungen der Attribute

| Attribut | Pflicht? | Bedeutung |
|----------|----------|-----------|
| `data-bg-src` | empfohlen | Desktop / hohe Qualität (ab ca. 1025 px Viewport-Breite) |
| `data-bg-src-md` | optional | Tablet (768–1024 px) |
| `data-bg-src-sm` | optional | Mobile (unter 768 px) |
| `data-bg-lq` | optional | Sofortiger Platzhalter (kleines Bild), bevor HQ geladen wird |
| Klasse `barmbini-progressive-bg` | empfohlen | Aktiviert Platzhalter-Styles; wird vom Skript sonst ergänzt |

### Auswahlregel (Viewport)

1. Breite **&lt; 768 px** → `data-bg-src-sm`, sonst Fallback auf `md` / `src`
2. Breite **&lt; 1025 px** → `data-bg-src-md`, sonst `src`
3. Sonst → `data-bg-src`

Mindestens eines der Attribute `data-bg-src`, `data-bg-src-md` oder `data-bg-src-sm` muss gesetzt sein.

### Sicherheit

Es werden nur URLs vom **eigenen Site-Host** (und relativen Pfaden wie `/wp-content/...`) akzeptiert. Fremde Domains werden ignoriert (Filter `barmbini_progressive_bg_allowed_hosts` für Ausnahmen).

---

## 4. Weg B: PHP-Filter (Kadence ohne Markup-Eingriff)

In einem kleinen MU-Plugin oder in projekteigenem Code:

```php
add_filter( 'barmbini_progressive_bg_targets', function ( $targets ) {
	$base = trailingslashit( wp_upload_dir()['baseurl'] ) . '2026/09/';

	$targets[] = array(
		'selector' => '.kadence-columnMEINE_ID > .kt-inside-inner-col',
		'src'      => $base . 'Mein-Hintergrund-1920.jpg',
		'srcMd'    => $base . 'Mein-Hintergrund-800.jpg',
		'srcSm'    => $base . 'Mein-Hintergrund-480.jpg',
		'lq'       => $base . 'Mein-Hintergrund-480.jpg',
	);

	return $targets;
} );
```

### Felder eines Targets

| Schlüssel | Pflicht? | Bedeutung |
|-----------|----------|-----------|
| `selector` | ja | CSS-Selektor des Hintergrund-Containers |
| `src` | mind. eines von src/srcMd/srcSm | Desktop-HQ |
| `srcMd` | optional | Tablet |
| `srcSm` | optional | Mobile |
| `lq` | optional | Sofort-Platzhalter |

Das Skript setzt daraus automatisch die `data-bg-*`-Attribute am gefundenen Element.

### CSS-Selektor einer Kadence-Column finden

1. Seite im Browser öffnen → Rechtsklick auf den Hintergrundbereich → **Untersuchen**.
2. Nach einer Klasse wie `kadence-column13_dbd800-e9` suchen.
3. Der Hintergrund sitzt oft auf dem Kind `.kt-inside-inner-col`:

```text
.kadence-column13_dbd800-e9 > .kt-inside-inner-col
```

> **Hinweis:** Die ID (`13_dbd800-e9`) kann sich ändern, wenn der Block neu angelegt wird. Dann den Selektor im Filter bzw. in `class-progressive-bg.php` anpassen.

---

## 5. Aktueller Standard auf der Startseite

Ab 0.10.2 ist auf der **Startseite** (`is_front_page()`) dieses Ziel vorkonfiguriert:

| | |
|---|---|
| Selektor | `.kadence-column13_dbd800-e9 > .kt-inside-inner-col` |
| Mobile / LQ | `…/2026/09/Hintergrund-480.jpg` |
| Tablet | `…/2026/09/Hintergrund-800.jpg` |
| Desktop | `…/2026/09/Hintergrund-1920.jpg` |

Code-Stelle: `includes/catalog/class-progressive-bg.php` → Methode `get_targets()`.

### Bilder austauschen (Startseite)

1. Neue Varianten (klein / mittel / groß) in die Mediathek hochladen.
2. In `get_targets()` (oder per Filter) die Dateinamen/URLs ersetzen.
3. **Kein Hintergrundbild im Kadence-Block** für diese Column setzen (oder leeren). Sonst schreibt Kadence `background-image` ins CSS — der Browser lädt die Datei **schon während des Parsings**, vor `DOMContentLoaded`, unabhängig von `lq` / Progressive-JS.
4. Optional nur `background-color` in Kadence als Platzhalter.
5. Cache leeren (WP Fastest Cache) und hard-reload im Browser.

### Warum lädt `Hintergrund-480.jpg` trotzdem vor DOMContentLoaded?

| Quelle | Wann lädt das Bild? |
|--------|---------------------|
| Kadence-Block → „Hintergrundbild“ / `backgroundImg` | Sofort mit dem CSS (oft **vor** DOMContentLoaded) |
| Progressive-JS `lq` / `data-bg-lq` | Erst **nach** DOMContentLoaded |
| Progressive-JS `src` / `srcMd` / `srcSm` | Nach DOMContentLoaded + Sichtbarkeit |

`lq` auskommentieren verhindert nur das Setzen durch unser Skript — **nicht** ein Bild, das Kadence bereits im CSS hat. Auf Desktop ohne `lq` und ohne Kadence-Hintergrundbild startet erst nach DOMContentLoaded der Download von `src` (1920) bzw. auf schmaleren Viewports `srcMd`/`srcSm`.

### Anderen Block statt der aktuellen Column

1. Neuen Selektor ermitteln (siehe oben).
2. In `get_targets()` den `selector` und die Bild-URLs anpassen **oder** per Filter ein weiteres Target hinzufügen / das alte ersetzen.
3. Alte, widersprüchliche Custom-CSS-Regeln am Block entfernen (keine `background-image: … !important` Media Queries, die das JS überschreiben).

---

## 6. CSS-Klassen (Verhalten)

| Klasse | Bedeutung |
|--------|-----------|
| `barmbini-progressive-bg` | Element nimmt am Feature teil (Platzhalter-Styles) |
| `barmbini-progressive-bg--loaded` | HQ (oder gewählte Variante) ist geladen |
| `barmbini-progressive-bg--failed` | Laden fehlgeschlagen; Platzhalter bleibt |
| `barmbini-progressive-bg--reduced-data` | Nutzer hat „Datensparmodus“; es bleibt bei LQ |

Stylesheet: `assets/css/progressive-bg.css`  
Skript: `assets/js/progressive-bg.js`

---

## 7. Typische Anpassungen (Kurzcheckliste)

| Wunsch | Vorgehen |
|--------|----------|
| Andere Bilddateien | URLs in Filter/`get_targets()` oder in `data-bg-*` ändern |
| Anderer Breakpoint | Logik in `progressive-bg.js` (`768` / `1025`) anpassen |
| Weiteres Element | Neues Target im Filter **oder** `data-bg-*` am Markup |
| Startseiten-Ziel entfernen | Filter liefert leeres Array für dieses Selektor-Target, oder `get_targets()`-Default leeren |
| Nur grauer Hintergrund, kein LQ-Bild | `lq` / `data-bg-lq` weglassen; Klasse behalten |
| Fremde CDN-Domain erlauben | Filter `barmbini_progressive_bg_allowed_hosts` |

---

## 8. Fehlerbehebung

| Symptom | Mögliche Ursache |
|---------|------------------|
| Immer nur das kleine Bild | JS nicht geladen; Cache; Selektor trifft nicht; HQ-URL falsch/blockiert |
| Großes Bild sofort | Element hat kein Progressive-Setup; Kadence setzt HQ inline/`!important` |
| Kein Übergang / kein Blur | Klasse fehlt; altes CSS überschreibt |
| Bild von anderer Domain wird ignoriert | Host-Whitelist (siehe Sicherheit) |
| Nach Block-Neuanlage wirkungslos | Column-`uniqueID` geändert → Selektor aktualisieren |

**Prüfung im Browser:** Entwicklertools → Netzwerk: zuerst kleine Datei, nach Scrollen/Sichtbarkeit die größere. Elements: `data-bg-src…` und Klasse `barmbini-progressive-bg--loaded`.

---

## 9. Dateien im Plugin

| Datei | Rolle |
|-------|-------|
| `includes/catalog/class-progressive-bg.php` | Registrierung, Defaults, Filter |
| `assets/js/progressive-bg.js` | Start nach DOMContentLoaded, IntersectionObserver, URL-Wahl, Sicherheitscheck |
| `assets/css/progressive-bg.css` | Platzhalter und Übergang |

Technik-Kurzbeschreibung auch in: `Barmbini_Plugin_Architektur_barmbini-core.md` (Catalog-Modul).
