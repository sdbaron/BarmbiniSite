# Detaillierte Aufgabe: Kundenkonto mit Abonnements, Benachrichtigungen und Kuendigung

## Ziel

Das Kundenkonto der Website Sozialkaufhaus Barmbini soll so erweitert werden, dass registrierte Kunden gezielt Benachrichtigungen abonnieren und wieder kuendigen koennen.

Der Kunde soll im eigenen Konto festlegen koennen, ob er Benachrichtigungen zu folgenden Bereichen erhalten moechte:

- Neuigkeiten
- bestimmten Produktkategorien
- Rabatten

Wenn ein relevantes Ereignis eintritt, muss der Kunde automatisch benachrichtigt werden.

Das bedeutet im Mindestumfang:

- bei neuen Neuigkeiten: Benachrichtigung an alle Kunden mit aktivem Neuigkeiten-Abonnement
- bei neuen Artikeln in einer abonnierten Kategorie: Benachrichtigung an die Kunden, die genau diese Kategorie abonniert haben
- bei neuen Rabatten: Benachrichtigung an die Kunden mit aktivem Rabatt-Abonnement
- jederzeitige Kuendigung des Abonnements durch den Kunden

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

## Verbindliche Annahmen fuer diese Aufgabe

Damit die Aufgabe umsetzbar und pruefbar ist, gelten fuer diese Version folgende Annahmen:

1. Das Abonnement ist an ein registriertes Kundenkonto gebunden.
2. Der Benachrichtigungskanal ist E-Mail.
3. `Neuigkeiten` meint WordPress-Beitraege, die in der News-Logik der Website veroeffentlicht werden, insbesondere in der Kategorie `Neuigkeiten`.
4. `Kategorien` meint WooCommerce-Produktkategorien aus der Taxonomie `product_cat`.
5. `Rabatt` meint in der Erstumsetzung einen Artikel, der neu in einen aktiven reduzierten Preiszustand wechselt.
6. Eine spaetere Erweiterung fuer komplexe Kampagnen- oder Gutscheinlogik ist moeglich, aber nicht Bestandteil dieser Aufgabe.

## Nicht Bestandteil dieser Aufgabe

Folgende Punkte gehoeren ausdruecklich nicht zum Mindestumfang:

- Push-Benachrichtigungen
- SMS-Benachrichtigungen
- allgemeine Marketing-Automation ausserhalb des Kundenkontos
- anonyme Abonnements ohne Kundenkonto
- vollwertiges CRM oder externer Kampagnen-Builder

## Umzusetzender Funktionsumfang

Die Loesung muss die folgenden fachlichen Faehigkeiten abdecken:

1. Kundenkonto mit Abonnement-Einstellungen
2. Speicherung der Abo-Eigenschaften am Kundenkonto
3. Auswahl konkreter Produktkategorien fuer Kategorie-Abonnements
4. automatische Benachrichtigung bei passenden Ereignissen
5. individuelle und vollstaendige Kuendigung des Abonnements
6. nachweisbare Einwilligung und technische Nachvollziehbarkeit

## Aufgabe

### 1. Kundenkonto fuer Abonnements technisch bereitstellen

Ziel: Ein Kunde muss ein nutzbares Konto besitzen, auch wenn die Website kein klassischer Checkout-Shop ist.

Arbeitsschritte:

1. Pruefen, wie Kundenkonten in der aktuellen WooCommerce-Konfiguration angelegt und genutzt werden.
2. Sicherstellen, dass es eine nutzbare Kontoansicht fuer Kunden gibt, z. B. ueber `Mein Konto`.
3. Falls erforderlich: Registrierung und Login fuer Kunden auch ohne Checkout-Pfad sauber verfuegbar machen.
4. Sicherstellen, dass Kundenkonten mit einer geeigneten Rolle, z. B. `customer`, angelegt werden.

Abnahmekriterium:

- Ein Kunde kann sich registrieren oder mit einem vorhandenen Konto anmelden.
- Ein angemeldeter Kunde erreicht einen Bereich, in dem Abo-Einstellungen gepflegt werden koennen.

### 2. Abo-Eigenschaften am Kundenkonto speichern

Ziel: Das Kundenkonto muss die fuer Abonnements benoetigten Eigenschaften dauerhaft speichern.

Empfohlene Datenfelder am Kundenkonto:

- `Neuigkeiten abonnieren`: Ja/Nein
- `Rabatte abonnieren`: Ja/Nein
- `abonnierte Produktkategorien`: Liste von Kategorie-IDs oder Slugs
- `Abo zuletzt aktualisiert am`
- `Einwilligung erteilt am`
- `Einwilligungsquelle`
- `Abmelde-Token` fuer sichere E-Mail-Links

Empfohlene technische Speicherung:

- als `usermeta` am WordPress-Benutzerkonto

Abnahmekriterium:

- Die Abo-Einstellungen eines Kunden werden gespeichert und bleiben nach erneutem Login erhalten.

### 3. Bedienoberflaeche im Kundenkonto aufbauen

