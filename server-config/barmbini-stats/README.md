# Barmbini Besucherstatistik – Server-Runbook (Option B)

Anonymisierte Besucherstatistik über das nginx-Zugriffslog von **barmbini.de**.
Keine Cookies, keine IP-Speicherung, keine externen Dienste. Es werden nur
**aggregierte Werte** pro Tag als JSON abgelegt; das Plugin `barmbini-core`
zeigt sie an (Admin + Frontend, nur für Administrator/Redakteur).

## Bestandteile

| Datei | Zweck |
|---|---|
| `process.php` | PHP-CLI: parst das rotierte Zugriffslog, filtert Views, anonymisiert, schreibt JSON-Aggregate |
| `process.sh` | Bash-Wrapper für den Cron (ruft `process.php` auf) |
| `install.sh` | Idempotente Installation (Verzeichnisse, Skripte, logrotate, Cron) |
| `logrotate-barmbini-stats` | logrotate-Konfiguration (nur falls nginx-logrotate die Datei nicht abdeckt) |

## Datenfluss

```text
nginx → /var/log/nginx/barmbini_access.log
        → logrotate (täglich) → barmbini_access.log.1 (Klartext, delaycompress)
        → process.sh (Cron 07:15) → process.php
        → /var/lib/barmbini-stats/stats/stats-YYYY-MM-DD.json
        → Plugin barmbini-core (Admin-Seite + Shortcode [barmbini_visitor_stats])
```

## Installation (auf dem Server, als root)

```bash
cd /root/barmbini-stats        # nach dem Kopieren der Dateien
./install.sh --test            # installiert + einmaliger Testlauf
```

`install.sh` ist idempotent und macht:

1. Verzeichnisse anlegen (`/root/barmbini-stats`, `/var/lib/barmbini-stats/stats`)
2. Skripte nach `/root/barmbini-stats` kopieren
3. logrotate: `/var/log/nginx/barmbini_access.log` → `daily`, `rotate 7`, `delaycompress`
   - Falls `/etc/logrotate.d/nginx` die Datei bereits abdeckt, wird dort nur
     `rotate 7` gesetzt (mit Backup unter `/root/barmbini-stats/backups/`),
     um **Doppelrotation** zu vermeiden.
4. Cron: `/etc/cron.d/barmbini-stats` mit `15 7 * * * root /root/barmbini-stats/process.sh`

**Wichtig:** Der Cron muss **nach** logrotate laufen, damit die Datei
`barmbini_access.log.1` noch als Klartext vorliegt (nicht zu `.gz` komprimiert).

## Metriken (Aggregate pro Tag)

- `views` – HTML-Seitenaufrufe (nur GET, Status 200; statische Assets,
  `/wp-admin`, `/wp-login.php`, `/wp-json/`, Feeds, Sitemaps ausgeschlossen)
- `unique_visitors` – Anzahl unterschiedlicher **anonymisierter** IPs (nur der
  Zähler; IPs werden nie gespeichert)
- `devices` – mobile / tablet / desktop (aus dem User-Agent)
- `top_pages` – beliebteste Seiten (Pfad + Views)
- `top_referrers` – Referrer-Domains (ohne Schema/Query; interne ausgeschlossen)
- `bots` – gefilterte Bot-Anfragen (nur wenn Bot-Filter aktiv, Standard: ja)

## Aufbewahrung (DSGVO)

- Roh-Log (`barmbini_access.log*`): **7 Tage** (logrotate `rotate 7`)
- Aggregate (`stats-*.json`): **90 Tage** (automatisch bereinigt durch `process.php`)

## Konfiguration (Umgebungsvariablen)

| Variable | Standard | Bedeutung |
|---|---|---|
| `BARMBINI_LOG_INPUT` | `/var/log/nginx/barmbini_access.log.1` | Eingabedatei |
| `BARMBINI_STATS_DIR` | `/var/lib/barmbini-stats/stats` | Zielverzeichnis |
| `BARMBINI_RUN_LOG` | `/var/log/barmbini-stats.log` | Lauf-Log |
| `BARMBINI_RETENTION_DAYS` | `90` | Aufbewahrung Aggregate |
| `BARMBINI_TOP_N` | `10` | Länge der Top-Listen |
| `BARMBINI_BOT_FILTER` | `1` | Bot-Filter an/aus |

## Validierung

```bash
# 1. Testlauf gegen die letzte rotierte Datei
/root/barmbini-stats/process.sh

# 2. Ausgabe prüfen (keine IPs, keine vollen Referrer-URLs)
cat /var/lib/barmbini-stats/stats/stats-*.json
tail -n 5 /var/log/barmbini-stats.log

# 3. logrotate-Konfiguration prüfen
logrotate -d /etc/logrotate.d/nginx   # Dry-Run

# 4. Cron prüfen
cat /etc/cron.d/barmbini-stats
```

## Fehlerbehandlung

| Symptom | Ursache / Lösung |
|---|---|
| „Input nicht gefunden" | logrotate hat `.1` noch nicht erzeugt → später laufen lassen oder `BARMBINI_LOG_INPUT` setzen |
| Keine Views | Log enthält nur Assets/Bots, oder Filter zu streng → Filter in `process.php` prüfen |
| Doppelrotation des Logs | `/etc/logrotate.d/nginx` und eigene logrotate-Regel gleichzeitig → nur eine Regel aktiv lassen |
| Keine Daten im Plugin | Plugin-Lese-Pfad ≠ `BARMBINI_STATS_DIR` → Filter `barmbini_stats_dir` im Plugin anpassen |

## Rechtlicher Hinweis

Die Verarbeitung ist bewusst **anonymisiert** (keine IP-/Referrer-Speicherung,
keine Cookies). Vor der Live-Schaltung muss die **Datenschutzerklärung**
aktualisiert werden (berechtigtes Interesse, Art. 6 Abs. 1 lit. f DSGVO,
Fristen). Siehe Task-Dokument `Tasks/Barmbini_Aufgabe_Besucherstatistik_nginx.md`.
