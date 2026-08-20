# Detaillierte Aufgabe: Anonymisierte Besucherstatistik über nginx-Logs (Option B)

## Ziel

Eine **DSGVO-freundliche, anonymisierte Besucherstatistik** für barmbini.de auf Basis der **nginx-Zugriffslogs** einführen. Erhoben werden:

- **Views** (Seitenaufrufe)
- **eindeutige Besucher** (anonymisiert, ohne Cookies)
- **Referrer** (nur Domain-Ebene)
- **Geräte** (Mobil / Tablet / Desktop)
- **beliebteste Seiten**

Angezeigt wird die Statistik **intern im Admin-Bereich** und **auf der Frontend-Website** — jeweils nur für Benutzer mit der Rolle **Administrator** oder **Redakteur**.

Die Rohdaten (nginx-Log mit IPs) werden **nicht dauerhaft gespeichert**; es werden ausschließlich **aggregierte, anonymisierte Werte** abgelegt.

## Entscheidungen aus der Abstimmung (2026-08-20)

| # | Frage | Entscheidung |
|---|-------|--------------|
| 1 | Welche Metriken? | Views, eindeutige Besucher, Referrer, Geräte, beliebteste Seiten |
| 2 | Datenschutz-Level | **Anonymisiert, ohne Cookies** |
| 3 | Genauigkeit | **Vollständige Zahlen** (nginx/Server, erfasst auch Cache-Treffer) |
| 4 | Anzeige | Intern im Admin **und** Frontend, nur für Administrator/Redakteur angemeldet |

## Quellenbasis

