# Plugin-Architektur für `barmbini-core`

## Ziel

Dieses Dokument beschreibt die aktuelle Architektur des Plugins `wp-content/plugins/barmbini-core/` (Stand Juli 2026).

Das Plugin ist die zentrale Stelle für projektspezifische Fachlogik, Kataloganpassungen, Account-Funktionen, Benachrichtigungen, Datenschutz und wiederverwendbare Shortcode-Komponenten.

## Zielbild

`barmbini-core` soll mittelfristig drei Aufgaben gleichzeitig erfuellen:

1. bestehende projektspezifische WooCommerce- und Kataloganpassungen aus `themes/kadence/functions.php` aufnehmen
2. neue Fachlogik für Kundenkonto, Abonnements und Benachrichtigungen kapseln
3. eine stabile Grundlage für spätere Support-, Admin- und Migrationsfunktionen bereitstellen

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
- Datenhaltung möglichst einfach: `usermeta` für Einstellungen, eigene Tabellen für Queue und Versandlog.
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
|   |   |-- class-catalog-hooks.php
|   |   |-- class-cache-maintenance.php
|   |   |-- class-footer-menu.php
|   |   |-- class-address-shortcode.php
|   |   |-- class-latest-news-shortcode.php
|   |   |-- class-promotion-post-type.php
|   |   |-- class-promotion-shortcode.php
|   |   |-- class-top-product-categories-shortcode.php
|   |   `-- class-homepage-layout.php
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
|   `-- security/
|       `-- class-rest-api-hardening.php
|   `-- roles/
|       `-- class-roles.php
|   `-- guides/
|       `-- class-staff-guides.php
|-- templates/
|   |-- account/
|   |   `-- subscriptions.php
|   |-- emails/
|   |   |-- immediate-news.php
|   |   |-- immediate-product.php
|   |   |-- immediate-discount.php
|   |   |-- daily-digest.php
|   |   `-- weekly-digest.php
|   `-- single-barmbini_aktion.php
`-- assets/
    |-- css/
    |   |-- account-subscriptions.css
    |   |-- footer-burger-menu.css
    |   |-- latest-news.css
    |   |-- homepage-hero.css
    |   `-- promotion.css
    `-- js/
        `-- footer-burger-menu.js
