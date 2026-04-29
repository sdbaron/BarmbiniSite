# Vorbereitung für neue Features und Bugfixes

## Ziel

Dieses Dokument fasst den verifizierten Ist-Stand zusammen und legt fest, wo neue Funktionalitaet oder Fehlerbehebungen im Projekt sauber eingebaut werden sollen.

Es dient als Arbeitsgrundlage, bevor konkrete Änderungen an WordPress, WooCommerce, Theme, Plugins oder dem Deployment-Prozess umgesetzt werden.

## Verifizierter Ist-Stand

### Fachlich und technisch

- WordPress wird als Informationswebsite mit WooCommerce-Katalog ohne Checkout betrieben.
- Das aktuelle Leitdokument ist das technische Konzept v2.5.
- Die Live-Bereitstellung ist dokumentiert auf dem Server `217.160.74.128` mit `nginx`, `php8.3-fpm`, `mariadb-server` und `wp-cli`.
- Der Update-Prozess unterscheidet zwischen:
  - Modus A: Vollabgleich mit SQL-Import
  - Modus B: Live-Daten behalten, kein Vollimport
- Für technische Änderungen steht jetzt ein editierbarer Code-Arbeitsstand unter `wp-content-workdir/` im Workspace bereit.

### Verifizierter `wp-content`-Bestand aus dem Archiv `barmbini-wp-content.zip`

### Temporaere Arbeitsartefakte im Workspace

- `wp-content-workdir/` ist ein entpackter, editierbarer Arbeitsstand für technische Pruefung und Änderungen.
- `barmbini-wp-content.zip` ist das Transport- und Import-Archiv für den dokumentierten Update-Prozess auf den Server.
- Beide Artefakte duerfen lokal gelöscht werden, wenn der gleiche Stand weiterhin in `C:\Users\Teilnehmer\Local Sites\barmbini\app\public\wp-content` vorhanden ist.

#### Fester Ablauf zum sicheren Neuerzeugen

1. Quelle ist der lokale WordPress-Bestand unter `C:\Users\Teilnehmer\Local Sites\barmbini\app\public\wp-content`.
2. Für einen Remote-Transfer wird daraus ein neues `barmbini-wp-content.zip` erzeugt.
3. `wp-content-workdir/` wird nur bei Bedarf aus diesem Archiv neu entpackt.
4. Für den Server-Import ist das ZIP erforderlich, `wp-content-workdir/` dagegen optional.

Beispiel in PowerShell:

```powershell
Set-Location 'C:\Users\Teilnehmer\Local Sites\barmbini\app\public\wp-content'
Compress-Archive -Path plugins,themes,uploads,languages,index.php -DestinationPath 'C:\Users\Teilnehmer\Dev\Website\barmbini-wp-content.zip' -Force

Set-Location 'C:\Users\Teilnehmer\Dev\Website'
Expand-Archive -Path '.\barmbini-wp-content.zip' -DestinationPath '.\wp-content-workdir' -Force
```

#### Plugins

- `barmbini-core`
- `all-in-one-wp-migration`
- `contact-form-7`
- `hide-cart-functions`
- `kadence-blocks`
- `kadence-starter-templates`
- `simple-local-avatars`
- `woocommerce`
- `wordpress-seo`
- `wp-fastest-cache`

#### Im lokalen Archiv und Arbeitsstand vorhandene Themes

- `kadence`
- `storefront`
- `twentytwentyfive`
- `twentytwentyfour`
- `twentytwentythree`
- `twentytwentytwo`

#### Tatsaechlich aktiv verwendetes Theme

- `kadence`

#### Nicht aktiv verwendete Standard-Themes

Diese Themes liegen zwar im lokalen Archiv bzw. im Arbeitsstand vor, werden aber laut Migrationsdokumentation nicht aktiv verwendet und wurden auf dem Server bei der Speicherbereinigung als inaktiv entfernt:

- `storefront`
- `twentytwentyfive`
- `twentytwentyfour`
- `twentytwentythree`
- `twentytwentytwo`

### Wichtige technische Beobachtung

Es gibt aktuell:

- kein Child-Theme
- ein projektspezifisches Custom-Plugin unter `wp-content/plugins/barmbini-core/`

Gleichzeitig liegen weiterhin projektspezifische WooCommerce-Anpassungen direkt in `themes/kadence/functions.php`.

Im Plugin `barmbini-core` sind bereits umgesetzt und lokal validiert:

- WooCommerce-Endpoint `abonnements` im Bereich `Mein Konto`
- Speicherung der Abo-Einstellungen in `usermeta`
- Trigger für Neuigkeiten, neue Produkte in abonnierten Kategorien und Rabatte
- Queue- und Digest-Logik mit eigenen Tabellen `wp_barmbini_notification_log` und `wp_barmbini_notification_queue`
- Admin-Übersicht, Unsubscribe-Logik und Datenschutz-Export/Löschintegration