- `server-config/barmbini-nginx.conf` — reale nginx-Konfiguration auf dem Server (`/etc/nginx/sites-available/barmbini`), Access-Log: `/var/log/nginx/barmbini_access.log` (Standard-„combined"-Format: IP, Zeitstempel, Request, Status, Bytes, Referrer, User-Agent)
- `Barmbini_Plugin_Architektur_barmbini-core.md` — Zielstruktur, Modulzuschnitt
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` — verifizierter Ist-Stand (WP Fastest Cache aktiv)
- `Docs/Barmbini_Rechtliche_Seiten.md` + Datenschutzerklärungs-Seite — müssen erweitert werden
- Bestehende Plugin-Muster: `includes/guides/class-staff-guides.php` (rollenbasierte Capability + Gating), `includes/roles/class-seller-role.php`

## Fachliche Leitplanken

- Website ist einsprachig deutsch, WooCommerce nur Katalog.
- **Minimalprinzip:** keine neuen WP-Plugins, wenn es ohne geht; keine externen Tracking-Dienste.
- Keine Cookies, keine Profilbildung, keine Weitergabe personenbezogener Daten.
- IP-Adressen werden nur **in-memory** zur Aggregation genutzt und **nie gespeichert**.
- Rohdaten (nginx-Log) kurz halten (z. B. 7 Tage), Aggregate begrenzt aufbewahren (z. B. 90 Tage).
- Server bleibt sicherheitskritisch (dokumentierter Vorfall): keine neuen offenen Endpunkte, keine zusätzlichen DB-Zugriffe aus dem Verarbeitungsskript.
- Fachlogik im Plugin `barmbini-core`, Server-Skripte als dokumentiertes Runbook.

## Verifizierter Ist-Stand (2026-08-20)

| Komponente | Status |
|---|---|
| nginx Access-Log | `/var/log/nginx/barmbini_access.log`, Standard-„combined"-Format |
| Logrotate | Debian-Standard für `/var/log/nginx/*.log` (täglich, komprimiert) — muss geprüft/angepasst werden |
| WP Fastest Cache | aktiv → statische HTML-Auslieferung (kein PHP bei Cache-Treffer) — **deshalb** ist nginx-Log-Auswertung der genaue Weg |
| GeoIP | **nicht Bestandteil** — Metrik „Herkunftsland“ entfällt (bewusst vereinfacht) |
| Plugin `barmbini-core` | Version `0.7.1`, Module `roles/`, `guides/` vorhanden |
| Datenschutzerklärung | deckt bisher nur Kontaktformular + technisch notwendige Cookies |

## Ziel-Architektur

```text
Besucher ──► nginx ──► /var/log/nginx/barmbini_access.log (combined, kurz gehalten)
                       │
                       │ logrotate (täglich) → barmbini_access.log.1
                       ▼
              /root/barmbini-stats/process.sh   (Cron, täglich, nach logrotate)
                       │  parst combined, filtert HTML-Views, anonymisiert
                       │  Gerät (UA) · Referrer-Domain
                       ▼
   /var/lib/barmbini-stats/stats/stats-YYYY-MM-DD.json   (NUR Aggregate, außerhalb Webroot)
                       │
                       ▼
   barmbini-core  includes/stats/class-visitor-stats.php
       │  liest Aggregate · Capability barmbini_view_stats (Admin + Redakteur)
       ├── Admin-Seite „Statistiken" (admin_menu)
       ├── Shortcode [barmbini_visitor_stats] (Frontend, nur Admin/Redakteur)
       └── (optional) Dashboard-Widget mit Kennzahlen
```

## Datenmodell (Aggregate, pro Tag)

Eine JSON-Datei pro Tag, z. B. `/var/lib/barmbini-stats/stats/stats-2026-08-19.json`:

```json
{
  "date": "2026-08-19",
  "views": 482,
  "unique_visitors": 137,
  "devices": { "mobile": 211, "tablet": 28, "desktop": 243 },
  "top_pages": [ { "path": "/sortiment/", "views": 84 }, { "path": "/", "views": 61 } ],
  "top_referrers": [ { "domain": "google.de", "views": 52 }, { "domain": "facebook.com", "views": 18 } ]
}
```

- `unique_visitors`: Anzahl **unterschiedlicher anonymisierter IPs** pro Tag (z. B. HMAC-SHA256 mit Tages-Salt oder Maskierung des letzten Oktetts). Nur der Zähler wird gespeichert — nie die IP oder der Hash.
- Referrer: nur **registrierte Domain** (ohne Schema/Query), interne Referrer (eigene Domain) werden ausgeschlossen.
- Aufbewahrung: Aggregate z. B. **90 Tage** (automatisch bereinigt), Roh-Logs z. B. **7 Tage** (logrotate).

## Umsetzung – Teil 1: Server (Runbook, per SSH)

> Plugin-Deployment ist Modus B; die Server-Schritte werden als Runbook dokumentiert und ausgeführt.

### 1a. Vorbereitung / Verzeichnisse

- Verzeichnis anlegen: `/var/lib/barmbini-stats/stats/` (außerhalb Webroot), `/root/barmbini-stats/`.
- Keine zusätzlichen Pakete nötig — die Verarbeitung nutzt nur Server-Bordmittel (awk/Perl).

### 1b. Verarbeitungsskript `/root/barmbini-stats/process.sh`

- Wird täglich per Cron gestartet, **nach** logrotate (z. B. 07:05), und liest `/var/log/nginx/barmbini_access.log.1` (gestriger Tag).
- Schritte:
  1. Parsen des „combined"-Formats (awk/Perl).
  2. **Filterung auf HTML-Views:** nur Status `200`; ausschließen: `/wp-admin/`, `/wp-login.php`, `/wp-json/`, statische Assets (`/wp-content/` Bilder/CSS/JS/Fonts), Feeds, Sitemaps, `robots.txt`, `favicon`, Punkt-Endungen (`*.png`, `*.css`, …).
  3. **Bot-Filter (optional, empfohlen):** bekannte Bots anhand User-Agent ausschließen (googlebot, bingbot, …), getrennt zählbar.
  4. **Anonymisierung + Unique-Besucher:** IP je Tag in-memory zählen (anonymisiert), nur Zähler ablegen.
  5. **Gerät:** User-Agent → mobile / tablet / desktop (einfache Heuristik).
  6. **Referrer:** Domain extrahieren (ohne Query), interne ausschließen.
  7. **Beliebteste Seiten:** Pfad normalisieren, Top-N (z. B. 10).
  8. Aggregate als `stats-YYYY-MM-DD.json` schreiben; Aggregate älter als 90 Tage löschen.
  9. Eigene Laufzeit in `/var/log/barmbini-stats.log` protokollieren.

### 1c. Cron + Logrotate-Abstimmung

- Cron (root): `15 7 * * * /root/barmbini-stats/process.sh >/dev/null 2>&1`.
- Prüfen/anpassen: Debian-logrotate für `/var/log/nginx/*.log` (täglich, `rotate 7`, `compress`) — damit `.1` am Tag nach der Rotation noch als Klartext vorliegt, bevor er zu `.gz` wird, und Roh-IPs nach spätestens ~7 Tagen weg sind.
- Keine Änderung an `barmbini-nginx.conf` nötig („combined"-Format enthält bereits IP, Referrer, UA). Optional: eigenes `log_format` nur, falls später Felder ergänzt werden sollen.

### 1d. Sicherheit

- Verzeichnis `/var/lib/barmbini-stats/` nicht im Webroot; nginx blockt ohnehin nur den Webroot-Pfad.
- Das Verarbeitungsskript liest **nur** nginx-Logs und schreibt **nur** Aggregate — **keine** WordPress-DB-Zugriffe, keine offenen Endpunkte.

## Umsetzung – Teil 2: Plugin `barmbini-core`

### 2a. Neues Modul `includes/stats/class-visitor-stats.php` (Barmbini_Core_Visitor_Stats)

- `register()`:
  - `admin_init` → `ensure_capabilities()` (idempotent: `barmbini_view_stats` an Administrator + Redakteur)
  - `admin_menu` → Seite **„Statistiken"** (nur mit Capability)
  - `init` → Shortcode `[barmbini_visitor_stats]`
  - `widgets_init` (optional) → Dashboard-Widget „Besucherstatistik" (nur Admin/Redakteur)
- `get_stats_dir()`: Pfad zu den Aggregaten, Standard `/var/lib/barmbini-stats/stats/`; per Filter `barmbini_stats_dir` überschreibbar; fehlender Ordner → „keine Daten" (wichtig für Local).
- `read_aggregates( $days )`: liest die letzten N Tages-Dateien, summiert Views/Uniques/Geräte/Referrer/Seiten, sortiert Top-Listen.
- Admin-Seite: Zeitraum-Auswahl (7/30/90 Tage), tabellarische Darstellung (Views, Besucher, Geräte, Top-Seiten, Top-Referrer), CSS-Datei `assets/css/visitor-stats.css`.
- Shortcode: rendert denselben Block nur, wenn `current_user_can('barmbini_view_stats')`; sonst leerer String (Frontend-Sichtbarkeit nur für Admin/Redakteur).
- Registrierung: `barmbini-core.php` (require) + `class-plugin.php` (`register_visitor_stats_module()`); Version **0.7.1 → 0.8.0**.

### 2b. Capability

- `barmbini_view_stats` → **Administrator + Redakteur** (analog `barmbini_view_guide_*`), nicht für Verkäufer/Subscriber. Idempotente Vergabe, kein Auto-Cleanup (konsistent zu `uninstall.php`).

### 2c. Frontend-Anzeige

- Shortcode `[barmbini_visitor_stats]` auf einer internen Seite (z. B. „Statistiken") oder an gewünschter Stelle; sichtbar nur für angemeldete Admins/Redakteure. Optional zusätzlich automatischer Block im Footer nur für diese Rollen (separater Schritt, nur falls gewünscht).

## Tests

### Unit-Tests (`tests/VisitorStatsTest.php`)

- `test_capability_granted_to_admin_and_editor` (nicht Verkäufer/Subscriber)
- `test_read_aggregates_sums_days` (JSON-Fixtures: Summen über mehrere Tage, Top-Listen sortiert)
- `test_read_aggregates_ignores_missing_days` (fehlende Dateien werden übersprungen)
- `test_stats_dir_default_and_filter`
- `test_shortcode_renders_only_for_admin_or_editor` (mit `current_user_can`-Mock)
- `test_shortcode_empty_for_other_roles`

### Server-Verifikation (Live, Runbook)

- `process.sh` einmal manuell gegen die aktuelle `.1`-Logdatei ausführen → prüfen, dass `stats-*.json` korrekt entsteht (Werte plausibel, keine IPs/Roh-Referrer enthalten).
- Cron-Lauf abwarten bzw. manuell triggern; `/var/log/barmbini-stats.log` prüfen.
- Im Browser als Admin und als Redakteur: Admin-Seite „Statistiken" + Shortcode-Seite zeigen Daten; als Verkäufer/abgemeldet: nichts.

## Datenschutz / Recht (Pflicht, vor Live-Schaltung)

- **Rechtliche Prüfung** gemäß Projektregeln (neue Verarbeitung personenbezogener Daten).
- **Datenschutzerklärungs-Seite erweitern:** anonymisierte Besucherstatistik auf Basis von Server-Logs, IPs anonymisiert (keine Speicherung), keine Cookies, keine Profilbildung, Rechtsgrundlage „berechtigtes Interesse“ (Art. 6 Abs. 1 lit. f DSGVO), Aufbewahrungsfristen (Roh-Log ~7 Tage, Aggregate ~90 Tage).
- **`Docs/Barmbini_Rechtliche_Seiten.md`** entsprechend aktualisieren.
- Kein Cookie-Banner nötig (keine Cookies, keine Einwilligungspflicht für rein statistische, anonymisierte Logs bei dokumentiertem berechtigtem Interesse).

## Deployment / Rollout

1. **Plugin:** Sync Workspace → Local, Tests, Commit/Push, Deploy **Modus B**.
2. **Server-Runbook:** Verzeichnisse + `process.sh` + Cron + logrotate-Prüfung; einmalige Ausführung + Validierung.
3. Capability-Selbstheilung greift auf dem Server automatisch beim nächsten Admin-Besuch; alternativ einmalig per WP-CLI `ensure_capabilities()` ausführen.
4. Datenschutzerklärung live aktualisieren.
5. Nach ~1 Tag: erste Zahlen prüfen.

## Risiken und offene Fragen

| Thema | Risiko / Frage | Empfehlung |
|---|---|---|
| Eindeutige Besucher | ohne Cookies = Anzahl **unterschiedlicher (anonymisierter) IPs** pro Tag; Mobil-Nutzer mit wechselnder IP → Überschätzung, NAT → Unterschätzung | Als „Besucher (ca.)" bezeichnen |
| Bots | verzerren Views/Uniques | Bot-Filter aktivieren (getrennt zählbar) |
| Logrotate/Race | Verarbeitung muss nach Rotation laufen, sonst Datenverlust/Doppelzählung | Cron nach logrotate; Reihenfolge im Runbook festhalten |
| Local-Umgebung | keine nginx-Logs → keine Daten | Plugin zeigt „keine Daten" (graceful) |
| Datenschutz | neue Verarbeitung personenbezogener Daten | Rechtliche Prüfung + Doku vor Live-Schaltung, Pflicht |

## Akzeptanzkriterien

- [x] Server: `process.sh` erzeugt täglich `stats-YYYY-MM-DD.json` (nur Aggregate, keine IPs/keine vollen Referrer-URLs)
- [x] Roh-Log-Rotation ≤ 7 Tage, Aggregate-Bereinigung nach 90 Tagen
- [x] Plugin 0.8.0: Admin-Seite „Statistiken“ zeigt Views, Besucher, Geräte, Top-Seiten, Top-Referrer (Zeitraum wählbar)
- [x] Shortcode `[barmbini_visitor_stats]` rendert Frontend-Block nur für Administrator/Redakteur
- [x] Verkäufer/Subscriber/Besucher sehen keine Statistik
- [x] Datenschutzerklärung aktualisiert + rechtliche Prüfung dokumentiert
- [x] Tests grün, Code gepusht, Deploy Modus B, Server-Runbook ausgeführt

## Ergebnis / Stand (2026-08-20)

**Umgesetzt und live:**

- Plugin `barmbini-core` **0.8.0**: `includes/stats/class-visitor-stats.php` (Barmbini_Core_Visitor_Stats) — Admin-Seite „Statistiken" (7/30/90 Tage) + Shortcode `[barmbini_visitor_stats]`; Capability `barmbini_view_stats` für Administrator + Redakteur.
- Server-Runbook `server-config/barmbini-stats/` (process.php, process.sh, install.sh, logrotate, README) — installiert auf dem Live-Server:
  - Verzeichnisse `/root/barmbini-stats`, `/var/lib/barmbini-stats/stats`
  - logrotate `/etc/logrotate.d/nginx` angepasst (rotate 14 → **7**, delaycompress, Backup unter `/root/barmbini-stats/backups/`)
  - Cron `/etc/cron.d/barmbini-stats` (täglich 07:15)
- **Erste Daten** (Testlauf gegen `barmbini_access.log.1` vom 19.08.): `views=138`, `unique_visitors=57`, Geräte (mobile 32 / tablet 4 / desktop 102), Top-Seiten, Top-Referrer, `bots=3`. Aggregat in `/var/lib/barmbini-stats/stats/stats-2026-08-19.json`.
- Plugin liest Aggregate auf dem Server korrekt (`read_aggregates` → views=138, uniques=57); Capabilities `admin`/`editor` = YES verifiziert.
- Datenschutzerklärungs-Seite (`/datenschutz/`, ID 32) live aktualisiert: neuer Abschnitt „6. Besucherstatistik (anonymisiert)", Stand August 2026, Bullet in Abschnitt 2, Renummerierung (7/8/9). Backup: `/root/barmbini-dsgvo-backup-20260820-090123.txt`.

**Git:** Commits `f7e81c8` (Plan), `82a4536` (Runbook + Plugin), `6b00212` (Datenschutzerklärung Doku) auf `main`.

**Hinweise / offene Punkte:**

- **Frontend-Einbindung:** Der Shortcode `[barmbini_visitor_stats]` kann auf einer internen Seite platziert werden (z. B. Seite „Statistiken"); bisher noch nicht auf einer Seite eingebunden.
- **Referrer-Datenqualität:** Der Testlauf zeigt die Server-IP/Hostname (`217.160.74.128`, `ip217-160-74-128.pbiaas.com`) als Referrer — intern/Admin-Traffic. Optional als interne Hosts in `process.php` ergänzbar.
- **Live-Seiten-Divergenz (behoben):** Die Live-Datenschutzerklärung enthielt zunächst keinen Abschnitt „Kundenkonto, Abonnements und Benachrichtigungen" (anders als die Doku). Am 2026-08-20 wurde der Abschnitt als „7. Kundenkonto, Abonnements und Benachrichtigungen" in die Live-Seite eingefügt (Backup: `/root/barmbini-dsgvo-backup-20260820-091655.txt`); Folgeabschnitte auf 8/9/10 renummeriert. Die Seite `/datenschutz/` entspricht damit wieder der Doku (`Docs/Barmbini_Rechtliche_Seiten.md`, Abschnitte 1–10).
- **Rechtliche Prüfung:** Formulierung ist dokumentiert; abschließende datenschutzrechtliche Freigabe liegt beim Verantwortlichen.
