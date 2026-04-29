# Barmbini Migrationsdurchfuehrung 2026-04-22

## Ziel

Migration der lokalen WordPress-Instanz `barmbini` auf den Server `217.160.74.128` als IP-basierte Bereitstellung unter `http://217.160.74.128`.

## Ausgangslage vor der Migration

- Der Server war zuvor mit einer alten `loyacrm`-Umgebung belegt.
- Port `80` war durch Docker belegt.
- PM2 und Node.js waren aktiv.
- Die Webroots `/var/www/loyacrm` und `/var/www/loyacrm-production` waren noch vorhanden.
- Root-Dateisystem war stark belegt und musste vor dem WordPress-Setup bereinigt werden.

## Durchgefuehrte Server-Bereinigung

- Docker-Container der Altumgebung gestoppt und entfernt.
- `docker.service` und `docker.socket` gestoppt und deaktiviert.
- Docker-Pakete sowie `nodejs` entfernt.
- `pm2-root.service` gestoppt und deaktiviert.
- PM2-Prozesse entfernt und PM2-Laufzeitdaten geloescht.
- Alte Webroots `/var/www/loyacrm` und `/var/www/loyacrm-production` entfernt.

## Installierter Ziel-Stack

- `nginx`
- `mariadb-server`
- `php8.3-fpm`
- PHP-Erweiterungen fuer WordPress: `php-mysql`, `php-curl`, `php-xml`, `php-mbstring`, `php-zip`, `php-gd`, `php-intl`, `php-imagick`, `php-opcache`
- `wp-cli`

## Zielkonfiguration auf dem Server

- Webroot: `/var/www/barmbini`
- Nginx-Site: `/etc/nginx/sites-available/barmbini`
- PHP-FPM Upload-Limits: `/etc/php/8.3/fpm/conf.d/99-barmbini.ini`
- Datenbankname: `barmbini_wp`
- Datenbankbenutzer: `barmbini_user`
- Datenbank-Zugangsdaten liegen auf dem Server in: `/root/barmbini-db.txt`
- WordPress-Core-Version: `6.9.4`
- `WP_HOME` und `WP_SITEURL` gesetzt auf `http://217.160.74.128`

## Importierte Quelldaten

- SQL-Dump aus der lokalen Instanz: `local.sql`
- Migrierter `wp-content`-Bestand:
  - `languages`
  - `plugins`
  - `themes`
  - `uploads`
  - `index.php`

Nicht uebertragen wurden lokale Altartefakte wie `ai1wm-backups`, `cache`, `upgrade` und `upgrade-temp-backup`.

## Durchgefuehrter WordPress-Import

- WordPress-Core in das Ziel-Webroot geladen.
- `wp-config.php` fuer MariaDB und IP-basierte Bereitstellung erstellt.
- `wp-content` aus dem lokalen Quellstand auf den Server uebernommen.
- Datenbank aus `local.sql` in `barmbini_wp` importiert.
- URL-Umschreibung mit `wp search-replace` ausgefuehrt:
  - von `http://barmbini.local`
  - nach `http://217.160.74.128`
- Temporäre Importdateien auf dem Server nach dem Import entfernt.

## Validierter Zielzustand

- `nginx`, `mariadb` und `php8.3-fpm` laufen.
- `http://217.160.74.128/` antwortet mit `HTTP/1.1 200 OK`.
- `http://217.160.74.128/wp-admin/` leitet korrekt auf `wp-login.php` um.
- Startseite liefert den erwarteten Seitentitel:
  - `Sozialkaufhaus Barmbini - Sozialkaufhaus Barmbini`
- WordPress-Optionen `home` und `siteurl` stehen auf `http://217.160.74.128`.
- Aktives Theme:
  - `kadence 1.4.5`
- Aktive Plugins:
  - `all-in-one-wp-migration 7.102`
  - `contact-form-7 6.1.5`
  - `simple-local-avatars 2.8.6`
  - `kadence-starter-templates 2.2.14`
  - `wp-fastest-cache 1.4.6`
  - `wordpress-seo 27.1.1`

## Nachtraegliche Validierung und Speicherbereinigung

