# Detaillierte Aufgabe: Kundenkonto mit Abonnements, Benachrichtigungen und Kündigung

## Ziel

Das Kundenkonto der Website Sozialkaufhaus Barmbini soll so erweitert werden, dass registrierte Kunden gezielt Benachrichtigungen abonnieren und wieder kündigen können.

Der Kunde soll im eigenen Konto festlegen können, ob er Benachrichtigungen zu folgenden Bereichen erhalten möchte:

- Neuigkeiten
- bestimmten Produktkategorien
- Rabatten

Wenn ein relevantes Ereignis eintritt, muss der Kunde automatisch benachrichtigt werden.

Das bedeutet im Mindestumfang:

- bei neuen Neuigkeiten: Benachrichtigung an alle Kunden mit aktivem Neuigkeiten-Abonnement
- bei neuen Artikeln in einer abonnierten Kategorie: Benachrichtigung an die Kunden, die genau diese Kategorie abonniert haben
- bei neuen Rabatten: Benachrichtigung an die Kunden mit aktivem Rabatt-Abonnement
- jederzeitige Kündigung des Abonnements durch den Kunden

Zusätzlich soll der Kunde für seine Benachrichtigungen selbst festlegen können, in welchem Zeitabstand er passende Informationen erhaelt:

- `sofort`
- `täglich`
- `wöchentlich`

Die Auswahl soll die Menge und den Rhythmus der Benachrichtigungen steuern, ohne die eigentliche Abo-Logik zu ändern.

## Quellenbasis

Die Aufgabe basiert auf:

- `Barmbini_Technisches_Konzept_v2.5.md`
- bestehender WordPress- und WooCommerce-Struktur im Projekt `barmbini`
- der aktuell verwendeten Architektur mit WooCommerce als Produktkatalog ohne Checkout und Zahlung

## Fachliche Leitplanken

Die Umsetzung muss zu den bestehenden Projektgrundsaetzen passen:

- WooCommerce dient als Produktkatalog, nicht als klassischer Shop mit Checkout
- die Website ist einsprachig deutsch
- das Projekt folgt dem Minimalprinzip bei Plugins
- Kundenabonnements muessen DSGVO-konform umgesetzt werden
- Benachrichtigungen erfolgen in der Erstumsetzung per E-Mail

## Verbindliche Annahmen für diese Aufgabe

Damit die Aufgabe umsetzbar und pruefbar ist, gelten für diese Version folgende Annahmen:

1. Das Abonnement ist an ein registriertes Kundenkonto gebunden.
2. Der Benachrichtigungskanal ist E-Mail.
3. `Neuigkeiten` meint WordPress-Beitraege, die in der News-Logik der Website veröffentlicht werden, insbesondere in der Kategorie `Neuigkeiten`.
4. `Kategorien` meint WooCommerce-Produktkategorien aus der Taxonomie `product_cat`.
5. `Rabatt` meint in der Erstumsetzung einen Artikel, der neu in einen aktiven reduzierten Preiszustand wechselt.
6. Eine spätere Erweiterung für komplexe Kampagnen- oder Gutscheinlogik ist möglich, aber nicht Bestandteil dieser Aufgabe.
7. Die Benachrichtigungsfrequenz wird pro Abo-Art gespeichert, nicht pro einzelner Produktkategorie.
8. `täglich` und `wöchentlich` bedeuten Sammelbenachrichtigungen, nicht mehrere einzelne E-Mails pro Ereignis.

## Nicht Bestandteil dieser Aufgabe

Folgende Punkte gehoeren ausdrücklich nicht zum Mindestumfang:

- Push-Benachrichtigungen
- SMS-Benachrichtigungen
- allgemeine Marketing-Automation ausserhalb des Kundenkontos
- anonyme Abonnements ohne Kundenkonto
- vollwertiges CRM oder externer Kampagnen-Builder

## Umzusetzender Funktionsumfang