Dort wurden bereits unter anderem umgesetzt:

- Ausblenden der Unterkategorie-Anzahl
- eigene Breadcrumb-Logik für `Sortiment`
- Einblendung von Kategoriebeschreibungen unter Unterkategorien
- Entfernung des Standard-Breadcrumb-Hooks und eigener Re-Insert

Das ist der wichtigste technische Hebel für kommende Arbeiten.

## Aktueller Validierungsstand für das Feature-Abonnementssystem

Der neue Stand wurde lokal gegen `C:\Users\Teilnehmer\Local Sites\barmbini\app\public` verifiziert.

- Das Plugin `barmbini-core` laesst sich in WordPress laden und aktivieren.
- Die Tabellen `wp_barmbini_notification_log` und `wp_barmbini_notification_queue` wurden lokal angelegt.
- Der Konto-Endpoint `Mein Konto -> Abonnements` ist im Browser sichtbar und speichert Einstellungen erfolgreich.
- Der News-Trigger erzeugt bei `sofort` einen direkten Log-Eintrag.
- Der Produkt-Trigger erzeugt bei `täglich` einen Queue-Eintrag und wird im Daily-Digest korrekt als `daily_digest` protokolliert.
- Der Rabatt-Trigger wurde lokal über den produktbezogenen WordPress-Hook auf ein Produkt im aktiven Sale-Zustand erfolgreich verifiziert.

## Schlussfolgerung für neue Implementierungen

### 1. Business-Logik nicht weiter im Vendor-Theme erweitern

Neue projektbezogene Funktionalitaet soll nicht weiter direkt in `kadence/functions.php` eingebaut werden.

Grund:

- Theme-Updates können die Änderungen überschreiben.
- Fachlogik und Darstellungslogik sind aktuell unnoetig vermischt.
- Bugfixes werden schwerer testbar und schwerer deploybar.

### 2. Bevorzugte Zielstruktur

Für neue Funktionen soll ein eigenes Projekt-Plugin angelegt werden, zum Beispiel:

- `wp-content/plugins/barmbini-core/`

Empfohlene Aufgaben dieses Plugins:

- bestehende projektbezogene WooCommerce-Hooks aus dem Kadence-Theme aufnehmen
- neue Fachlogik kapseln
- Admin- und Support-Hilfen enthalten
- eigene Datenbanktabellen oder Cron-Logik kontrolliert registrieren

### 3. Wann stattdessen ein Child-Theme sinnvoll ist

Ein Child-Theme ist nur dann die bessere Wahl, wenn künftige Änderungen vor allem diese Bereiche betreffen:

- Template-Overrides
- umfangreiche Layout-Anpassungen
- theme-nahe CSS- und Markup-Änderungen

Für Fachlogik, Integrationen, Kontofunktionen, Benachrichtigungen und Datenverarbeitung bleibt ein eigenes Plugin die richtige Stelle.

## Empfohlene Einbauorte nach Änderungstyp

### Neue Fachfunktion, z. B. Kundenkonto, Abonnements, Benachrichtigungen

Einbauort:

- eigenes Projekt-Plugin

Warum:

- unabhaengig vom Theme
- sauber testbar
- besser mit WooCommerce- und WordPress-Hooks integrierbar

### WooCommerce-Verhalten, z. B. Breadcrumbs, Katalogmodus, Kontobereiche

Einbauort:

- primaer eigenes Projekt-Plugin
- nur bei reinem Template-Markup optional Child-Theme

### Design- oder Layout-Bugfixes

Einbauort:

- bei kleinen Korrekturen zunächst Theme-CSS oder Child-Theme
- bei strukturellen Template-Änderungen Child-Theme

### Deployment-, Server- oder Migrationsfehler

Einbauort:

- Runbooks und Serverdokumentation aktualisieren
- niemals nur ad hoc auf dem Live-System reparieren, wenn die Änderung später wieder aus lokal deployt wird

## Konkrete Vorbereitung für die nächste größere Funktion

### Fall: Kundenkonto mit Abonnements und Benachrichtigungen

Die vorhandene Aufgabenbeschreibung ist fachlich bereits weit genug, um eine saubere technische Umsetzung vorzubereiten.

Empfohlene technische Richtung:

1. eigenes Plugin für die Funktion anlegen
2. WooCommerce-Endpoint `abonnements` im Bereich `Mein Konto` registrieren
3. Speicherung der Einstellungen in `usermeta`
4. Versandprotokoll in eigener Tabelle, z. B. `wp_barmbini_notification_log`
5. Trigger getrennt behandeln für:
   - Neuigkeiten
   - neue Produkte in abonnierten Kategorien
   - neue aktive Rabatte
