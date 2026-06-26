# Detaillierte Aufgabe: Website vom Local-Stand auf dem Server aktualisieren

## Ziel

Die bereits laufende Website Sozialkaufhaus Barmbini auf dem Server `217.160.74.128` soll kontrolliert und reproduzierbar aus dem lokalen WordPress-Stand unter `D:\Local Sites\barmbini` aktualisiert werden.

Die Aufgabe beschreibt den Standardprozess fuer ein inhaltliches oder funktionales Update von lokal nach live, ohne die Server-Basis neu aufzusetzen.

## Geltungsbereich

Diese Aufgabe gilt fuer den aktuell aufgebauten Zielstand:

- Server-IP: `217.160.74.128`
- Live-URL: `http://217.160.74.128`
- Server-Webroot: `/var/www/barmbini`
- Server-Datenbankzugang liegt in: `/root/barmbini-db.txt`
- Server-Webstack: `nginx`, `php8.3-fpm`, `mariadb-server`, `wp-cli`
- Lokaler Quellstand liegt in `D:\Local Sites\barmbini`

## Grundprinzip

Der lokale WordPress-Stand ist die fachliche Quelle. Der Server wird bei einem Update nicht manuell im Live-System gepflegt, sondern aus dem lokalen Stand aktualisiert.

Wichtige Einordnung:

- Der unten beschriebene Vollimport ist nur dann zulaessig, wenn der Live-Stand keine schutzwuerdigen, nur auf dem Server vorhandenen Daten behalten muss.
- Dazu gehoeren insbesondere Kundenkonten, WordPress-Benutzerkonten, Bestellungen, kundenbezogene WooCommerce-Daten, Formularhistorien, Kommentare und andere transaktionale Live-Daten.
- Sobald solche Daten auf dem Live-System relevant sind, darf `local.sql` nicht als Vollimport ueber die Live-Datenbank gespielt werden.

Standardweg fuer Vollabgleich-Updates:

1. lokalen Stand fachlich fertigstellen
2. Server sichern
3. lokalen SQL-Dump und relevanten `wp-content`-Bestand bereitstellen
4. Dateien per `scp -O` auf den Server uebertragen
5. Datenbank und `wp-content` auf dem Server ersetzen
6. URLs und WordPress-Zustand pruefen
7. Live-System validieren

## Kritische Entscheidung vor jedem Update

Vor jedem Update muss zuerst entschieden werden, welcher Modus gilt.

### Modus A: Vollabgleich

Dieser Modus ist nur geeignet, wenn der komplette Live-Stand bewusst durch den lokalen Stand ersetzt werden darf.

Zulaessig zum Beispiel:

- Vor dem echten Produktivstart
- Bei einer rein redaktionellen Seite ohne relevante Live-Benutzerkonten
- Wenn das Live-System keine eigenstaendigen Kunden-, Benutzer- oder Bestelldaten aufgebaut hat

### Modus B: Live-Daten erhalten

Dieser Modus ist Pflicht, sobald Live-Daten auf dem Server nicht verloren gehen duerfen.

Typische Beispiele:

- Kundenkonten in `wp_users`
- Benutzer-Metadaten in `wp_usermeta`
- WooCommerce-Kundendaten in `wp_wc_customer_lookup`
- Bestellungen und transaktionale Inhalte, insbesondere ueber `wp_posts` und `wp_postmeta`
- Sitzungen in `wp_woocommerce_sessions`

In diesem Modus gilt:

- Kein Vollimport von `local.sql` in die Live-Datenbank
- Kein ungeprueftes Ersetzen der gesamten Live-Datenbank durch den lokalen Stand
- Redaktions- und Inhaltsaenderungen muessen selektiv uebernommen werden
- Fuer Datenbank-Merges ist ein separater, tabellen- oder inhaltsbezogener Merge-Prozess noetig

Verbindlicher Verweis:

- Fuer die konkrete Durchfuehrung von Modus B ist das separate Dokument `Barmbini_Aufgabe_Update_Modus_B_Live_Daten_erhalten.md` zu verwenden.

## Wichtige Regel

Manuelle Aenderungen direkt auf dem Server gehen bei diesem Prozess verloren, wenn sie nicht vorher in den lokalen Stand uebernommen wurden.

Das betrifft insbesondere:

