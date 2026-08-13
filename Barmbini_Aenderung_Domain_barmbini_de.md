# Änderungsdokumentation: Domain-Umstellung auf barmbini.de

Stand: 2026-08-13

## Anlass

Die Website war unter `http://217.160.74.128` erreichbar, aber die Domain `barmbini.de` zeigte keine Inhalte. Ursache: zwei getrennte Probleme.

## Befund (vor der Korrektur)

| Punkt | Zustand |
|---|---|
| DNS `barmbini.de` (Apex) | zeigte auf falsche IP `217.160.0.39` |
| DNS `www.barmbini.de` | zeigte korrekt auf `217.160.74.128` |
| nginx `server_name` | `barmbini.de www.barmbini.de` (bereit) |
| `wp-config.php` `WP_HOME`/`WP_SITEURL` | hart auf `http://217.160.74.128` gesetzt |
| WordPress `home`/`siteurl` (DB) | `http://217.160.74.128` |

**Wirkung:**
- `barmbini.de` → falsche IP → keine Inhalte
- `www.barmbini.de` → korrekte IP → WordPress-Redirect auf die IP (wegen `WP_HOME`/`WP_SITEURL`)

## Durchgeführte Korrekturen

### 1. DNS (extern, IONOS – vom Nutzer durchgeführt)

- A-Record `barmbini.de` von `217.160.0.39` auf `217.160.74.128` geändert (TTL 3600).
- `www.barmbini.de` bleibt auf `217.160.74.128`.
- Verifiziert über Google DNS (8.8.8.8) und Cloudflare (1.1.1.1): beide liefern `barmbini.de → 217.160.74.128`.

### 2. `wp-config.php` (per SSH)

- Backup: `/root/wp-config-backup-2026-08-13-domain.php`
- `WP_HOME` und `WP_SITEURL` von `http://217.160.74.128` auf `http://barmbini.de` geändert.

### 3. Datenbank (per SSH)

- DB-Backup: `/root/barmbini-db-backup-2026-08-13-domain/live-before-domain-fix.sql`
- `home`/`siteurl` Optionen auf `http://barmbini.de` gesetzt.
- `wp search-replace 'http://217.160.74.128' 'http://barmbini.de' --all-tables --skip-columns=guid` → **161 Ersetzungen** (guid-Spalte bewusst unangetastet).

### 4. nginx (per SSH)

- Backup: `/root/barmbini-nginx-backup-2026-08-13-domain`
- Haupt-Serverblock: `server_name barmbini.de;`
- Neuer Redirect-Block:
  ```nginx
  server {
      listen 80;
      listen [::]:80;
      server_name www.barmbini.de;
      return 301 http://barmbini.de$request_uri;
  }
  ```
- `nginx -t` erfolgreich, `systemctl reload nginx`, Cache geleert.
- Aktuelle Konfiguration liegt versioniert unter `server-config/barmbini-nginx.conf`.

## Validierter Endzustand

- `http://barmbini.de/` → **200 OK**, Titel „Startseite - Sozialkaufhaus"
- `http://www.barmbini.de/` → **301** `Location: http://barmbini.de/`
- WordPress `home`/`siteurl` → `http://barmbini.de`
- Inhalts-URLs (161) auf Domain umgestellt

## Offene Punkte / Folge-Aufgaben

- **HTTPS:** Die Domain läuft weiterhin über **HTTP** (kein SSL-Zertifikat). Für `barmbini.de` sollte Let's Encrypt eingerichtet werden (separate Aufgabe).
- **Lokaler DNS-Cache:** Einzelne Clients (z. B. die lokale Arbeitsmaschine) können bis zu 1 Stunde (TTL) noch die alte IP liefern.
- **Lokale Dev-Umgebung:** `D:\Local Sites\barmbini` nutzt weiterhin `barmbini.local` – das ist korrekt und unverändert.

## Rollback

- DNS: A-Record zurück auf alte IP setzen (IONOS).
- `wp-config.php`: `/root/wp-config-backup-2026-08-13-domain.php` zurückspielen.
- DB: `/root/barmbini-db-backup-2026-08-13-domain/live-before-domain-fix.sql` einspielen.
- nginx: `/root/barmbini-nginx-backup-2026-08-13-domain` zurückspielen + `nginx -t && reload`.
