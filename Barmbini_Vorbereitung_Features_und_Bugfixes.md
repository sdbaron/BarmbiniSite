# Vorbereitung für neue Features und Bugfixes

## Ziel

Dieses Dokument fasst den verifizierten Ist-Stand zusammen und legt fest, wo neue Funktionalitaet oder Fehlerbehebungen im Projekt sauber eingebaut werden sollen.

Es dient als Arbeitsgrundlage, bevor konkrete Änderungen an WordPress, WooCommerce, Theme, Plugins oder dem Deployment-Prozess umgesetzt werden.

## Verifizierter Ist-Stand

### Server-Sicherheitsanalyse vom 2026-08-11

Am 2026-08-11 wurde eine lesende Analyse des Live-Servers `217.160.74.128` durchgeführt. Befunde (nach Schweregrad):

**� Mittel (korrigiert am 2026-08-11):**
- Die Startseite lädt einen Google-Maps-iframe (`/maps/embed/v1/place?key=AIzaSyBAM2o7...`). Der sichtbare Key ist **kein eigener Key**, sondern der **eingebaute Standard-Key des Kadence-Blocks-Plugins** (Fallback, wenn keine eigene Option `kadence_blocks_google_maps_api` gesetzt ist – verifiziert im Plugin-Quellcode). **Kein eigenes Google-Cloud-Konto/Rotationsrisiko.** Reale Punkte: (1) Architektur-Konflikt mit „nur statische Karte" (v2.5 §3), (2) Datenschutzerklärung behauptet „keine externen Dienste" – unzutreffend, (3) Kadences geteilter Key kann rate-limitiert werden (Verfügbarkeitsrisiko), (4) blockt eine enge Content-Security-Policy. **Entscheidung:** Block bleibt vorerst (Optionen + spätere Ersetzung in `Tasks/Barmbini_Aufgabe_Google_Maps_statt_Iframe_Startseite.md`).

**🔴 Mittel:**
- **REST-API gibt Benutzernamen preis:** `GET /wp-json/wp/v2/users` liefert `barmbini` (ID 1) an nicht angemeldete Besucher. → Aufgabe `Tasks/Barmbini_Aufgabe_Sicherheit_REST_API_Benutzernamen.md` (Plugin-Fix).

**🟠 Mittel:**
- **Keine Sicherheits-Header:** `X-Frame-Options`, `X-Content-Type-Options`, `Content-Security-Policy`, `Referrer-Policy`, `Permissions-Policy`, `Strict-Transport-Security` fehlen komplett. → Aufgabe `Tasks/Barmbini_Aufgabe_Sicherheit_HTTP_Header.md` (nginx-Runbook). HSTS erst nach HTTPS-Einführung. **Fortschritt:** Runbook freigegeben, CSP-Entscheidung = Option A (keine CSP im ersten Schritt wegen Google-Maps-Embed); 4 Google-unabhängige Header stehen zur Umsetzung per SSH bereit.
- **HTTP statt HTTPS:** Die gesamte Site läuft unverschlüsselt; Konzept v2.5 §2 sieht SSL vor.

**🟡 Niedrig:**
- **Information Disclosure:** `/readme.html` erreichbar (200), `/xmlrpc.php` aktiv (405). → Aufgabe `Tasks/Barmbini_Aufgabe_Sicherheit_Information_Disclosure.md` (Server-Runbook). **Fortschritt:** WP-Ebene-Filter (`xmlrpc_enabled`→false) ist umgesetzt und live (Commit `19280f8`); nginx-Block + `readme.html`-Löschung stehen aus (per SSH).
- Externe Emoji-SVGs von `https://s.w.org` werden geladen (gegen „keine externen Dienste").

**🔵 Redaktionell:**
- Tippfehler **„Deutcshland"** im Startseiten-Inhalt (Post 13). → Aufgabe `Tasks/Barmbini_Aufgabe_Redaktioneller_Textfehler_Startseite.md`. **Fortschritt (2026-08-11):** Lokal korrigiert (verifiziert: 0× „Deutcshland"); **Live-Nachzug im Editor steht noch aus** (Modus B, kein SQL).

**Positiv:** Kein exponiertes `debug.log`; WooCommerce-Katalog ohne Warenkorb; kein offensichtliches Schad-Skript im Frontend-HTML.

### Server-Ressourcen (per SSH, 2026-08-12)

Der Server ist ein **1-Kern-VPS mit nur 826 MB RAM** und **8.7 GB Disk (79 % belegt)**. Load Average praktisch idle (0.08), Uptime 43 Tage – kein Leistungsengpass, aber:

- **RAM ist der Engpass:** PHP-FPM-Worker (bis zu 5, à ~110–123 MB RSS) können bei Spitzen fast den gesamten RAM belegen; Swap (2 GB, 192 MB genutzt) fängt es ab.
- **Disk-Druck:** `/` zu 79 % voll (1.9 GB frei). `/root` enthält 390 MB, davon 2× 176 MB Deploy-Backups vom 2026-08-11. → Aufgabe `Tasks/Barmbini_Aufgabe_Server_Wartung_root_aufraeumen.md`. **Fortschritt (2026-08-12):** Älteres Deploy-Backup `...-112119` wurde nach `/root/deploy-backups-archiv/` **verschoben** (reversibel, kein Speichergewinn); `barmbini-db.txt` + Malware-Backups unangetastet. Speicher wird erst durch **Löschung** des archivierten Backups (176 MB) frei – nur nach Freigabe.

### Fachlich und technisch

- WordPress wird als Informationswebsite mit WooCommerce-Katalog ohne Checkout betrieben.
- Das aktuelle Leitdokument ist das technische Konzept v2.5.
- Die Live-Bereitstellung ist dokumentiert auf dem Server `217.160.74.128` mit `nginx`, `php8.3-fpm`, `mariadb-server` und `wp-cli`.
- **Seit 2026-08-13 läuft die Website unter der Domain `https://barmbini.de`** (vorher IP-basiert `http://217.160.74.128`, dann `http://barmbini.de`). `www.barmbini.de` leitet per 301 auf `https://barmbini.de` um; HTTP→HTTPS-Redirect + HSTS aktiv. Siehe `Barmbini_Aenderung_Domain_barmbini_de.md` und `Barmbini_Aenderung_HTTPS_barmbini_de.md`.
- Der Update-Prozess unterscheidet zwischen:
  - Modus A: Vollabgleich mit SQL-Import
  - Modus B: Live-Daten behalten, kein Vollimport
- Für technische Änderungen steht jetzt ein editierbarer Code-Arbeitsstand unter `wp-content-workdir/` im Workspace bereit.

### Verifizierter `wp-content`-Bestand aus dem Archiv `barmbini-wp-content.zip`

### Temporaere Arbeitsartefakte im Workspace

- `wp-content-workdir/` ist ein entpackter, editierbarer Arbeitsstand für technische Pruefung und Änderungen.
- `barmbini-wp-content.zip` ist das Transport- und Import-Archiv für den dokumentierten Update-Prozess auf den Server.
- Beide Artefakte duerfen lokal gelöscht werden, wenn der gleiche Stand weiterhin in `D:\Local Sites\barmbini\app\public\wp-content` vorhanden ist.

#### Fester Ablauf zum sicheren Neuerzeugen

1. Quelle ist der lokale WordPress-Bestand unter `D:\Local Sites\barmbini\app\public\wp-content`.
2. Für einen Remote-Transfer wird daraus ein neues `barmbini-wp-content.zip` erzeugt.
3. `wp-content-workdir/` wird nur bei Bedarf aus diesem Archiv neu entpackt.
4. Für den Server-Import ist das ZIP erforderlich, `wp-content-workdir/` dagegen optional.

Beispiel in PowerShell:

```powershell
Set-Location 'D:\Local Sites\barmbini\app\public\wp-content'
Compress-Archive -Path plugins,themes,uploads,languages,index.php -DestinationPath 'D:\Dev\Website\barmbini-wp-content.zip' -Force

Set-Location 'D:\Dev\Website'
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
- Abo-Optionen im Formular: **Neuigkeiten und Aktionen** (je mit Frequenz `sofort`, `täglich`, `wöchentlich`). Rabatte/Produktkategorien sind im Formular ausgeblendet (Datenlogik und Trigger bleiben im Code, reversibel)
- Benutzerregistrierung über `/mein-konto/` (WooCommerce; Einstellung `woocommerce_enable_myaccount_registration = yes`)
- DSGVO-Pflicht-Checkbox bei der Registrierung (ohne Zustimmung wird abgelehnt); Einwilligung wird mit Zeitstempel und Quelle `registration` in `barmbini_consent_at`/`barmbini_consent_source` gespeichert
- E-Mail-Absender aller Mails: `Barmbini Sozialkaufhaus <info@barmbini.de>` (`wp_mail_from`/`wp_mail_from_name`)
- Redirect nach nativer WP-Registrierung auf `/mein-konto/`
- Admin-Benachrichtigung per E-Mail an `info@barmbini.de` bei neuer Kundenregistrierung
- Kontodetails (Vorname, Nachname, Anzeigename, E-Mail, Passwort) über WooCommerce-Standard bearbeitbar
- Passwort-Anforderung gelockert: nur noch **mindestens 6 Zeichen** (statt WooCommerce-Stärke-Anforderung). Hinweis-Meldung via Filter `password_hint` ersetzt, `woocommerce_min_password_strength` auf 0, eigene 6-Zeichen-Validierung auf `user_profile_update_errors`, `woocommerce_save_account_details_errors` und `woocommerce_registration_errors`
- Konto-Dashboard angepasst: Menüpunkte `Bestellungen`, `Downloads`, `Adressen` und `Zahlungsarten` ausgeblendet (reiner Katalog ohne Checkout); Dashboard-Text ersetzt durch „Von Ihrem Konto-Dashboard aus können Sie Ihr Passwort und Ihre Kontodetails bearbeiten.“ (`add_menu_item`-Filter + `render_custom_dashboard` via `woocommerce_account_content`-Override)
- Trigger für Neuigkeiten, neue Produkte in abonnierten Kategorien und Rabatte
- Trigger für **Aktionen beim Startdatum** (Variante A): Cron-Job `barmbini_core_action_start_notifier` (täglich 08:00) benachrichtigt Aktionen-Abonnenten, sobald das Startdatum einer Aktion erreicht ist (Meta-Flag `_barmbini_action_start_notified` verhindert Duplikate; kein Versand beim Veröffentlichen, kein rückwirkender Versand)
- Queue- und Digest-Logik mit eigenen Tabellen `wp_barmbini_notification_log` und `wp_barmbini_notification_queue`
- Admin-Übersicht, Unsubscribe-Logik und Datenschutz-Export/Löschintegration
- Responsives Footer-Burger-Menü (Grid-basiert, breakpoint 1024px, Toggle-Klasse `.barmbini-footer-grid-open`)
- Wiederverwendbarer Adressblock-Shortcode `[barmbini_address]` (Daten in `wp_options`, Format wie /barrierefreiheit/)
- Shortcode `[barmbini_latest_news]` für die letzten drei Neuigkeiten-Beiträge (Attribute: `count`, `show_excerpt`, `show_date`, `empty_message`)
- Custom Post Type `barmbini_aktion` für zeitlich begrenzte Aktionen (Start-/Enddatum, Flyer-Bild, Pro-Aktion-Checkbox für Beschreibung, Einzelansicht unter `/aktion/{slug}/` im Kadence-Layout, Archivseite `/aktion/`, Shortcode `[barmbini_promotion]`, Admin-Archiv-Filter, Gutenberg-kompatibel). Frontend-Archivfilter (`filter_archive_for_visitors`, `pre_get_posts`): Blendet für Besucher ohne Administrator-/Redakteur-Rolle Aktionen mit zukünftigem Startdatum aus (`current_user_can('edit_others_posts')`-Check). Admins und Redakteure sehen alle Aktionen ungefiltert.
- Shortcode `[barmbini_top_product_categories]` für die Sortiment-Seite (Top-Level-Produktkategorien als gruppierte Grids; Attribute `columns`, `hide_empty`, `exclude`, `move_last`, `parent`, `orderby`, `order`). Wurde aus dem MU-Plugin `mu-plugins/barmbini-sortiment-shortcodes.php` in `class-top-product-categories-shortcode.php` migriert und auf dem Server live deployt (Modus B).
- WP-Cron-Job `barmbini_core_cache_maintenance` (alle 6 Stunden): leert den WP Fastest Cache via `wpfc_clear_all_cache`, damit abgelaufene Aktionen zuverlässig von der Startseite verschwinden (Free-Version kennt keine native Cache-Lebensdauer).
- Deployment-Tooling: `sync.ps1` (auto-discover), `deploy.ps1` (-Full/-Force/-NoBackup), `dump-db.php`
- Katalog-Styling über `class-catalog-hooks.php`/`get_inline_styles()`: u. a. Breadcrumb-Einrückung (`woocommerce-breadcrumb`, 15px mit `!important` wegen Kadence-Ladereihenfolge), Ausblenden von `.kadence-breadcrumbs`, Hover-Kategoriebeschreibungen
- Startseiten-Layout-Modul `class-homepage-layout.php` (Barmbini_Core_Homepage_Layout) mit `assets/css/homepage-hero.css`: hält den Hero bis 600 px zweispaltig (nur `is_front_page()`)

Dort wurden bereits unter anderem umgesetzt:

- Ausblenden der Unterkategorie-Anzahl
- eigene Breadcrumb-Logik für `Sortiment`
- Einblendung von Kategoriebeschreibungen unter Unterkategorien
- Entfernung des Standard-Breadcrumb-Hooks und eigener Re-Insert
- Produktgalerie-Bilder werden quadratisch zugeschnitten und zentriert (`object-fit: cover`); Größe responsiv `max(25vw, 170px)` — mindestens **170 px**, darüber ein Viertel der Fensterbreite (Regel `.woocommerce-product-gallery__image img:not(.zoomImg)`, erfasst alle Galerie-Slides, `zoomImg`-Klon ausgenommen; `!important` nötig wegen WooCommerce-Regel `div.product div.images img {height:auto}`)
- Beispiel-Badge: Produkte mit dem Produkt-Schlagwort `Beispiel` (`product_tag`, Slug `beispiel`) erhalten ein Badge `Beispiel` (`render_example_badge()` via Hooks `woocommerce_before_shop_loop_item_title`/`woocommerce_before_single_product_summary`). Das Badge trägt die Klasse `barmbini-example-badge onsale` und übernimmt dadurch exakt Größe/Schrift des „Angebot!“-Badges; Position oben links (im Loop), auf Einzelseiten unter dem onsale-Badge gestapelt (`top: 44px`), Farbe `#2d6a4f`
- Beispiel-Hinweis: Auf Sortiment- und Kategorieseiten erscheint ein grüner Hinweisbalken „Die gezeigten Artikel dienen als Beispiele…“ (`render_example_notice()`, Klasse `.barmbini-example-notice`, Hook `woocommerce_before_main_content` Prio 30)
- Startseiten-Hero bleibt bis 600 px zweispaltig (`class-homepage-layout.php` + `assets/css/homepage-hero.css`, Override mit `!important`, nur `is_front_page()`), damit das Logo nicht überbreit gestapelt wird
- Aktions-Karten (`assets/css/promotion.css`): max. **500 px** Breite, Grid `minmax(300px, 500px)`, zentriert
- Kadence-Titelbanner auf inneren Seiten (`.entry-hero-container-inner .entry-header`) von **200 px auf 120 px** Höhe reduziert — globale Inline-Regel in `class-catalog-hooks.php`/`get_global_inline_styles()` (Handle `barmbini-core-global`, lädt auf allen Seiten, `min-height: 120px !important`). Startseite und Produktseiten haben keinen solchen Banner und bleiben unverändert.

