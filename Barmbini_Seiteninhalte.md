# Seiteninhalte – Sozialkaufhaus Barmbini

---

## Startseite

Auf der Startseite wird über den Gutenberg-Editor ein Shortcode-Block mit folgendem Inhalt platziert:

```
[barmbini_latest_news]
```

Dieser Shortcode gibt die letzten drei veröffentlichten Beiträge aus der Kategorie „Neuigkeiten" aus, jeweils mit Titel (als Link), Datum und Kurztext.

Optionale Attributvarianten:

| Shortcode | Wirkung |
|-----------|---------|
| `[barmbini_latest_news count="5"]` | Zeigt 5 Beiträge an |
| `[barmbini_latest_news show_date="0"]` | Datum ausblenden |
| `[barmbini_latest_news show_excerpt="0"]` | Kurztext ausblenden |
| `[barmbini_latest_news empty_message="Aktuell keine Neuigkeiten."]` | Text bei leerer Liste |

Weitere Details siehe `Barmbini_Aufgabe_Startseite_Letzte_Neuigkeiten.md`.

---

## Aktionen

Auf der Startseite können zusätzlich aktuell gültige Aktionen eingeblendet werden. Dafür wird ein Shortcode-Block mit folgendem Inhalt platziert:

```
[barmbini_promotion]
```

Dieser Shortcode gibt alle Aktionen aus dem Custom Post Type `barmbini_aktion` aus, deren Gültigkeitszeitraum (Start-/Enddatum) das heutige Datum umfasst. Jede Aktion zeigt Flyer-Bild, Titel, Beschreibung und Gültigkeitszeitraum. Flyer und Titel sind auf die Einzelansicht der Aktion verlinkt (z. B. `/aktion/sommerschlussverkauf/`).

Aktionen werden im Admin unter dem Menüpunkt „Aktionen" gepflegt. Redakteure legen dort Titel, Beschreibung, Flyer-Bild (Beitragsbild), Startdatum und Enddatum an. Abgelaufene und noch nicht begonnene Aktionen erscheinen automatisch nicht.

Optionale Attributvarianten:

| Shortcode | Wirkung |
|-----------|---------|
| `[barmbini_promotion show_image="0"]` | Flyer-Bild ausblenden |
| `[barmbini_promotion show_date="0"]` | Gültigkeitszeitraum ausblenden |
| `[barmbini_promotion show_description="0"]` | Beschreibung ausblenden |
| `[barmbini_promotion empty_message="Aktuell keine Aktionen."]` | Text bei keiner gültigen Aktion |

Weitere Details siehe `Barmbini_Aufgabe_Startseite_Aktionen.md`.

Eine praktische Kurzanleitung für Redakteure und Administratoren findet sich in `Barmbini_Anleitung_Aktionen_Admin.md`.

---

## FAQ

**Was ist das Sozialkaufhaus Barmbini?**
Barmbini ist ein Sozialkaufhaus in Hamburg-Barmbek. Wir verkaufen gebrauchte Kleidung für Groß und Klein, Schuhe, Accessoires und Kinderspielzeug zu günstigen Preisen – für Menschen mit kleinem Geldbeutel.

**Wer darf bei uns einkaufen?**
Der Einkauf bei Barmbini ist für Menschen mit geringem Einkommen vorgesehen – konkret für Personen, deren Einkommen unterhalb der Pfändungsfreigrenze liegt. Bitte bringen Sie beim ersten Besuch einen entsprechenden Nachweis mit (z.B. ALG-II-Bescheid, Sozialhilfebescheid oder ähnliches).

**Welche Nachweise werden akzeptiert?**
Folgende Nachweise werden anerkannt:

- ALG-II-Bescheid (Bürgergeld)
- Sozialhilfebescheid
- Wohngeld-Bescheid
- Andere Nachweise über Einkommen unterhalb der Pfändungsfreigrenze

Bei Fragen wenden Sie sich gerne direkt an uns.

**Was wird bei Barmbini verkauft?**
Wir führen ein breites Sortiment an gebrauchten Waren:

- Damen- und Herrenbekleidung
- Kindersachen und Babybedarf
- Schuhe und Accessoires
- Kinderspielzeug
- Kinder- und Erwachsenenbücher