Die Loesung muss die folgenden fachlichen Faehigkeiten abdecken:

1. Kundenkonto mit Abonnement-Einstellungen
2. Speicherung der Abo-Eigenschaften am Kundenkonto
3. Auswahl konkreter Produktkategorien für Kategorie-Abonnements
4. Auswahl der Benachrichtigungsfrequenz je Abo-Art
5. automatische Benachrichtigung bei passenden Ereignissen
6. individuelle und vollstaendige Kündigung des Abonnements
7. nachweisbare Einwilligung und technische Nachvollziehbarkeit

## Erweiterte Aufgabenbeschreibung: Benachrichtigungsfrequenz

Die bestehende Abonnement-Funktion wird um eine steuerbare Versandfrequenz erweitert.

Der Kunde soll im Kundenkonto nicht nur festlegen können, welche Benachrichtigungen er erhalten möchte, sondern auch in welchem Rhythmus diese versendet werden.

Für die Erstumsetzung gelten folgende fachliche Regeln:

1. Die Frequenz ist je Abo-Art wählbar für `Neuigkeiten`, `Rabatte` und `Produktkategorien`.
2. Für Kategorie-Abonnements gilt eine gemeinsame Frequenz für alle vom Kunden gewählten Produktkategorien.
3. `sofort` bedeutet: passende Ereignisse werden direkt nach Eintritt versendet.
4. `täglich` bedeutet: passende Ereignisse werden gesammelt und einmal pro Tag als Digest versendet.
5. `wöchentlich` bedeutet: passende Ereignisse werden gesammelt und einmal pro Woche als Digest versendet.
6. Eine Änderung der Frequenz wirkt für künftige Benachrichtigungen.
7. Eine Abmeldung muss auch noch nicht versendete Digest-Einträge für künftige Läufe wirksam unterbinden.

## Aufgabe

### 1. Kundenkonto für Abonnements technisch bereitstellen

Ziel: Ein Kunde muss ein nutzbares Konto besitzen, auch wenn die Website kein klassischer Checkout-Shop ist.

Arbeitsschritte:

1. Pruefen, wie Kundenkonten in der aktuellen WooCommerce-Konfiguration angelegt und genutzt werden.
2. Sicherstellen, dass es eine nutzbare Kontoansicht für Kunden gibt, z. B. über `Mein Konto`.
3. Falls erforderlich: Registrierung und Login für Kunden auch ohne Checkout-Pfad sauber verfuegbar machen.
4. Sicherstellen, dass Kundenkonten mit einer geeigneten Rolle, z. B. `customer`, angelegt werden.

Abnahmekriterium:

- Ein Kunde kann sich registrieren oder mit einem vorhandenen Konto anmelden.
- Ein angemeldeter Kunde erreicht einen Bereich, in dem Abo-Einstellungen gepflegt werden können.

### 2. Abo-Eigenschaften am Kundenkonto speichern

Ziel: Das Kundenkonto muss die für Abonnements benoetigten Eigenschaften dauerhaft speichern.

Empfohlene Datenfelder am Kundenkonto:

- `Neuigkeiten abonnieren`: Ja/Nein
- `Neuigkeiten Frequenz`: `sofort`, `täglich`, `wöchentlich`
- `Rabatte abonnieren`: Ja/Nein
- `Rabatte Frequenz`: `sofort`, `täglich`, `wöchentlich`
- `abonnierte Produktkategorien`: Liste von Kategorie-IDs oder Slugs
- `Produktkategorien Frequenz`: `sofort`, `täglich`, `wöchentlich`
- `Abo zuletzt aktualisiert am`
- `Einwilligung erteilt am`
- `Einwilligungsquelle`
- `Abmelde-Token` für sichere E-Mail-Links

Empfohlene technische Speicherung:

- als `usermeta` am WordPress-Benutzerkonto

Abnahmekriterium:

- Die Abo-Einstellungen eines Kunden werden gespeichert und bleiben nach erneutem Login erhalten.

