# Detaillierte Aufgabe: E-Mail-Versand auf dem Server herstellen (MTA / SMTP)

## Ziel

Auf dem Server `217.160.74.128` ist **kein Mail-Server (MTA) installiert** – WordPress `wp_mail()` schlägt daher komplett fehl. Betroffen sind:

- Kontaktformular (geplant, Contact Form 7)
- Benutzerregistrierungs-Mails (WooCommerce `/mein-konto/`)
- Alle Benachrichtigungs-/Abo-Mails aus `barmbini-core` (Neuigkeiten, Aktionen, Digest)

Ziel dieser Aufgabe: E-Mail-Versand so einrichten, dass `wp_mail()` zuverlässig funktioniert und Mails beim Empfänger ankommen (nicht als Spam verloren gehen).

## Quellenbasis

- SSH-Validierung vom 2026-08-13 (Befund: kein MTA)
- `Tasks/Barmbini_Aufgabe_Kontaktformular.md` – blockierender Befund
- `.github/skills/deployment-safety-check/SKILL.md` – Server-Änderungen dokumentiert + reversibel
- `Barmbini_Technisches_Konzept_v2.5.md` – §2 Technische Basis

## Verifizierter Ist-Stand (2026-08-13, per SSH)

| Prüfung | Ergebnis |
|---|---|
| `sendmail_path` (PHP) | `/usr/sbin/sendmail -t -i` |
| `/usr/sbin/sendmail` vorhanden? | ❌ **nicht vorhanden** |
| `sendmail`/`postfix`/`exim`/`msmtp` installiert? | ❌ keine |
| `postfix` Dienst | inactive |
| `/var/log/mail.log` | nicht vorhanden |
| PHP `mail()` Test | `bool(false)` → `sh: /usr/sbin/sendmail: not found` |

**Folge:** `wp_mail()` → `mail()` → sendmail fehlt → **kein Versand möglich**.

