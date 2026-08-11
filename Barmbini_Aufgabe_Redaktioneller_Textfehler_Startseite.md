# Detaillierte Aufgabe: Redaktioneller Textfehler "Deutcshland" auf der Startseite

## Ziel

Auf der Startseite des Live-Servers `http://217.160.74.128/` steht im Adress-/Kontakt-Bereich der Tippfehler **„Deutcshland"** statt „Deutschland". Der Fehler ist redaktioneller Natur und soll sauber korrigiert werden.

## Quellenbasis

- Server-Analyse vom 2026-08-11 (Befund: Adressblock auf der Startseite zeigt „Deutcshland")
- Lokaler SQL-Dump `D:\Local Sites\barmbini\app\sql\local.sql` – Suche nach „Deutcshland" bestätigt Vorkommen im Seiteninhalt der Startseite (u. a. Post-ID 13, Kadence-Row-Layouts)
- `Barmbini_Aufgabe_Update_von_local_auf_Server.md` – Inhalts-Update-Prozess
- `Barmbini_Aufgabe_Update_Modus_B_Live_Daten_erhalten.md` – Live-Daten bleiben erhalten

## Fachliche Leitplanken

- Der lokale Stand ist die fachliche Quelle für Inhalte.
- Es gilt **Modus B**: Live-Daten (Benutzerkonten, Abos) bleiben erhalten – **kein** SQL-Vollimport.
- Ein einzelner Textfehler im `post_content` der Startseite wird **selektiv** korrigiert, nicht per Datenbank-Merge.
- Keine Code-Änderung, kein Plugin-Eingriff – reine Inhaltskorrektur.

## Verifizierter Ist-Stand (2026-08-11)

- Der Tippfehler steht im **Seiteninhalt der Startseite** (Post-ID 13, Gutenberg-/Kadence-Blöcke), nicht im zentralen Adress-Optionswert `barmbini_address_data` (dieser enthält Stadt „Hamburg", nicht „Deutschland").
- Der Fehler ist sowohl lokal als auch live vorhanden.
- Betroffene Vorkommen im SQL-Dump: mehrere Zeilen (Post und Revisionen), da die Startseite mehrfach überarbeitet wurde.

## Umsetzungsstatus (2026-08-11)

| Schritt | Status |
|---|---|
| 1. Lokale Korrektur | ✅ **Erledigt** – lokal verifiziert: 0× „Deutcshland", 1× „Deutschland" |
| 2. Live-Nachzug (Modus B) | ⏳ **Offen** – Startseite im Live-Editor öffnen, korrigieren, veröffentlichen |
| 3. Verifikation live | ⏳ Offen |
| 4. Cache live leeren | ⏳ Offen (nach Live-Nachzug) |

> **Hinweis:** Der Live-Nachzug ist ein **separater, manueller Schritt im Live-Editor** (`http://217.160.74.128/wp-admin/`), da Modus B keinen SQL-Import ausführt. Der lokale Stand ist nur die Quelle – er wird **nicht** automatisch live.

## Aufgabe

### 1. Lokale Korrektur (Quelle)

1. Lokale WordPress-Installation (`D:\Local Sites\barmbini\app\public`) öffnen.
2. Startseite im Gutenberg-Editor öffnen (Seiten → Startseite).
3. Im Adress-/Kontakt-Block „Deutcshland" zu **„Deutschland"** korrigieren.
4. Seite aktualisieren/veröffentlichen.
5. Lokal im Browser verifizieren: Startseite zeigt korrektes „Deutschland".

**Hinweis:** Der Tippfehler kann auch in Revisionen der Startseite stecken – für die Auslieferung zählt nur der aktuelle `post_content`. Ein Bereinigen der Revisionen ist **nicht** Teil dieser Aufgabe.

### 2. Live-Korrektur (selektiv, Modus B)

Da in Modus B kein SQL-Vollimport erfolgt, wird die Korrektur auf dem Live-System **manuell im WordPress-Editor** nachgezogen (einzelner Textersatz, keine Datenbank-Operation):

1. Im Live-WP-Admin (`http://217.160.74.128/wp-admin/`) die Startseite öffnen.
2. „Deutcshland" → „Deutschland" korrigieren.
3. Veröffentlichen.

**Wichtig:** Diese Live-Anpassung ist bewusst eine **Inhaltskorrektur**, die nicht durch spätere Deployments überschrieben wird, da Modus B keine SQL-Importe ausführt. Sie wird im Runbook dokumentiert, damit der Stand nachvollziehbar bleibt.

### 3. Verifikation

- Live: `curl -s http://217.160.74.128/ | grep -c "Deutcshland"` → erwartet `0`
- Live: `curl -s http://217.160.74.128/ | grep -c "Deutschland"` → erwartet `>= 1`
- Browser-Sichtprüfung des Adressblocks auf der Startseite.

### 4. Cache leeren (live)

Nach der Korrektur den WP Fastest Cache auf dem Live-System leeren (im Admin: WP Fastest Cache → Cache löschen), damit die Änderung sofort sichtbar wird.

## Abnahmekriterien

- [x] Lokale Startseite zeigt „Deutschland" (Quelle aktualisiert – verifiziert 2026-08-11)
- [ ] Live-Startseite zeigt „Deutschland" (kein „Deutcshland" mehr im HTML)
- [x] Kein SQL-Vollimport durchgeführt (Modus B eingehalten)
- [ ] WP Fastest Cache live geleert (nach Live-Nachzug)
- [ ] Korrektur im Inhalts-Runbook dokumentiert

## Deployment

- **Inhaltsänderung** (post_content der Startseite), kein Code-Deploy.
- Lokale Quelle zuerst, Live-Nachzug manuell (Modus B).
- Kein `deploy.ps1`-Einsatz erforderlich.

## Rollback

- Nicht sinnvoll/nötig: reine Textkorrektur. Falls doch: im Editor zurück auf „Deutcshland" setzen (unwahrscheinlich).

## Risiken und offene Punkte

- **Offene Frage:** Der Adressblock auf der Startseite (E-Mail/Telefon/Adresse mit Icons) könnte zusätzlich auf dem zentralen Optionswert `barmbini_address_data` basieren. Verifizieren, ob dort ebenfalls ein Fehler steckt – falls ja, dort im Admin unter „Einstellungen → Barmbini Adresse" korrigieren.
- Keine weiteren offenen Risiken – rein redaktionell.

## Dokumentation

- Nach Umsetzung die Korrektur im Inhalts-/Runbook-Stand vermerken.
- Falls weitere Textfehler gefunden werden (Rechtschreibprüfung der Startseite), als kleine Folge-Notiz sammeln.
