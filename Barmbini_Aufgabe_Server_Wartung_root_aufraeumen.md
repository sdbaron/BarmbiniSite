# Detaillierte Aufgabe: `/root`-Speicher aufräumen (Server-Wartung)

## Umsetzungsstatus (2026-08-12)

| Schritt | Status |
|---|---|
| Sicherheits-Check (`barmbini-db.txt`, Forensik-Backups) | ✅ bestanden |
| Integrität des zu behaltenden Backups `...-125648` | ✅ intakt (TAR_OK, SQL: 6004 Zeilen MariaDB-Dump) |
| Älteres Backup `...-112119` **verschoben** | ✅ nach `/root/deploy-backups-archiv/` |
| Geschützte Einträge (`barmbini-db.txt`, Malware-Backups) | ✅ unangetastet |

**Wichtige Erkenntnis (ehrlich dokumentiert):** Durch `mv` innerhalb desselben Dateisystems wird **kein physischer Speicher freigegeben** – `/root` bleibt bei 390 MB und der freie Speicher auf `/` unverändert bei **1.9 GB / 79 %**. Die Verschiebung gewährleistet Reversibilität, aber der Speicher wird **erst durch Löschung** des archivierten Backups (`/root/deploy-backups-archiv/barmbini-backup-2026-08-11-112119`, 176 MB) frei. Das Löschen erfolgt nur nach **separater expliziter Freigabe** (Sicherheits-Check ist dann erneut nötig).

## Ziel

Der Root-Speicher `/` des Servers `217.160.74.128` ist zu **79 % belegt** (8.7 GB total, nur 1.9 GB frei). Ein wesentlicher Anteil liegt in `/root` (aktuell **390 MB**), darunter zwei große Deployment-Backups aus dem **2026-08-11**.

Ziel ist ein **kontrolliertes, reversibles Aufräumen** von `/root`, um freien Speicherplatz zurückzugewinnen, ohne Sicherheits- oder Rollback-Backups unbedacht zu verlieren.

## Quellenbasis

- Server-Ressourcenanalyse vom 2026-08-12 (per SSH)
- `.github/skills/deployment-safety-check/SKILL.md` – Backups/Rollback/Nachvollziehbarkeit
- `Server_Aenderungsdokumentation_2026-04-22.md` – Malware-Quarantäne-Backups (forensischer Wert)
- Migration-/Deploy-Runbooks (Backup-Logik aus `deploy.ps1`, `fetch.ps1`)

## Verifizierter Ist-Stand (2026-08-12)

Aufschlüsselung `/root` (Stand 2026-08-12):

| Eintrag | Größe | Art | Rollback-/Sicherheitswert |
|---|---|---|---|
| `barmbini-backup-2026-08-11-112119` | 176 MB | Deploy-Backup (DB + wp-content) | ⚠️ **älteres Deploy-Backup** – gleicher Tag, aktualeres Pendant vorhanden |
| `barmbini-backup-2026-08-11-125648` | 176 MB | Deploy-Backup (DB + wp-content) | ✅ **aktuellstes** Deploy-Backup – BEHALTEN |
| `barmbini-db-backup-2026-08-05-103727` | 5.4 MB | DB-Backup vor HTTPS-Fix | ✅ klein – BEHALTEN |
| `resolver-malware-backup-2026-04-22-111339` | 3.3 MB | **Malware-Quarantäne** | 🔴 **forensisch wertvoll – BEHALTEN** |
| `update_persistence_backup_2026-04-22-105221` | 48 KB | Persistenz-Backup (Alt) | ⚠️ veraltet, klein – optional prüfen |
| `malware-cleanup-2026-04-29-*` (2×) | je 8 KB | Malware-Cleanup-Notizen | 🔴 BEHALTEN (Sicherheitskontext) |
| `barmbini-muplugin-backup-2026-08-05-100340` | 8 KB | MU-Plugin-Rollback | ⚠️ klein, Rollback-Rest – optional prüfen |
| `systemd-resolved.service.malicious-*` | 4 KB | Malware-Artefakt | 🔴 BEHALTEN (forensisch) |
| `root-crontab-backup-2026-04-22-110302` | 4 KB | Cron-Backup (Alt) | ⚠️ veraltet – optional prüfen |
| `barmbini-db.txt` | 4 KB | **DB-Zugangsdaten** | 🔴 **KRITISCH – NIE LÖSCHEN** |
| `fetch-zip.sh`, `barmbini-import` | je 4 KB | Temp-Skripte/Ordner | ⚠️ Temp-Artefakte – prüfen |

**Hauptziel:** Die **352 MB** (2× 176 MB) an Deploy-Backups vom 2026-08-11 reduzieren, indem **nur das ältere** (`...-112119`) entfernt wird und das aktuellste (`...-125648`) als Rollback-Stand erhalten bleibt.

## Fachliche Leitplanken

- Der Server ist **sicherheitskritisch** (frühere Kompromittierung). Alle **Malware-/Forensik-Backups** bleiben unangetastet.
- **Niemals** die Datei `barmbini-db.txt` (DB-Zugangsdaten) löschen.
- Sicherste Regel: Es wird nur das **eine klar identifizierte, redundant ältere Deploy-Backup** entfernt; alle anderen Bestandteile werden entweder behalten oder **nur nach expliziter Freigabe** gelöscht.
- Vorgehen bevorzugt **verschieben statt sofort löschen** (Reversibilität), oder zumindest mit dokumentiertem Inhalt.