**Was ist Kunstkram?**
Kunstkram ist ein Schwester-Projekt von Barmbini, das seit dem 1. Februar 2022 ebenfalls am Alter Teichweg 11 aktiv ist. Dort werden gespendete Waren sortiert, aufbereitet und in einer Näh- und Holzwerkstatt weiterverarbeitet – zum Beispiel zu Taschen, Kissen oder Artikeln für Mutter und Kind. Das Projekt dient auch der beruflichen Qualifizierung der Teilnehmenden.

**Kann ich Sachen spenden?**
Ja, sehr gerne! Wir freuen uns über Sachspenden in gutem Zustand. Bitte bringen Sie Ihre Spenden während unserer Öffnungszeiten vorbei. Mehr Informationen finden Sie auf unserer Seite „Helfen & Spenden".

**Wie kann ich mich ehrenamtlich engagieren?**
Wir suchen immer engagierte Menschen, die uns unterstützen möchten – im Laden, in der Werkstatt oder im Büro. Schreiben Sie uns einfach eine E-Mail oder rufen Sie uns an.

---

## Helfen & Spenden

### Sachspenden

Sie möchten Kleidung, Schuhe, Spielzeug oder andere Artikel spenden? Wir freuen uns über jeden Beitrag!

**Was wir annehmen:**

- Saubere Kleidung für Damen, Herren und Kinder in gutem Zustand
- Schuhe und Accessoires
- Kinderspielzeug (vollständig und funktionsfähig)
- Kinderbücher und Erwachsenenbücher
- Babybedarf

**Was wir nicht annehmen:**

- Beschädigte oder stark verschlissene Waren
- Elektrogeräte
- Möbel

**Abgabezeiten:**
Sachspenden können während unserer regulären Öffnungszeiten direkt im Laden abgegeben werden.

Adresse: Alter Teichweg 11, 22081 Hamburg

---

### Ehrenamtliches Engagement

Barmbini und Kunstkram leben vom Engagement freiwilliger Helferinnen und Helfer. Bei uns können Sie sich in verschiedenen Bereichen einbringen:

- **Im Laden:** Kundinnen und Kunden beraten, Waren sortieren und präsentieren
- **In der Werkstatt:** In der Näh- oder Holzwerkstatt mitarbeiten, Waren aufbereiten und reparieren
- **Im Büro:** Administrative Aufgaben übernehmen

Das Projekt Kunstkram bietet darüber hinaus Möglichkeiten zur beruflichen Qualifizierung durch praktische Arbeit.

**Interesse?**
Melden Sie sich einfach bei uns:

📞 040 4294 5339
✉️ <agh.kunstkram@verbandshaus-hamburg.de>

---

### Kontaktformular

*[Kontaktformular hier einfügen – Contact Form 7]*

Felder: Name, E-Mail, Nachricht, Datenschutz-Checkbox

---

## Kontakt & Anfahrt

### Adresse

**Sozialkaufhaus Barmbini**
Alter Teichweg 11
22081 Hamburg

### Kontakt

📞 040 4294 5339
✉️ <agh.kunstkram@verbandshaus-hamburg.de>

### Öffnungszeiten

*[Öffnungszeiten hier eintragen – in den gefundenen Quellen nicht eindeutig angegeben]*

| Tag | Uhrzeit |
| --- | --- |
| Montag | – |
| Dienstag | – |
| Mittwoch | – |
| Donnerstag | – |
| Freitag | – |
| Samstag | – |

> Bitte erfragen Sie die aktuellen Öffnungszeiten telefonisch oder per E-Mail.

### Anfahrt

**Mit dem ÖPNV:**
Hamburg-Barmbek ist gut mit U-Bahn und Bus erreichbar.

- U-Bahn: U3 Haltestelle Barmbek
- Bus: Haltestellen in der Nähe des Alter Teichweg

**Mit dem Auto:**
Alter Teichweg 11, 22081 Hamburg

*[Statische Karte hier einfügen]*

[In Google Maps öffnen →](https://www.google.com/maps/search/Alter+Teichweg+11,+22081+Hamburg)

---

### Kontaktformular

*[Kontaktformular hier einfügen – Contact Form 7]*

Felder: Name, E-Mail, Betreff, Nachricht, Datenschutz-Checkbox
