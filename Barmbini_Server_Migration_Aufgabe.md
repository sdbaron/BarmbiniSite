# Detaillierte Aufgabe: Serverbereinigung, WordPress-Installation und Migration

## Ziel

Der Server `217.160.74.128` soll exklusiv fuer die Produktivumgebung der Website Sozialkaufhaus Barmbini vorbereitet werden. Danach soll WordPress installiert und die lokale Website aus `C:\Users\Teilnehmer\Local Sites\barmbini` auf den Server migriert werden.

Die Aufgabe ist so zu bearbeiten, dass am Ende eine lauffaehige, DSGVO-konforme, deutschsprachige WordPress-Produktivseite mit Kadence-Theme auf dem Zielserver verfuegbar ist.

## Quellenbasis

Die Aufgabe basiert auf:

- `Barmbini_Technisches_Konzept_v2.5.md`
- `Barmbini_Rechtliche_Seiten.md`
- `Barmbini_Seiteninhalte.md`
- lokaler WordPress-Installation unter `C:\Users\Teilnehmer\Local Sites\barmbini`

## Verifizierter Ist-Stand der lokalen Installation

### Lokaler Quellstand

- WordPress-Dateien liegen unter `C:\Users\Teilnehmer\Local Sites\barmbini\app\public`
- SQL-Dump liegt unter `C:\Users\Teilnehmer\Local Sites\barmbini\app\sql\local.sql`
- All-in-One-Backup liegt unter `C:\Users\Teilnehmer\Local Sites\barmbini\app\public\wp-content\ai1wm-backups\barmbini-local-20260305-143837-3cxpxokmsuk7.wpress`
- `wp-config.php` zeigt eine Local-Installation mit `DB_NAME=local`, `DB_USER=root`, `DB_PASSWORD=root`, `DB_HOST=localhost`
- WordPress-Core-Dateien stehen auf `6.9.4`

### Lokal aktive Theme-/Plugin-Kombination laut Datenbankauszug

- Aktives Theme: `Kadence 1.4.5`
- Aktive Plugins:
  - `Polylang 3.7.8`
  - `All-in-One WP Migration and Backup 7.102`
  - `Contact Form 7 6.1.5`
  - `Kadence Starter Templates 2.2.14`
  - `Simple Local Avatars 2.8.6`
  - `Yoast SEO 27.1`
  - `WP Fastest Cache 1.4.6`

### Wichtige Abweichungen zum technischen Konzept

- Das Konzept beschreibt eine einsprachige deutsche Website. Lokal ist jedoch `Polylang` aktiv und es existieren Sprachreste fuer `de`, `en` und `ru`.
- Das Konzept sieht WooCommerce als Produktkatalog vor. Lokal sind `woocommerce`, `hide-cart-functions` und `kadence-blocks` zwar im Plugin-Verzeichnis vorhanden, laut lokalem Datenbankauszug aber nicht aktiv.
- Das Konzept nennt `WP Super Cache`, lokal aktiv ist dagegen `WP Fastest Cache`.

Diese Abweichungen muessen vor oder spaetestens waehrend der Migration entschieden und bereinigt werden. Ein ungepruefter 1:1-Import wuerde einen Produktivstand herstellen, der nicht vollstaendig dem Konzept entspricht.

## Voraussetzungen vor Beginn

Die Aufgabe darf erst umgesetzt werden, wenn folgende Punkte vorliegen:

- SSH-Zugang mit `root` oder `sudo` auf `217.160.74.128`
- bestaetigtes Server-Betriebssystem, z. B. Debian oder Ubuntu
- Produktionsdomain und DNS-Zuordnung auf die Ziel-IP
- Entscheidung, ob der Webserver mit `Nginx + PHP-FPM` oder `Apache + PHP` betrieben wird
- finale Datenbank-Zugangsdaten fuer Produktion
- Freigabe fuer potenziell destruktive Serverbereinigung
- Wartungsfenster oder Freigabe fuer die Inbetriebnahme