```

## Bootstrap-Konzept

### Haupteinstiegspunkt

`barmbini-core.php` ist die einzige Plugin-Datei, die WordPress direkt laedt.

Verantwortung:

- Plugin-Metadaten bereitstellen
- Konstanten für Pfade und Version definieren
- `class-plugin.php` laden
- Plugin-Initialisierung starten

### Zentrale Plugin-Klasse

`class-plugin.php` orchestriert die Modulregistrierung.

Verantwortung:

- Kernmodule registrieren
- WordPress- und WooCommerce-Hooks anbinden
- Aktivierungs- und Deaktivierungslogik anstoessen
- spätere Module kontrolliert erweitern

### Loader-Klasse

`class-loader.php` kapselt Hook-Registrierung, damit Aktions- und Filteranbindung an einer Stelle zusammengefasst bleibt.

## Modulzuschnitt

### 1. Catalog-Modul

Zweck:

- Übernahme bestehender projektspezifischer Kataloglogik aus dem Kadence-Theme
- Breadcrumb-Anpassungen für `Sortiment`
- Ausblenden von Unterkategorie-Anzahlen
- Kategoriebeschreibung unter Unterkategorien
- Responsives Footer-Burger-Menü (analog zum Header-Menü, Grid-basiert)
- Wiederverwendbarer Adressblock-Shortcode `[barmbini_address]`
- Shortcode `[barmbini_latest_news]` für die letzten Neuigkeiten-Beiträge
- Custom Post Type `barmbini_aktion` für zeitlich begrenzte Aktionen (Start-/Enddatum, Flyer-Bild, optionaler Link)
- Shortcode `[barmbini_promotion]` für aktuell gültige Aktionen auf der Startseite
- Shortcode `[barmbini_top_product_categories]` für die Sortiment-Seite (Top-Level-Produktkategorien als gruppiertes Grid)

Wichtig:

- Dieses Modul reduziert Theme-Abhaengigkeit.
- Es enthaelt keine kundenspezifische Benachrichtigungslogik.
- `class-catalog-hooks.php` (Barmbini_Core_Catalog_Hooks) setzt die Katalog-Hooks: entfernt die Standard-WooCommerce-Breadcrumb-, Ergebnisanzahl- und Sortier-Hooks und verknüpft die eigene Breadcrumb (`class-breadcrumbs.php`) via `woocommerce_before_main_content`. Außerdem blendet es Unterkategorie-Anzahlen aus (`remove_subcategory_count`) und stellt per `enqueue_styles()`/`get_inline_styles()` die thematischen Catalog-CSS-Regeln bereit — u. a. `woocommerce-breadcrumb` Einrückung (außen auf 15px mit `!important`, da Kadence-Ladereihenfolge), Ausblenden von `.kadence-breadcrumbs` und den Hover-Kategoriebeschreibungen. Zusätzlich:
  - **Produktgalerie**: `.woocommerce-product-gallery__image img:not(.zoomImg)` → `width/height: max(25vw, 170px) !important` mit `object-fit: cover` (quadratisch, zentriert); erfasst alle Galerie-Slides, der `zoomImg`-Klon bleibt ausgenommen. `!important` nötig, weil WooCommerce `div.product div.images img {height:auto}` höhere Spezifität hat. Minimum **170 px**, darüber `25vw` der Fensterbreite.
  - **Beispiel-Badge**: `render_example_badge()` injiziert bei Produkten mit Produkt-Schlagwort `Beispiel` (`has_term('beispiel', 'product_tag')`) ein `<span class="barmbini-example-badge onsale">Beispiel</span>` — via `woocommerce_before_shop_loop_item_title` (Prio 10, Loop) und `woocommerce_before_single_product_summary` (Prio 10, Einzelseite). Die Klasse `onsale` sorgt für identische Größe/Schrift wie „Angebot!“; Position links oben, auf Einzelseiten unter dem onsale-Badge (`top: 44px`) gestapelt, Farbe `#2d6a4f`.
  - **Beispiel-Hinweis**: `render_example_notice()` gibt auf `is_shop()`/`is_product_category()` einen grünen Balken „Die gezeigten Artikel dienen als Beispiele…“ aus (Klasse `.barmbini-example-notice`, Hook `woocommerce_before_main_content` Prio 30).