- Ein echter WordPress-Admin-Login wurde erfolgreich getestet.
- Fuer den Test wurde temporaer ein Administrator-Benutzer angelegt.
- Der Login auf `/wp-login.php` und der Zugriff auf `/wp-admin/` wurden von der lokalen Arbeitsmaschine erfolgreich verifiziert.
- Der temporaere Test-Benutzer wurde anschliessend wieder entfernt.

Durchgefuehrte Speicherbereinigung:

- `apt-get clean`
- Paketlisten unter `/var/lib/apt/lists/*` entfernt
- Systemd-Journal auf `200M` reduziert
- Inaktive Standard-Themes entfernt:
  - `storefront`
  - `twentytwentyfive`
  - `twentytwentyfour`
  - `twentytwentythree`
  - `twentytwentytwo`

Ergebnis der Speicherbereinigung:

- Freier Speicher auf `/` von ca. `693M` auf ca. `2.0G` erhoeht
- Paketcache reduziert auf rund `24K`
- Paketlisten reduziert auf rund `12K`
- Journalspeicher reduziert auf rund `188M`

## Dokumentierter Folge-Update-Lauf 2026-04-23

Am `2026-04-23` wurde ein weiterer Update-Lauf vom lokalen Stand auf den Live-Server ausgefuehrt.

### Entscheidungsgrundlage vor dem Import

Vor dem Lauf wurde geprueft, ob ein Vollabgleich fachlich vertretbar ist.

Festgestellter Live-Stand vor dem Import:

- `wp_users`: `2`
- vorhandene Benutzerkonten: `barmbini`, `Redaktuer`
- `shop_order`: `0`
- `shop_order_refund`: `0`
- Tabelle `wp_wc_customer_lookup` war auf dem Live-System nicht vorhanden

Da vor dem Lauf keine Bestellungen und keine abweichenden Live-Benutzerkonten festgestellt wurden, wurde der Import als Vollabgleich durchgefuehrt.

### Durchgefuehrte Schritte im Folge-Update

- aktuelles Backup erstellt unter `/root/barmbini-backup-2026-04-23-065957`
- aktuelle lokale Datei `local.sql` erneut auf den Server uebertragen
- aktuelles Archiv `barmbini-wp-content.zip` erneut auf den Server uebertragen
- WordPress-Wartungsmodus aktiviert
- `wp-content` auf dem Server aus dem aktuellen lokalen Archiv ersetzt
- Datenbank `barmbini_wp` erneut aus `local.sql` importiert
- URL-Umschreibung von `barmbini.local` auf `http://217.160.74.128` erneut ausgefuehrt
- Rewrite-Regeln neu geschrieben und WordPress-Cache geleert
- Wartungsmodus deaktiviert
- temporaere Importdateien unter `/root/barmbini-import` entfernt

### Validiertes Ergebnis des Folge-Updates

- Startseite extern mit `200 OK` erreichbar
- Seitentitel extern bestaetigt als `Sozialkaufhaus Barmbini - Sozialkaufhaus Barmbini`
- im ausgelieferten HTML keine aktiven Verweise mehr auf `barmbini.local`
- `/wp-admin/` antwortet mit `302` auf `wp-login.php`
- `home` und `siteurl` stehen weiterhin auf `http://217.160.74.128`
- WordPress-Core bleibt installiert und lauffaehig
- aktive Plugins nach dem Folge-Update:
  - `all-in-one-wp-migration`
  - `contact-form-7`
  - `hide-cart-functions`
  - `kadence-blocks`
  - `simple-local-avatars`
  - `kadence-starter-templates`
  - `woocommerce`
  - `wp-fastest-cache`
  - `wordpress-seo`
- aktives Theme nach dem Folge-Update:
  - `kadence`

### Durchgefuehrter Funktionstest nach dem Folge-Update

- temporaer ein Administrator-Benutzer `copilot-check-admin` angelegt
- echter Login von der lokalen Arbeitsmaschine auf `/wp-login.php` erfolgreich getestet
- Zugriff auf `/wp-admin/` nach erfolgreichem Login mit `200 OK` bestaetigt
- Dashboard-Inhalt wurde nach dem Login erfolgreich geladen
- temporaerer Test-Benutzer anschliessend wieder geloescht
- finale Benutzerliste nach dem Test:
  - `barmbini` als `administrator`
  - `Redaktuer` als `editor`

### Operative Besonderheit des Folge-Updates

