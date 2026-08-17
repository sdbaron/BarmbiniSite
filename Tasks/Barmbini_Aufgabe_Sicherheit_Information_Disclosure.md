# Detaillierte Aufgabe: Information Disclosure schließen (readme.html, xmlrpc.php)

## Ziel

Auf dem Live-Server `217.160.74.128` sind zwei Informationslecks vorhanden:

1. **`/readme.html`** ist öffentlich erreichbar (HTTP 200) und gibt die WordPress-Versionsnummer preis.
2. **`/xmlrpc.php`** ist aktiv (HTTP 405 auf GET) und kann als Brute-Force-Vektor missbraucht werden (system.multicall, Amplifikation von Login-Angriffen).

Ziel ist es, beide Angriffsflächen zu schließen – server-seitig über ein dokumentiertes Runbook, reversibel und mit Rollback-Plan.

## Quellenbasis

- Server-Analyse vom 2026-08-11 (Befund: `/readme.html` → 200, `/xmlrpc.php` → 405)
- `.github/skills/deployment-safety-check/SKILL.md` – Deployment-Regeln (Server-Änderungen dokumentiert und reversibel)
- `Barmbini_Technisches_Konzept_v2.5.md` – §14 Risiken
- `Server_Aenderungsdokumentation_2026-04-22.md` – Server-Sicherheitskontext (frühere Kompromittierung)

## Fachliche Leitplanken

- Der Server gilt als **sicherheitskritisch** – jede Änderung mit Backup und Rollback.
- Beide Maßnahmen sind **server-seitig** (Dateisystem bzw. nginx) und werden als Runbook dokumentiert.
- Der Seitenbetrieb darf nicht beeinträchtigt werden.
- Kein Client des Projekts nutzt XML-RPC (kein externer App-Anschluss dokumentiert) – das Deaktivieren ist unkritisch.
- WordPress-Core-Updates dürfen die Maßnahme nicht aushebeln (deshalb nginx-Block statt Datei-Löschung bei `xmlrpc.php`).

## Verifizierter Ist-Stand (2026-08-11)

| Endpunkt | Status | Risiko |
|---|---|---|
| `GET /readme.html` | HTTP 200 | WordPress-Version öffentlich sichtbar |
| `GET /xmlrpc.php` | HTTP 405 (aktiv) | Brute-Force-/Multicall-Vektor |
| `GET /wp-content/debug.log` | HTTP 404 | ✅ kein Leck (positiv) |

## Aufgabe

### 1. `readme.html` entfernen

**Datei:** `/var/www/barmbini/readme.html`

Vorgehen:

```bash
# Backup statt sofortigem Löschen
cp /var/www/barmbini/readme.html /root/barmbini-readme-backup-$(date +%F-%H%M%S).html
rm /var/www/barmbini/readme.html
```

**Wichtig:** `readme.html` liegt im **WordPress-Root**, nicht in `wp-content`. Die Deployment-Skripte übertragen nur `wp-content`, daher bleibt das Löschen von späteren Deployments unberührt.

**Optional (empfohlen, zusätzliche Absicherung):** Auch `license.txt` und `wp-admin/images/wordpress-logo.svg` sind nicht kritisch, können aber ebenfalls Versionshinweise enthalten. Nur `readme.html` ist Pflicht dieser Aufgabe.

### 2. `xmlrpc.php` per nginx blocken

**Empfohlener Weg (überlebt Core-Updates):**

In `/etc/nginx/sites-available/barmbini` einen Block ergänzen:

```nginx
# XML-RPC blockieren (Brute-Force-Vektor, wird nicht benötigt)
location = /xmlrpc.php {
    deny all;
    return 403;
}
```

**Alternative (optional, WP-Ebene, Code):** In `barmbini-core` einen Filter setzen:

```php
add_filter( 'xmlrpc_enabled', '__return_false' );
```

**Achtung:** `xmlrpc_enabled = false` deaktiviert nur die XML-RPC-**Methoden**, entfernt den Endpoint aber nicht vollständig. Für eine echte Blockade ist der nginx-Block (z. B. 403) die robustere Maßnahme. Empfehlung: **nginx-Block als Primärmaßnahme**, optional zusätzlich der WP-Filter.

> **Status (2026-08-11):** Der WP-Ebene-Filter (`xmlrpc_enabled` → `false`) ist bereits umgesetzt – als Teil des Security-Moduls `Barmbini_Core_Rest_Api_Hardening` (`includes/security/class-rest-api-hardening.php`). Lokal validiert: `wp.getUsersBlogs` liefert Fehler 405 „XML-RPC-Dienst auf dieser Website deaktiviert". Der **nginx-Block (return 403)** für eine vollständige Blockade steht noch aus und wird über dieses Server-Runbook ausgeführt.