### 3. Bedienoberflaeche im Kundenkonto aufbauen

Ziel: Der Kunde soll seine Abonnements ohne Admin-Zugriff selbst pflegen können.

Pflichtfunktionen in der Kontooberflaeche:

1. Bereich oder Tab `Abonnements`
2. Option `Neuigkeiten abonnieren`
3. Option `Rabatte abonnieren`
4. Mehrfachauswahl für abonnierte Produktkategorien
5. Auswahl der Benachrichtigungsfrequenz für `Neuigkeiten`
6. Auswahl der Benachrichtigungsfrequenz für `Rabatte`
7. Auswahl der Benachrichtigungsfrequenz für Kategorie-Abonnements
8. Speichern-Schaltflaeche
9. Rückmeldung nach erfolgreichem Speichern

Erwartetes Verhalten:

- vorhandene Einstellungen sind beim Öffnen sichtbar
- Kategorien können individuell an- und abgewählt werden
- ein Kunde kann einzelne Abo-Typen aktivieren, ohne alle anderen mit zu abonnieren
- die Versandfrequenz ist für jede Abo-Art klar erkennbar und änderbar
- für Kategorie-Abonnements wird genau eine gemeinsame Frequenz für alle gewählten Kategorien verwendet

Abnahmekriterium:

- Der Kunde kann seine Auswahl und die Versandfrequenz direkt im Konto ändern und speichern.

### 4. Einwilligung und Datenschutz sauber umsetzen

Ziel: Abonnements muessen rechtlich und fachlich nachvollziehbar sein.

Pflichtanforderungen:

1. Keine Abo-Option darf standardmaessig vorausgewählt sein.
2. Der Kunde muss klar erkennen können, welche Benachrichtigungen er aktiviert.
3. Die Einwilligung muss protokolliert werden.
4. Die Datenschutzerklaerung muss den neuen Zweck der Datenverarbeitung abdecken.
5. Jede E-Mail muss eine Abmeldemöglichkeit enthalten.

Empfohlene Protokollierung:

- Zeitstempel der Einwilligung
- Zeitstempel der letzten Änderung
- optional die verwendete Formularquelle oder Kontoansicht

Abnahmekriterium:

- Es gibt keine stillschweigende oder verdeckte Anmeldung zu Benachrichtigungen.
- Die Abo-Verwaltung ist DSGVO-konform dokumentiert und technisch nachvollziehbar.

### 5. Neuigkeiten-Benachrichtigungen auslösen

Ziel: Kunden mit aktivem Neuigkeiten-Abonnement erhalten eine E-Mail, wenn neue Neuigkeiten erscheinen.

Auslöselogik:

1. Wird ein neuer Beitrag veröffentlicht und gehoert fachlich zu `Neuigkeiten`, dann wird eine Benachrichtigung erzeugt.
2. Bei Frequenz `sofort` wird die E-Mail direkt versendet.
3. Bei Frequenz `täglich` oder `wöchentlich` wird das Ereignis für den nächsten passenden Digest vorgemerkt.
4. Die Benachrichtigung wird nur beim erstmaligen Veröffentlichen ausgeloest, nicht bei jeder späteren Bearbeitung.
5. Ein Kunde darf für denselben Beitrag nicht mehrfach benachrichtigt werden, auch nicht innerhalb desselben Digest-Zeitraums.

Pflichtinhalt der E-Mail:

- Betreff mit Hinweis auf eine neue Neuigkeit
- Titel der Neuigkeit
- kurzer Teaser oder Auszug
- Link zum Beitrag
- Link zur Abmeldung

Abnahmekriterium:

- Bei einer neu veröffentlichten Neuigkeit erhalten nur passende Abonnenten eine Benachrichtigung im gewählten Rhythmus.
- Es werden keine Dubletten für denselben Beitrag erzeugt.

### 6. Kategorie-Benachrichtigungen für neue Artikel auslösen

