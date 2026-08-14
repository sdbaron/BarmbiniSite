# Detaillierte Aufgabe: Update von local auf den Server im Modus B

## Ziel

Die Website Sozialkaufhaus Barmbini auf dem Server `217.160.74.128` soll aus dem lokalen WordPress-Stand aktualisiert werden, ohne produktive Live-Daten unkontrolliert zu ueberschreiben.

Dieser Ablauf ist fuer den Fall gedacht, dass auf dem Live-System bereits relevante Daten existieren, die erhalten bleiben muessen.

Dazu gehoeren insbesondere:

- WordPress-Benutzerkonten
- Benutzer-Metadaten
- Kundenkonten
- Bestellungen
- Sitzungen
- andere transaktionale oder nur live entstandene Daten

## Wann dieser Ablauf verwendet werden muss

Dieser Ablauf ist Pflicht, sobald mindestens einer der folgenden Punkte zutrifft:

- auf dem Live-System gibt es Benutzerkonten, die nicht aus lokal ueberschrieben werden duerfen
- auf dem Live-System gibt es WooCommerce-Kunden oder Bestellungen
- auf dem Live-System wurden Inhalte oder Einstellungen gepflegt, die nicht komplett durch lokal ersetzt werden duerfen
- ein voller Import von `local.sql` wuerde fachlich wertvolle Live-Daten loeschen oder zuruecksetzen

## Grundprinzip

Im Modus B wird nicht die gesamte lokale Datenbank in die Live-Datenbank importiert.

Stattdessen gilt:

1. Code-, Plugin-, Theme- und Sprachdateien duerfen kontrolliert aktualisiert werden.
2. Medien duerfen nur selektiv und ohne Loeschung des gesamten Live-Ordners `uploads` uebernommen werden.
3. Inhalte werden gezielt uebernommen, nicht per blindem SQL-Vollimport.
4. Live-Benutzer-, Kunden- und Bestelldaten bleiben unangetastet.

## Strikte Verbote im Modus B

Die folgenden Schritte sind im Modus B nicht zulaessig:

- `mariadb "$DB_NAME" < /root/barmbini-import/local.sql`
- jeder andere Vollimport eines kompletten lokalen SQL-Dumps in die Live-Datenbank
- `DROP TABLE` oder `CREATE TABLE` gegen produktive WordPress-Tabellen ohne freigegebenen Migrationsplan
- blindes Loeschen des gesamten Live-Ordners `wp-content/uploads`

Besonders schuetzenswert sind unter anderem:

- `wp_users`
- `wp_usermeta`
- `wp_wc_customer_lookup`
- `wp_woocommerce_sessions`
- Bestellungen und bestellungsbezogene Inhalte in `wp_posts` und `wp_postmeta`
- Action-Scheduler- und WooCommerce-Laufzeitdaten, soweit sie nur produktiv entstanden sind

## Quellen und Pfade

### Lokal

- WordPress-Root: `D:\Local Sites\barmbini\app\public`
- lokaler SQL-Dump: `D:\Local Sites\barmbini\app\sql\local.sql`
- lokaler Content-Ordner: `D:\Local Sites\barmbini\app\public\wp-content`

### Server

- Webroot: `/var/www/barmbini`
- Content-Ordner: `/var/www/barmbini/wp-content`
- temporärer Importordner: `/root/barmbini-import`
- DB-Credentials: `/root/barmbini-db.txt`

## Aufgabe

### 1. Aenderungsumfang vor dem Update festlegen

Ziel: Vor dem Deployment muss eindeutig feststehen, was uebernommen werden darf und was live erhalten bleiben muss.

Vor dem Start schriftlich festhalten:

- welche Code- oder Dateiaenderungen aus lokal uebernommen werden sollen
- ob neue Medien uebernommen werden sollen
- welche redaktionellen Inhalte uebernommen werden sollen
- welche Live-Daten in jedem Fall erhalten bleiben muessen

Abnahmekriterium:

- Es gibt eine klare Freigabe fuer Dateien, Medien und Inhalte.
- Es ist explizit festgehalten, dass kein SQL-Vollimport erfolgt.

### 2. Live-System vor dem Update sichern

