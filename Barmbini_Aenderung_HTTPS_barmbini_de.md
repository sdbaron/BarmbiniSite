# Änderungsdokumentation: HTTPS für barmbini.de (Let's Encrypt)

Stand: 2026-08-13

## Anlass

Die Website lief unter `http://barmbini.de` (unverschlüsselt). Nach der Domain-Umstellung (siehe `Barmbini_Aenderung_Domain_barmbini_de.md`) wurde HTTPS eingerichtet.

## Durchgeführte Schritte

### 1. Zertifikat (vom Nutzer per certbot ausgeführt)

```bash
apt install certbot python3-certbot-nginx -y
certbot --nginx -d barmbini.de -d www.barmbini.de
```

- E-Mail: `info@barmbini.de`
- Zertifikat: `/etc/letsencrypt/live/barmbini.de/fullchain.pem` (+ `privkey.pem`)
- Gültig bis: **2026-11-11**; automatische Erneuerung eingerichtet (certbot-Timer).

### 2. nginx-Korrekturen (per SSH, nach certbot)

certbot hat die Config um HTTPS-Blöcke erweitert, aber zwei Probleme hinterlassen:

| Problem | Fix |
|---|---|
| `www.barmbini.de` (443) leitete auf `http://barmbini.de` (Downgrade) | `return 301 http://barmbini.de$request_uri;` → `https://barmbini.de$request_uri;` |
| HSTS fehlte | `add_header Strict-Transport-Security "max-age=31536000" always;` im Haupt-HTTPS-Block ergänzt |

Backup: `/root/barmbini-nginx-backup-2026-08-13-https`

### 3. WordPress-URLs auf HTTPS

- `wp-config.php`: `WP_HOME`/`WP_SITEURL` → `https://barmbini.de` (Backup `/root/wp-config-backup-2026-08-13-https.php`)
- DB `home`/`siteurl` → `https://barmbini.de` (direktes SQL-Update, da WP-CLI die Konstanten mitlas)
- `wp search-replace 'http://barmbini.de' 'https://barmbini.de' --all-tables --skip-columns=guid` → **214 Ersetzungen** (guid unangetastet)
- WP Fastest Cache geleert

## Validierter Endzustand (vom Server aus geprüft)

| Prüfung | Ergebnis |
|---|---|
| `https://barmbini.de/` | **200 OK** (Link-Header zeigen `https://barmbini.de/`) |
| `https://www.barmbini.de/` | **301** → `https://barmbini.de/` |
| `http://barmbini.de/` | **301** → `https://barmbini.de/` |
| HSTS | `Strict-Transport-Security: max-age=31536000` ✅ |
| Mixed-Content (`http://barmbini.de` im HTML) | **0** ✅ |

## Hinweise

- **Lokaler Browser/Windows:** Der Test von Windows aus kann mit `CRYPT_E_REVOCATION_OFFLINE` fehlschlagen (Windows-Schannel-Prüfung) – das ist ein **Client-Problem**, kein Server-Problem. Vom Server aus funktioniert HTTPS einwandfrei.
- **DNS-Cache:** Lokale Router können die alte IP bis zu 1 Stunde (TTL) cachen.
- **Zertifikat-Erneuerung:** certbot-Timer läuft automatisch; `certbot renew` verlängert das Zertifikat.

## Rollback

- nginx: `/root/barmbini-nginx-backup-2026-08-13-https` zurückspielen
- wp-config: `/root/wp-config-backup-2026-08-13-https.php` zurückspielen
- DB: `wp search-replace 'https://barmbini.de' 'http://barmbini.de' --all-tables --skip-columns=guid`
- Zertifikat: `certbot delete --cert-name barmbini.de` (falls komplett zurück)

## Offene Punkte

- **Keine** unmittelbar – HTTPS, Redirects, HSTS und Mixed-Content sind sauber.
- Optionale Folge-Härtung: `Content-Security-Policy` (nach eventueller Google-Maps-Bereinigung, siehe `Barmbini_Aufgabe_Sicherheit_HTTP_Header.md`), sowie die übrigen Sicherheits-Header aus derselben Aufgabe.
