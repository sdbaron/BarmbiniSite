# Detaillierte Aufgabe: Kontaktformular (Contact Form 7)

## Ziel

Die Website Sozialkaufhaus Barmbini erhält ein **barrierefreies, DSGVO-konformes Kontaktformular**, damit Besucher niedrigschwellig Anfragen stellen können (Sachspenden, Ehrenamt, allgemeine Fragen) – ohne ein E-Mail-Programm öffnen zu müssen.

## Quellenbasis

- `Barmbini_Technisches_Konzept_v2.5.md` – §7.4 „Helfen & Spenden" und §7.5 „Kontakt & Anfahrt" (Kontaktformular vorgesehen)
- `Docs/Barmbini_Seiteninhalte.md` – Platzhalter „[Kontaktformular hier einfügen – Contact Form 7]"
- `Docs/Barmbini_Rechtliche_Seiten.md` – Datenschutzerklärung §4 (Kontaktformular bereits beschrieben, Speicherdauer 6 Monate)
- Plugin-Bestand: **Contact Form 7** ist bereits installiert (Phase 1)
- `Barmbini_Aufgabe_Benutzerregistrierung_und_Kontobearbeitung.md` – Hinweis: „E-Mail-Versand (wp_mail) – ungetestet"

## Fachliche Leitplanken

- Die Website ist einsprachig deutsch.
- **Minimalprinzip:** Contact Form 7 verwenden (bereits installiert), **kein neues Plugin**.
- Neue Fachlogik gehört in `barmbini-core` – **jedoch**: das Kontaktformular selbst ist **Inhalt/Plugin-Konfiguration** (CF7), kein eigener Code. Eine Custom-Logik (z. B. zusätzliche Validierung) wäre in `barmbini-core` zu kapseln, ist aber für den Mindestumfang nicht nötig.
- DSGVO: klare Einwilligung, Datenminimierung, transparente Speicherdauer.
- E-Mail-Absender ist bereits konfiguriert (`Barmbini Sozialkaufhaus <info@barmbini.de>`).

## Wichtige Regeländerung (2026-08-13, Nutzerentscheidung)

**Die Projektregel „keine externen Dienste" wird gelockert** (Nutzerentscheidung). Dadurch wird für den Spam-Schutz auch ein externer Dienst wie **Google reCAPTCHA** technisch möglich.

**Konsequenzen dieser Entscheidung (müssen parallel bearbeitet werden):**

1. `Barmbini_Technisches_Konzept_v2.5.md` §3 (Architektur-Grundsätze) muss die Regeländerung abbilden („keine externen Dienste" → „externe Dienste nur mit DSGVO-Abwägung/Einwilligung").
2. `Docs/Barmbini_Rechtliche_Seiten.md` (Datenschutzerklärung §2) behauptet aktuell „lädt keine externen Dienste" – bei reCAPTCHA wäre diese Aussage **unzutreffend** und muss um einen reCAPTCHA-Abschnitt (Google, Datenübertragung, Rechtsgrundlage) ergänzt werden.
3. reCAPTCHA überträgt Daten an Google → es braucht eine **Rechtsgrundlage** (in der Regel Art. 6 Abs. 1 lit. f DSGVO mit dokumentierter Interessenabwägung oder lit. a Einwilligung) und ggf. eine **Cookie-Einwilligung**, je nach Umsetzung.

## Verifizierter Ist-Stand (2026-08-13)

| Komponente | Status |
|---|---|
| Contact Form 7 | ✅ installiert (Phase 1) |
| Kontaktformular angelegt | ❌ noch nicht (Platzhalter in Seiteninhalten) |
| E-Mail-Zustellung (wp_mail) | 🔴 **DEFEKT** – kein MTA auf dem Server (siehe unten) |
| Datenschutzerklärung §4 | ✅ deckt Kontaktformular ab (6 Monate Speicherdauer) |
| Absender info@barmbini.de | ✅ bereits konfiguriert |