Ziel: Auch im Modus B muss ein Rueckfallstand vorliegen.

Arbeitsschritte auf dem Server:

```bash
mkdir -p /root/barmbini-backup-$(date +%F-%H%M%S)
BACKUP_DIR=$(ls -dt /root/barmbini-backup-* | head -n 1)

DB_NAME=$(awk -F= '/^DB_NAME=/{print $2}' /root/barmbini-db.txt)

mariadb-dump "$DB_NAME" > "$BACKUP_DIR/live-before-mode-b.sql"
tar -czf "$BACKUP_DIR/wp-content-before-mode-b.tar.gz" -C /var/www/barmbini wp-content
```

Abnahmekriterium:

- Live-Datenbank gesichert
- aktueller `wp-content`-Stand gesichert

### 3. Code-Archiv ohne riskante Massenueberschreibung vorbereiten

Ziel: Fuer Modus B wird ein separates Dateipaket gebaut, das keine blind zu ersetzenden Live-Mediendaten enthaelt.

Empfohlener Inhalt des Code-Archivs:

- `languages`
- `plugins`
- `themes`
- `index.php`

Standardmaessig nicht im Code-Archiv enthalten:

- `uploads`
- `ai1wm-backups`
- `cache`
- `upgrade`
- `upgrade-temp-backup`

Beispiel in PowerShell:

```powershell
$src = 'D:\Local Sites\barmbini\app\public\wp-content'
$zip = 'D:\Dev\Website\barmbini-wp-content-code-only.zip'

if (Test-Path $zip) { Remove-Item $zip -Force }

$items = @('languages','plugins','themes','index.php') |
  ForEach-Object { Join-Path $src $_ }

Compress-Archive -Path $items -DestinationPath $zip -Force
```

Abnahmekriterium:

- Ein aktuelles `barmbini-wp-content-code-only.zip` liegt vor.
- `uploads` ist nicht Teil dieses Archivs.

### 4. Neue Medien nur selektiv vorbereiten

Ziel: Neue oder geaenderte Medien werden gezielt uebernommen, ohne den kompletten Live-Ordner `uploads` zu ersetzen.

Vorgehen:

1. Nur die konkret freigegebenen neuen Medien identifizieren.
2. Diese Dateien in eine separate Transferstruktur legen.
3. Keine pauschale Komplettkopie des gesamten lokalen `uploads`-Ordners erstellen.

Beispiel in PowerShell fuer ausgewaehlte Medien:

```powershell
$src = 'D:\Local Sites\barmbini\app\public\wp-content'
$zip = 'D:\Dev\Website\barmbini-media-selected.zip'

if (Test-Path $zip) { Remove-Item $zip -Force }

$items = @(
  'uploads\2026\04\hero-startseite.jpg',
  'uploads\2026\04\team-barmbini.jpg'
) | ForEach-Object { Join-Path $src $_ }

Compress-Archive -Path $items -DestinationPath $zip -Force
```

Wenn keine neuen Medien uebernommen werden muessen, wird dieser Schritt ausgelassen.

Abnahmekriterium:

- Es gibt entweder ein bewusst freigegebenes Medienarchiv oder die Entscheidung, keine Medien zu uebernehmen.

### 5. Redaktionelle Inhalte gezielt exportieren

Ziel: Inhalte werden selektiv uebernommen, nicht per SQL-Vollimport.

Empfohlene sichere Wege:

1. Seiten und Beitraege ueber `Werkzeuge -> Daten exportieren` in WordPress lokal exportieren.
2. Produkte und Produktkategorien nur dann separat exportieren, wenn ihre Uebernahme fachlich freigegeben ist.
3. Theme- und Plugin-Einstellungen nicht per SQL-Vollimport ueberschreiben, sondern gezielt vergleichen und nachpflegen.

Empfehlung fuer den Export:

- eigene Exportdatei fuer Seiten
- eigene Exportdatei fuer Beitraege
- eigene Exportdatei fuer Produkte, falls erforderlich

Abnahmekriterium:

- Es existieren nur die wirklich benoetigten Inhalts-Exporte.
- Kein Voll-Dump der kompletten lokalen Datenbank ist als Importgrundlage vorgesehen.