## Aufgabe

### 1. Server vor jeder Aenderung inventarisieren und absichern

Ziel: Vor der Bereinigung muss eindeutig nachvollziehbar sein, was auf dem Server aktuell laeuft und was entfernt werden darf.

Arbeitsschritte:

1. Per SSH am Server anmelden.
2. Betriebssystem, Kernel, aktive Benutzer, laufende Dienste, geoeffnete Ports und installierte Pakete erfassen.
3. Vollstaendige Bestandsaufnahme in einer Textdatei sichern.
4. Falls bereits produktive Inhalte vorhanden sind: Dateisystem und Datenbanken sichern.
5. Vor der Bereinigung einen Snapshot oder ein Vollbackup erstellen.

Pruefbefehle fuer Debian/Ubuntu:

```bash
hostnamectl
cat /etc/os-release
who
systemctl list-units --type=service --state=running
ss -tulpn
df -h
free -h
apt list --installed
ps aux --sort=-%mem | head -30
```

Abnahmekriterium:

- Ein dokumentierter Vorher-Zustand liegt vor.
- Es existiert ein wiederherstellbares Backup oder ein Provider-Snapshot.

### 2. Server bereinigen, aber nicht blind loeschen

Ziel: Der Server soll nur noch Komponenten enthalten, die fuer Betrieb, Sicherheit und Wartung einer WordPress-Installation benoetigt werden.

Wichtig:

- Nicht loeschen: SSH, Firewall, sudo, Logrotation, Zeitsynchronisation, Paketverwaltung, Fail2ban falls vorhanden, Monitoring-Agent falls vertraglich noetig.
- Nur entfernen, was nach Inventarisierung eindeutig ungenutzt ist.
- Keine pauschale Loeschung von Systemverzeichnissen.

Arbeitsschritte:

1. Nicht benoetigte Webstacks deinstallieren, z. B. doppelte Webserver, alte PHP-Versionen, Testdatenbanken, Demo-Deployments.
2. Verwaiste Prozesse identifizieren und deaktivieren.
3. Nicht benoetigte Cronjobs entfernen.
4. Alte Deploy-Verzeichnisse, Testordner, ungenutzte Archive und Installationsreste loeschen.
5. Nicht benoetigte Datenbanken und Datenbanknutzer entfernen.
6. Unnoetige Pakete mit dem Paketmanager entfernen und verwaiste Abhaengigkeiten bereinigen.

Pruefbefehle fuer Debian/Ubuntu:

```bash
systemctl list-unit-files --type=service | grep enabled
crontab -l
ls -la /var/www
find /var/www -maxdepth 2 -type d
mysql -u root -p -e "SHOW DATABASES;"
apt autoremove
apt autoclean
```

Abnahmekriterium:

- Der Server enthaelt nur noch die fuer WordPress benoetigte Systembasis.
- Es gibt genau einen produktiv vorgesehenen Webroot.
- Keine konkurrierenden Webserver- oder PHP-Konfigurationen mehr aktiv.

### 3. WordPress-Produktivstack vorbereiten

Ziel: Eine saubere technische Basis fuer WordPress schaffen.

Arbeitsschritte:

1. System aktualisieren.
2. Webserver, PHP 8.1+ und notwendige PHP-Erweiterungen installieren.
3. MariaDB oder MySQL installieren und absichern.
4. Produktionsverzeichnis anlegen, z. B. `/var/www/barmbini`.
5. Dateiberechtigungen sauber setzen.
6. Virtuellen Host bzw. Serverblock fuer die Domain anlegen.
7. SSL mit Let's Encrypt oder Provider-Zertifikat einrichten.

Empfohlene PHP-Erweiterungen fuer WordPress:

- `php-fpm`
- `php-mysql`
- `php-curl`
- `php-xml`
- `php-mbstring`
- `php-zip`
- `php-gd` oder `php-imagick`
- `php-intl`
- `php-opcache`

Abnahmekriterium:

- Die Domain liefert per HTTPS eine funktionierende WordPress- oder Platzhalterseite aus.
- PHP und Datenbank sind einsatzbereit.

### 4. Frische WordPress-Installation auf dem Server anlegen

Ziel: Zuerst eine saubere WordPress-Basis aufsetzen, dann die Inhalte importieren.

Arbeitsschritte:

1. Neue Datenbank und Datenbankbenutzer anlegen.
2. Aktuelle stabile WordPress-Version herunterladen.
3. Dateien in den Webroot entpacken.
4. `wp-config.php` mit Produktionsdaten anlegen.
5. Einmalige WordPress-Installation ueber Browser oder WP-CLI abschliessen.
6. Permalink-Struktur auf `/%postname%/` setzen.
7. Automatische Dateibearbeitung im Backend deaktivieren.
8. Debugging fuer Produktion korrekt setzen.

Empfohlene `wp-config`-Ergaenzungen fuer Produktion:

```php
define('DISALLOW_FILE_EDIT', true);
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('WP_ENVIRONMENT_TYPE', 'production');
```

Abnahmekriterium:

- Frisches WordPress ist erreichbar.
- Anmeldung im Backend funktioniert.
- HTTPS und Permalinks funktionieren.

### 5. Lokale Website fuer die Migration vorbereiten

Ziel: Einen uebertragbaren und konsistenten Stand erzeugen.

Arbeitsschritte:

1. Lokale Website in Local starten und Sichtpruefung durchfuehren.
2. Pruefen, ob das vorhandene `.wpress`-Backup aktuell genug ist.
3. Falls noetig ein neues All-in-One-WP-Migration-Backup aus dem finalen lokalen Stand erzeugen.
4. Cache lokal leeren.
5. Nicht benoetigte Testinhalte, Entwuerfe und Platzhalter entfernen.
6. Pruefen, ob Mehrsprachigkeit wirklich gebraucht wird. Falls nein: Polylang vor der Produktivmigration sauber entfernen oder spaetestens nach Import rueckbauen.
7. Pruefen, ob WooCommerce-Katalog und Hide-Cart-Funktionen im finalen Stand aktiviert und konfiguriert sein muessen. Falls ja, lokale Konfiguration vor Export finalisieren.

Abnahmekriterium:

- Ein konsistenter finaler Export der lokalen Site liegt vor.
- Die fachliche Entscheidung zu Polylang und WooCommerce ist getroffen.

### 6. Migration auf den Server durchfuehren

Bevorzugter Weg laut Konzept: `All-in-One WP Migration`

Arbeitsschritte:

1. Auf dem frischen Server-WordPress mindestens folgende Plugins installieren:
   - `All-in-One WP Migration and Backup`
   - `Kadence`
   - `Contact Form 7`
   - `Yoast SEO`
2. Das lokale `.wpress`-Backup auf den Server uebertragen.
3. Backup ueber All-in-One WP Migration importieren.
4. Nach dem Import erneut anmelden.
5. Permalinks einmal speichern.
6. Cache-Plugin-Konfiguration kontrollieren oder Cache-Plugin vorerst deaktivieren.

Fallback, falls Importgroesse oder Plugin-Limit blockiert:

1. WordPress-Dateien per `rsync` oder SFTP uebertragen.
2. Datenbank aus `local.sql` in die Produktionsdatenbank importieren.
3. URLs von `http://barmbini.local` auf Produktionsdomain ersetzen.
4. Serialized-Daten nur mit WP-CLI oder einem WordPress-kompatiblen Such-/Ersetzungswerkzeug anfassen.

Beispiel mit WP-CLI:

```bash
wp search-replace 'http://barmbini.local' 'https://example.de' --all-tables
wp rewrite flush
wp cache flush
```

Abnahmekriterium:

- Inhalte, Medien, Menues und Theme-Einstellungen sind auf dem Server sichtbar.
- Interne Links zeigen nicht mehr auf `barmbini.local`.

