# Detaillierte Aufgabe: Benutzerregistrierung und Kontobearbeitung

## Ziel

Besucher der Website sollen sich selbstständig registrieren und ihr eigenes Benutzerkonto bearbeiten können. Die Registrierung und Kontoverwaltung wird über die bestehende WooCommerce-Account-Seite (`/mein-konto/`) abgewickelt.

Der Funktionsumfang gliedert sich in drei aufeinander aufbauende Stufen:

| Stufe | Ziel | Aufwand |
|---|---|---|
| 1 | Bestand verifizieren und produktiv machen | ~1 h |
| 2 | Qualität, Rechtssicherheit und UX verbessern | ~3 h |
| 3 | Komfort und optionale Erweiterungen | ~5 h |

## Quellenbasis

Die Aufgabe basiert auf:

- `Barmbini_Technisches_Konzept_v2.5.md` — insbesondere §7 (Kundenkonto)
- `Barmbini_Plugin_Architektur_barmbini-core.md` — Zielstruktur, Modulzuschnitt Account, §2 Account-Modul
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` — verifizierter Ist-Stand und Einbauorte
- `Docs/Barmbini_Rechtliche_Seiten.md` — Datenschutzerklärung, Impressum
- `Barmbini_Aufgabe_Kundenkonto_Abonnements_und_Benachrichtigungen.md` — bestehende Abo-Logik
- dem vorhandenen Account-Modul: `wp-content/plugins/barmbini-core/includes/account/`
- der bestehenden WooCommerce-Account-Infrastruktur (Seite `/mein-konto/`, Endpoint `abonnements`)

## Fachliche Leitplanken

- Die Website ist einsprachig deutsch.
- WooCommerce dient als Produktkatalog — es gibt keinen Checkout, keinen Warenkorb und keine Zahlungslogik. Die WooCommerce-Account-Funktionen werden ausschließlich für die Benutzerverwaltung genutzt.
- Barmbini ist eine gemeinnützige Einrichtung. Die Registrierung muss datenschutzfreundlich und DSGVO-konform sein (keine unnötigen Pflichtfelder, klare Einwilligung, transparente Speicherung).
- Neue Fachlogik gehört in `barmbini-core`, nicht ins Kadence-Theme.
- Keine neuen Plugins, wenn es ohne geht. WP/WooCommerce-Bordmittel bevorzugen.

## Verifizierter Ist-Stand (2026-08-10)

| Komponente | Status |
|---|---|
| WordPress `users_can_register` | **AN** |
| Standardrolle neue Benutzer | `subscriber` |
| WooCommerce Account-Seite | `/mein-konto/` existiert, enthält Login-/Register-Formular und Dashboard |
| barmbini-core Endpoint `abonnements` | registriert, speichert Abo-Einstellungen in usermeta |
| WooCommerce „Kontodetails"-Bereich | Standard: Name, E-Mail, Passwort-Änderung |
| Datenschutz-Checkbox bei Registrierung | fehlt (keine WooCommerce-eigene DSGVO-Checkbox) |
| E-Mail-Versand (wp_mail) | ungetestet — muss validiert werden |
| Redirect nach Registrierung | Standard: wp-login.php |
| Hauptmenü-Eintrag „Ihr Konto" | vorhanden (→ `/mein-konto/`) |

## Aufgabe – Stufe 1: Bestand verifizieren (~1 h)

### Ziel

Sicherstellen, dass der bereits vorhandene Registrierungs- und Login-Prozess von Anfang bis Ende funktioniert — ohne Code-Änderungen.

### Arbeitsschritte

1. **Registrierung testen (Frontend)**
   - `/mein-konto/` als abgemeldeter Besucher öffnen
   - Das Registrierungsformular ausfüllen (E-Mail + Passwort)
   - Prüfen ob die Registrierung erfolgreich ist
   - Prüfen ob die WordPress-Registrierungsmail („Dein Konto wurde erstellt …") ankommt

2. **Login und Konto-Dashboard testen**
   - Mit dem neu registrierten Konto auf `/mein-konto/` einloggen
   - Dashboard prüfen: Übersicht, „Abonnements" (barmbini-core), „Kontodetails"
   - Name und Passwort im Bereich „Kontodetails" ändern
   - Ausloggen und mit neuen Daten erneut einloggen

3. **Abonnements testen**
   - Im Bereich „Abonnements" Einstellungen ändern (News/Rabatte/Kategorien)
   - Speichern und prüfen ob die Werte in `usermeta` bestehen bleiben

4. **E-Mail-Versand validieren**
   - Passwort-Reset-E-Mail anfordern und prüfen
   - Falls Mails nicht ankommen: wp-mail-logging aktivieren oder SMTP-Plug-in prüfen
   - Absenderadresse prüfen (kein `wordpress@...`)

5. **Fehlerfälle testen**
   - Registrierung mit bereits verwendeter E-Mail
   - Registrierung mit ungültiger E-Mail
   - Passwort-Reset mit unbekannter E-Mail
   - Leeres Formular absenden

### Abnahmekriterium Stufe 1

- [ ] Ein Besucher kann sich auf `/mein-konto/` registrieren
- [ ] Die Registrierungsmail kommt an (Absender nicht `wordpress@`)
- [ ] Login funktioniert mit den registrierten Daten
- [ ] Kontodetails können bearbeitet werden
- [ ] Abonnements können konfiguriert werden
- [ ] Passwort-Reset funktioniert
- [ ] Fehlerfälle zeigen verständliche Meldungen (deutsch)

---

## Aufgabe – Stufe 2: Qualität & Recht (~3 h)

### Ziel

Die Registrierung rechtssicher und benutzerfreundlich machen.

### Arbeitsschritte

1. **Datenschutz-Checkbox bei Registrierung** (Pflicht)
   - Hook: `woocommerce_register_form`
   - Checkbox: „Ich habe die Datenschutzerklärung gelesen und stimme der Verarbeitung meiner Daten zu."
   - Validierung: `woocommerce_registration_errors` — Ablehnung, wenn nicht angehakt
   - Speicherung der Einwilligung: `barmbini_consent_at` und `barmbini_consent_source` in usermeta (wie in der Plugin-Architektur vorgesehen)
   - **Einbauort**: `includes/account/class-account-endpoint.php` oder neue `includes/privacy/class-consent-recorder.php`

2. **Datenschutzerklärung aktualisieren**
   - Abschnitt zur Benutzerregistrierung ergänzen:
     - Welche Daten werden gespeichert (E-Mail, Name, Abo-Einstellungen)
     - Zweck der Speicherung (Kontoverwaltung, Benachrichtigungen)
     - Speicherdauer (bis zur Löschung des Kontos)
     - Rechtsgrundlage (Einwilligung, Art. 6 Abs. 1 lit. a DSGVO)
   - **Quelle**: `Docs/Barmbini_Rechtliche_Seiten.md`

3. **E-Mail-Anpassungen**
   - Absender-Name: „Barmbini Sozialkaufhaus" (statt `WordPress`)
   - Absender-Adresse: `info@barmbini.de`
   - Betreff und Inhalt der Registrierungsmail auf Deutsch prüfen
   - **Umsetzung**: Filter `wp_mail_from` und `wp_mail_from_name` im Plugin

4. **Redirect nach Registrierung**
   - Nach erfolgreicher Registrierung auf `/mein-konto/` (Dashboard) umleiten
   - Statt wp-login.php mit kryptischer Erfolgsmeldung
   - **Umsetzung**: Filter `woocommerce_registration_redirect` oder `registration_redirect`

5. **Fehlermeldungen verbessern**
   - Deutsche, verständliche Texte für alle Fehlerfälle
   - Kein technisches Englisch im Frontend („Invalid username" → „Diese E-Mail-Adresse wird bereits verwendet.")
   - **Umsetzung**: Ggf. `gettext`-Filter oder WooCommerce-Fehlerfilter

### Abnahmekriterium Stufe 2

- [ ] Datenschutz-Checkbox erscheint bei der Registrierung und ist Pflicht
- [ ] Einwilligung wird mit Zeitstempel gespeichert
- [ ] Datenschutzerklärung enthält Abschnitt zur Benutzerregistrierung
- [ ] E-Mail-Absender ist „Barmbini Sozialkaufhaus <info@barmbini.de>"
- [ ] Nach Registrierung landet der Benutzer auf `/mein-konto/` (Dashboard)
- [ ] Alle Fehlermeldungen sind auf Deutsch und verständlich

---

## Aufgabe – Stufe 3: Komfort & Erweiterung (~5 h)

### Ziel

Die Account-Verwaltung komfortabler machen und optionale Erweiterungen umsetzen.

### Arbeitsschritte

1. **Profilfelder in „Kontodetails" erweitern**
   - Vorname, Nachname (WooCommerce `billing_first_name`/`billing_last_name` — bereits in usermeta, aber nur im Checkout sichtbar)
   - Per Hook `woocommerce_edit_account_form` ins Formular einblenden
   - Per Hook `woocommerce_save_account_details` speichern
   - **Einbauort**: `includes/account/class-account-endpoint.php`

2. **Eigenes Registrierungs-Shortcode (optional)**
   - Shortcode `[barmbini_register]` für beliebige Seiten
   - Gibt das WooCommerce-Registrierungsformular aus
   - Nützlich für Landingpages oder separate Registrierungsseiten
   - **Einbauort**: `includes/account/class-register-shortcode.php`

3. **Admin-Benachrichtigung bei neuer Registrierung**
   - Hook: `user_register`
   - E-Mail an `info@barmbini.de` mit Benutzername, E-Mail, Zeitpunkt
   - **Einbauort**: im Account-Modul oder als Teil des Notifications-Moduls

4. **Konto-Löschung durch Benutzer (optional)**
   - Button/Link in „Kontodetails"
   - Bestätigungsdialog („Möchten Sie Ihr Konto wirklich löschen? Alle Ihre Daten werden entfernt.")
   - Technisch: `wp_delete_user()` mit Zuweisung aller Inhalte (Beiträge/Kommentare) an einen neutralen Benutzer
   - Rechtlich: DSGVO-konform (Art. 17 — Recht auf Löschung)
   - **Achtung**: Muss mit der rechtlichen Beratung abgestimmt sein — Abo-Einstellungen und Versandhistorie müssen mitgelöscht werden

### Abnahmekriterium Stufe 3

- [ ] Vorname und Nachname können in „Kontodetails" bearbeitet werden
- [ ] Shortcode `[barmbini_register]` funktioniert auf beliebigen Seiten
- [ ] Admin erhält E-Mail bei neuer Registrierung
- [ ] (Optional) Benutzer kann sein Konto selbst löschen
- [ ] Keine Regression in Stufen 1 und 2

---

## Technische Anmerkungen

### Wo der Code hingehört

| Funktion | Empfohlener Ort |
|---|---|
| Datenschutz-Checkbox + Validierung | `includes/account/class-account-endpoint.php` (Hook `register`) oder neue `includes/privacy/class-consent-recorder.php` |
| E-Mail-Absender | `includes/class-plugin.php` (Filter `wp_mail_from`) |
| Redirect nach Registrierung | `includes/account/class-account-endpoint.php` |
| Profilfelder (Name) | `includes/account/class-account-endpoint.php` |
| Shortcode `[barmbini_register]` | `includes/account/class-register-shortcode.php` (neu) |
| Admin-Benachrichtigung | `includes/account/class-account-endpoint.php` |

### Wichtige Hooks

```php
// Datenschutz-Checkbox
add_action( 'woocommerce_register_form', ... );
add_filter( 'woocommerce_registration_errors', ..., 10, 3 );