Ziel: Der Kunde soll seine Abonnements ohne Admin-Zugriff selbst pflegen koennen.

Pflichtfunktionen in der Kontooberflaeche:

1. Bereich oder Tab `Abonnements`
2. Option `Neuigkeiten abonnieren`
3. Option `Rabatte abonnieren`
4. Mehrfachauswahl fuer abonnierte Produktkategorien
5. Speichern-Schaltflaeche
6. Rueckmeldung nach erfolgreichem Speichern

Erwartetes Verhalten:

- vorhandene Einstellungen sind beim Oeffnen sichtbar
- Kategorien koennen individuell an- und abgewaehlt werden
- ein Kunde kann einzelne Abo-Typen aktivieren, ohne alle anderen mit zu abonnieren

Abnahmekriterium:

- Der Kunde kann seine Auswahl direkt im Konto aendern und speichern.

### 4. Einwilligung und Datenschutz sauber umsetzen

Ziel: Abonnements muessen rechtlich und fachlich nachvollziehbar sein.

Pflichtanforderungen:

1. Keine Abo-Option darf standardmaessig vorausgewaehlt sein.
2. Der Kunde muss klar erkennen koennen, welche Benachrichtigungen er aktiviert.
3. Die Einwilligung muss protokolliert werden.
4. Die Datenschutzerklaerung muss den neuen Zweck der Datenverarbeitung abdecken.
5. Jede E-Mail muss eine Abmeldemoeglichkeit enthalten.

Empfohlene Protokollierung:

- Zeitstempel der Einwilligung
- Zeitstempel der letzten Aenderung
- optional die verwendete Formularquelle oder Kontoansicht

Abnahmekriterium:

- Es gibt keine stillschweigende oder verdeckte Anmeldung zu Benachrichtigungen.
- Die Abo-Verwaltung ist DSGVO-konform dokumentiert und technisch nachvollziehbar.

### 5. Neuigkeiten-Benachrichtigungen ausloesen

Ziel: Kunden mit aktivem Neuigkeiten-Abonnement erhalten eine E-Mail, wenn neue Neuigkeiten erscheinen.

Ausloeselogik:

1. Wird ein neuer Beitrag veroeffentlicht und gehoert fachlich zu `Neuigkeiten`, dann wird eine Benachrichtigung erzeugt.
2. Die Benachrichtigung wird nur beim erstmaligen Veroeffentlichen ausgeloest, nicht bei jeder spaeteren Bearbeitung.
3. Ein Kunde darf fuer denselben Beitrag nicht mehrfach benachrichtigt werden.

Pflichtinhalt der E-Mail:

- Betreff mit Hinweis auf eine neue Neuigkeit
- Titel der Neuigkeit
- kurzer Teaser oder Auszug
- Link zum Beitrag
- Link zur Abmeldung

Abnahmekriterium:

- Bei einer neu veroeffentlichten Neuigkeit erhalten nur passende Abonnenten eine E-Mail.
- Es werden keine Dubletten fuer denselben Beitrag erzeugt.

### 6. Kategorie-Benachrichtigungen fuer neue Artikel ausloesen

Ziel: Kunden mit Kategorie-Abonnement erhalten eine E-Mail, wenn ein neuer Artikel in einer von ihnen abonnierten Kategorie erscheint.

Ausloeselogik:

1. Wird ein Produkt neu veroeffentlicht, werden seine Produktkategorien ermittelt.
2. Es werden nur Kunden ausgewaehlt, die mindestens eine dieser Kategorien abonniert haben.
3. Ist ein Kunde fuer mehrere passende Kategorien eingetragen, darf er fuer dieses Produkt trotzdem nur eine E-Mail erhalten.
4. Die Benachrichtigung wird beim ersten relevanten Live-Erscheinen des Produkts erzeugt, nicht bei jeder Bearbeitung.

Pflichtinhalt der E-Mail:

- Betreff mit Hinweis auf einen neuen Artikel
- Produktname
- Produktbild, falls sinnvoll verfuegbar
- betroffene Kategorie oder Kategorien
- Link zum Produkt
- Link zur Abmeldung

Abnahmekriterium:

- Ein Kunde wird nur fuer Produkte in seinen abonnierten Kategorien benachrichtigt.
- Ein Kunde bekommt pro Produkt maximal eine Benachrichtigung.

### 7. Rabatt-Benachrichtigungen ausloesen

Ziel: Kunden mit aktivem Rabatt-Abonnement erhalten eine E-Mail, wenn ein neuer Rabatt aktiv wird.

Verbindliche Mindestdefinition fuer Rabatt in dieser Aufgabe:

- ein Produkt wechselt neu in einen aktiven reduzierten Preiszustand

Das umfasst insbesondere:

- neues Setzen eines Sale-Preises
- Start eines terminierten Sale-Zeitraums

Nicht erforderlich fuer diese Erstumsetzung:

- komplexe Gutscheinlogik
- manuelle Rabattkampagnen ausserhalb von Produktpreisen