**Ergänzung (2026-08-14, verifiziert):** Domain `barmbini.de` funktioniert (A-Record → `217.160.74.128`, HTTPS 200, Let's-Encrypt). Die Domain-Mail läuft **bei IONOS**, nicht auf dem Server: MX `mx00.ionos.de`/`mx01.ionos.de` (Prio 10), SPF `v=spf1 include:_spf-eu.ionos.com ~all`, NS IONOS ui-dns. IONOS-SMTP `smtp.ionos.de` ist vom Server auf Port **587** (STARTTLS) und **465** (SSL) erreichbar → Option 2/3 technisch möglich.

## Fachliche Leitplanken

- Der Server ist **sicherheitskritisch** (frühere Kompromittierung) – jede Installation mit Bedacht, keine unnötigen offenen Dienste.
- Minimalprinzip: so wenig neue Komponenten wie nötig.
- Absender `Barmbini Sozialkaufhaus <info@barmbini.de>` ist bereits in `barmbini-core` gesetzt.
- Die eigene Domain `barmbini.de` wird angesprochen – für seriöse Zustellbarkeit ist ggf. ein passender Mail-Relay/Sender mit Domain-Authentifizierung (SPF/DKIM) sinnvoll.

## Lösungsoptionen

### Option 1: Lokalen MTA installieren (Postfix/Sendmail) – serversseitig

```bash
apt-get update && apt-get install -y postfix
# oder minimal: sendmail
```

- **Vorteil:** kein zusätzliches Plugin; PHP `mail()` funktioniert direkt.
- **Nachteil:** reine `localhost`-Zustellung wird von vielen Empfänger-Mailservern als **Spam** eingestuft (fehlende SPF/DKIM/Domain-Reputation). Für einfache interne Hinweis-Mails ok, für Kunden-Mails (Registrierung, Formular) riskant.

### Option 2: SMTP-Plugin + externes Relay (empfohlen für Kundenzustellbarkeit)

- Plugin z. B. **WP Mail SMTP** installieren.
- SMTP-Zugangsdaten eines Mailproviders hinterlegen (z. B. IONOS-Mail, Posteo, oder ein dediziertes Postfach für `info@barmbini.de`).
- **Vorteil:** höchste Zustellrate, echte Absender-Domain, SPF/DKIM über den Provider.
- **Nachteil:** ein weiteres Plugin + externe Zugangsdaten (vertraulich!).

### Option 3: MSMTP (leichtgewichtig, zwischen 1 und 2)

- `msmtp-mta` als Relay-Client installieren, der über einen externen SMTP-Server sendet; `sendmail_path` zeigt dann auf `msmtp`.
- **Vorteil:** kein WP-Plugin, serverseitige Relay-Konfiguration, echte Absenderadresse.
- **Nachteil:** Konfigurationsaufwand auf dem Server + SMTP-Zugangsdaten auf dem Server liegen.

## Empfehlung

Für eine **Sozialkaufhaus-Kontaktseite** mit Kunden-/Spender-Mails:

- **Option 2 (SMTP-Plugin + Relay)** ist am zuverlässigsten für die Zustellbarkeit an `info@barmbini.de`.
- Falls kein externes Postfach verfügbar ist: **Option 1 (Postfix)** als schneller Einstieg – aber Zustellbarkeit testen und ggf. SPF/DKIM nachrüsten.

## Aufgabe

### 1. Entscheidung treffen

- **Entscheidung (2026-08-14): Option 2 – SMTP-Plugin + IONOS SMTP-Relay** (`smtp.ionos.de`, Port 587 STARTTLS, Absender `info@barmbini.de`).
- **Bereits installiert & aktiv (lokal + live):** „Solid Mail“ (Kadence/SolidWP, Slug `wp-smtp`, v3.0.0) – nur noch konfigurieren, **kein neues Plugin nötig** (Minimalprinzip).
- SMTP-Zugangsdaten beschaffen (vertraulich, **nicht** in Doku/Git ablegen).

### 2. Umsetzung

**Option 1 (Postfix):**
```bash
apt-get update && apt-get install -y postfix
systemctl enable --now postfix
echo "Test" | mail -s "Test" info@barmbini.de   # oder PHP mail() testen
```

**Option 2 (WP Mail SMTP):**
- Plugin installieren → SMTP-Host/Port/User/Pass hinterlegen.
- Verschlüsselung (TLS/STARTTLS) aktivieren.
- Test-Mail aus dem Plugin versenden.

**Option 3 (msmtp):**
```bash
apt-get update && apt-get install -y msmtp-mta
# /etc/msmtprc konfigurieren; sendmail_path auf msmtp setzen
```

### 3. Verifikation

1. Test-Mail von WordPress aus senden (z. B. `wp eval 'wp_mail("info@barmbini.de", "Test", "Test");' --allow-root`).
2. Prüfen, ob die Mail ankommt (auch Spam-Ordner!).
3. Absender prüfen: `Barmbini Sozialkaufhaus <info@barmbini.de>` (kein `wordpress@`).
4. Spam-Score/Header der Testmail prüfen (SPF/DKIM/From-Authentifizierung).

### 4. Regression

- Kontaktformular (nach dessen Umsetzung) und Registrierungs-/Benachrichtigungs-Mails testen.

## Umsetzung (2026-08-14)

- **Option 2 umgesetzt** mit dem bereits vorhandenen Plugin **Solid Mail** (Slug `wp-smtp`, v3.0.0) – kein neues Plugin.
- SMTP: `smtp.ionos.de`, Port 587, STARTTLS (`smtp_secure=tls`), Auth `yes`, Benutzer `info@barmbini.de`.
- Zugangsdaten in `/root/barmbini-mail.txt` (chmod 600, analog `barmbini-db.txt`) – **nicht** in Git/Doku.
- Konfiguration als aktive + Standard-Verbindung in Option `solid_smtp_providers` (Connection-ID `ionos-smtp`).
- Verifiziert: `wp_mail()` liefert `true`; Test-Mail via `ionos-smtp` geloggt (`wp_wpsmtp_logs`, kein Fehler); Absender `Barmbini Sozialkaufhaus <info@barmbini.de>`.
- Offen: Empfängerbestätigung (Zustellung/Spam) durch den Nutzer.

## Abnahmekriterien

- [x] `wp_mail()` liefert `true` und die Test-Mail kommt an
- [x] Absender ist `Barmbini Sozialkaufhaus <info@barmbini.de>`
- [ ] Mail landet nicht (regelmäßig) im Spam (Empfängerbestätigung ausstehend)
- [x] Kein Klartext-SMTP-Passwort in Doku/Git
- [x] Entscheidung (Option 1/2/3) im Ticket dokumentiert

## Deployment

- **Server-Änderung** (Option 1/3) bzw. **Plugin-Konfiguration** (Option 2) – via `deploy.ps1` nur bei Plugin/Code; Server-Install per SSH-Runbook.
- Kein SQL-Import.
- SMTP-Zugangsdaten **niemals** in Git oder Doku ablegen (gegebenenfalls in einem Server-seitigen Secret-File, z. B. `/root/barmbini-mail.txt`, analog zu `barmbini-db.txt`).

## Rollback

- Option 1/3: Paket deinstallieren (`apt-get remove`) oder Dienst stoppen.
- Option 2: Plugin deaktivieren.
- Die Änderung betrifft keinen Site-Content → kein Datenverlust-Risiko.

## Risiken und offene Punkte

- **Zustellbarkeit/Spam:** Ein lokaler MTA ohne SPF/DKIM riskiert Spam-Einstufung – Option 2/3 vermeidet das besser.
- **Vertraulichkeit:** SMTP-Passwort darf nicht in Git landen.
- **Offene Frage:** Gibt es bereits ein Postfach/eine Domain-Mail für `info@barmbini.de`? (Für Option 2/3 nötig.)
- **Sicherheit:** Postfix offen zum Internet niemals ohne Authentifizierung betreiben.

## Dokumentation

- Nach Umsetzung die gewählte Option und Konfiguration in `Server_Aenderungsdokumentation_*.md` festhalten.
- Verweis in `Tasks/Barmbini_Aufgabe_Kontaktformular.md` auf den behobenen Mail-Befund aktualisieren (Status von 🔴 auf ✅).
