# Internes Technisches Konzept (Version 2.0)

## Website – Sozialkaufhaus Barmbini

---

# 1. Projektübersicht

Ziel ist die Erstellung einer schlanken, barrierearmen und DSGVO-konformen Informationswebsite auf WordPress-Basis.

Kein Online-Shop.
Fokus: Information, Vertrauen, Spenden & Besuch vor Ort.

Entwicklung lokal mit Local (Nginx).
Hosting später über IONOS VPS Linux S+.

---

# 2. Technische Basis

CMS: WordPress (aktuelle stabile Version)
PHP: 8.1+
Webserver (lokal): Nginx
Datenbank: MySQL

Hosting (Produktiv):

* IONOS VPS Linux S+
* 1 Domain
* ein kostenloses SSL-Zertifikat (z. B. Let's Encrypt)
* 80 GB NVMe SSD
* 1 Website
* Serverstandort Deutschland
* SSH / SFTP / WP-CLI verfügbar

Migration:

* All-in-One WP Migration oder Duplicator

---

# 3. Architektur-Grundsätze

* Minimalprinzip bei Plugins
* Kein Page Builder (nur Gutenberg)
* Keine externen Drittanbieter-Services
* Keine eingebetteten Google Maps (nur statische Karte)
* Keine extern geladenen Google Fonts
* Mobile-first Design
* Performance-orientierte Umsetzung

---

# 4. Mehrsprachigkeit

## 4.1 Plugin

Polylang (kostenlose Version)

## 4.2 Sprachstruktur
Die Website ist einsprachig: Deutsch. Es wird keine Mehrsprachigkeit eingerichtet und es werden keine Sprachpräfixe verwendet.

URL-Struktur:

example.de/

Hinweis: Mehrsprachigkeits-Plugins (z. B. Polylang) sind nicht erforderlich.

## 4.3 Übersetzungsstrategie

* Inhalte werden manuell über Polylang UI gepflegt
* Jede Seite existiert in 3 Sprachversionen
* Interface-Strings über .po / .mo-Dateien
* Möglichkeit zur direkten Bearbeitung von Sprachdateien (z. B. via Poedit)

Keine automatische Übersetzung.

---

# 5. Benutzerrollen

## Administrator

* Volle Systemrechte
* Plugin- und Theme-Verwaltung
* Systemeinstellungen
* Benutzerverwaltung

## Redakteur (Mitarbeiter Sozialkaufhaus)

* Inhalte erstellen und bearbeiten
* Blogbeiträge pflegen
* Übersetzungen verwalten
* Kein Zugriff auf Plugins, Theme, Einstellungen

---

# 6. Seitenstruktur

## Hauptnavigation

* Startseite
* Über uns
* Sortiment
* Helfen & Spenden
* Kontakt & Anfahrt
* Neuigkeiten
* FAQ

## Footer

* Impressum
* Datenschutzerklärung
* Barrierefreiheitserklärung

---

# 7. Detailkonzept der Seiten

## 7.1 Startseite

* Hero-Bereich (Bild + Slogan)
* Kurzvorstellung
* 3 Teaser-Blöcke
* Bildergalerie
* Adresse + Öffnungszeiten
* Statische Karte (Bild)
* Button „In Google Maps öffnen“
* Letzte 3 Blogbeiträge

---

## 7.2 Über uns

* Geschichte
* Mission
* Team
* Zahlen & Fakten
* Bilder

---

## 7.3 Sortiment

* Warengruppen als Abschnitte
* Preisbeispiele
* Regeln für Einkauf
* Hinweise zu Nachweisen

---

## 7.4 Helfen & Spenden

* Sachspenden
* Abgabezeiten
* Ehrenamt
* Kontaktformular
* Optional: Geldspenden-Information

---

## 7.5 Kontakt & Anfahrt

* Adresse
* Öffnungszeiten (Tabelle)
* Telefon
* E-Mail
* Statische Karte
* Google Maps Link
* Kontaktformular
* ÖPNV-Hinweise

---

## 7.6 Neuigkeiten

* Standard WordPress Posts
* Kategorien:

  * Neuigkeiten
  * Aktionen
  * Rückblick

---

## 7.7 FAQ

* Accordion-Struktur
* Mehrsprachig gepflegt

---

# 8. DSGVO & Rechtliches

* SSL aktiv
* Keine externen Dienste
* Keine externen Fonts
* Keine Tracking-Tools
* Keine eingebetteten Videos
* Statische Karte statt Google Maps iframe
* Datenschutzerklärung individuell angepasst
* Impressum gemäß §5 TMG
* Serverstandort Deutschland

Cookie-Banner nur falls technisch erforderlich.

---

# 9. Performance

* Bilder vor Upload komprimieren
* Max. Bildbreite 1600px
* Lazy Loading aktiv
* Caching über Hosting
* Minimale Plugin-Anzahl

Ziel: PageSpeed > 85

---

# 10. Administrationskonzept

* Schulung der Mitarbeiter (ca. 1 Stunde)
* Dokumentation für:

  * Inhalte bearbeiten
  * Übersetzungen anlegen
  * Blogbeiträge erstellen
* Keine technischen Eingriffe durch Redakteure

---

# 11. Projektphasen (Phase 1)

Woche 1:
Struktur + Grunddesign

Woche 2:
Inhalte einpflegen + Mehrsprachigkeit

Woche 3:
Testing (Sprachen, Formulare, SEO)

Woche 4:
Migration + Go-Live

---

# 12. Aufgaben Zweiter Priorität (Phase 2)

Diese Punkte werden nach Go-Live geplant:

## Sicherheit

* 2FA für Administratoren
* Limit Login Attempts
* Security Headers (Nginx)
* Regelmäßige Sicherheitsprüfung

## Backup-Strategie

* Offsite Backup
* Automatisierter wöchentlicher Full-Backup
* Täglicher Datenbank-Backup

## Performance-Optimierung

* Gzip / Brotli aktivieren
* Cache-Control Header optimieren
* WebP-Bilder
* Preload wichtiger Assets

## Monitoring

* Uptime Monitoring
* Error Logging
* Security Logging

## Deployment & Infrastruktur

* Staging-Umgebung
* Automatisierter Deployment-Prozess
* Versionsverwaltung (Git)

## SEO-Optimierung

* hreflang Validierung
* XML-Sitemap je Sprache
* Strukturierte Daten (Schema.org)

## Erweiterungen (optional später)

* Newsletter
* Spendenfunktion mit Zahlungsanbieter
* Terminbuchung
* Erweiterte Barrierefreiheit (WCAG AA)

---

# 13. Risiken

* Mehrsprachigkeit erhöht Pflegeaufwand
* Inhalte müssen synchron in 3 Sprachen gepflegt werden
* Plugin-Updates müssen regelmäßig geprüft werden
* Redakteure benötigen klare Anleitung

---

Stand: Intern
Version: 2.0
Architektur: Nginx + Polylang + Gutenberg + Minimal-Plugin-Strategie