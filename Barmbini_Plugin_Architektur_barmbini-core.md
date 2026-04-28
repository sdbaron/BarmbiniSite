# Plugin-Architektur fuer `barmbini-core`

## Ziel

Dieses Dokument leitet aus der Aufgabenbeschreibung fuer Kundenkonto, Abonnements, Benachrichtigungen und Kuendigung eine konkrete Plugin-Architektur fuer `wp-content/plugins/barmbini-core/` ab.

Das Plugin soll die zentrale Stelle fuer projektspezifische Fachlogik werden, damit neue Logik nicht weiter direkt im Vendor-Theme `kadence` umgesetzt wird.

## Zielbild

`barmbini-core` soll mittelfristig drei Aufgaben gleichzeitig erfuellen:

1. bestehende projektspezifische WooCommerce- und Kataloganpassungen aus `themes/kadence/functions.php` aufnehmen
2. neue Fachlogik fuer Kundenkonto, Abonnements und Benachrichtigungen kapseln
3. eine stabile Grundlage fuer spaetere Support-, Admin- und Migrationsfunktionen bereitstellen

## Nicht-Ziele

Die erste Architekturversion umfasst bewusst nicht:

- Checkout oder Zahlungslogik
- allgemeine Marketing-Automation
- Push- oder SMS-Benachrichtigungen
- externe Kampagnenplattformen
- Mehrsprachigkeitslogik

## Architekturgrundsaetze

- WooCommerce bleibt Katalog, nicht Shop mit Checkout.
- Fachlogik lebt im Plugin, nicht im Theme.
- Template- oder CSS-lastige Anpassungen bleiben optional im Child-Theme.
- Keine externen PHP-Abhaengigkeiten oder Composer-Pflicht in der Erstversion.
- Datenhaltung moeglichst einfach: `usermeta` fuer Einstellungen, eigene Tabellen fuer Queue und Versandlog.
- Datenschutz, Dubletten-Schutz und nachvollziehbare Deaktivierung sind Pflichtbestandteile der Architektur.

## Zielverzeichnis

Empfohlene Struktur:

```text
wp-content/plugins/barmbini-core/
|-- barmbini-core.php
|-- uninstall.php
|-- includes/
|   |-- class-plugin.php
|   |-- class-loader.php
|   |-- class-activator.php
|   |-- class-deactivator.php
|   |-- catalog/
|   |   |-- class-breadcrumbs.php
|   |   |-- class-category-display.php
|   |   `-- class-catalog-hooks.php
|   |-- account/
|   |   |-- class-account-endpoint.php
|   |   |-- class-subscription-settings.php
|   |   `-- class-subscription-validator.php
|   |-- notifications/
|   |   |-- class-event-collector.php
|   |   |-- class-news-trigger.php
|   |   |-- class-product-trigger.php
|   |   |-- class-discount-trigger.php
|   |   |-- class-queue-repository.php
|   |   |-- class-log-repository.php
|   |   |-- class-digest-scheduler.php
|   |   |-- class-delivery-service.php
|   |   `-- class-unsubscribe-service.php
|   |-- admin/
|   |   |-- class-admin-menu.php
|   |   |-- class-subscription-overview.php
|   |   `-- class-delivery-log-screen.php
|   `-- privacy/
|       |-- class-consent-recorder.php
|       `-- class-privacy-exporter.php
|-- templates/
|   |-- account/
|   |   `-- subscriptions.php
|   `-- emails/
|       |-- immediate-news.php
|       |-- immediate-product.php
|       |-- immediate-discount.php
|       |-- daily-digest.php
|       `-- weekly-digest.php
`-- assets/
    `-- css/
        `-- account-subscriptions.css
```

## Bootstrap-Konzept

### Haupteinstiegspunkt

`barmbini-core.php` ist die einzige Plugin-Datei, die WordPress direkt laedt.

Verantwortung:

- Plugin-Metadaten bereitstellen
- Konstanten fuer Pfade und Version definieren
- `class-plugin.php` laden
- Plugin-Initialisierung starten

### Zentrale Plugin-Klasse