Das ist der wichtigste technische Hebel für kommende Arbeiten.

## Aktueller Validierungsstand für das Feature-Abonnementssystem

Der neue Stand wurde lokal gegen `D:\Local Sites\barmbini\app\public` verifiziert.

- Das Plugin `barmbini-core` laesst sich in WordPress laden und aktivieren.
- Die Tabellen `wp_barmbini_notification_log` und `wp_barmbini_notification_queue` wurden lokal angelegt.
- Der Konto-Endpoint `Mein Konto -> Abonnements` ist im Browser sichtbar und speichert Einstellungen erfolgreich.
- Footer-Burger-Menü funktioniert auf Desktop (2-Spalten-Grid) und Mobile (Toggle + Grid-Wechsel).
- Shortcode `[barmbini_address]` gibt Adressblock im korrekten Format aus.
- Shortcode `[barmbini_latest_news]` gibt die letzten Neuigkeiten-Beiträge aus.
- `sync.ps1` synchronisiert Workspace ↔ Local (auto-discover) und leert nach Push mit kopierten Dateien automatisch den WP Fastest Cache (lokale Installation).
- `deploy.ps1 -Full -Force -NoBackup` deployed Code + DB auf den Server (217.160.74.128).

## Live-Fix (2026-08-05): HTTPS-URLs in Inhalten bereinigt

Auf dem Live-Server `217.160.74.128` enthielten 15 Posts/Beiträge/Produkte hartcodierte `https://217.160.74.128`-URLs in `post_content` (86 Ersetzungen), `post_excerpt` (2) und `option_value` (2). Da der Server nur HTTP bedient, schlugen diese mit `ERR_CONNECTION_REFUSED` fehl. `wp search-replace` hat die URLs gezielt auf `http://217.160.74.128` korrigiert; die `guid`-Spalte (207 Einträge) bleibt bewusst unangetastet. DB-Backup: `/root/barmbini-db-backup-2026-08-05-103727`.

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

- Konzept v2.5 spricht von `IONOS VPS Linux S+`.
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
