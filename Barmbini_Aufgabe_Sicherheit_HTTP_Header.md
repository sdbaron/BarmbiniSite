# Detaillierte Aufgabe: Sicherheits-Header auf dem Server setzen

## Ziel

Der Live-Server `217.160.74.128` (nginx) liefert aktuell **keine** Sicherheits-Header aus. Dadurch fehlen Browser-Schutzmechanismen wie Clickjacking-Absicherung (X-Frame-Options) und MIME-Sniffing-Schutz (X-Content-Type-Options).

Ziel ist es, die fehlenden HTTP-Sicherheits-Header in der nginx-Konfiguration zu ergänzen. Die Änderung erfolgt **server-seitig über ein dokumentiertes Runbook** – nicht ad hoc am Live-System.

## Quellenbasis

- Server-Analyse vom 2026-08-11 (Befund: alle geprüften Sicherheits-Header fehlen)
- `.github/skills/deployment-safety-check/SKILL.md` – Deployment-Regeln (Server-Änderungen dokumentiert und reversibel)
- `Barmbini_Technisches_Konzept_v2.5.md` – §14 Risiken, §2 Technische Basis
- `Barmbini_Server_Migration_Aufgabe.md` / `Server_Aenderungsdokumentation_2026-04-22.md` – Server-Kontext

## Fachliche Leitplanken