`class-plugin.php` orchestriert die Modulregistrierung.

Verantwortung:

- Kernmodule registrieren
- WordPress- und WooCommerce-Hooks anbinden
- Aktivierungs- und Deaktivierungslogik anstoessen
- spaetere Module kontrolliert erweitern

### Loader-Klasse

`class-loader.php` kapselt Hook-Registrierung, damit Aktions- und Filteranbindung an einer Stelle zusammengefasst bleibt.

## Modulzuschnitt

### 1. Catalog-Modul

Zweck:

- Uebernahme bestehender projektspezifischer Kataloglogik aus dem Kadence-Theme
- Breadcrumb-Anpassungen fuer `Sortiment`
- Ausblenden von Unterkategorie-Anzahlen
- Kategoriebeschreibung unter Unterkategorien

Wichtig:

- Dieses Modul reduziert Theme-Abhaengigkeit.
- Es enthaelt keine kundenspezifische Benachrichtigungslogik.

### 2. Account-Modul

Zweck:

- WooCommerce-Endpoint `abonnements` im Bereich `Mein Konto`
- Laden, Validieren und Speichern der Abo-Einstellungen
- Darstellung der Frequenzwahl fuer `sofort`, `taeglich`, `woechentlich`

Verantwortung:

- Form-Rendering
- Nonce-Pruefung
- Sanitizing
- Rueckmeldungen nach dem Speichern

### 3. Notifications-Modul

Zweck:

- Erfassen von fachlich relevanten Ereignissen
- Sofortversand fuer `sofort`
- Queue-Aufbau fuer `taeglich` und `woechentlich`
- Digest-Lauf und Versand
- Dubletten-Schutz
- Abmeldung und Tokenpruefung

Dieses Modul ist der eigentliche Kern der neuen Funktion.

### 4. Admin-Modul

Zweck:

- Sicht auf aktive Abonnements
- Sicht auf Versandereignisse und Fehler
- Basis fuer spaetere Support-Werkzeuge

Empfohlene Platzierung im Backend:

- Untermenue unter `WooCommerce`
- alternativ unter `Werkzeuge`, falls die Oberflaeche nur technisch orientiert sein soll

### 5. Privacy-Modul

Zweck:

- Protokollierung der Einwilligung
- spaetere Export- oder Loeschunterstuetzung
- technische Grundlage fuer Datenschutzanfragen

## Datenmodell

### `usermeta`

Empfohlene Felder:

- `barmbini_news_enabled`
- `barmbini_news_frequency`
- `barmbini_discount_enabled`
- `barmbini_discount_frequency`
- `barmbini_category_enabled`
- `barmbini_category_frequency`
- `barmbini_category_terms`
- `barmbini_subscription_updated_at`
- `barmbini_consent_at`
- `barmbini_consent_source`
- `barmbini_unsubscribe_token_hash`

Bewusst nicht vorgesehen:

- eigene Frequenz pro einzelner Kategorie
- unstrukturierte Serialisierung verschiedener Fachobjekte in ein einziges Meta-Feld

### Tabelle `wp_barmbini_notification_queue`

Zweck:

- Vormerkung geplanter Digest-Eintraege
- Trennung zwischen Ereigniserfassung und Versand

Empfohlene Spalten:

- `id`
- `user_id`
- `event_type`
- `object_id`
- `object_type`
- `frequency`
- `scheduled_for`
- `status`
- `created_at`
- `processed_at`

Empfohlene Statuswerte:

- `queued`
- `processing`
- `sent`
- `cancelled`
- `failed`

Empfohlene Indizes:

- `(user_id, frequency, status)`
- `(event_type, object_id, user_id)`
- `(scheduled_for, status)`

### Tabelle `wp_barmbini_notification_log`

Zweck:

- nachvollziehbarer Versandnachweis
- Fehleranalyse
- Dubletten-Schutz

Empfohlene Spalten:

- `id`
- `user_id`
- `event_type`
- `object_id`
- `object_type`
- `delivery_mode`
- `digest_run_key`
- `status`
- `sent_at`
- `error_message`