- Interaktive `ssh`- und kombinierte `scp`-Aufrufe waren in der lokalen Arbeitsumgebung instabil.
- Fuer die tatsaechliche Remote-Ausfuehrung des Folge-Updates wurde deshalb lokal `paramiko` verwendet.
- Die fachlichen und technischen Server-Schritte entsprachen weiterhin dem dokumentierten Update-Ablauf.

## Dokumentierter Modus-B-Plugin-Deploy 2026-04-28

Am `2026-04-28` wurde ein weiterer Live-Update-Lauf ausgefuehrt, diesmal bewusst als Modus-B-Deployment ohne SQL-Vollimport.

### Entscheidungsgrundlage vor dem Lauf

Vor dem Deploy wurden die minimalen Live-Indikatoren erneut direkt auf dem Server geprueft:

- `wp_users`: `2`
- `shop_order`: `0`
- freier Speicher auf `/`: rund `1.3G`
- `wp-content`-Groesse: rund `305M`
- Plugin-Verzeichnis `wp-content/plugins/barmbini-core` war auf dem Live-System noch nicht vorhanden

Die Entscheidung fiel trotzdem auf Modus B, weil bereits produktive Live-Benutzerkonten vorhanden waren und fuer den neuen Stand kein Datenbank-Vollabgleich erforderlich war.

### Durchgefuehrte Schritte im Modus-B-Lauf

- aktuelles Backup erstellt unter `/root/barmbini-backup-2026-04-28-140650-barmbini-core`
- Live-Datenbank gesichert als `live-before-barmbini-core.sql`
- aktueller `wp-content`-Stand gesichert als `wp-content-before-barmbini-core.tar.gz`
- minimales Plugin-Artefakt `barmbini-core-plugin.zip` lokal gebaut und auf den Server uebertragen
- WordPress-Wartungsmodus aktiviert
- Plugin `barmbini-core` unter `/var/www/barmbini/wp-content/plugins/` neu abgelegt
- Eigentumsrechte auf `www-data:www-data` gesetzt
- Plugin `barmbini-core` per WP-CLI aktiviert
- WordPress-Wartungsmodus wieder deaktiviert
- temporaere Transferdateien unter `/root/` und `/root/barmbini-import/` wieder entfernt

Wichtig:

- Es wurde kein Import von `local.sql` ausgefuehrt.
- Es wurde kein bestehender Live-Ordner `uploads` ersetzt oder geloescht.
- Es wurden nur die fuer das neue Plugin benoetigten Code-Dateien uebernommen.

### Validiertes Ergebnis des Modus-B-Laufs

- Plugin `barmbini-core` ist auf Live aktiv
- die Tabellen `wp_barmbini_notification_log` und `wp_barmbini_notification_queue` wurden auf Live angelegt
- der berechnete Endpoint lautet `http://217.160.74.128/mein-konto/abonnements/`
- `http://217.160.74.128/` antwortet weiter mit `HTTP/1.1 200 OK`
- `http://217.160.74.128/mein-konto/` antwortet weiter mit `HTTP/1.1 200 OK`
- nach dem Cleanup standen rund `1.1G` freier Speicher auf `/` zur Verfuegung

## Festgestellte Abweichung zur frueheren Inventarliste

Eine fruehere Arbeitsnotiz nannte `Polylang` als aktives Plugin. Der aktuelle lokale Quellstand enthaelt jedoch kein Plugin-Verzeichnis `polylang`. Der auf den Server migrierte Plugin-Bestand entspricht dem tatsaechlich vorhandenen lokalen `wp-content/plugins`.

## Restrisiken und Hinweise

- Die Bereitstellung erfolgt ausdruecklich nur ueber IP und damit ohne regulaeres TLS/Let's Encrypt.
- Nach dem Folge-Update stehen rund `1.5G` freier Speicher auf `/` zur Verfuegung. Das ist fuer den aktuellen Stand ausreichend, bleibt aber fuer weiteres Wachstum begrenzt.
- Fuer Dateiuebertragungen auf diesen Server musste `scp -O` verwendet werden, da der Standard-`scp`-Pfad die Verbindung geschlossen hat.

## Empfohlene naechste Schritte

1. Kontaktformular und medienlastige Seiten im Browser fachlich pruefen.
2. Fuer kuenftige Updates mit zu erhaltenden Live-Daten den separaten Modus-B-Ablauf verwenden.
3. Bei weiterem Betrieb Speicher vergroessern oder ungenutzte Inhalte gezielt abbauen.
4. Vor einem Produktivbetrieb eine Domain und TLS nachziehen.