### 🔴 Blockierender Befund: E-Mail-Versand funktioniert nicht (2026-08-13)

Die Validierung per SSH hat ergeben, dass der Server **keinen Mail-Server (MTA)** hat:

- `sendmail_path` ist konfiguriert auf `/usr/sbin/sendmail -t -i`
- **aber `/usr/sbin/sendmail` existiert nicht** (kein sendmail, postfix, exim installiert)
- `postfix` Dienst: **inactive**
- PHP `mail()` liefert `bool(false)` mit Fehler `sh: /usr/sbin/sendmail: not found`

**Folge:** `wp_mail()` schlägt komplett fehl – Kontaktformular, Benutzerregistrierungs-Mails **und** die Abo-/Benachrichtigungs-Mails (barmbini-core) kommen alle nicht an. Das muss **vor** dem Kontaktformular (und eigentlich auch für die bestehenden Mail-Features) behoben werden.

**Lösungsoptionen (separate Aufgabe):**
1. **Postfix/Sendmail installieren** (einfachster Weg; Server-Mail via PHP `mail()`), oder
2. **SMTP-Plugin** (z. B. WP Mail SMTP) mit externem Relay (z. B. IONOS/Posteo) – robuster gegen Spam-Absender-Problem, benötigt SMTP-Zugangsdaten.

> Siehe `Barmbini_Aufgabe_Server_Mail_Zustellung.md` (wird bei Umsetzung angelegt).

## Aufgabe

### 1. Contact Form 7-Formular anlegen

1. Lokale WordPress-Installation (`D:\Local Sites\barmbini\app\public`) öffnen.
2. Unter **Kontakt → Neu erstellen** ein Formular anlegen.
3. Felder (minimal):
   - **Name** (Pflichtfeld)
   - **E-Mail-Adresse** (Pflichtfeld, E-Mail-Validierung)
   - **Nachricht** (Pflichtfeld, Textarea)
   - **DSGVO-Checkbox** (Pflicht): „Ich habe die Datenschutzerklärung gelesen und stimme der Verarbeitung meiner Daten zu." (mit Link auf `/datenschutzerklaerung/`)
4. **Empfänger:** `info@barmbini.de` (bzw. die dokumentierte Kontaktadresse).
5. **Betreff:** z. B. `[Barmbini] Neue Kontaktanfrage von [Name]`.

> **Hinweis:** Die DSGVO-Checkbox ist bei CF7 ein normales Pflichtfeld (`[acceptance …]`). Sie stellt die **Einwilligung** sicher, speichert aber standardmäßig keinen Zeitstempel. Für eine nachweisbare Einwilligung (Protokollierung) wäre optional eine kleine `barmbini-core`-Erweiterung nötig – für den Mindestumfang reicht die Checkbox mit Pflicht-Validierung.

### 2. Spam-Schutz wählen (Regel gelockert)

**Entscheidung (2026-08-13): Option A – Honeypot** (kein externer Dienst).

- CF7-intern oder kleines Honeypot-Feld; datenschutzfreundlich, keine externe Übertragung.
- reCAPTCHA (Option B) bleibt als **spätere Rückfall-Option** dokumentiert, falls Spam zum Problem wird.

> **Status:** Option A gewählt. Die Regel-Lockerung bleibt bestehen (für andere künftige Dienste), wird aber für dieses Formular **nicht** genutzt.

### 3. Einbinden auf den Seiten

1. Formular-Shortcode (z. B. `[contact-form-7 id="…" title="Kontaktformular"]`) kopieren.
2. Auf der Seite **Kontakt & Anfahrt** (`/kontakt/`) im Gutenberg-Editor einfügen.
3. Optional zusätzlich auf **Helfen & Spenden** (`/helfen-spenden/`) für Spenden-/Ehrenamt-Anfragen.
4. Lokal speichern und veröffentlichen.

### 4. E-Mail-Zustellung validieren (Pflicht vor Live-Gang)