// E-Mail-Absender
add_filter( 'wp_mail_from', ... );
add_filter( 'wp_mail_from_name', ... );

// Redirect
add_filter( 'registration_redirect', ... );

// Profilfelder
add_action( 'woocommerce_edit_account_form', ... );
add_action( 'woocommerce_save_account_details', ... );

// Admin-Mail
add_action( 'user_register', ... );
```

### Registrierung im Plugin (class-plugin.php)

Jede neue Klasse muss in `class-plugin.php` registriert werden (analog zum bestehenden Account-Modul):

```php
$account_endpoint = new Barmbini_Core_Account_Endpoint();
$this->loader->add_action( 'init', $account_endpoint, 'register' );
$this->loader->add_action( 'wp_enqueue_scripts', $account_endpoint, 'enqueue_styles' );
```

### E-Mail in Local-Umgebung

In der lokalen Entwicklungsumgebung (Local by Flywheel) funktioniert `wp_mail()` möglicherweise nicht. Entweder:
- SMTP-Plug-in installieren (MailHog in Local, WP Mail SMTP, o. ä.)
- wp_mail-Logging aktivieren und Mails im Log prüfen
- Test direkt auf dem Live-Server validieren

## Rechtliche Hinweise

- **Datenschutzerklärung**: MUSS vor dem Go-Live um einen Abschnitt zur Benutzerregistrierung ergänzt werden. Vorlage siehe `Docs/Barmbini_Rechtliche_Seiten.md`.
- **Einwilligung**: Die Zustimmung zur Datenverarbeitung (Checkbox) muss protokolliert werden (Zeitstempel + Quelle in usermeta).
- **Widerruf**: Die Abmeldung von Benachrichtigungen und die Kontolöschung müssen einfach und ohne Hürden möglich sein.
- **Auskunft**: Auf Anfrage muss der Benutzer eine Übersicht seiner gespeicherten Daten erhalten können (→ Privacy-Modul).
- **Server-Sicherheit**: Der Server `217.160.74.128` hatte eine dokumentierte Kompromittierung (siehe `Barmbini_Migrationsdurchfuehrung_2026-04-22.md`). Passworthashes und personenbezogene Daten sind daher besonders sensibel zu behandeln.

## Abgrenzung zu anderen Aufgaben

- **NICHT Teil dieser Aufgabe**: Abonnements und Benachrichtigungen (→ `Barmbini_Aufgabe_Kundenkonto_Abonnements_und_Benachrichtigungen.md`)
- **NICHT Teil dieser Aufgabe**: Checkout, Warenkorb oder Zahlungslogik (kein Bestandteil des Projekts)
- **NICHT Teil dieser Aufgabe**: E-Mail-Design und -Templates für Benachrichtigungen (separate Aufgabe)
- **NICHT Teil dieser Aufgabe**: Admin-Übersicht der Benutzerkonten (WordPress-Bordmittel vorhanden)

## Definition of Done

1. Die drei Stufen sind nacheinander umgesetzt und lokal verifiziert.
2. Die Plugin-Architektur-Doku (`Barmbini_Plugin_Architektur_barmbini-core.md`) ist um neue Klassen/Module ergänzt.
3. Der verifizierte Ist-Stand (`Barmbini_Vorbereitung_Features_und_Bugfixes.md`) ist aktualisiert.
4. Die rechtlichen Dokumente (`Docs/Barmbini_Rechtliche_Seiten.md`) sind um den Datenschutz-Abschnitt ergänzt.
5. Die Seiteninhalte-Doku (`Docs/Barmbini_Seiteninhalte.md`) listet neue Shortcodes (falls vorhanden).
6. Alle Änderungen sind auf GitHub (`origin/main`) gepusht.
7. Für Live-Daten greift Modus B (kein SQL-Import); ggf. müssen DB-Änderungen (neue Optionen, Tabellen) separat dokumentiert werden.