- Seiteninhalte
- Medien
- Plugin-Dateien
- Theme-Dateien
- Theme-Einstellungen in der Datenbank
- Menues, Widgets, Formulare und Optionswerte

## Nicht Bestandteil dieses Update-Prozesses

Folgende Dateien und Einstellungen werden im Standardfall nicht lokal erzeugt und deshalb nicht aus dem Local-Stand auf den Server ueberschrieben:

- `/var/www/barmbini/wp-config.php`
- `/etc/nginx/sites-available/barmbini`
- `/etc/php/8.3/fpm/conf.d/99-barmbini.ini`
- `/root/barmbini-db.txt`
- Server-Logs, Journals und Systemkonfiguration

## Voraussetzungen vor jedem Update

- Die lokale Website laeuft fehlerfrei in Local.
- Es existiert ein aktueller SQL-Dump der lokalen Instanz.
- SSH-Zugang zum Server ist vorhanden.
- Fuer das Wartungsfenster ist klar, dass die Live-Seite waehrend des Imports kurz inkonsistent sein kann.
- Alle beabsichtigten inhaltlichen und technischen Aenderungen sind lokal abgeschlossen.

## Quellen und Pfade

### Lokal

- WordPress-Root:
  - `D:\Local Sites\barmbini\app\public`
- Lokaler SQL-Dump:
  - `D:\Local Sites\barmbini\app\sql\local.sql`
- Lokaler `wp-content`:
  - `D:\Local Sites\barmbini\app\public\wp-content`

### Server

- Webroot:
  - `/var/www/barmbini`
- Relevanter Content-Bereich:
  - `/var/www/barmbini/wp-content`
- Temporäres Import-Verzeichnis:
  - `/root/barmbini-import`
- Datenbank-Credential-Datei:
  - `/root/barmbini-db.txt`

## Aufgabe

### 1. Lokalen Stand finalisieren

Ziel: Nur ein bewusst freigegebener lokaler Stand darf live uebernommen werden.

Arbeitsschritte:

1. Local starten und die Site `barmbini` oeffnen.
2. Inhalte, Menues, Medien, Formulare und Theme-Einstellungen lokal pruefen.
3. Caches lokal leeren.
4. Nicht benoetigte Testinhalte entfernen.
5. Falls Plugin- oder Theme-Dateien lokal geaendert wurden: sicherstellen, dass genau dieser Stand deployt werden soll.

Abnahmekriterium:

- Der Local-Stand entspricht dem gewuenschten neuen Live-Stand.

### 2. Lokalen Export vorbereiten

Ziel: Nur die produktiv benoetigten Daten und Dateien werden fuer den Transfer vorbereitet.

Empfohlenes Exportmodell im Vollabgleich-Modus:

- Datenbank: kompletter lokaler SQL-Dump
- Dateien: relevanter `wp-content`-Bestand ohne Altbackups und technische Restdaten

Wichtiger Hinweis:

- Der komplette SQL-Dump ist nur fuer Modus A gedacht.
- In Modus B darf `local.sql` nicht ungeprueft in die Live-Datenbank importiert werden.

Zu uebertragende Unterverzeichnisse und Dateien aus `wp-content`:

- `languages`
- `plugins`
- `themes`
- `uploads`
- `index.php`

Nicht mit uebertragen im Standardfall:

- `ai1wm-backups`
- `cache`
- `upgrade`
- `upgrade-temp-backup`

Beispiel in PowerShell fuer ein Transfer-Archiv:

```powershell
$src = 'D:\Local Sites\barmbini\app\public\wp-content'
$zip = 'D:\Dev\Website\barmbini-wp-content.zip'

if (Test-Path $zip) { Remove-Item $zip -Force }

$items = @('languages','plugins','themes','uploads','index.php') |
  ForEach-Object { Join-Path $src $_ }

Compress-Archive -Path $items -DestinationPath $zip -Force
```

Optionaler Kontrollschritt:

```powershell
Get-Item 'D:\Local Sites\barmbini\app\sql\local.sql' |
  Select-Object FullName,Length,LastWriteTime

Get-Item 'D:\Dev\Website\barmbini-wp-content.zip' |
  Select-Object FullName,Length,LastWriteTime
```

Abnahmekriterium:

- `local.sql` liegt vor.
- Ein aktuelles `barmbini-wp-content.zip` liegt vor.

### 3. Live-System vor dem Update sichern

