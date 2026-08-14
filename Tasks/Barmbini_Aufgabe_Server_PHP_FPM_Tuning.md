# Detaillierte Aufgabe: PHP-FPM-Tuning zur RAM-Entlastung (826-MB-VPS)

## Ziel

Der Server `217.160.74.128` ist ein 1-Kern-VPS mit nur **826 MB RAM**. Während eines `-Full`-Deployments (DB-Import + Cache-Aufbau) steigt die Last stark an; am 2026-08-13 wurde dabei einmalig ein `curl`-Prozess vom **OOM-Killer** beendet (Load Average kurz bis 1.19).

Ziel dieser Aufgabe: die **PHP-FPM-Konfiguration so anpassen**, dass der Speicherbedarf unter Volllast die knappe RAM-Grenze nicht überschreitet und OOM-Kills künftig vermieden werden.

## Quellenbasis

- Server-Ressourcenanalyse vom 2026-08-12 (RAM-Engpass dokumentiert)
- Beobachtung beim `-Full`-Deployment am 2026-08-13 (OOM-Kill eines curl-Prozesses, Load-Spike)
- `.github/skills/deployment-safety-check/SKILL.md` – Server-Änderungen mit Backup + Rollback

## Verifizierter Ist-Stand (2026-08-13)

| Parameter | Wert |
|---|---|
| RAM gesamt / verfügbar | 826 MB / ~240 MB |
| Swap | 2 GB |
| `pm` | `dynamic` |
| `pm.max_children` | **5** |
| `pm.start_servers` | 2 |
| `pm.min_spare_servers` | 1 |
| `pm.max_spare_servers` | 3 |
| Worker-RSS (je) | ~110–123 MB |

**Rechnung:** 5 Worker × ~120 MB = **~600 MB nur für PHP-FPM** – plus nginx + MariaDB (~50 MB) überschreitet das knapp die 826 MB und erzwingt Swap/OOM.

## Empfehlung

`pm.max_children` von **5 auf 3** senken und die Spare-Server konservativ halten. Das reduziert die Worst-Case-Speicherspitze um ~240 MB:

```
pm = dynamic
pm.max_children = 3
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 2
```

**Begründung:**
- Die Website hat wenig Traffik (Load Average sonst ~0.08, praktisch idle); 3 Worker reichen locker.
- 3 × ~120 MB = ~360 MB PHP-FPM → deutlich sicherer unter der 826-MB-Grenze.
- Gelegentliche Spitzen werden vom 2-GB-Swap abgefangen, ohne Akut-OOM.

## Aufgabe

### 1. Backup der FPM-Konfiguration

```bash
cp /etc/php/8.3/fpm/pool.d/www.conf /root/www.conf-backup-$(date +%F-%H%M%S)
```

### 2. Werte anpassen

In `/etc/php/8.3/fpm/pool.d/www.conf` setzen:

```
pm = dynamic
pm.max_children = 3
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 2
```

### 3. Konfiguration testen + neu laden

```bash
php-fpm8.3 -t                 # Konfigurationstest (muss "syntax is ok" liefern)
systemctl reload php8.3-fpm   # Reload ohne Downtime
```

### 4. Verifikation

```bash
# Neue Werte prüfen
grep -E '^pm.max_children|^pm.start_servers|^pm.max_spare_servers' /etc/php/8.3/fpm/pool.d/www.conf

# Worker-Zahl nach Reload
ps -eLf | grep -c 'php-fpm: pool'

# Speicher-Lage
free -h
```

### 5. Regressionstest

- Ein `-Full`-Deployment (oder zumindest DB-Import + Cache-Flush) erneut ausführen und beobachten, ob ein OOM-Kill auftritt.
- Startseite, Sortiment, Kontakt, eine Aktion laden → alles funktioniert.

## Abnahmekriterien

- [ ] `pm.max_children` auf 3 gesenkt
- [ ] `php-fpm8.3 -t` erfolgreich vor Reload
- [ ] `systemctl reload php8.3-fpm` ohne Downtime
- [ ] Kein OOM-Kill beim nächsten Deployment
- [ ] Frontend unverändert funktioniert
- [ ] Backup der `www.conf` existiert unter `/root/`

## Deployment

- **Server-Änderung** (PHP-FPM-Konfiguration) per SSH, nicht über `deploy.ps1`.
- Kein SQL-Import, keine Datenänderung.
- Die Änderung überlebt Neustarts (sie liegt in `/etc/php/8.3/fpm/pool.d/`).

## Rollback

```bash
cp /root/www.conf-backup-<zeitstempel> /etc/php/8.3/fpm/pool.d/www.conf
php-fpm8.3 -t && systemctl reload php8.3-fpm
```

## Risiken und offene Punkte

- **Offene Frage:** Reicht `max_children = 3` bei künftig höherem Traffic? (Bei Bedarf auf 4 anheben – mit 826 MB RAM ist 3 der sichere Wert.)
- `memory_limit` (PHP) war bei der Abfrage nicht feststellbar; falls es hoch gesetzt ist (z. B. 256M), sollte es zusätzlich auf z. B. `128M` begrenzt werden, um einzelne Worker-Spitzen zu begrenzen.
- **Nicht Teil dieser Aufgabe:** MariaDB-/nginx-Tuning, RAM-/VPS-Erweiterung.

## Dokumentation

- Nach Umsetzung in `Server_Aenderungsdokumentation_*.md` nachtragen.
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` Ist-Stand (Server-Ressourcen) aktualisieren.