### 3. Konfiguration testen und aktivieren

```bash
nginx -t                 # Konfigurationstest
systemctl reload nginx   # Reload ohne Downtime
```

### 4. Verifikation

```bash
curl -sI http://217.160.74.128/readme.html   # erwartet: 404
curl -sI http://217.160.74.128/xmlrpc.php    # erwartet: 403
```

### 5. Regressionstest im Browser

- Startseite, ein Beitrag, Admin-Login (`/wp-login.php`) laden → alles funktioniert.
- Gutenberg-Editor eines Redakteurs öffnen (XML-RPC wird von keinem Editor benötigt, der Block bricht nichts).

## Abnahmekriterien

- [ ] `GET /readme.html` → 404 (keine Versions-Info mehr)
- [x] `GET /xmlrpc.php` → 403 (blockiert)
- [ ] Backup von `readme.html` existiert unter `/root/`
- [ ] Backup der nginx-Konfiguration existiert (siehe Aufgabe HTTP-Header, falls noch nicht vorhanden)
- [ ] `nginx -t` erfolgreich vor dem Reload
- [ ] Frontend- und Admin-Regressionstest bestanden

## Deployment

- **Server-Änderung** – erfolgt über das dokumentierte Runbook, nicht über `deploy.ps1`.
- Kein SQL-Import, keine Datenänderung.
- Die `readme.html`-Löschung und der nginx-Block werden im Server-Runbook festgehalten.

### Umsetzungsstatus (2026-08-11)

| Teil | Status |
|---|---|
| WP-Ebene: `xmlrpc_enabled` → `false` | ✅ **Umgesetzt & live deployed** (Security-Modul, Commit `19280f8`, deploy.ps1 Modus B) – live verifiziert: `wp.getUsersBlogs` → 405 |
| Server: nginx-Block `xmlrpc.php` → 403 | ✅ **Umgesetzt (2026-08-17)** – `location = /xmlrpc.php { deny all; return 403; }` in `/etc/nginx/sites-available/barmbini`, Backup `/root/barmbini-nginx-backup-2026-08-17` |
| Server: `readme.html` löschen | ⏳ **Offen** – nur per SSH ausführbar, Abschnitt „Server-Schritte" unten |

### Server-Schritte (per SSH, nach Freigabe)

Diese Schritte erfordern SSH-Zugriff auf `217.160.74.128` und werden **nicht** über die lokalen Skripte ausgeführt. Vor Ausführung: Backup und Freigabe einholen.

**1. Server-Backup:**
```bash
cp /etc/nginx/sites-available/barmbini /root/barmbini-nginx-backup-$(date +%F-%H%M%S)
cp /var/www/barmbini/readme.html /root/barmbini-readme-backup-$(date +%F-%H%M%S).html 2>/dev/null || true
```

**2. `readme.html` löschen:**
```bash
rm /var/www/barmbini/readme.html
```

**3. nginx-Block für `xmlrpc.php` ergänzen** (in `/etc/nginx/sites-available/barmbini`):
```nginx
# XML-RPC blockieren (Brute-Force-Vektor, wird nicht benötigt)
location = /xmlrpc.php {
    deny all;
    return 403;
}
```

**4. Testen + aktivieren:**
```bash
nginx -t && systemctl reload nginx
```

**5. Verifikation:**
```bash
curl -sI http://217.160.74.128/readme.html   # 404
curl -sI http://217.160.74.128/xmlrpc.php    # 403
```

**6. Regression:** Startseite, ein Beitrag, `/wp-login.php`, `/wp-admin/` laden.

**7. Doku:** Nach Durchführung in `Server_Aenderungsdokumentation_*.md` nachtragen.

## Rollback

```bash
# readme.html wiederherstellen (falls nötig)
cp /root/barmbini-readme-backup-<zeitstempel>.html /var/www/barmbini/readme.html

# nginx-Block entfernen
cp /root/barmbini-nginx-backup-<zeitstempel> /etc/nginx/sites-available/barmbini
nginx -t && systemctl reload nginx
```

## Risiken und offene Punkte

- **Offene Frage:** Nutzt irgendein Service oder Plugin XML-RPC (z. B. eine App-Verknüpfung, Jetpack-artige Dienste)? Im Projekt ist kein solcher Dienst dokumentiert. Vor der Umsetzung einmal prüfen, ob ein Plugin XML-RPC erwartet; falls ja, nur den nginx-Block (statt komplett) setzen.
- Der nginx-Block für `xmlrpc.php` muss bei einer Neuinstallation des Servers erneut eingespielt werden – im Server-Setup-Runbook vermerken.

## Dokumentation

- Nach Umsetzung in `Server_Aenderungsdokumentation_*.md` nachtragen.
- Offene Frage (XML-RPC-Nutzung) vor dem Deployment im Ticket klären.