Ziel: Kunden mit Kategorie-Abonnement erhalten eine E-Mail, wenn ein neuer Artikel in einer von ihnen abonnierten Kategorie erscheint.

Auslöselogik:

1. Wird ein Produkt neu veröffentlicht, werden seine Produktkategorien ermittelt.
2. Es werden nur Kunden ausgewählt, die mindestens eine dieser Kategorien abonniert haben.
3. Bei Frequenz `sofort` wird die E-Mail direkt versendet.
4. Bei Frequenz `täglich` oder `wöchentlich` wird das Ereignis für den nächsten passenden Digest vorgemerkt.
5. Ist ein Kunde für mehrere passende Kategorien eingetragen, darf er für dieses Produkt trotzdem nur eine E-Mail oder einen Digest-Eintrag erhalten.
6. Die Benachrichtigung wird beim ersten relevanten Live-Erscheinen des Produkts erzeugt, nicht bei jeder Bearbeitung.

Pflichtinhalt der E-Mail:

- Betreff mit Hinweis auf einen neuen Artikel
- Produktname
- Produktbild, falls sinnvoll verfuegbar
- betroffene Kategorie oder Kategorien
- Link zum Produkt
- Link zur Abmeldung

Abnahmekriterium:

- Ein Kunde wird nur für Produkte in seinen abonnierten Kategorien im gewählten Rhythmus benachrichtigt.
- Ein Kunde bekommt pro Produkt maximal eine Benachrichtigung oder einen Digest-Eintrag.

### 7. Rabatt-Benachrichtigungen auslösen

Ziel: Kunden mit aktivem Rabatt-Abonnement erhalten eine E-Mail, wenn ein neuer Rabatt aktiv wird.

Verbindliche Mindestdefinition für Rabatt in dieser Aufgabe:

- ein Produkt wechselt neu in einen aktiven reduzierten Preiszustand

Das umfasst insbesondere:

- neues Setzen eines Sale-Preises
- Start eines terminierten Sale-Zeitraums

Nicht erforderlich für diese Erstumsetzung:

- komplexe Gutscheinlogik
- manuelle Rabattkampagnen ausserhalb von Produktpreisen

Pflichtlogik:

1. Der Rabatt-Trigger darf nicht bei jeder Produktspeicherung erneut feuern.
2. Bei Frequenz `sofort` wird die E-Mail direkt versendet.
3. Bei Frequenz `täglich` oder `wöchentlich` wird das Ereignis für den nächsten passenden Digest vorgemerkt.
4. Der gleiche Kunde darf für den gleichen Rabatt-Event nicht mehrfach benachrichtigt werden.
5. Die E-Mail muss klar erkennbar machen, welches Produkt rabattiert ist.

Abnahmekriterium:

- Rabatt-Abonnenten erhalten bei neu aktivem Rabatt genau eine passende Benachrichtigung im gewählten Rhythmus.

### 8. Abonnement kündigen und abmelden

Ziel: Der Kunde muss sein Abonnement jederzeit beenden können.

Pflichtfunktionen:

1. Abmeldung einzelner Abo-Typen im Kundenkonto
2. Abwahl einzelner Produktkategorien im Kundenkonto
3. Vollstaendige Abmeldung aller Benachrichtigungen im Kundenkonto
4. Abmeldelink in jeder Benachrichtigungs-E-Mail
5. Sichere tokenbasierte Abmeldung ohne Admin-Eingriff

Erwartetes Verhalten:

- Die Abmeldung wirkt sofort.
- Nach der Abmeldung werden keine weiteren passenden E-Mails mehr versendet.
- Eine teilweise Abmeldung, z. B. nur von Rabatten, darf andere Abo-Arten nicht automatisch entfernen.
- Noch nicht versendete Digest-Einträge duerfen nach einer wirksamen Abmeldung nicht mehr an den Kunden ausgeliefert werden.

Abnahmekriterium:

- Ein Kunde kann einzelne oder alle Abonnements jederzeit selbst kündigen.

### 9. Benachrichtigungsversand technisch sauber umsetzen

Ziel: Die Benachrichtigungen muessen robust, nachvollziehbar und ohne unkontrollierte Dubletten verschickt werden.

Empfohlene technische Umsetzung:

1. eigene Projektlogik als Custom Plugin oder projektbezogene Erweiterung
2. Nutzung von WordPress- und WooCommerce-Hooks für Produkt- und Beitragsereignisse
3. Versand über den konfigurierten E-Mail-Kanal des Projekts
4. Versandprotokoll und Digest-Logik zur Vermeidung doppelter Benachrichtigungen
5. geplanter Scheduler für tägliche und wöchentliche Sammelbenachrichtigungen

Empfohlene Protokollierung pro Versand:

- Benutzer-ID
- E-Mail-Adresse
- Ereignistyp, z. B. `news`, `category_product`, `discount`
- bezogenes Objekt, z. B. Beitrag oder Produkt
- Versandmodus, z. B. `immediate`, `daily_digest`, `weekly_digest`
- Versandstatus
- Versandzeitpunkt

Empfohlene technische Form:

- eigene Tabelle wie `wp_barmbini_notification_log` oder gleichwertige persistente Protokollierung
- zusätzliche Queue- oder Digest-Tabelle wie `wp_barmbini_notification_queue`, falls die Versandlogik nicht sauber in einer einzigen Tabelle abgebildet werden kann

Abnahmekriterium:

- Die Versandlogik ist nachvollziehbar.
- Doppelte Benachrichtigungen für denselben Auslöser werden verhindert.

### 10. Admin- und Support-Sicht berücksichtigen

Ziel: Die Loesung darf nicht nur für Kunden, sondern auch für Betrieb und Support handhabbar sein.

Mindestens vorzusehen:

1. nachvollziehbare Sicht auf aktive Abonnements pro Kunde
2. nachvollziehbare Sicht auf versendete Benachrichtigungen oder Versandfehler
3. technische Möglichkeit, Versandprobleme zu analysieren

Optional, aber sinnvoll:

- Exportfunktion für Abo-Stände
- Filter nach Abo-Typ
- Testmodus für Benachrichtigungen in der Entwicklungsumgebung

Abnahmekriterium:

- Support und Admin können Abo-Stände und Versandprobleme nachvollziehen.

### 11. Test- und Abnahmefaelle definieren

Ziel: Vor dem Live-Einsatz muss die Loesung pruefbar sein.

Pflicht-Testfaelle:

1. Kunde aktiviert nur `Neuigkeiten`.
2. Kunde aktiviert nur bestimmte Produktkategorien.
3. Kunde aktiviert nur `Rabatte`.
4. Kunde aktiviert alle Abo-Arten.
5. Kunde wählt für `Neuigkeiten` die Frequenz `sofort`.
6. Kunde wählt für `Neuigkeiten` die Frequenz `täglich`.
7. Kunde wählt für `Neuigkeiten` die Frequenz `wöchentlich`.
8. Neuer News-Beitrag wird veröffentlicht.
9. Neues Produkt in abonnierter Kategorie wird veröffentlicht.
10. Neues Produkt in nicht abonnierter Kategorie wird veröffentlicht.
11. Rabatt auf ein Produkt wird neu aktiv.
12. Ein täglicher Digest fasst mehrere passende Ereignisse korrekt zusammen.
13. Ein wöchentlicher Digest fasst mehrere passende Ereignisse korrekt zusammen.
14. Kunde meldet sich nur von einer Abo-Art ab.
15. Kunde meldet sich komplett ab.
16. Eine Abmeldung vor dem nächsten Digest verhindert die Auslieferung des noch nicht versendeten Sammelversands.
17. Gleicher Auslöser wird bearbeitet oder erneut gespeichert und erzeugt keine Dublette.