### 6. Transferdateien auf den Server uebertragen

Ziel: Die fuer Modus B freigegebenen Artefakte liegen auf dem Server bereit.

Wichtiger technischer Hinweis:

- Auf diesem Server muss fuer Dateiuebertragungen `scp -O` verwendet werden.

Beispiel lokal in PowerShell:

```powershell
ssh root@217.160.74.128 "mkdir -p /root/barmbini-import"

scp -O "D:\Dev\Website\barmbini-wp-content-code-only.zip" root@217.160.74.128:/root/barmbini-import/
scp -O "D:\Dev\Website\barmbini-media-selected.zip" root@217.160.74.128:/root/barmbini-import/
```

Die zweite `scp`-Zeile wird nur verwendet, wenn wirklich ein Medienarchiv existiert.

Abnahmekriterium:

- Code-Archiv liegt auf dem Server
- optionales Medienarchiv liegt auf dem Server

### 7. WordPress in den Wartungsmodus versetzen

Ziel: Waehren des Datei-Deployments sollen moeglichst keine parallelen Aenderungen stattfinden.

```bash
cd /var/www/barmbini
su -s /bin/sh -c '/usr/local/bin/wp maintenance-mode activate' www-data
```

Abnahmekriterium:

- Wartungsmodus aktiv

### 8. Code und Layout deployen, aber `uploads` nicht loeschen

Ziel: Plugins, Themes und Sprachdateien werden aktualisiert, ohne den Live-Medienbestand blind zu ersetzen.

Arbeitsschritte:

```bash
cd /var/www/barmbini/wp-content

rm -rf plugins themes languages index.php
unzip -oq /root/barmbini-import/barmbini-wp-content-code-only.zip
chown -R www-data:www-data /var/www/barmbini/wp-content
```

Wichtig:

- `uploads` wird in diesem Schritt nicht geloescht.
- Es findet kein SQL-Import statt.

Abnahmekriterium:

- Plugin-, Theme- und Sprachdateien entsprechen dem freigegebenen lokalen Stand.
- Live-Medien in `uploads` bleiben erhalten.

### 9. Neue Medien kontrolliert nachziehen

Ziel: Nur freigegebene neue Dateien werden in `uploads` eingefuegt.

Beispiel auf dem Server:

```bash
mkdir -p /root/barmbini-import/media-selected
unzip -oq /root/barmbini-import/barmbini-media-selected.zip -d /root/barmbini-import/media-selected
cp -a /root/barmbini-import/media-selected/uploads/. /var/www/barmbini/wp-content/uploads/
chown -R www-data:www-data /var/www/barmbini/wp-content/uploads
```

Wichtig:

- Kein `rm -rf /var/www/barmbini/wp-content/uploads`
- Keine pauschale Komplettkopie aller lokalen Uploads

Abnahmekriterium:

- Nur freigegebene neue Medien sind hinzugekommen.
- Vorhandene Live-Medien bleiben bestehen.

### 10. Inhalte gezielt auf Live uebernehmen

Ziel: Redaktionsinhalte werden selektiv uebernommen.

Sichere Wege:

1. Import einzelner WordPress-Exportdateien im Live-Backend
2. manuelle Nachpflege im Live-Backend bei kleinen Aenderungen
3. gezielter Produktimport nur fuer fachlich freigegebene Kataloginhalte

Nicht zulaessig:

- kompletter SQL-Import aus lokal
- direktes Ueberschreiben der produktiven Datenbank mit lokalen Tabellen

Empfehlung fuer Theme- und Plugin-Einstellungen:

- lokale und Live-Einstellungen parallel vergleichen
- Aenderungen gezielt im Live-Backend nachtragen
- nur pluginspezifische Export-/Import-Funktionen verwenden, wenn sie die Live-Daten nicht global ueberschreiben

Abnahmekriterium:

- nur die freigegebenen Inhalte wurden uebernommen
- Live-Benutzer- und Transaktionsdaten wurden nicht ersetzt

### 11. Geschuetzte Live-Daten vor und nach dem Update pruefen

Ziel: Sicherstellen, dass Modus B die kritischen Live-Daten nicht veraendert hat.

Pruefbefehle auf dem Server:

```bash
DB_NAME=$(awk -F= '/^DB_NAME=/{print $2}' /root/barmbini-db.txt)

mariadb -N -D "$DB_NAME" -e "SELECT 'wp_users', COUNT(*) FROM wp_users;"
mariadb -N -D "$DB_NAME" -e "SELECT 'shop_orders', COUNT(*) FROM wp_posts WHERE post_type='shop_order';"
mariadb -N -D "$DB_NAME" -e "SELECT 'shop_order_refunds', COUNT(*) FROM wp_posts WHERE post_type='shop_order_refund';"
```

Wenn vorhanden, zusaetzlich pruefen:

```bash
mariadb -N -D "$DB_NAME" -e "SELECT 'wc_customer_lookup', COUNT(*) FROM wp_wc_customer_lookup;"
```

Abnahmekriterium:

- Anzahl und Integritaet der geschuetzten Live-Daten passen zur Erwartung.
- Es gibt keinen Hinweis auf einen Vollimport oder Tabellenaustausch.

### 12. Technische Live-Pruefung durchfuehren

Ziel: Der technische Stand muss nach dem Modus-B-Update funktionsfaehig sein.

Serverseitige Pruefung:

```bash
cd /var/www/barmbini

su -s /bin/sh -c '/usr/local/bin/wp option get home' www-data
su -s /bin/sh -c '/usr/local/bin/wp option get siteurl' www-data
su -s /bin/sh -c '/usr/local/bin/wp plugin list --status=active --field=name' www-data
su -s /bin/sh -c '/usr/local/bin/wp theme list --status=active --field=name' www-data

curl -I -H 'Host: 217.160.74.128' http://127.0.0.1/
curl -I -H 'Host: 217.160.74.128' http://127.0.0.1/wp-admin/
```

Externe Pruefung lokal in PowerShell:

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
4. Medien und Bilder
5. Kontaktformular
6. Login ins WordPress-Backend

Abnahmekriterium:

- Die Live-Seite ist erreichbar.
- Backend-Login funktioniert.
- Die geschuetzten Live-Daten sind weiterhin vorhanden.

### 13. Wartungsmodus beenden und Importreste loeschen

```bash
cd /var/www/barmbini
su -s /bin/sh -c '/usr/local/bin/wp maintenance-mode deactivate || true' www-data

rm -f /root/barmbini-import/barmbini-wp-content-code-only.zip
rm -f /root/barmbini-import/barmbini-media-selected.zip
rm -rf /root/barmbini-import/media-selected
```

Abnahmekriterium:

- Wartungsmodus beendet
- keine unnoetigen Importreste mehr vorhanden

## Rollback

Wenn das Modus-B-Update fehlschlaegt, wird der vorherige Stand zurueckgespielt.

Beispiel:

```bash
BACKUP_DIR=/root/barmbini-backup-YYYY-MM-DD-HHMMSS
DB_NAME=$(awk -F= '/^DB_NAME=/{print $2}' /root/barmbini-db.txt)

rm -rf /var/www/barmbini/wp-content
mkdir -p /var/www/barmbini
tar -xzf "$BACKUP_DIR/wp-content-before-mode-b.tar.gz" -C /var/www/barmbini
chown -R www-data:www-data /var/www/barmbini/wp-content

mariadb "$DB_NAME" < "$BACKUP_DIR/live-before-mode-b.sql"
```

Danach erneut pruefen:

- Startseite
- Login
- Medien
- Formulare
- Benutzerkonten
- Bestellungen, falls vorhanden

## Definition of Done

Die Aufgabe ist abgeschlossen, wenn alle folgenden Punkte erfuellt sind:

1. Die freigegebenen Code- und Dateiaenderungen aus lokal sind live sichtbar.
2. `uploads` wurde nicht pauschal geloescht oder komplett ersetzt.
3. Es wurde kein Vollimport von `local.sql` in die Live-Datenbank ausgefuehrt.
4. Geschuetzte Live-Daten wie Benutzer, Kunden und Bestellungen sind weiterhin vorhanden.
5. Die wichtigsten Seiten, Medien und das Backend funktionieren nach dem Update.
6. Ein Rueckfallbackup wurde vor dem Update erstellt.