6. Abmeldelogik über Token und eigene Endpunkt- oder Query-Logik
7. Datenschutzerklaerung parallel erweitern

### Minimale Plugin-Module für diese Funktion

- Bootstrap / Plugin-Loader
- WooCommerce-Account-Endpoint
- Usermeta-Read/Write für Abo-Einstellungen
- Trigger-Handler für Posts und Produkte
- Mail-Versand
- Versandlog gegen Dubletten
- Unsubscribe-Handler
- optional Admin-Ansicht für Support

## Dokumentierte Widersprueche und offene Klaerungen

### 1. Mehrsprachigkeit

- Das alte Konzept v2.0 beschreibt Polylang mit `de`, `en` und `ru`.
- Das aktuelle Konzept v2.5 beschreibt eine rein deutsche Website.
- Die Migrationsdokumentation sagt, Polylang sei im aktuellen lokalen Stand nicht mehr vorhanden.
- Im Archiv liegen aber weiterhin zahlreiche `ru_RU`-Sprachdateien.

Folgerung:

Vor künftigen Features oder Bugfixes muss entschieden werden, ob diese Sprachreste nur technische Altlasten sind oder bewusst behalten werden.

### 2. Cache-Strategie

- Konzept v2.5 nennt `WP Super Cache`.
- Verifizierter Bestand und Migrationsdokumentation zeigen `WP Fastest Cache`.

Folgerung:

Für Performance- oder Cache-Bugfixes ist `WP Fastest Cache` als realer Ist-Stand zu behandeln, bis eine bewusste Umstellung beschlossen wird.

### 3. Hosting-Modell

- Konzept v2.5 spricht von `IONOS WordPress Hosting Start`.
- Die aktuelle Betriebsdokumentation beschreibt einen selbst administrierten Server mit `nginx`, `php8.3-fpm` und `mariadb`.

Folgerung:

Vor Infrastruktur-, Backup- oder Sicherheitsänderungen muss das reale Zielmodell als führend behandelt werden: selbst verwalteter Serverablauf, nicht rein gemanagtes WordPress-Hosting.

### 4. Rechtliche Texte bei neuen Funktionen

- Die vorhandene Datenschutzerklaerung deckt Kontaktformular und technisch notwendige Cookies ab.
- Die geplante Abo- und Benachrichtigungsfunktion ist dort noch nicht beschrieben.

Folgerung:

Jede Funktion mit personenbezogenen Daten braucht parallel ein Update der rechtlichen Seiten.

### 5. Sicherheitslage des Servers

- Die Server-Änderungsdokumentation beschreibt einen früher kompromittierten Zustand mit manipulativer Persistenz und bösartiger Nachladung.

Folgerung:

Neue Features sollten nicht unkritisch direkt auf diesem Server aufgebaut werden, ohne die dokumentierten Haertungs- oder Neuaufsetzungsfragen zu klaeren.

## Praktische Regeln für kommende Änderungen

1. Lokaler Stand bleibt die fachliche Quelle.
2. Keine produktiven Datenbank-Vollimporte mehr, sobald Live-Daten erhalten bleiben muessen.
3. Keine neuen projektbezogenen Änderungen direkt im Vendor-Theme, wenn sie auch im Projekt-Plugin leben können.
4. Keine manuellen Live-Fixes, die später beim nächsten Deploy überschrieben werden.
5. Fachlogik, Deploy-Logik und rechtliche Texte immer zusammen denken.

## Empfohlene Reihenfolge vor der ersten echten Erweiterung

1. Bestehende Theme-Anpassungen aus `themes/kadence/functions.php` in ein eigenes Projekt-Plugin überfuehren.
2. Entscheiden, ob für Frontend-Anpassungen zusätzlich ein Child-Theme gebraucht wird.
3. Mehrsprachigkeitsreste und reale Sprachstrategie bereinigen.
4. Für das nächste Release vorab festlegen, ob Modus A oder Modus B gilt.
5. Bei neuen personenbezogenen Funktionen die rechtlichen Seiten im selben Arbeitspaket mit aktualisieren.

## Kurzfazit

Das Projekt ist fachlich gut dokumentiert, technisch aber an einer Stelle noch unsauber vorbereitet: projektspezifische WooCommerce-Logik liegt direkt im Kadence-Theme.

Bevor neue Features oder größere Bugfixes umgesetzt werden, sollte ein eigener Projektcontainer für diese Logik geschaffen werden. Danach lassen sich neue Funktionen wie Kundenkonto-Erweiterungen, Benachrichtigungen, Support-Ansichten oder robustere Bugfixes deutlich sauberer und risikoärmer einbauen.