Abnahmekriterium:

- Alle Pflicht-Testfaelle sind dokumentiert und erfolgreich nachvollziehbar.

## Technisches WordPress-Konzept für die Benachrichtigungsfrequenz

### Zielbild

Die bestehende Abo-Funktion wird so erweitert, dass ein Kunde je Abo-Art zwischen `sofort`, `täglich` und `wöchentlich` wählen kann.

Die technische Loesung soll dabei:

- weiterhin im WooCommerce-Konto verankert sein
- projektbezogene Fachlogik in einem eigenen Plugin halten
- direkte Sofortbenachrichtigungen und geplante Digest-Läufe gemeinsam unterstuetzen
- Dubletten, Mehrfachversand und unkontrollierte Cron-Logik vermeiden

### Zielarchitektur

Empfohlener Ort der Umsetzung:

- eigenes Plugin, z. B. `wp-content/plugins/barmbini-core/`

Empfohlene interne Module:

1. Account-Endpoint für Abo-Einstellungen und Frequenzen
2. Persistenz für `usermeta`
3. Event-Collector für News, Produkte und Rabatte
4. Queue- oder Digest-Service für geplante Benachrichtigungen
5. Mail-Service für Sofort- und Sammelversand
6. Versandlog und Dubletten-Schutz
7. Unsubscribe-Handler
8. optionale Admin- oder Support-Ansicht

### Datenmodell

Empfohlene `usermeta`-Felder:

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
- `barmbini_unsubscribe_token`

Empfohlene persistente Protokollierung:

1. `wp_barmbini_notification_log`
2. optional `wp_barmbini_notification_queue`

Empfohlene Felder in der Queue-Tabelle:

- `id`
- `user_id`
- `event_type`
- `object_id`
- `frequency`
- `scheduled_for`
- `status`
- `created_at`
- `processed_at`

Empfohlene Felder im Versandlog:

- `id`
- `user_id`
- `event_type`
- `object_id` oder Digest-Referenz
- `delivery_mode`
- `status`
- `sent_at`
- `error_message`

### UI im WooCommerce-Konto

Empfohlene Darstellung im Endpoint `Abonnements`:

1. Checkbox `Neuigkeiten abonnieren`
2. Select `Neuigkeiten Frequenz`
3. Checkbox `Rabatte abonnieren`
4. Select `Rabatte Frequenz`
5. Checkbox oder Mehrfachauswahl für Produktkategorien
6. Select `Produktkategorien Frequenz`
7. Speichern

Empfohlene Werte im Select:

- `sofort`
- `täglich`
- `wöchentlich`

Wichtige Vereinfachung:

- keine eigene Frequenz pro einzelner Produktkategorie
- nur eine gemeinsame Kategorie-Frequenz pro Benutzerkonto

### Ereignis- und Versandlogik

#### Sofortmodus

- Bei `sofort` wird ein passendes Ereignis direkt in eine E-Mail übersetzt.
- Vor dem Versand wird geprueft, ob bereits ein Versandlog für denselben Benutzer und Auslöser existiert.

#### Digest-Modus

- Bei `täglich` oder `wöchentlich` wird nicht direkt versendet.
- Stattdessen wird ein Queue-Eintrag für den nächsten passenden Digest-Lauf angelegt.
- Mehrere passende Ereignisse desselben Zeitraums werden im Digest pro Benutzer zusammengefasst.
- Vor dem Versand wird erneut geprueft, ob das Abo noch aktiv ist.

### WordPress- und WooCommerce-Hooks

Empfohlene Ereignisquellen:

- News: Hook auf erstmalige Veröffentlichung eines relevanten Beitrags
- Produkte: Hook auf erstmalige Veröffentlichung eines Produkts
- Rabatte: Hook auf Produktänderung mit Übergang in einen aktiven Sale-Zustand

Empfohlene geplante Läufe:

- täglicher WP-Cron-Event für Daily Digests
- wöchentlicher WP-Cron-Event für Weekly Digests

Falls die Website wenig Traffic hat:

- echter Server-Cron statt reinem WP-Cron bevorzugt

### Datenschutz und Einwilligung

Die Frequenzwahl muss in den rechtlichen Texten nachvollziehbar beschrieben werden.

Erforderlich sind insbesondere:

- transparente Beschreibung, dass Benachrichtigungen sofort oder als Sammelmail versendet werden können
- dokumentierte Einwilligung
- Abmeldelink in Sofort- und Digest-Mails
- sichere Pruefung, dass abgemeldete Benutzer nicht mehr aus einer vorhandenen Queue beliefert werden

## Empfohlene technische Richtung

Für dieses Projekt ist folgende Richtung sinnvoll:

1. Abo-Einstellungen im WooCommerce-Konto integrieren
2. Speicherung über `usermeta`
3. eigene Projektlogik für Trigger, Queue und Versand statt unkontrollierter Plugin-Ketten
4. tägliche und wöchentliche Digests über geplante Läufe des Projekt-Plugins umsetzen
5. E-Mail-Versand über die bestehende Projekt-Infrastruktur
6. optionale spätere Anbindung an `Brevo` nur dann, wenn Phase 2 dies ausdrücklich verlangt

## Definition of Done

Die Aufgabe ist abgeschlossen, wenn alle folgenden Punkte erfuellt sind:

1. Kunden können im Konto Neuigkeiten, Kategorien und Rabatte abonnieren.
2. Kunden können für jede Abo-Art zwischen `sofort`, `täglich` und `wöchentlich` wählen.
3. Kunden können ihre Auswahl später selbst ändern.
4. Kunden erhalten bei neuen passenden Ereignissen eine Benachrichtigung per E-Mail im gewählten Rhythmus.
5. Kunden können einzelne oder alle Abonnements wieder kündigen.
6. Die Einwilligung ist nachvollziehbar dokumentiert.
7. Es werden keine unkontrollierten Dubletten verschickt.
8. Datenschutz, Abmeldung und Nachvollziehbarkeit sind berücksichtigt.

## Aktueller Umsetzungs- und Teststand

Stand der lokalen Verifikation: 2026-04-28.

Bereits umgesetzt im Projekt-Plugin `wp-content/plugins/barmbini-core/`:

- Bootstrap und Modulregistrierung für Konto, Benachrichtigungen, Admin und Datenschutz
- WooCommerce-Endpoint `abonnements` im Bereich `Mein Konto`
- Speicherung der Abo-Einstellungen in `usermeta`
- Trigger für `news`, `category_product` und `discount`
- Versandlog, Digest-Queue und Scheduler für `täglich` und `wöchentlich`
- tokenbasierte Abmeldung sowie Datenschutz-Export/Löschintegration

Lokal verifiziert gegen `C:\Users\Teilnehmer\Local Sites\barmbini\app\public`:

- Browser-Test mit Kundenlogin: Der neue Konto-Menuepunkt `Abonnements` ist sichtbar und der Endpoint laedt.
- Formularspeicherung: Abo-Einstellungen werden gespeichert; Einwilligungs- und Aktualisierungszeitstempel werden angezeigt.
- News-Trigger: Ein neu veröffentlichter Beitrag in der Kategorie `neuigkeiten` erzeugt bei `sofort` einen direkten Versandlog-Eintrag mit Status `sent`.
- Produkt-Trigger: Ein neu veröffentlichtes Produkt in der abonnierten Kategorie `Babybedarf` erzeugt bei `täglich` einen Queue-Eintrag; ein erzwungener Daily-Digest verarbeitet ihn erfolgreich zu einem `daily_digest`-Log-Eintrag.
- Rabatt-Trigger: Ein Produkt im aktiven Sale-Zustand erzeugt beim Hook-Pfad `save_post_product` einen direkten Versandlog-Eintrag mit Status `sent`.

