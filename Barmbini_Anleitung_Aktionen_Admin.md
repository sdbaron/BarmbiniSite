# Anleitung: Aktionen – Arbeiten mit dem Aktionssystem

Diese Anleitung beschreibt, wie Redakteure und Administratoren Aktionen für die Startseite anlegen und pflegen. Eine Aktion ist ein zeitlich begrenzter Hinweis — zum Beispiel ein Sonderangebot, ein Flohmarkt oder ein Spendenaufruf.

---

## 1. Wo finde ich die Aktionen?

Nach dem Einloggen im WordPress-Admin sehen Sie in der linken Seitenleiste den Menüpunkt **„Aktionen"** (mit einem Megaphon-Symbol 📢). Ein Klick öffnet die Übersicht aller Aktionen — ähnlich wie bei Beiträgen oder Seiten.

![Menüpunkt Aktionen](dashicons-megaphone)

---

## 2. Eine neue Aktion erstellen

1. Klicken Sie auf den Button **„Neue Aktion"** (oben links oder im Menü).
2. Es öffnet sich der vertraute Gutenberg-Editor.

### Felder im Editor

| Feld | Beschreibung |
|------|-------------|
| **Titel** | Der Name der Aktion, z. B. _„Sommerschlussverkauf"_. Erscheint fett auf der Startseite. |
| **Beschreibung** | Der große Textbereich. Hier beschreiben Sie die Aktion — was es gibt, warum es sich lohnt, wichtige Details. |
| **Flyer-Bild** | In der rechten Seitenleiste unter „Flyer-Bild". Klicken Sie auf „Flyer-Bild auswählen" und laden Sie Ihren Flyer hoch. **Das ist meist das Wichtigste** — ein guter Flyer spricht für sich. |

---

## 3. Gültigkeitszeitraum festlegen

In der rechten Seitenleiste finden Sie die Box **„Gültigkeitszeitraum"**. Hier legen Sie fest, wann die Aktion auf der Startseite erscheint:

| Feld | Was eintragen? | Beispiel |
|------|---------------|---------|
| **Startdatum** | Ab wann soll die Aktion sichtbar sein? | `01.08.2026` |
| **Enddatum** | Bis wann soll die Aktion sichtbar sein? | `31.08.2026` |

> **Wichtig:** Nur Aktionen, deren Zeitraum das **heutige Datum** umfasst, werden auf der Startseite angezeigt. Läuft das Enddatum ab, verschwindet die Aktion automatisch — Sie müssen nichts löschen.

### Typische Beispiele

| Was Sie wollen | Startdatum | Enddatum |
|---------------|-----------|----------|
| Eine Woche Aktion (ab morgen) | Morgen | Morgen + 7 Tage |
| Monatsaktion (ab heute) | Heute | Letzter Tag des Monats |
| Aktion sofort beenden | — | Gestern setzen |

---

## 4. Aktion veröffentlichen

1. Sind Titel, Beschreibung, Flyer-Bild und Daten ausgefüllt?
2. Klicken Sie rechts oben auf **„Veröffentlichen"**.
3. Die Aktion erscheint automatisch auf der Startseite — **sobald das Startdatum erreicht ist**.

---

## 5. Aktion auf der Startseite einblenden (einmalig)

Damit Aktionen überhaupt auf der Startseite erscheinen, muss der Shortcode dort platziert sein. Das ist eine einmalige Einrichtung:

1. Im Admin auf **„Seiten"** klicken.
2. Die **Startseite** im Gutenberg-Editor öffnen.
3. Einen **Shortcode-Block** einfügen.
4. Folgenden Shortcode eintragen:

```
[barmbini_promotion]
```

5. Seite aktualisieren/veröffentlichen.

Fertig! Ab jetzt erscheinen alle gültigen Aktionen automatisch auf der Startseite.

### Optional: Aussehen anpassen

Sie können einzelne Elemente der Aktion ausblenden:

| Shortcode | Ergebnis |
|-----------|----------|
| `[barmbini_promotion show_image="0"]` | Kein Flyer-Bild |
| `[barmbini_promotion show_date="0"]` | Kein Zeitraum |
| `[barmbini_promotion show_description="0"]` | Kein Beschreibungstext |
| `[barmbini_promotion empty_message="Aktuell keine Aktionen."]` | Text, wenn keine Aktion läuft |