### 7. Nachmigration: Konzept und Live-Stand abgleichen

Ziel: Der Live-Stand muss nicht nur technisch laufen, sondern auch dem Konzept entsprechen.

Pflichtpruefungen:

1. Theme `Kadence` ist aktiv.
2. Deutsche Hauptsprache ist korrekt.
3. Falls Mehrsprachigkeit nicht Teil des finalen Projekts ist:
   - Polylang deaktivieren
   - Sprachreste bereinigen
   - Sprachverzeichnisse und Menues pruefen
4. WooCommerce nur dann aktivieren, wenn der Sortiment-Katalog jetzt wirklich live gehen soll.
5. Falls WooCommerce live geht:
   - Shop-Seite = `Sortiment`
   - Warenkorb und Checkout deaktiviert
   - Breadcrumb-Logik pruefen
   - Keine Kaufabwicklung sichtbar
6. Kontaktformular testen.
7. Impressum, Datenschutzerklaerung und Barrierefreiheitserklaerung veroeffentlichen.
8. Statische Karte statt eingebetteter Google Maps pruefen.
9. Keine externen Fonts oder unnötigen Drittanbieter-Skripte laden.

Abnahmekriterium:

- Der Live-Stand entspricht dem freigegebenen Funktionsumfang.
- DSGVO- und Inhaltsseiten sind vorhanden und aufrufbar.

### 8. Betriebsreife und Absicherung herstellen

Arbeitsschritte:

1. Admin-Benutzer pruefen und unnoetige Standardkonten entfernen.
2. Sichere Passwoerter setzen.
3. Automatische Backups einrichten.
4. Error-Logs aktiv kontrollieren.
5. Dateiberechtigungen und Schreibrechte pruefen.
6. XML-RPC deaktivieren, falls nicht benoetigt.
7. REST-API und Login nicht unnoetig einschraenken, aber absichern.
8. Caching aktivieren, nachdem die Seite fachlich freigegeben wurde.
9. Suchmaschinen-Sichtbarkeit fuer Produktion korrekt setzen.

Abnahmekriterium:

- Seite ist produktionsbereit, abgesichert und wartbar.

## Offene Entscheidungen, die vor Umsetzung geklaert werden muessen

1. Welche Produktionsdomain soll verwendet werden?
2. Welches Server-Betriebssystem laeuft auf `217.160.74.128`?
3. Soll die Seite wirklich nur deutsch sein, wie im Konzept beschrieben, oder soll die lokal sichtbare Polylang-Mehrsprachigkeit erhalten bleiben?
4. Soll WooCommerce bereits beim ersten Go-Live aktiv sein oder spaeter?
5. Soll lokal `WP Fastest Cache` uebernommen oder gemaess Konzept auf eine andere Cache-Strategie gewechselt werden?

## Definition of Done

Die Aufgabe ist abgeschlossen, wenn alle folgenden Punkte erfuellt sind:

- Der Server wurde inventarisiert, gesichert und bereinigt.
- Nur die fuer WordPress benoetigte Serverumgebung ist aktiv.
- WordPress laeuft produktiv auf `217.160.74.128` unter HTTPS.
- Die lokale Site aus `C:\Users\Teilnehmer\Local Sites\barmbini` wurde erfolgreich migriert.
- Es gibt keine Verweise mehr auf `barmbini.local`.
- Die rechtlichen Seiten sind vorhanden.
- Kontaktformular, Navigation, Permalinks und Medien funktionieren.
- Der Live-Stand entspricht dem abgestimmten Projektumfang und nicht einem ungeprueften Local-Stand.

## Hinweis zur Ausfuehrung

Diese Aufgabe ist absichtlich als kontrolliertes Runbook formuliert. Die geforderte Serverbereinigung darf nicht als pauschales "alles loeschen" ausgefuehrt werden, sondern nur nach Inventarisierung, Backup und klarer Trennung zwischen benoetigten Systemkomponenten und Altlasten.