Empfohlene `delivery_mode`-Werte:

- `immediate`
- `daily_digest`
- `weekly_digest`

## UI-Konzept im WooCommerce-Konto

Empfohlene Oberflaeche im Endpoint `Abonnements`:

1. Checkbox `Neuigkeiten abonnieren`
2. Select `Neuigkeiten Frequenz`
3. Checkbox `Rabatte abonnieren`
4. Select `Rabatte Frequenz`
5. Mehrfachauswahl fuer Produktkategorien
6. Select `Produktkategorien Frequenz`
7. Speichern
8. Link oder Aktion `Alle Benachrichtigungen kuendigen`

Empfohlene Select-Werte:

- `sofort`
- `taeglich`
- `woechentlich`

UI-Regeln:

- keine Vorauswahl auf `taeglich` oder `woechentlich` ohne aktive Zustimmung
- Frequenzfelder nur aktiv, wenn die zugehoerige Abo-Art aktiv ist
- klare deutsche Beschriftung ohne Marketing-Sprache

## Hook-Konzept

### Plugin-Initialisierung

Empfohlene Hooks:

- `plugins_loaded`
- `init`
- `admin_menu`

### WooCommerce-Account-Endpoint

Empfohlene Hooks und Filter:

- `init` fuer Endpoint-Registrierung
- `query_vars` oder WooCommerce-eigene Endpoint-Registrierung
- `woocommerce_account_menu_items`
- `woocommerce_account_abonnements_endpoint`

### News-Trigger

Empfohlener Hook:

- `transition_post_status`

Regel:

- nur `post`
- nur Wechsel in `publish`
- nur wenn Beitrag fachlich zur Kategorie `Neuigkeiten` gehoert

### Produkt-Trigger

Empfohlener Hook:

- `transition_post_status`

Regel:

- nur `product`
- nur Wechsel in `publish`
- Produktkategorien ermitteln und passende Benutzer suchen

### Rabatt-Trigger

Empfohlener technischer Ansatz:

- Hook auf Produktspeicherung, kombiniert mit einer eigenen `Discount_State_Detector`-Klasse

Wichtig:

- nicht blind bei jeder Produktspeicherung versenden
- vorherigen Rabattzustand gegen aktuellen aktiven Rabattzustand vergleichen
- das Ergebnis als eigenen Status oder Fingerprint speichern

### Cron- und Digest-Laeufe

Empfohlene Events:

- `barmbini_core_daily_digest`
- `barmbini_core_weekly_digest`

Empfohlene Ausfuehrung:

- in Entwicklungsumgebungen kann WP-Cron ausreichen
- fuer Live-Betrieb ist ein echter Server-Cron robuster, der `wp cron event run` oder WP-CLI gesteuert ausloest

## Versandlogik

### Sofortversand

Ablauf:

1. Ereignis wird erkannt.
2. Passende Benutzer mit Frequenz `sofort` werden bestimmt.
3. Versandlog wird auf bestehende Dublette geprueft.
4. E-Mail wird direkt versendet.
5. Versand wird protokolliert.

### Digest-Versand

Ablauf:

1. Ereignis wird erkannt.
2. Passende Benutzer mit Frequenz `taeglich` oder `woechentlich` werden bestimmt.
3. Queue-Eintraege werden angelegt oder aktualisiert.
4. Geplanter Lauf sammelt die offenen Eintraege pro Benutzer.
5. Vor dem Versand wird geprueft, ob das Abo noch aktiv ist.
6. Digest-E-Mail wird erstellt und versendet.
7. Queue und Versandlog werden aktualisiert.

Wichtige Regel:

- Eine spaetere Abmeldung muss noch nicht versendete Queue-Eintraege technisch entwerten koennen.

## E-Mail-Konzept

Es werden zwei Mailtypen benoetigt:

1. Sofortmail fuer ein einzelnes Ereignis
2. Digest-Mail fuer mehrere Ereignisse eines Zeitraums

Pflichtbestandteile jeder Mail:

- klarer Betreff
- deutschsprachiger Inhalt
- Link zum relevanten Beitrag oder Produkt
- Link zur Abmeldung
- Hinweis auf die gewaehlte Versandfrequenz bei Digest-Mails

## Sicherheits- und Datenschutzkonzept

- Tokens fuer Abmeldelinks nur gehasht speichern, nicht im Klartext.
- Formularspeicherung nur mit Nonce und Berechtigungspruefung.
- Eingaben konsequent validieren und escapen.
- Keine versteckte oder vorausgewaehlte Einwilligung.
- Bei Export- oder Loeschanfragen muessen Abo- und Versanddaten technisch auffindbar sein.
- Abmeldungen muessen auch Queue-Eintraege fuer kuenftige Digests sperren.

## Aktivierung, Deaktivierung, Uninstall

### Aktivierung

Beim Aktivieren des Plugins:

- Tabellen fuer Queue und Versandlog anlegen
- Cron-Events registrieren
- Standardoptionen setzen, falls noetig

### Deaktivierung

Beim Deaktivieren des Plugins:

- Cron-Events sauber entfernen
- keine fachlichen Daten automatisch loeschen

### Uninstall

Vorsichtige Empfehlung:

- keine automatische Loeschung von `usermeta` und Versandhistorie ohne explizite Administratorentscheidung
- Datenschutzrelevante Loeschungen besser ueber eine separate Admin-Aktion oder ein explizites Cleanup-Flag steuern

## Migration bestehender Theme-Logik

`barmbini-core` soll nicht nur neue Abo-Logik aufnehmen, sondern auch die bereits vorhandenen projektspezifischen WooCommerce-Anpassungen aus `themes/kadence/functions.php` uebernehmen.

Empfohlene Reihenfolge:

1. bestehende Katalog- und Breadcrumb-Hooks zuerst in das Catalog-Modul verschieben
2. danach Account- und Notification-Module einbauen
3. erst nach erfolgreicher Uebernahme die Theme-Datei bereinigen

So bleibt die Einfuehrung von `barmbini-core` nicht nur eine neue Funktion, sondern auch eine technische Bereinigung des bisherigen Zustands.

## Rollout-Empfehlung

### Phase 1

- Plugin-Grundgeruest
- Aktivierungslogik
- Catalog-Modul fuer bestehende Theme-Hooks

### Phase 2

- Account-Endpoint
- `usermeta`-Persistenz
- Einwilligungs- und Abmeldelogik

### Phase 3

- Sofortbenachrichtigungen fuer News, Produkte und Rabatte
- Versandlog

### Phase 4

- Queue-Tabelle
- Daily- und Weekly-Digest
- Admin-Ansicht fuer Versandstatus

### Phase 5

- Datenschutz-Exporthilfen
- Support-Werkzeuge
- weitere Bereinigung alter Theme-Logik

## Risiken und offene Punkte

- Die Rabatt-Erkennung ist fachlich anspruchsvoller als News- oder Produktveroeffentlichungen und braucht eine saubere Zustandspruefung.
- WP-Cron allein kann fuer Digests auf einer traffic-armen Website unzuverlaessig sein.
- Bei spaeterer Einfuehrung externer Versanddienste darf die Plugin-Architektur nicht auf einen bestimmten Anbieter fest verdrahtet sein.
- Rechtliche Texte muessen parallel zur technischen Einfuehrung aktualisiert werden.

## Abnahmebild fuer die erste Architekturversion

Die Architektur ist passend, wenn folgende Punkte erfuellt sind:

1. `barmbini-core` ist die zentrale Stelle fuer projektspezifische Fachlogik.
2. Neue Benachrichtigungslogik liegt nicht im Kadence-Theme.
3. Kontoeinstellungen, Queue, Versandlog und Abmeldung sind sauber getrennt.
4. Sofort-, Daily- und Weekly-Versand koennen ohne Architekturbruch gemeinsam betrieben werden.
5. Bestehende Kataloganpassungen koennen kontrolliert aus dem Theme in das Plugin uebernommen werden.
6. Datenschutz, Dubletten-Schutz und Support-Sicht sind technisch beruecksichtigt.