---

## 6. Mehrere gleichzeitige Aktionen

Sie können mehrere Aktionen anlegen, die sich zeitlich überlappen. Alle gültigen Aktionen erscheinen nebeneinander auf der Startseite — üblicherweise ein bis drei Stück.

Sortiert wird nach Startdatum: Die **neueste Aktion zuerst**.

---

## 7. Aktion bearbeiten oder löschen

- **Bearbeiten:** In der Übersicht auf den Titel klicken. Änderungen speichern.
- **Löschen:** In der Übersicht auf „Papierkorb" klicken. Gelöschte Aktionen können 30 Tage lang wiederhergestellt werden.

---

## 8. Archiv: Abgelaufene Aktionen

Sobald das Enddatum einer Aktion überschritten ist, wird sie automatisch aus der aktiven Ansicht entfernt und erscheint im **Archiv**. Das passiert ohne Ihr Zutun — Sie müssen nichts manuell verschieben.

### Zwischen Aktiv und Archiv wechseln

In der Übersichtsseite **„Aktionen"** sehen Sie oben drei Filter-Links:

| Link | Zeigt |
|------|-------|
| **Aktiv** (Standard) | Nur laufende und zukünftige Aktionen |
| **Archiv** | Nur abgelaufene Aktionen (Enddatum älter als heute) |
| **Alle** | Alle veröffentlichten Aktionen |

Ein Klick auf **„Archiv"** listet alle abgelaufenen Aktionen. Sie können jede Archiv-Aktion wie gewohnt **bearbeiten** — z. B. das Enddatum verlängern, dann erscheint sie wieder unter „Aktiv".

---

## 9. Aktion vorzeitig beenden

Es gibt zwei Wege:

| Weg | Vorgehen |
|-----|----------|
| **Empfohlen** | Aktion bearbeiten und das **Enddatum auf gestern** setzen → verschwindet sofort |
| **Alternativ** | Aktion in den Papierkorb legen → nicht wiederherstellbar nach 30 Tagen |

---

## 10. Einzelansicht der Aktion

Jede Aktion ist unter ihrer eigenen Adresse im Internet erreichbar, z. B.:

```
https://barmbini.de/aktion/sommerschlussverkauf/
```

Auf dieser Seite sehen Besucher den **Flyer in voller Größe**, den Titel, den Gültigkeitszeitraum und die vollständige Beschreibung. Abgelaufene Aktionen zeigen einen Hinweis **„Diese Aktion ist beendet"**, bleiben aber weiterhin abrufbar.

### Wie kommen Besucher dorthin?

- Auf der Startseite sind **Flyer-Bild und Titel jeder Aktion anklickbar** — ein Klick führt direkt zur Einzelansicht.
- Sie können den Link auch manuell teilen (z. B. in sozialen Medien oder per E-Mail).

---

## 11. Wichtige Hinweise

- **Keine Aktion ohne Enddatum anlegen.** Ohne Enddatum erscheint die Aktion nicht auf der Startseite — das System verlangt einen definierten Zeitraum.
- **Flyer-Bilder sollten nicht zu groß sein.** Empfohlene Größe: 800–1200 px Breite. Größere Bilder werden automatisch skaliert, verlangsamen aber die Seite.
- **Der Gutenberg-Editor funktioniert ganz normal.** Sie können Absätze, Listen, Überschriften und alles andere verwenden, was Sie aus Beiträgen und Seiten kennen.
- **Vorschau ist nicht nötig.** Da die Einzelansicht der Aktion deaktiviert ist, sehen Sie das Ergebnis direkt auf der Startseite.
- **Mehrere Sprachen werden nicht unterstützt.** Die Website ist einsprachig Deutsch.

---

## Kurzreferenz: Eine Aktion in 60 Sekunden

1. **Aktionen → Neue Aktion**
2. **Titel** eintragen
3. **Flyer-Bild** auswählen (rechte Seitenleiste)
4. **Beschreibung** schreiben
5. **Start- und Enddatum** setzen (rechts: „Gültigkeitszeitraum")
6. **Veröffentlichen**

🎉 Fertig. Die Aktion erscheint automatisch zum Startdatum.