1. Testanfrage absenden.
2. Prüfen, ob die Mail an `info@barmbini.de` ankommt (auch Spam-Ordner prüfen).
3. Falls Mails nicht ankommen: SMTP-Plugin prüfen oder Server-Mailkonfiguration (sendmail/Postfix) verifizieren. **Achtung:** Ein SMTP-Plugin wäre ein weiterer Baustein; Minimalprinzip beachten.
4. Absender-Name/-Adresse im Mail prüfen.

### 5. Live-Übernahme (Modus B)

CF7-Formulare werden als Custom Post Type (`wpcf7_contact_form`) in der **Datenbank** gespeichert – das ist **Inhalt**, kein Code. Deshalb:

1. Formular lokal erstellen.
2. Auf dem Live-Server im WP-Admin **manuell nachziehen** (Formular neu anlegen oder per CF7-Export/Import übertragen) – **kein SQL-Vollimport** (Modus B).
3. Formular-Shortcode auf der Live-Seite `/kontakt/` einfügen.
4. WP Fastest Cache live leeren.

## Abnahmekriterien

- [ ] Kontaktformular mit Name, E-Mail, Nachricht + DSGVO-Checkbox funktioniert
- [ ] Formular ist auf `/kontakt/` eingebunden
- [ ] Test-E-Mail kommt an `info@barmbini.de` an (kein Spam-Verlust)
- [ ] DSGVO-Checkbox ist Pflichtfeld
- [ ] Kein neues Plugin (Contact Form 7 vorhanden)
- [x] Spam-Schutz-Entscheidung dokumentiert (Option A – Honeypot)
- [ ] Bei Option B: Datenschutzerklärung um reCAPTCHA ergänzt (entfällt – Option A)
- [ ] Regeländerung „externe Dienste" im Konzept v2.5 dokumentiert (✅ Konzept aktualisiert, Spam bleibt aber ohne externen Dienst)
- [ ] Live-Nachzug ohne SQL-Vollimport (Modus B)
- [ ] **Voraussetzung:** E-Mail-Versand auf dem Server behoben (MTA/SMTP – blockiert aktuell)

## Deployment

- **Inhalt + Plugin-Konfiguration** (CF7-Formular in DB, Seiteninhalt), kein Code-Deploy.
- Lokale Quelle zuerst, Live-Nachzug manuell (Modus B, kein SQL-Import).
- Kein `deploy.ps1`-Einsatz erforderlich (außer bei einer `barmbini-core`-Erweiterung).

## Rollback

- Formular löschen oder Shortcode von der Seite entfernen (reversibel).
- CF7 speichert Formulare als Beiträge im Papierkorb – dort 30 Tage wiederherstellbar.

## Risiken und offene Punkte

- **Offene Frage:** Spam-Schutz Option A (Honeypot) oder B (reCAPTCHA, extern)? – Entscheidung offen.
- **Offene Frage:** Reicht die DSGVO-Checkbox als Einwilligung, oder ist eine **Protokollierung** (Zeitstempel) nötig? (Bei Option B wird sie rechtlich relevanter.)
- **Regeländerung:** `Barmbini_Technisches_Konzept_v2.5.md` §3 und `Docs/Barmbini_Rechtliche_Seiten.md` §2 müssen an die gelockerte Regel angepasst werden – **vor** dem Live-Gang.
- **E-Mail-Zustellung:** Ungeprüft; bei Problemen ist ein SMTP-Setup oder Server-Mail-Fix nötig.

## Dokumentation

- Nach Umsetzung `Docs/Barmbini_Seiteninhalte.md` (Platzhalter durch echten Shortcode ersetzen) und `Docs/Barmbini_Shortcodes.md` (falls CF7-Shortcode dokumentiert werden soll) aktualisieren.
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` Ist-Stand aktualisieren.
- `Barmbini_Technisches_Konzept_v2.5.md` §3 + `Docs/Barmbini_Rechtliche_Seiten.md` an die Regeländerung anpassen.