## Dokumentierter Modus-B-Plugin-Deploy 2026-04-29

Am `2026-04-29` wurde der lokal verifizierte Umlaut- und Frequenz-Stand fuer `wp-content/plugins/barmbini-core/` als Modus-B-Deploy auf dem Live-System bereitgestellt.

### Entscheidungsprotokoll vor dem Lauf

- Ziel: den bereits lokal verifizierten Umlaut- und Frequenz-Update fuer `wp-content/plugins/barmbini-core/` auf Live bereitstellen
- gewaehlter Modus: `Modus B`
- Begruendung: bestehende Live-Benutzer und produktiv entstandene Daten duerfen nicht durch einen SQL-Vollimport ersetzt werden

### Angewandte Leitplanken

- Backup der Live-Datenbank vor dem Lauf
- Backup des bestehenden Live-Plugin-Verzeichnisses `wp-content/plugins/barmbini-core`
- Datei-Deploy nur fuer das Plugin `barmbini-core`
- Setzen korrekter Eigentumsrechte
- optionales erneutes Aktivieren des Plugins per WP-CLI, falls fuer Hooks oder Tabellenpruefung noetig
- kein Import von `local.sql`
- kein Vollabgleich des gesamten `wp-content`
- kein Loeschen oder Ersetzen von `wp-content/uploads`
- keine unkontrollierten Datenbankeingriffe ausser Backup und lesender Validierung

### Durchgefuehrte Schritte im Lauf

- Backup-Verzeichnis `/root/barmbini-backup-2026-04-29-095939-umlaut-plugin` angelegt
- Live-Datenbank als `live-before-umlaut-plugin.sql` gesichert
- bestehendes Live-Plugin als `barmbini-core-before-umlaut-plugin.tar.gz` archiviert
- lokales Plugin-Artefakt `barmbini-core-plugin.zip` gebaut und per `scp -O` nach `/root/barmbini-core-plugin.zip` uebertragen
- WordPress-Wartungsmodus fuer den Austausch aktiviert
- Live-Verzeichnis `wp-content/plugins/barmbini-core` ersetzt und anschliessend auf `www-data:www-data` gesetzt
- Plugin per WP-CLI erneut geprueft und aktiv belassen
- Deploy-Dateien direkt auf dem Server auf die Texte `Täglich` und `wöchentlich` geprueft
- WordPress-Optionen `home` und `siteurl` erneut geprueft
- WordPress-Wartungsmodus wieder deaktiviert
- temporaeres Transferartefakt `/root/barmbini-core-plugin.zip` wieder entfernt

### Validiertes Ergebnis des Laufs

- die Deploy-Ausgabe bestaetigte `plugin-active`
- die Deploy-Ausgabe bestaetigte `home` und `siteurl` weiterhin als `http://217.160.74.128`
- der ausgelieferte Template-Stand enthaelt `Täglich`
- die ausgelieferte Settings-Klasse enthaelt `wöchentlich`
- ein serverseitiger HEAD-Check auf `http://127.0.0.1/mein-konto/abonnements/` mit Host `217.160.74.128` lieferte nach Ende des Wartungsmodus `HTTP/1.1 200 OK`
- die externe Browser-Pruefung zeigte die Startseite weiter mit dem Titel `Sozialkaufhaus Barmbini - Sozialkaufhaus Barmbini`
- die externe Browser-Pruefung zeigte fuer `http://217.160.74.128/mein-konto/abonnements/` den Titel `Ihr Konto - Sozialkaufhaus Barmbini` und das erwartete Anmeldeformular statt einer Fehler- oder Wartungsseite
- es wurde bewusst kein SQL-Import und kein Vollabgleich des gesamten `wp-content` ausgefuehrt

### Operative Hinweise aus dem Lauf

- fuer Dateiuebertragungen auf diesen Server bleibt `scp -O` erforderlich
- fuer zusammengesetzte Remote-Kommandos war ein per Base64 uebergebenes Bash-Skript robuster als langes Inline-Quoting in PowerShell
- fuer nachtraegliche WP-CLI-Checks muss der WordPress-Pfad explizit gesetzt oder vorher in `/var/www/barmbini` gewechselt werden