Ziel: Vor dem Import muss ein Rueckfallstand vorhanden sein.

Arbeitsschritte auf dem Server:

1. SSH-Verbindung aufbauen.
2. Ein temporäres Sicherungsverzeichnis anlegen.
3. Live-Datenbank dumpen.
4. Bestehendes `wp-content` archivieren.

Beispiel:

```bash
mkdir -p /root/barmbini-backup-$(date +%F-%H%M%S)
BACKUP_DIR=$(ls -dt /root/barmbini-backup-* | head -n 1)

DB_NAME=$(awk -F= '/^DB_NAME=/{print $2}' /root/barmbini-db.txt)

mariadb-dump "$DB_NAME" > "$BACKUP_DIR/live-before-update.sql"
tar -czf "$BACKUP_DIR/wp-content-before-update.tar.gz" -C /var/www/barmbini wp-content
```

Abnahmekriterium:

- Datenbank-Backup liegt vor.
- Dateibackup von `wp-content` liegt vor.

### 4. Transferdateien auf den Server uebertragen

Ziel: SQL-Dump und Archiv liegen auf dem Server bereit.

Wichtiger technischer Hinweis:

- Auf diesem Server muss fuer Dateiuebertragungen `scp -O` verwendet werden.
- Der Standard-`scp` ohne `-O` hat in der praktischen Ausfuehrung die Verbindung geschlossen.

Arbeitsschritte lokal in PowerShell:

```powershell
ssh root@217.160.74.128 "mkdir -p /root/barmbini-import"

scp -O "D:\Local Sites\barmbini\app\sql\local.sql" root@217.160.74.128:/root/barmbini-import/
scp -O "D:\Dev\Website\barmbini-wp-content.zip" root@217.160.74.128:/root/barmbini-import/
```

Kontrolle auf dem Server:

```bash
ls -lh /root/barmbini-import
df -h /
```

Abnahmekriterium:

- `local.sql` liegt in `/root/barmbini-import`
- `barmbini-wp-content.zip` liegt in `/root/barmbini-import`

### 5. WordPress auf dem Server in den Wartungszustand bringen

Ziel: Waehren des Imports sollen moeglichst keine parallelen Inhaltsaenderungen stattfinden.

Empfohlene Massnahme:

```bash
cd /var/www/barmbini
su -s /bin/sh -c '/usr/local/bin/wp maintenance-mode activate' www-data
```

Falls WP-CLI Maintenance Mode nicht verfuegbar ist, alternativ ein kurzes Wartungsfenster kommunizieren und den Import direkt ausfuehren.

### 6. Dateibestand auf dem Server aktualisieren

Ziel: Der relevante `wp-content`-Bestand des Servers wird durch den lokalen Stand ersetzt.

Arbeitsschritte:

1. In das Zielverzeichnis wechseln.
2. Nur die zu aktualisierenden Teile entfernen.
3. Archiv entpacken.
4. Rechte neu setzen.

Beispiel:

```bash
cd /var/www/barmbini/wp-content

rm -rf plugins themes uploads languages index.php
unzip -oq /root/barmbini-import/barmbini-wp-content.zip
chown -R www-data:www-data /var/www/barmbini/wp-content
```

Hinweis:

- Dieser Schritt ueberschreibt den produktiven Stand in `plugins`, `themes`, `uploads` und `languages` bewusst mit dem lokalen Stand.

Abnahmekriterium:

- Die neuen Verzeichnisse liegen unter `/var/www/barmbini/wp-content`
- Berechtigungen gehoeren `www-data`

### 7. Datenbank nur im Vollabgleich aktualisieren

Ziel: Die Live-Datenbank wird mit dem lokalen fachlichen Stand ersetzt.

Wichtige Klarstellung:

- Dieser Update-Prozess ist nicht nur ein Dateiupload.
- `local.sql` ist der Datenbank-Teil des Deployments.
- Der Import aktualisiert die Live-Datenbank aktiv mit dem lokalen Stand.
- Der vorliegende Dump ist ein vollwertiger MariaDB-Dump und enthaelt nicht nur `INSERT`, sondern auch `DROP TABLE IF EXISTS` und `CREATE TABLE`.
- Dadurch werden die vorhandenen WordPress-Tabellen im Zielschema beim Import durch den lokalen Stand ersetzt bzw. neu aufgebaut.
- Änderungen, die nur direkt auf dem Server in der Datenbank gemacht wurden und nicht lokal vorliegen, gehen bei diesem Schritt verloren.