Pflichtlogik:

1. Der Rabatt-Trigger darf nicht bei jeder Produktspeicherung erneut feuern.
2. Der gleiche Kunde darf fuer den gleichen Rabatt-Event nicht mehrfach benachrichtigt werden.
3. Die E-Mail muss klar erkennbar machen, welches Produkt rabattiert ist.

Abnahmekriterium:

- Rabatt-Abonnenten erhalten bei neu aktivem Rabatt genau eine passende Benachrichtigung.

### 8. Abonnement kuendigen und abmelden

Ziel: Der Kunde muss sein Abonnement jederzeit beenden koennen.

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

Abnahmekriterium:

- Ein Kunde kann einzelne oder alle Abonnements jederzeit selbst kuendigen.

### 9. Benachrichtigungsversand technisch sauber umsetzen

Ziel: Die Benachrichtigungen muessen robust, nachvollziehbar und ohne unkontrollierte Dubletten verschickt werden.

Empfohlene technische Umsetzung:

1. eigene Projektlogik als Custom Plugin oder projektbezogene Erweiterung
2. Nutzung von WordPress- und WooCommerce-Hooks fuer Produkt- und Beitragsereignisse
3. Versand ueber den konfigurierten E-Mail-Kanal des Projekts
4. Versandprotokoll zur Vermeidung doppelter Benachrichtigungen

Empfohlene Protokollierung pro Versand:

- Benutzer-ID
- E-Mail-Adresse
- Ereignistyp, z. B. `news`, `category_product`, `discount`
- bezogenes Objekt, z. B. Beitrag oder Produkt
- Versandstatus
- Versandzeitpunkt

Empfohlene technische Form:

- eigene Tabelle wie `wp_barmbini_notification_log` oder gleichwertige persistente Protokollierung

Abnahmekriterium:

- Die Versandlogik ist nachvollziehbar.
- Doppelte Benachrichtigungen fuer denselben Ausloeser werden verhindert.

### 10. Admin- und Support-Sicht beruecksichtigen

Ziel: Die Loesung darf nicht nur fuer Kunden, sondern auch fuer Betrieb und Support handhabbar sein.

Mindestens vorzusehen:

1. nachvollziehbare Sicht auf aktive Abonnements pro Kunde
2. nachvollziehbare Sicht auf versendete Benachrichtigungen oder Versandfehler
3. technische Moeglichkeit, Versandprobleme zu analysieren

Optional, aber sinnvoll:

- Exportfunktion fuer Abo-Staende
- Filter nach Abo-Typ
- Testmodus fuer Benachrichtigungen in der Entwicklungsumgebung

Abnahmekriterium:

- Support und Admin koennen Abo-Staende und Versandprobleme nachvollziehen.

### 11. Test- und Abnahmefaelle definieren

Ziel: Vor dem Live-Einsatz muss die Loesung pruefbar sein.

Pflicht-Testfaelle:

1. Kunde aktiviert nur `Neuigkeiten`.
2. Kunde aktiviert nur bestimmte Produktkategorien.
3. Kunde aktiviert nur `Rabatte`.
4. Kunde aktiviert alle Abo-Arten.
5. Neuer News-Beitrag wird veroeffentlicht.
6. Neues Produkt in abonnierter Kategorie wird veroeffentlicht.
7. Neues Produkt in nicht abonnierter Kategorie wird veroeffentlicht.
8. Rabatt auf ein Produkt wird neu aktiv.
9. Kunde meldet sich nur von einer Abo-Art ab.
10. Kunde meldet sich komplett ab.
11. Gleicher Ausloeser wird bearbeitet oder erneut gespeichert und erzeugt keine Dublette.

Abnahmekriterium:

- Alle Pflicht-Testfaelle sind dokumentiert und erfolgreich nachvollziehbar.

## Empfohlene technische Richtung

Fuer dieses Projekt ist folgende Richtung sinnvoll:

1. Abo-Einstellungen im WooCommerce-Konto integrieren
2. Speicherung ueber `usermeta`
3. eigene Projektlogik fuer Trigger und Versand statt unkontrollierter Plugin-Ketten
4. E-Mail-Versand ueber die bestehende Projekt-Infrastruktur
5. optionale spaetere Anbindung an `Brevo` nur dann, wenn Phase 2 dies ausdruecklich verlangt

## Definition of Done

Die Aufgabe ist abgeschlossen, wenn alle folgenden Punkte erfuellt sind:

1. Kunden koennen im Konto Neuigkeiten, Kategorien und Rabatte abonnieren.
2. Kunden koennen ihre Auswahl spaeter selbst aendern.
3. Kunden erhalten bei neuen passenden Ereignissen eine Benachrichtigung per E-Mail.
4. Kunden koennen einzelne oder alle Abonnements wieder kuendigen.
5. Die Einwilligung ist nachvollziehbar dokumentiert.
6. Es werden keine unkontrollierten Dubletten verschickt.
7. Datenschutz, Abmeldung und Nachvollziehbarkeit sind beruecksichtigt.