- Der Server gilt als **sicherheitskritisch** (frühere Kompromittierung dokumentiert) – Änderungen nur mit Backup und Rollback-Plan.
- Server-Konfiguration wird als Runbook dokumentiert, nicht als „vergessene" Live-Anpassung.
- Die Website läuft aktuell über **HTTP** (kein SSL). Deshalb wird `Strict-Transport-Security` (HSTS) bewusst **zurückgestellt**, bis HTTPS eingerichtet ist.
- Eine Content-Security-Policy darf den Seitenbetrieb nicht brechen – insbesondere den aktuell eingebetteten Google-Maps-iframe auf der Startseite (siehe Abschnitt „CSP und Google Maps").
- Der Seitenbetrieb (Frontend, Admin, WooCommerce, Contact Form 7, Kadence) muss unverändert funktionieren.

## Verifizierter Ist-Stand (2026-08-11)

| Header | Status |
|---|---|
| `Server` | `nginx/1.24.0 (Ubuntu)` |
| `Strict-Transport-Security` | fehlt |
| `Content-Security-Policy` | fehlt |
| `X-Frame-Options` | fehlt |
| `X-Content-Type-Options` | fehlt |
| `Referrer-Policy` | fehlt |
| `Permissions-Policy` | fehlt |
| `X-XSS-Protection` | fehlt |

## Aufgabe

### 1. Server-Backup erstellen (Pflicht)

Vor jeder Konfigurationsänderung:

```bash
cp /etc/nginx/sites-available/barmbini /root/barmbini-nginx-backup-$(date +%F-%H%M%S)
```

Backup-Verzeichnis und Zeitstempel im Runbook festhalten.

### 2. Nginx-Konfiguration ergänzen

**Datei:** `/etc/nginx/sites-available/barmbini`

In den `server { ... }`-Block (bzw. den `location`-Kontext, der die Antworten der Site betrifft) folgende Header ergänzen:

```nginx
# Sicherheits-Header
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(), camera=(), microphone=(), payment=(), usb=()" always;
# X-XSS-Protection ist veraltet; kann optional gesetzt werden:
# add_header X-XSS-Protection "1; mode=block" always;
```

**Nicht jetzt setzen (zurückgestellt):**

- `Strict-Transport-Security` – erst nach Einrichtung von HTTPS/SSL sinnvoll.

### 3. Content-Security-Policy (bewusst vorsichtig)

Eine strikte CSP würde den **Google-Maps-iframe auf der Startseite** blockieren (`https://www.google.com/maps/embed/...`). Deshalb gilt:

- **Option A (empfohlen für den ersten Schritt):** Noch **keine** CSP setzen, sondern in einer Folge-Aufgabe klären, ob der Google-Maps-Embed durch eine statische Karte ersetzt wird (siehe Server-Analyse, Priorität 1). Erst danach eine enge CSP einführen.
- **Option B:** Eine erste, lockere CSP mit expliziter Freigabe für Google setzen:
  ```nginx
  add_header Content-Security-Policy "default-src 'self'; frame-src https://www.google.com; img-src 'self' data: https://s.w.org; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; font-src 'self' data:" always;
  ```
  **Achtung:** Diese Variante ist nur eine Zwischenlösung, solange der Google-Maps-Embed und die s.w.org-Emojis existieren. Eine enge CSP ist erst nach deren Beseitigung sinnvoll.

**Entscheidung im Projekt treffen und im Runbook dokumentieren** (Option A oder B), bevor der Header gesetzt wird.

### 4. Konfiguration testen und aktivieren

```bash
nginx -t                 # Konfigurationstest – muss "syntax is ok" + "test is successful" liefern
systemctl reload nginx   # Reload ohne Downtime
```

### 5. Verifikation

```bash
curl -sI http://217.160.74.128/ | grep -iE 'x-content-type-options|x-frame-options|referrer-policy|permissions-policy|content-security-policy'
```

Erwartet: Die gesetzten Header erscheinen in der Antwort.

### 6. Regressionstest im Browser

- Startseite, Sortiment, Kontakt, eine Aktion laden → kein Fehler, keine sichtbaren Brüche.
- Falls CSP (Option B) gesetzt wurde: Google-Maps-Karte auf der Startseite prüfen, Emojis prüfen.

## Abnahmekriterien

- [ ] `X-Content-Type-Options: nosniff` ist gesetzt
- [ ] `X-Frame-Options: SAMEORIGIN` ist gesetzt
- [ ] `Referrer-Policy` ist gesetzt
- [ ] `Permissions-Policy` ist gesetzt
- [ ] Falls entschieden: `Content-Security-Policy` ist gesetzt und bricht nichts
- [ ] Backup der Konfiguration existiert unter `/root/`
- [ ] `nginx -t` war erfolgreich vor dem Reload
- [ ] Frontend-Regressionstest bestanden

## Deployment

- **Server-Änderung** (nginx-Konfiguration) – erfolgt **nicht** über `deploy.ps1`, sondern über das dokumentierte Runbook (Backup → Edit → Test → Reload → Verify → Rollback-Bereit).
- Kein SQL-Import, keine Datenänderung.
- Die Änderung ist lokal **nicht** replizierbar (nginx ist server-spezifisch) – wird ausschließlich im Server-Runbook festgehalten.

## Rollback

```bash
cp /root/barmbini-nginx-backup-<zeitstempel> /etc/nginx/sites-available/barmbini
nginx -t && systemctl reload nginx
```

## Risiken und offene Punkte

- **HSTS zurückgestellt:** Erst nach HTTPS-Einführung sinnvoll – als Folge-Aufgabe dokumentieren.
- **CSP-Abhängigkeit:** Eine enge CSP blockiert den Google-Maps-Embed und die s.w.org-Emojis. Reihenfolge: erst statische Karte + Emoji-Problem lösen (Priorität 1 der Server-Analyse), dann CSP härten.
- `X-Frame-Options` verhindert das Einbetten der Site in fremde Frames (Clickjacking-Schutz) – sollte für eine normale Informationsseite unkritisch sein.
- Falls einzelne Seiten (z. B. Admin-Preview) bewusst in Frames eingebettet werden müssen: `SAMEORIGIN` erlaubt dieselbe Domain, das genügt in der Regel.

## Dokumentation

- Nach Umsetzung die Änderung im Server-Runbook und in `Server_Aenderungsdokumentation_*.md` nachtragen.
- Die Entscheidung CSP (Option A/B) explizit dokumentieren.