- `class-catalog-hooks.php` lädt zusätzlich über `enqueue_global_styles()`/`get_global_inline_styles()` einen **globalen** Inline-Style (Handle `barmbini-core-global`, auf allen Seiten, unabhängig von WooCommerce): `.entry-hero-container-inner .entry-header { min-height: 120px !important }` — reduziert die Höhe des Kadence-Titelbanners auf inneren Seiten von 200 px auf **120 px**. Die Startseite (eigener Hero-Layout) und Produktseiten (kein Titelbanner) bleiben unverändert.
- `class-homepage-layout.php` (Barmbini_Core_Homepage_Layout) lädt nur auf `is_front_page()` das Stylesheet `assets/css/homepage-hero.css`. Damit bleibt der Startseiten-Hero (Block-ID `.kb-row-layout-id13_93d54b-9c`) bis **600 px** zweispaltig (`grid-template-columns: repeat(2, minmax(0,1fr)) !important`), damit das Logo nicht überbreit gestapelt wird. Hinweis: Die Block-ID kann sich bei Neu-Erstellung des Hero-Blocks ändern (CSS-Kommentar).
- `class-promotion-shortcode.php` rendert die Aktions-Karten mit `assets/css/promotion.css`: max. **500 px** Breite, Grid `minmax(300px, 500px)`, zentriert.
- `class-footer-menu.php` steuert das mobile Footer-Menü per CSS/JS/Grid.
- `class-address-shortcode.php` stellt den Adressblock als Shortcode bereit (Daten in `wp_options`). Das Telefonfeld wird als anklickbarer `tel:`-Link ausgegeben (nur Ziffern/`+`, z. B. `tel:04042945339`).
- `class-latest-news-shortcode.php` stellt die letzten Beiträge aus der Kategorie "Neuigkeiten" als Shortcode bereit (Attribute: `count`, `show_excerpt`, `show_date`, `empty_message`).
- `class-promotion-post-type.php` registriert den CPT `barmbini_aktion` (capability_type='post', rewrite-Slug 'aktion', has_archive=true) mit Metaboxen für Gültigkeitszeitraum und Startseiten-Anzeige, Archiv-Filtern (Aktiv/Archiv/Alle), Template-freier Einzelansicht (the_content-Filter), Rewrite-Flush und Kategorie-Cleanup. Enthält außerdem `filter_archive_for_visitors()` (`pre_get_posts`): Blendet im Frontend-Archiv für Besucher ohne Redakteurs-/Admin-Rolle (`current_user_can('edit_others_posts')`) Aktionen mit zukünftigem Startdatum aus (Meta-Abfrage: `_barmbini_promotion_start_date <= heute OR NOT EXISTS`). Admins/Redakteure sehen das ungefilterte Archiv.
- `class-promotion-shortcode.php` stellt den Shortcode `[barmbini_promotion]` bereit (Attribute: `show_image`, `show_date`, `show_description`, `empty_message`). Gezeigt werden nur Aktionen, deren Zeitraum das heutige Datum umfasst. Flyer und Titel verlinken auf die Einzelansicht.
- `class-top-product-categories-shortcode.php` stellt den Shortcode `[barmbini_top_product_categories]` bereit (Attribute: `columns`, `hide_empty`, `exclude`, `move_last`, `parent`, `orderby`, `order`). Er rendert die Top-Level-Produktkategorien als gruppierte Grids, jeweils mit Unterkategorien und/oder der Kategorie selbst, in Sektionen mit Überschrift und Trenner. Wurde aus dem MU-Plugin `mu-plugins/barmbini-sortiment-shortcodes.php` ins Plugin migriert. Auf der Seite „Sortiment" wird er mit `[barmbini_top_product_categories columns="4" hide_empty="0" exclude="60"]` verwendet.
- `class-cache-maintenance.php` (Barmbini_Core_Cache_Maintenance) plant einen WP-Cron-Job, der alle 6 Stunden den **WP Fastest Cache** leert (Standard-Hook `wpfc_clear_all_cache` + Verzeichnis-Fallback + `wp_cache_flush`). Zweck: Zeitlich begrenzte Inhalte (z. B. abgelaufene Aktionen des CPT `barmbini_aktion`) verschwinden zuverlässig von der Startseite, da die Free-Version von WP Fastest Cache keine native Cache-Lebensdauer kennt und rein datumsbasierte Änderungen keine Cache-Invalidierung auslösen. Cron-Hook: `barmbini_core_cache_maintenance`, Intervall `barmbini_core_6_hours`. Deaktivierung räumt das Ereignis auf (`class-deactivator.php`).

### 2. Account-Modul

Zweck:

- WooCommerce-Endpoint `abonnements` im Bereich `Mein Konto`
- Laden, Validieren und Speichern der Abo-Einstellungen
- Darstellung der Frequenzwahl für `sofort`, `täglich`, `wöchentlich`
- Benutzerregistrierung und DSGVO-Einwilligung über `/mein-konto/`

Verantwortung:

- Form-Rendering
- Nonce-Pruefung
- Sanitizing
- Rückmeldungen nach dem Speichern
- `class-account-endpoint.php` (Barmbini_Core_Account_Endpoint): über `register_registration_features()` werden die Hooks für die Benutzerregistrierung gesetzt (via `woocommerce_register_form`/`woocommerce_registration_errors`/`woocommerce_created_customer`):
  - **DSGVO-Pflicht-Checkbox** bei der Registrierung (`render_privacy_consent_checkbox`); ohne Zustimmung wird die Registrierung abgelehnt (`validate_privacy_consent`).
  - **Einwilligungs-Protokollierung**: nach erfolgreicher Registrierung werden `barmbini_consent_at` und `barmbini_consent_source = 'registration'` gespeichert (`save_privacy_consent` → `Subscription_Settings::update_consent()`).
  - **E-Mail-Absender**: `wp_mail_from` → `info@barmbini.de`, `wp_mail_from_name` → `Barmbini Sozialkaufhaus` für alle Mails.
  - **Redirect**: nach nativer WP-Registrierung auf die WooCommerce-Account-Seite (`registration_redirect`).
  - **Admin-Benachrichtigung**: bei neuer Kundenregistrierung erhält `info@barmbini.de` eine E-Mail mit Benutzername, E-Mail und Zeitpunkt (`notify_admin_new_user`).
  - **Passwort-Anforderung**: nur noch mindestens 6 Zeichen. Filter `password_hint` ersetzt die Standard-Meldung (`password_hint()`), `woocommerce_min_password_strength` wird auf 0 gesetzt (`password_min_strength()`), und eine eigene Mindestlängen-Prüfung läuft auf `user_profile_update_errors`, `woocommerce_save_account_details_errors` und `woocommerce_registration_errors` (`validate_password_length_*` mit Helfer `get_submitted_password()`).
  - **Konto-Dashboard**: `add_menu_item()` blendet `orders`, `downloads`, `edit-address` und `payment-methods` aus (reiner Katalog ohne Checkout). `register_registration_features()` entfernt den Standard-Dashboard-Renderer (`woocommerce_account_content`) und bindet `render_custom_dashboard()`: „Hallo … (Abmelden)“ plus „Von Ihrem Konto-Dashboard aus können Sie Ihr Passwort und Ihre Kontodetails bearbeiten.“; der WooCommerce-Hook `woocommerce_account_dashboard` wird weiterhin ausgelöst. **Wichtig:** `render_custom_dashboard()` prüft zuerst die `query_vars` und reicht spezifische Endpoints (z. B. `abonnements`, `edit-account`) an ihre eigenen Renderer durch — nur die reine Dashboard-Seite (kein Endpoint aktiv) rendert den angepassten Text. (Regressions-Fix: Ohne diese Prüfung hätte der Dashboard-Override alle Endpoints überdeckt.)

### 3. Notifications-Modul

Zweck:

- Erfassen von fachlich relevanten Ereignissen
- Sofortversand für `sofort`
- Queue-Aufbau für `täglich` und `wöchentlich`
- Digest-Lauf und Versand
- Dubletten-Schutz
- Abmeldung und Tokenpruefung

Dieses Modul ist der eigentliche Kern der neuen Funktion.

### 4. Admin-Modul

Zweck:

- Sicht auf aktive Abonnements
- Sicht auf Versandereignisse und Fehler
- Basis für spätere Support-Werkzeuge

Empfohlene Platzierung im Backend:

- Untermenue unter `WooCommerce`
- alternativ unter `Werkzeuge`, falls die Oberflaeche nur technisch orientiert sein soll

Zusätzlich:

- `class-address-settings.php` (Barmbini_Core_Address_Settings) stellt die Unterseite **Einstellungen → Barmbini Adresse** bereit (Settings-API, Option `barmbini_address_data`), über die die zentralen Adress-/Kontaktdaten inkl. Telefon im Admin gepflegt werden. Registriert in `class-plugin.php`/`register_address_settings_module()`.

### 5. Privacy-Modul

Zweck:

- Protokollierung der Einwilligung
- spätere Export- oder Löschunterstuetzung
- technische Grundlage für Datenschutzanfragen

### 6. Security-Modul

Zweck:

- Härtung der REST-API gegen öffentliches Auslesen von Benutzernamen
- Server-Sicherheitsthemen, die sich im Plugin abbilden lassen (ohne nginx-/Server-Eingriff)

Verantwortung:

- `class-rest-api-hardening.php` (Barmbini_Core_Rest_Api_Hardening): sperrt via `rest_endpoints`-Filter die Benutzer-Routen (`/wp/v2/users`, `users/{id}`, `users/me`, `users/{id}/posts`) für Aufrufer ohne `list_users`-Berechtigung. Dadurch liefert `GET /wp-json/wp/v2/users` keinen Benutzernamen mehr an nicht angemeldete Besucher (HTTP 404 `rest_no_route`). Angemeldete Administratoren behalten vollen Zugriff. Deaktiviert zusätzlich XML-RPC (`xmlrpc_enabled` → `false`), wodurch WordPress-XML-RPC-Methoden (z. B. `wp.getUsersBlogs`) mit Fehler 405 „XML-RPC-Dienst deaktiviert" antworten. Registriert in `class-plugin.php`/`register_security_module()`.
- `class-contact-form-honeypot.php` (Barmbini_Core_Contact_Form_Honeypot): setzt einen minimalen, datenschutzfreundlichen Spam-Schutz für das Contact Form 7-Kontaktformular um. Ein per CSS verstecktes Feld (`your-website`) wird von Spambots typischerweise ausgefüllt; der Filter `wpcf7_spam` markiert die Einreichung dann als Spam. Kein externer Dienst, keine Cookies. Registriert in `class-plugin.php`/`register_contact_form_honeypot_module()`.
- `class-login-limiter.php` (Barmbini_Core_Login_Limiter): begrenzt fehlgeschlagene Anmeldeversuche pro IP (5 Fehlversuche → 15 Minuten Sperre) über Transients; Filter `authenticate` + Actions `wp_login_failed`/`wp_login`. Keine Cookies, keine externen Dienste. Registriert in `class-plugin.php`/`register_login_limiter_module()`.

Hinweis:

- Reine Server-Maßnahmen (nginx-Sicherheitsheader, `readme.html`, `xmlrpc.php`, HTTPS) liegen außerhalb des Plugins und werden über Server-Runbooks dokumentiert (`Tasks/Barmbini_Aufgabe_Sicherheit_HTTP_Header.md`, `Tasks/Barmbini_Aufgabe_Sicherheit_Information_Disclosure.md`).

### 7. Rollen-Modul

Zweck:

- Migration der früheren Projektrolle **„Verkäufer“** (`barmbini_verkaeufer`) zur WooCommerce-Standardrolle **Shop Manager** (`shop_manager`)
- die Standardrolle `editor` („Redakteur“) bleibt unangetastet (Kernrollen werden nicht umbenannt)
- nach der Migration existiert keine eigene Projektrolle mehr – Shop Manager ist eine WooCommerce-Standardrolle (Produkte, Kategorien, Preise, Lagerstatus, Papierkorb, endgültiges Löschen)

Verantwortung:

- `class-roles.php` (Barmbini_Core_Roles):
  - `migrate_legacy_seller_role()`: hängt alle Nutzer der Rolle `barmbini_verkaeufer` per `add_role('shop_manager')`/`remove_role('barmbini_verkaeufer')` um und entfernt die Alt-Rolle danach idempotent über `remove_role()` (No-op, wenn Alt-Rolle oder `shop_manager` fehlt)
  - Selbstheilung über `admin_init` (nur für `manage_options`-Nutzer), damit die Migration auch nach einem reinen Code-Deploy ausgeführt wird
  - Registriert in `class-plugin.php`/`register_roles_module()`; in `class-activator.php` keine Rollen-Anlage mehr nötig (Standardrollen verwaltet WooCommerce)

### 8. Anleitungs-Modul

Zweck:

- interne, ausführliche Schritt-für-Schritt-Anleitung für die Rolle **„Redakteur“**
- nur die Seite `/anleitung-redakteur/` (Capability `barmbini_view_guide_redakteur` für Administrator + Redakteur)
- Einstieg über Admin-Menüpunkt „Anleitungen“ und Links in der Admin-Bar

Verantwortung:

- `class-staff-guides.php` (Barmbini_Core_Staff_Guides):
  - legt die Frontend-Seite `/anleitung-redakteur/` idempotent an (`ensure_pages()` via `get_page_by_path`/`wp_insert_post`)
  - eine Capability: `barmbini_view_guide_redakteur` (Administrator + Redakteur); die veralteten Capabilities `barmbini_view_guide_verkaeufer` und `barmbini_view_guides` werden aus allen Rollen entfernt
  - seit 0.9.1 gibt es **keine Shop-Manager-Anleitung** mehr: Die frühere Seite `/anleitung-verkaeufer/` wird bei `admin_init` automatisch in den Papierkorb verschoben (`maybe_remove_obsolete_verkaeufer_page()` via `wp_trash_post`)
  - Gating über `template_redirect`: ohne Capability → Login-Umleitung (nicht angemeldet) bzw. Umleitung zur Startseite (angemeldet); Seite mit `noindex` gegen Suchmaschinen-Indexierung
  - Admin-Menüpunkt „Anleitungen“ (Landingpage zeigt die Karte) + Admin-Bar-Link (nur zugänglich)
  - Registriert in `class-plugin.php`/`register_staff_guides_module()`

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
- `barmbini_actions_enabled`
- `barmbini_actions_frequency`
- `barmbini_subscription_updated_at`

Hinweis: Die Felder `barmbini_discount_*` und `barmbini_category_*` existieren weiterhin im Code (inkl. Trigger), werden aber im Formular seit 2026-08-10 nicht mehr angeboten (Abo-Optionen auf Neuigkeiten und Aktionen reduziert).
- `barmbini_consent_at`
- `barmbini_consent_source`
- `barmbini_unsubscribe_token_hash`

Bewusst nicht vorgesehen:

- eigene Frequenz pro einzelner Kategorie
- unstrukturierte Serialisierung verschiedener Fachobjekte in ein einziges Meta-Feld

### Tabelle `wp_barmbini_notification_queue`

Zweck:

- Vormerkung geplanter Digest-Einträge
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

Empfohlene Oberflaeche im Endpoint `Abonnements` (Ist-Stand 2026-08-10: nur Neuigkeiten und Aktionen; Rabatte/Produktkategorien ausgeblendet, Logik reversibel):

1. Checkbox `Neuigkeiten abonnieren`
2. Select `Neuigkeiten Frequenz`
3. Checkbox `Aktionen abonnieren`
4. Select `Aktionen Frequenz`
9. Speichern
10. Link oder Aktion `Alle Benachrichtigungen kündigen`

Empfohlene Select-Werte:

- `sofort`
- `täglich`
- `wöchentlich`

UI-Regeln:

- keine Vorauswahl auf `täglich` oder `wöchentlich` ohne aktive Zustimmung
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

- `init` für Endpoint-Registrierung
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

### Aktions-Trigger (Startdatum, Variante A)

Benachrichtigung erfolgt **nicht beim Veröffentlichen**, sondern per **Cron** sobald das Startdatum einer Aktion erreicht ist.

Mechanik (Ist-Stand 2026-08-10):