Wichtige Einordnung für lokale CLI-Tests:

- Die fachliche Rabatt-Logik ist verifiziert.
- In einem reinen CLI-Test über WooCommerce-CRUD `product->save()` wurde der WordPress-Hook `save_post_product` nicht automatisch in derselben Form erreicht wie bei einem echten Produktspeichern über WordPress oder WooCommerce.
- Für die lokale Verifikation wurde deshalb der echte Hook-Pfad gezielt ausgeloest.

Offene Restpunkte ausserhalb der reinen Technik:

- Vor einem Live-Rollout ist der finale Deploy-Ablauf gemaess Modus A oder Modus B festzulegen und zu dokumentieren.

## Definierte lokale Testfixtures

Die folgenden Testfixtures sind für die lokale Entwicklungs- und Verifikationsumgebung festgelegt. Sie duerfen nicht in ein Live-System übernommen werden und sind ausschliesslich für lokale Reproduktion, Debugging und Abnahmetests gedacht.

### Geltungsbereich

- lokale WordPress-Installation unter `C:\Users\Teilnehmer\Local Sites\barmbini\app\public`
- Plugin `wp-content/plugins/barmbini-core/` ist lokal aktiv
- PHP-CLI-Checks erfolgen mit der Local-PHP-Installation und der site-spezifischen `php.ini`

### Fixture 1: Testkunde für Konto- und Abo-Tests

- Benutzername: `copilot_test_customer`
- E-Mail: `copilot-test@barmbini.local`
- Passwort: `Barmbini!Test123`
- Rolle: `customer`
- Zweck: Browser-Login, Speichern der Abo-Einstellungen, Datenschutz- und Unsubscribe-Pruefungen

### Fixture 2: Standard-Abo-Konfiguration für Trigger-Tests

Diese Konfiguration wurde für die reproduzierbaren lokalen Trigger-Tests verwendet:

- `news_enabled = 1`
- `news_frequency = sofort`
- `discount_enabled = 1`
- `discount_frequency = sofort`
- `category_enabled = 1`
- `category_frequency = täglich`
- `category_terms = [63]`

Interpretation auf dem aktuellen lokalen Stand:

- Produktkategorie-ID `63` entspricht `Babybedarf`

### Fixture 3: Inhaltsdaten für Ereignistests

Aktuell lokal angelegte Referenzobjekte:

- News-Testbeitrag: ID `272`
- Produkt-Testobjekt: ID `273`
- abonnierte Testkategorie: `Babybedarf` mit lokaler Term-ID `63`

Fachliche Verwendung:

- News-Trigger: erstmalige Veröffentlichung eines Beitrags in der Kategorie `neuigkeiten`
- Kategorie-Trigger: erstmalige Veröffentlichung eines Produkts in der abonnierten Kategorie `Babybedarf`
- Rabatt-Trigger: Produkt im aktiven Sale-Zustand mit Auslösung über den WordPress-Hook `save_post_product`

### Reproduktionsregeln für künftige lokale Tests

- Der Testkunde bleibt als definierter lokaler Fixture-Benutzer bestehen, solange keine bereinigte Testdatenbasis ausdrücklich verlangt wird.
- Die Objekt-IDs `272` und `273` gelten für den aktuellen lokalen Stand als Referenz. Bei einer neu aufgebauten lokalen Datenbank duerfen sie neu erzeugt werden, muessen dann aber in dieser Dokumentation aktualisiert werden.
- Für reproduzierbare Trigger-Checks ist die oben definierte Abo-Konfiguration vor jedem Testlauf erneut zu setzen.
- Queue- und Log-Einträge des Testkunden sollen vor einem neuen Testlauf bereinigt werden, damit die Ergebnisse eindeutig bleiben.
- Alle definierten Testfixtures sind lokal zu halten und duerfen weder auf Live noch in dauerhaft produktive Inhaltsdaten übernommen werden.
