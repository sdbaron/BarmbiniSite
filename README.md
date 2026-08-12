# BarmbiniSite

WordPress-/WooCommerce-Projekt für das Sozialkaufhaus Barmbini.
Dokumentation, Deployment- und Fetch-Skripte für den Server `217.160.74.128`.

## Skript-Übersicht

| Skript | Richtung | Beschreibung |
|--------|----------|-------------|
| `deploy.ps1` / `deploy.sh` | **Local → Server** | Code (und optional DB) auf den Live-Server deployen |
| `fetch.ps1` / `fetch.sh`   | **Server → Local** | Code (und optional DB) vom Live-Server abrufen und lokal einspielen |
| `sync.ps1` / `sync.sh`     | **Bidirektional** | `barmbini-core` Plugin zwischen Workspace und Local-Installation synchronisieren |
| `fetch-backups.ps1`        | **Server → Local** | Server-Backups (DB + wp-content) nach lokal holen, nach Verifikation vom Server löschen |

## Backups vom Server sichern (`fetch-backups.ps1`)

Holt die Server-Backups (DB-Dumps + `wp-content`-Archive) nach lokal und löscht sie nach erfolgreicher Verifikation standardmäßig vom Server – so wird der knappe Server-Speicher entlastet, während die Daten lokal als Offsite-Kopie erhalten bleiben.

```powershell
.\fetch-backups.ps1                  # holen + Server bereinigen (Standard)
.\fetch-backups.ps1 -KeepServer      # nur holen, Server-Kopien behalten
.\fetch-backups.ps1 -IncludeForensic # + Offsite-Kopie der Malware-Backups (keine Server-Löschung)
```

**Wichtig:** Lokale Backups enthalten personenbezogene DB-Daten und liegen unter `server-backups/`, das in `.gitignore` ausgeschlossen ist – **nicht committen**.

## Deployment (`deploy.ps1` / `deploy.sh`)

```bash
# Modus B (Standard): Nur Code deployen – Live-Daten bleiben erhalten
./deploy.sh

# Modus A: Vollabgleich mit SQL-Import (Datenbank wird ersetzt!)
./deploy.sh --full --force

# PowerShell
.\deploy.ps1
.\deploy.ps1 -Full -Force
```

## Fetch – Vom Server abrufen (`fetch.ps1` / `fetch.sh`)

Holt Sitedaten vom Live-Server und spielt sie in die lokale Installation ein.
Das Gegenstück zu `deploy` – nützlich wenn der Server-Stand der aktuellere ist.

```bash
# Modus B (Standard): Nur Code holen (plugins, themes, languages)
./fetch.sh

# Modus A: Vollabgleich mit Datenbank + Uploads
./fetch.sh --full

# PowerShell
.\fetch.ps1
.\fetch.ps1 -Full
```

### Fetch-Modi

| Modus | Schalter | Holt | Überschreibt |
|-------|----------|------|-------------|
| **B** (Standard) | _keiner_ | `wp-content/languages`, `wp-content/plugins`, `wp-content/themes`, `wp-content/index.php` | Nur Code-Dateien |
| **A** | `--full` / `-Full` | Alles aus Modus B + `wp-content/uploads` + **komplette Datenbank** | Gesamten lokalen Stand |

### Fetch-Schutzmaßnahmen

- **Lokales Backup** vor jedem Fetch (überspringbar mit `--nobackup` / `-NoBackup`)
- **Altersprüfung** des DB-Dumps (Warnung wenn >24h, überspringbar mit `--force` / `-Force`)
- **URL-Umschreibung** nach DB-Import: `http://217.160.74.128` → `https://barmbini.local`
- **Sanity-Check** nach Entpacken: Kadence-Theme & barmbini-core werden auf Existenz geprüft

### Optionen (beide Skripte)

| Flag | Bedeutung |
|------|-----------|
| `--full` / `-f` / `-Full` | Modus A: Vollabgleich mit DB + Uploads |
| `--nobackup` / `-nb` / `-NoBackup` | Lokales Backup überspringen |
| `--nobrowser` / `-n` / `-NoBrowser` | Kein Browser-Tab nach Abschluss |
| `--force` / `-ff` / `-Force` | Altersprüfung deaktivieren / erzwungener Import |

## Umgebungsvariablen

| Variable | Standard | Beschreibung |
|----------|----------|-------------|
| `BARMBINI_LOCAL_ROOT` | `D:\Local Sites\barmbini\app\public` (Windows) / `~/Local Sites/barmbini/app/public` (macOS/Linux) | Pfad zur lokalen WP-Installation |
| `BARMBINI_WORKSPACE` | Verzeichnis des Skripts | Pfad zum Arbeitsverzeichnis |
| `BARMBINI_TARGET` | `217.160.74.128` | Server-IP oder Hostname |

## Shortcodes

Alle projektspezifischen Shortcodes stellt das Plugin `barmbini-core` bereit:

- `[barmbini_address]` – Adressblock (Kontakt)
- `[barmbini_latest_news]` – Letzte Neuigkeiten-Beiträge
- `[barmbini_promotion]` – Aktuell gültige Aktionen
- `[barmbini_top_product_categories]` – Sortiment-Kategorien als Grid

Vollständige Referenz mit allen Attributen und Beispielen: **`Docs/Barmbini_Shortcodes.md`**

## Server-Infos

- **IP:** `217.160.74.128`
- **Webroot:** `/var/www/barmbini`
- **DB-Name:** `barmbini_wp`
- **DB-Credentials:** `/root/barmbini-db.txt`
- **Stack:** nginx, php8.3-fpm, mariadb, wp-cli

## Projektstruktur

```
.
├── deploy.ps1 / deploy.sh       # Deployment Local → Server
├── fetch.ps1 / fetch.sh         # Fetch Server → Local
├── sync.ps1 / sync.sh           # Plugin-Sync Workspace ↔ Local
├── README.md                    # Diese Datei
├── Docs/                        # Benutzerdokumentation (Anleitungen, Inhalte, Referenzen)
│   ├── Barmbini_Anleitung_Aktionen_Admin.md
│   ├── Barmbini_Seiteninhalte.md
│   ├── Barmbini_Shortcodes.md
│   ├── Barmbini_Rechtliche_Seiten.md
│   └── *.docx                   # Inhalts- und Anforderungsdateien
├── Barmbini_Technisches_Konzept_v2.5.md
├── Barmbini_Aufgabe_Update_von_local_auf_Server.md
├── Barmbini_Aufgabe_Update_Modus_B_Live_Daten_erhalten.md
├── Barmbini_Migrationsdurchfuehrung_2026-04-22.md
├── Barmbini_Vorbereitung_Features_und_Bugfixes.md
├── wp-content/
│   └── plugins/
│       └── barmbini-core/       # Haupt-Plugin
└── Images/                      # Bildmaterial
```

## Wichtige Regeln

- Vor jedem Live-Update **explizit Modus A oder B entscheiden** (siehe `Barmbini_Aufgabe_Update_von_local_auf_Server.md`)
- **Modus A nur**, wenn keine Live-Benutzer-/Kundendaten existieren
- **Modus B ist Pflicht**, sobald Kundenkonten, Bestellungen oder andere Live-Daten vorhanden sind
- Manuelle Änderungen direkt auf dem Server gehen beim nächsten Deployment verloren
- `wp-config.php`, Nginx-Config und PHP-FPM-Einstellungen werden **nicht** von den Skripten überschrieben