- Cron-Job `barmbini_core_action_start_notifier` (täglich, 08:00), geplant via `Event_Collector::schedule_action_notifier()` auf `init` (`wp_next_scheduled()`-Prüfung)
- `Event_Collector::handle_scheduled_action_starts()` lädt alle `barmbini_aktion` (publish) ohne Meta-Flag `_barmbini_action_start_notified`
- Nur wenn `_barmbini_promotion_start_date === heute` → Versand an Benutzer mit aktivem `barmbini_actions_enabled` (Event-Typ `aktion`, Betreff „Neue Aktion bei Barmbini", Frequenz `sofort`/`täglich`/`wöchentlich`)
- Meta-Flag `_barmbini_action_start_notified = 1` nach Versand → Duplikat-Schutz (einmalige Benachrichtigung)
- Kein rückwirkender Versand (nur Startdatum = heute, nicht `<`)
- Deaktivator räumt den Cron auf (`class-deactivator.php`)
- Der frühere Veröffentlichungs-Trigger (`transition_post_status` → `publish`) für `barmbini_aktion` wurde entfernt

### Rabatt-Trigger

Empfohlener technischer Ansatz:

- Hook auf Produktspeicherung, kombiniert mit einer eigenen `Discount_State_Detector`-Klasse

Wichtig:

- nicht blind bei jeder Produktspeicherung versenden
- vorherigen Rabattzustand gegen aktuellen aktiven Rabattzustand vergleichen
- das Ergebnis als eigenen Status oder Fingerprint speichern

### Cron- und Digest-Läufe

Empfohlene Events:

- `barmbini_core_daily_digest`
- `barmbini_core_weekly_digest`

Empfohlene Ausfuehrung:

- in Entwicklungsumgebungen kann WP-Cron ausreichen
- für Live-Betrieb ist ein echter Server-Cron robuster, der `wp cron event run` oder WP-CLI gesteuert auslöst

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
2. Passende Benutzer mit Frequenz `täglich` oder `wöchentlich` werden bestimmt.
3. Queue-Einträge werden angelegt oder aktualisiert.
4. Geplanter Lauf sammelt die offenen Einträge pro Benutzer.
5. Vor dem Versand wird geprueft, ob das Abo noch aktiv ist.
6. Digest-E-Mail wird erstellt und versendet.
7. Queue und Versandlog werden aktualisiert.

Wichtige Regel:

- Eine spätere Abmeldung muss noch nicht versendete Queue-Einträge technisch entwerten können.

## E-Mail-Konzept

Es werden zwei Mailtypen benoetigt:

1. Sofortmail für ein einzelnes Ereignis
2. Digest-Mail für mehrere Ereignisse eines Zeitraums

Pflichtbestandteile jeder Mail:

- klarer Betreff
- deutschsprachiger Inhalt
- Link zum relevanten Beitrag oder Produkt
- Link zur Abmeldung
- Hinweis auf die gewählte Versandfrequenz bei Digest-Mails

## Sicherheits- und Datenschutzkonzept

- Tokens für Abmeldelinks nur gehasht speichern, nicht im Klartext.
- Formularspeicherung nur mit Nonce und Berechtigungspruefung.
- Eingaben konsequent validieren und escapen.
- Keine versteckte oder vorausgewählte Einwilligung.
- Bei Export- oder Löschanfragen muessen Abo- und Versanddaten technisch auffindbar sein.
- Abmeldungen muessen auch Queue-Einträge für künftige Digests sperren.

## Aktivierung, Deaktivierung, Uninstall

### Aktivierung

Beim Aktivieren des Plugins:

- Tabellen für Queue und Versandlog anlegen
- Cron-Events registrieren
- Standardoptionen setzen, falls noetig

### Deaktivierung

Beim Deaktivieren des Plugins:

- Cron-Events sauber entfernen
- keine fachlichen Daten automatisch löschen

### Uninstall

Vorsichtige Empfehlung:

- keine automatische Löschung von `usermeta` und Versandhistorie ohne explizite Administratorentscheidung
- Datenschutzrelevante Löschungen besser über eine separate Admin-Aktion oder ein explizites Cleanup-Flag steuern

## Migration bestehender Theme-Logik

`barmbini-core` soll nicht nur neue Abo-Logik aufnehmen, sondern auch die bereits vorhandenen projektspezifischen WooCommerce-Anpassungen aus `themes/kadence/functions.php` übernehmen.

Empfohlene Reihenfolge:

1. bestehende Katalog- und Breadcrumb-Hooks zuerst in das Catalog-Modul verschieben
2. danach Account- und Notification-Module einbauen
3. erst nach erfolgreicher Übernahme die Theme-Datei bereinigen

So bleibt die Einfuehrung von `barmbini-core` nicht nur eine neue Funktion, sondern auch eine technische Bereinigung des bisherigen Zustands.

## Rollout-Empfehlung

### Phase 1

- Plugin-Grundgerüst
- Aktivierungslogik
- Catalog-Modul für bestehende Theme-Hooks

### Phase 2

- Account-Endpoint
- `usermeta`-Persistenz
- Einwilligungs- und Abmeldelogik

### Phase 3

- Sofortbenachrichtigungen für News, Produkte und Rabatte
- Versandlog

### Phase 4

- Queue-Tabelle
- Daily- und Weekly-Digest
- Admin-Ansicht für Versandstatus

### Phase 5

- Datenschutz-Exporthilfen
- Support-Werkzeuge
- weitere Bereinigung alter Theme-Logik

## Risiken und offene Punkte

- Die Rabatt-Erkennung ist fachlich anspruchsvoller als News- oder Produktveröffentlichungen und braucht eine saubere Zustandspruefung.
- WP-Cron allein kann für Digests auf einer traffic-armen Website unzuverlässig sein.
- Bei späterer Einfuehrung externer Versanddienste darf die Plugin-Architektur nicht auf einen bestimmten Anbieter fest verdrahtet sein.
- Rechtliche Texte muessen parallel zur technischen Einfuehrung aktualisiert werden.

## Abnahmebild für die erste Architekturversion

Die Architektur ist passend, wenn folgende Punkte erfuellt sind:

1. `barmbini-core` ist die zentrale Stelle für projektspezifische Fachlogik.
2. Neue Benachrichtigungslogik liegt nicht im Kadence-Theme.
3. Kontoeinstellungen, Queue, Versandlog und Abmeldung sind sauber getrennt.
4. Sofort-, Daily- und Weekly-Versand können ohne Architekturbruch gemeinsam betrieben werden.
5. Bestehende Kataloganpassungen können kontrolliert aus dem Theme in das Plugin übernommen werden.
6. Datenschutz, Dubletten-Schutz und Support-Sicht sind technisch berücksichtigt.

## Deployment-Tooling

Zum Workspace gehören drei PowerShell-Skripte für den Entwicklungs-Workflow:

| Skript | Zweck | Befehl |
|---|---|---|
| `sync.ps1` | Lokaler Sync (Workspace ↔ Local) | `.\sync.ps1` (Push), `.\sync.ps1 -Pull` |
| `deploy.ps1` | Deployment auf den Server | `.\deploy.ps1` (Modus B), `.\deploy.ps1 -Full -Force` |
| `dump-db.php` | Datenbank-Dump per HTTP | `curl -k https://barmbini.local/dump-db.php` |

`deploy.ps1` unterstützt folgende Flags:
- `-Full`: Modus A (Code + SQL-Vollimport)
- `-Force`: Erstellt vor dem Deployment einen frischen SQL-Dump via `dump-db.php`
- `-NoBackup`: Überspringt das Server-Backup
- `-NoBrowser`: Kein Browser-Tab nach Deployment

`sync.ps1` erkennt neue Dateien automatisch (auto-discover via `Get-ChildItem`).

Zusätzlich leert `sync.ps1` (und das Pendant `sync.sh`) nach einem **Push** mit kopierten Dateien automatisch den **WP Fastest Cache** in der lokalen Installation (`D:\Local Sites\barmbini\app\public\wp-content\cache\all`), sodass CSS-/JS-/PHP-Änderungen sofort nach dem Browser-Reload sichtbar sind. Bei `-Pull` oder ohne kopierte Dateien wird der Cache nicht angefasst.

### Deployment von Cron-basierten Funktionen (z. B. Cache-Maintenance)

WP-Cron-Ereignisse werden in der Datenbank (`wp_options.cron`) gespeichert. Reine Code-Deployments (**Modus B**) importieren keine SQL-Daten — dennoch ist **kein manueller DB-Schritt** nötig: Die Cron-Funktionen von `barmbini-core` (z. B. `Barmbini_Core_Cache_Maintenance::schedule_event()`) prüfen auf `init` mit `wp_next_scheduled()` und planen ihr Ereignis beim **nächsten Seitenaufruf nach dem Deployment** automatisch selbst.

Voraussetzungen:
- `DISABLE_WP_CRON` darf nicht gesetzt sein (WP-Cron feuert dann bei Seitenaufrufen). Auf dem Server `217.160.74.128` ist das aktuell der Fall.
- Bei sehr geringem Traffic können Cron-Takte zeitlich verzögern; ein externer Cron-Aufruf von `wp-cron.php` wäre dann die Option.
- Der Deaktivator (`class-deactivator.php`) räumt das Cron-Ereignis bei Plugin-Deaktivierung auf.

## Adressblock-Shortcode

`[barmbini_address]` gibt einen formatierten Adressblock aus (identisch zur Seite /barrierefreiheit/). Die Daten sind zentral in `wp_options` (`barmbini_address_data`) gespeichert:
- `shortname`, `name`, `street`, `address2`, `zip`, `city`, `phone`, `email`