## Aufgabe

### 1. Erneuter Sicherheits-Check vor dem Löschen

Vor jedem Löschvorgang:

```bash
# Kategorien auflisten und NICHT löschbare Einträge verifizieren
ls -ld /root/barmbini-db.txt                    # MUSS existieren (Zugangsdaten)
ls -d /root/resolver-malware-backup-*           # MUSS bleiben (forensisch)
ls -d /root/malware-cleanup-*                   # MUSS bleiben (forensisch)
ls -d /root/systemd-resolved.service.malicious-* # MUSS bleiben (forensisch)
```

### 2. Älteres redundantes Deploy-Backup entfernen

**Empfohlen (sicherste Variante – verschieben statt löschen):**

```bash
# In /root/deploy-backups-archiv verschieben (nicht löschen)
mkdir -p /root/deploy-backups-archiv
mv /root/barmbini-backup-2026-08-11-112119 /root/deploy-backups-archiv/
# Danach freier Platz prüfen
df -h /
```

**Alternative (wenn Speicher sofort frei werden muss):**

```bash
# Nur wenn Roollback-Stand durch 125648 sichergestellt ist
rm -rf /root/barmbini-backup-2026-08-11-112119
```

**Begründung:** Das Backup `...-112119` wurde am selben Tag durch das jüngere `...-125648` (gleicher Deployment-Lauf) abgelöst. Beide enthalten identischen Zweck (DB + wp-content vor Deploy). Das aktuellste bleibt als Rollback-Stand.

> **Vorsicht vor dem Löschen via `rm -rf`:** Erst verifizieren, dass `...-125648` vollständig und lesbar ist:
> ```bash
> tar -tzf /root/barmbini-backup-2026-08-11-125648/wp-content-before-deploy.tar.gz >/dev/null && echo 'BACKUP_INTACT'
> gzip -t /root/barmbini-backup-2026-08-11-125648/live-before-deploy.sql && echo 'SQL_OK'
> ```

### 3. Temp-Artefakte prüfen (optional, nach Freigabe)

Folgende Einträge sind **keine Backups**, sondern operative Reste. Nur nach expliziter Freigabe entfernen:

```bash
# Temp-Skript / Importordner – nur löschen, wenn nicht mehr benötigt
rm -f /root/fetch-zip.sh
rm -rf /root/barmbini-import   # leeres/zeitweiliges Import-Verzeichnis
```

> **Hinweis:** `fetch-zip.sh` wird evtl. vom fetch-Workflow erzeugt – erst prüfen, ob ein aktiver Ablauf es braucht. Bei Unsicherheit BEHALTEN.

### 4. Verifikation

```bash
df -h /      # freier Platz – steigt ERST nach Löschung (mv gibt keinen Platz frei)
du -sh /root # bleibt bei 390 MB nach Variante 1 (Verschieben)
```

**Hinweis zum Speicher-Freisetzung:**
- **Variante 1 (verschieben):** Reversibel, aber **kein** Speichergewinn (mv = nur Verzeichniseintrag).
- **Variante 2 (löschen):** Gibt die 176 MB erst frei; erst nach separater Freigabe.

## Abnahmekriterien

- [x] Das ältere Deploy-Backup `...-112119` ist verschoben (2026-08-12, nach `/root/deploy-backups-archiv/`)
- [x] Das aktuellste Deploy-Backup `...-125648` ist intakt (TAR_OK, SQL-Validierung)
- [x] `barmbini-db.txt` ist **unangetastet** (Zugangsdaten)
- [x] Alle **Malware-/Forensik-Backups** sind erhalten
- [ ] `df -h /` zeigt mehr freien Speicher → **offen:** erst nach Löschung des archivierten Backups (nur nach Freigabe)
- [x] Kein aktives Skript ist durch das Aufräumen betroffen

## Deployment

- **Server-Wartung** per SSH, nicht über `deploy.ps1`.
- Kein SQL-Import, keine Datenänderung.
- Die entfernten/verschobenen Einträge werden im Wartungs-Log dokumentiert.

## Rollback

- **Variante 1 (verschieben):** Backup ist unter `/root/deploy-backups-archiv/` jederzeit verfügbar → einfaches `mv` zurück.
- **Variante 2 (löschen):** Nicht wiederherstellbar; deshalb nur nach verifizierter `...-125648`-Integrität.

## Risiken und offene Punkte

- **Offene Frage:** Wird `fetch-zip.sh` von einem aktiven Workflow benötigt? (Bei Unsicherheit BEHALTEN.)
- Die alte `update_persistence_backup_2026-04-22-105221` (48 KB) und `root-crontab-backup` sind Altbestände aus dem Compromise-Cleanup – **nicht automatisch löschen**, da potenziell forensisch relevant; erst nach Sicherheitsfreigabe.
- **Nicht Teil dieser Aufgabe:** (weitere) Disk-Optimierung (`/usr`, apt-cache), Festplatten-Vergrößerung, nginx/FPM-Tuning.

## Dokumentation

- Nach Umsetzung in `Server_Aenderungsdokumentation_*.md` nachtragen (was wurde entfernt/verschoben, Wann, Wie).
- In `Barmbini_Vorbereitung_Features_und_Bugfixes.md` einen Vermerk zum Speicherstand ergänzen.