Fuer den vorliegenden lokalen Dump ist dieses Risiko konkret bestaetigt. Der Dump enthaelt unter anderem `DROP TABLE IF EXISTS` fuer:

- `wp_usermeta`
- `wp_users`
- `wp_wc_customer_lookup`
- `wp_woocommerce_sessions`
- `wp_posts`

Das bedeutet:

- Ein Vollimport wuerde bestehende Live-Benutzerkonten ueberschreiben.
- Ein Vollimport wuerde bestehende WooCommerce-Kundeninformationen ueberschreiben.
- Ein Vollimport kann auch Bestell- und transaktionale Inhalte treffen, wenn diese im Live-System genutzt werden.

Sperrregel:

- Wenn Kundenkonten, Benutzerkonten, Bestellungen oder andere nur live vorhandene Daten erhalten bleiben muessen, darf dieser Schritt nicht ausgefuehrt werden.

Arbeitsschritte:

1. Datenbanknamen aus `/root/barmbini-db.txt` lesen.
2. SQL-Dump importieren.

Beispiel:

```bash
DB_NAME=$(awk -F= '/^DB_NAME=/{print $2}' /root/barmbini-db.txt)
mariadb "$DB_NAME" < /root/barmbini-import/local.sql
```

Praktische Bedeutung des Befehls:

- Es wird nicht nur ein paar Optionen aktualisiert.
- Es wird der lokale Datenbankzustand in das Live-Schema `barmbini_wp` eingespielt.
- Seiten, Beitraege, Menues, Formulare, Theme-Optionen, Plugin-Optionen, Widgets und Medienreferenzen kommen dabei aus dem lokalen Dump.

Abnahmekriterium:

- SQL-Import lief ohne Fehler durch.
- Die Live-Datenbank spiegelt danach den lokalen Stand wider.

### 7b. Wenn Live-Konten und Live-Daten erhalten bleiben muessen

Ziel: Dateien und technische Aenderungen aus lokal uebernehmen, ohne die produktive Live-Datenbank vollstaendig zu ersetzen.

Wichtiger Verweis:

- Dieser Abschnitt ist nur die Kurzfassung.
- Die operative Durchfuehrung erfolgt nach `Barmbini_Aufgabe_Update_Modus_B_Live_Daten_erhalten.md`.

Regeln:

1. Den Befehl `mariadb "$DB_NAME" < /root/barmbini-import/local.sql` nicht ausfuehren.
2. Datei-Updates in `wp-content` koennen weiterhin erfolgen, wenn sie fachlich freigegeben sind.
3. Inhaltsaenderungen muessen selektiv uebernommen werden.
4. Fuer Datenbankaenderungen ist ein separater Merge-Prozess erforderlich.

Pragmatische sichere Varianten fuer Modus B:

- Nur Datei- und Code-Updates deployen, aber keine Voll-Datenbankmigration durchfuehren.
- Redaktionsaenderungen direkt im Live-Backend nachpflegen.
- Falls Inhalte aus lokal zwingend uebernommen werden muessen: zuerst in eine temporäre Vergleichsdatenbank importieren und dann gezielt mergen, statt die Live-Datenbank voll zu ersetzen.

Abnahmekriterium:

- Live-Konten und Live-Transaktionsdaten bleiben erhalten.
- Es wurde kein unkontrollierter Vollimport ueber die Live-Datenbank ausgefuehrt.

### 8. URLs auf den Live-Stand korrigieren

Ziel: Nach dem Datenbankimport duerfen keine `barmbini.local`-Verweise mehr aktiv bleiben.

Arbeitsschritte:

```bash
cd /var/www/barmbini

su -s /bin/sh -c '/usr/local/bin/wp search-replace "http://barmbini.local" "http://217.160.74.128" --all-tables --skip-columns=guid --precise' www-data
su -s /bin/sh -c '/usr/local/bin/wp search-replace "https://barmbini.local" "http://217.160.74.128" --all-tables --skip-columns=guid --precise || true' www-data
su -s /bin/sh -c '/usr/local/bin/wp rewrite flush' www-data
su -s /bin/sh -c '/usr/local/bin/wp cache flush || true' www-data
```

Abnahmekriterium:

- `home` und `siteurl` zeigen auf `http://217.160.74.128`
- interne Verweise zeigen nicht mehr auf `barmbini.local`

### 9. Live-System validieren

Ziel: Nach dem Update muss technisch und fachlich geprueft werden, ob der neue Stand korrekt live ist.

Serverseitige Pruefung:

```bash
cd /var/www/barmbini

su -s /bin/sh -c '/usr/local/bin/wp option get home' www-data
su -s /bin/sh -c '/usr/local/bin/wp option get siteurl' www-data
su -s /bin/sh -c '/usr/local/bin/wp plugin list --status=active --fields=name,status,version' www-data
su -s /bin/sh -c '/usr/local/bin/wp theme list --fields=name,status,version' www-data

curl -I -H 'Host: 217.160.74.128' http://127.0.0.1/
curl -I -H 'Host: 217.160.74.128' http://127.0.0.1/wp-admin/
```

Externe Pruefung von lokal in PowerShell:

```powershell
try {
  $response = Invoke-WebRequest -Uri 'http://217.160.74.128/' -UseBasicParsing -TimeoutSec 20
  [pscustomobject]@{
    StatusCode = $response.StatusCode
    Title      = ([regex]::Match($response.Content, '<title>(.*?)</title>', 'IgnoreCase')).Groups[1].Value
  } | Format-List
}
catch {
  $_ | Out-String
}
```

Pflichtkontrollen im Browser:

1. Startseite
2. Impressum
3. Datenschutz
4. Kontaktseite
5. Medien und Bilder
6. WordPress-Admin-Login
7. Kontaktformular

Abnahmekriterium:

- Die Startseite liefert `200 OK`
- `/wp-admin/` leitet sinnvoll zur Anmeldung oder ins Backend
- Inhalte, Bilder und Menues entsprechen dem lokalen Stand

### 10. Wartungsmodus beenden und Importreste entfernen

Ziel: Das Live-System wird sauber hinterlassen.

Arbeitsschritte:

```bash
cd /var/www/barmbini
su -s /bin/sh -c '/usr/local/bin/wp maintenance-mode deactivate || true' www-data

rm -f /root/barmbini-import/local.sql
rm -f /root/barmbini-import/barmbini-wp-content.zip
```

Abnahmekriterium:

- Keine Importdateien mehr im temporären Importordner
- Live-Site wieder regulär erreichbar

## Rollback

Wenn das Update fehlschlaegt, wird auf den vorherigen Stand zurueckgesetzt.

Beispiel:

```bash
BACKUP_DIR=/root/barmbini-backup-YYYY-MM-DD-HHMMSS
DB_NAME=$(awk -F= '/^DB_NAME=/{print $2}' /root/barmbini-db.txt)

rm -rf /var/www/barmbini/wp-content
mkdir -p /var/www/barmbini
tar -xzf "$BACKUP_DIR/wp-content-before-update.tar.gz" -C /var/www/barmbini
chown -R www-data:www-data /var/www/barmbini/wp-content

mariadb "$DB_NAME" < "$BACKUP_DIR/live-before-update.sql"
```

Danach erneut pruefen:

- Startseite
- Login
- Menues
- Bilder
- Formulare

## Definition of Done

Die Aufgabe ist abgeschlossen, wenn alle folgenden Punkte erfuellt sind:

1. Der neue lokale Stand ist auf dem Server sichtbar.
2. `home` und `siteurl` stehen auf dem Live-Ziel, sofern Modus A verwendet wurde.
3. Keine produktiven Inhalte verweisen mehr auf `barmbini.local`.
4. Die wichtigsten Seiten und Medien funktionieren im Browser.
5. Das Backend ist erreichbar.
6. Importreste wurden geloescht.
7. Ein Rueckfallbackup wurde vor dem Update erstellt.
8. Falls Live-Konten oder Live-Transaktionsdaten erhalten werden mussten, wurde kein Vollimport der Live-Datenbank ausgefuehrt.

## Empfohlene Zusatzregel fuer kuenftige Updates

Vor jedem Live-Update sollte in einer kurzen Freigabe festgehalten werden:

- welcher lokale Stand eingespielt wird
- welche Inhalte oder Funktionen sich aendern
- ob Plugins oder Themes neu hinzugekommen sind
- wer das Update fachlich freigegeben hat
- wann das Rollback-Fenster endet
