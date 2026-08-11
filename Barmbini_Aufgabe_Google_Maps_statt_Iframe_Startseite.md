# Detaillierte Aufgabe: Google-Maps-Embed auf der Startseite durch statische Karte ersetzen

## Ziel

Auf der Startseite (`http://217.160.74.128/`) ist im Block „Hier finden Sie uns" ein **Google-Maps-iframe** eingebettet, der gegen die Architektur-Regel „Keine eingebetteten Google Maps (nur statische Karte)" verstößt und einen **öffentlichen Google-API-Key** exponiert.

Ziel ist es, den Google-Maps-Embed durch eine **statische Karte** zu ersetzen – analog zur bereits korrekt umgesetzten Kontaktseite (`/kontakt/`). Damit verschwindet der öffentliche API-Key aus dem HTML, die externe Google-Einbindung entfällt und der Weg für eine spätere **enge Content-Security-Policy** wird frei (siehe `Barmbini_Aufgabe_Sicherheit_HTTP_Header.md`, CSP-Entscheidung Option A).

## Quellenbasis

- Server-Analyse vom 2026-08-11 (Befund 🔴 Hoch: Google-Maps-iframe mit öffentlichem API-Key auf der Startseite)
- `Barmbini_Technisches_Konzept_v2.5.md` – §3 Architektur-Grundsätze („Keine eingebetteten Google Maps (nur statische Karte)"), §7.5
- `Barmbini_Aufgabe_Sicherheit_HTTP_Header.md` – CSP-Entscheidung (enge CSP erst nach Google-Maps-Bereinigung möglich)
- Die Kontaktseite `/kontakt/` als korrektes Referenz-Muster (statische `map.png` + „In Google Maps öffnen"-Link)

## Verifizierter Ist-Stand (2026-08-11)

**Live-Startseite** – Block „Hier finden Sie uns":

- Google-Maps-iframe im Kadence-Block:
  ```
  <div class="kb-google-maps-container kb-google-maps-container13_b84d5f-6e wp-block-kadence-googlemaps">
    <iframe width="100%" height="100%" style="border:0" loading="lazy"
      src="https://www.google.com/maps/embed/v1/place?key=AIzaSyBAM2o7PiQqwk15LC1XRH2e_KJ-jUa7KYk&zoom=13&maptype=roadmap&q=Alter+Teichweg+11+Hamburg"
      title="Google map of Alter Teichweg 11 Hamburg"></iframe>
  </div>
  ```
- Der **API-Key `AIzaSyBAM2o7PiQqwk15LC1XRH2e_KJ-jUa7KYk` ist öffentlich im HTML** sichtbar.
- Block-ID: `13_b84d5f-6e` (Teil des Startseiten-Posts, Post-ID 13).

**Referenz Kontaktseite `/kontakt/`:**
- Statische Karte: `img alt="map"` → `/wp-content/uploads/2026/04/map.png`
- Link „In Google Maps öffnen ?" → `https://www.google.com/maps/search/Alter+Teichweg+11,+22081+Hamburg`
- **Kein** Google-iframe, **kein** API-Key.

## Fachliche Leitplanken

- Die Website ist einsprachig deutsch.
- Es gilt „Keine eingebetteten Google Maps – nur statische Karte" (Architektur v2.5).
- Der Google-Maps-Embed liegt im **Seiteninhalt** (Gutenberg-Block), nicht im Theme/Plugin – die Korrektur ist eine **Inhaltsänderung**, keine Code-Änderung.
- Es gilt **Modus B**: Live-Daten bleiben erhalten, kein SQL-Vollimport.
- Der **API-Key muss rotiert/gesperrt** werden, sobald er nicht mehr verwendet wird – das ist eine Google-Cloud-Konto-Aufgabe (extern).

## Aufgabe

### 1. Lokale Korrektur (Quelle)

1. Lokale WordPress-Installation (`D:\Local Sites\barmbini\app\public`) öffnen.
2. Startseite im Gutenberg-Editor öffnen (Seiten → Startseite).
3. Im Block „Hier finden Sie uns" den **Google-Maps-Block löschen** (Kadence-Google-Maps-Block).
4. Stattdessen ein **Bild (statische Karte)** einfügen – möglichst dieselbe `map.png` wie auf der Kontaktseite, oder ein neu für diesen Platz zugeschnittener Ausschnitt.
5. Darunter/nebenbei einen Link-Button **„In Google Maps öffnen"** auf `https://www.google.com/maps/search/Alter+Teichweg+11,+22081+Hamburg` ergänzen.
6. Seite aktualisieren/veröffentlichen.
7. Lokal verifizieren: kein `iframe` mit `google.com/maps/embed` mehr im HTML.

> **Hinweis zur Bildquelle:** Die vorhandene `map.png` (Kontaktseite) zeigt vermutlich ein großflächigeres Kartenbild. Falls auf der Startseite ein anderer Ausschnitt gewünscht ist: neues statisches Kartenbild erzeugen (z. B. Screenshot der statischen Karte, mit korrektem `alt`-Text) und über die Mediathek hochladen.

### 2. Live-Korrektur (selektiv, Modus B)

Da kein SQL-Vollimport erfolgt, wird die Korrektur auf dem Live-System **manuell im WordPress-Editor** nachgezogen:

1. Im Live-WP-Admin (`http://217.160.74.128/wp-admin/`) die Startseite öffnen.
2. Google-Maps-Block löschen, statische Karte + „In Google Maps öffnen"-Link einfügen (identisch zu Schritt 1).
3. Veröffentlichen.
4. WP Fastest Cache live leeren (WP Fastest Cache → Cache löschen).

### 3. API-Key rotieren (extern, Google Cloud)

Nachdem der Embed aus dem HTML entfernt wurde:

1. In der Google Cloud Console (falls zugänglich) den betroffenen API-Key identifizieren (`AIzaSyBAM2o7PiQqwk15LC1XRH2e_KJ-jUa7KYk`).
2. Den Key **deaktivieren/löschen** oder auf die erlaubten Referrer/Domains einschränken, da er öffentlich war.
3. Falls der Maps-Embed künftig **doch** wiederverwendet werden soll: neuen, restriktierten Key verwenden (mit HTTP-Referrer-Einschränkung). Für dieses Projekt gilt aber: **nur statische Karte**, kein Embed.

> **Hinweis:** Der API-Key-Rotator erfordert Zugang zum Google-Konto/Cloud-Projekt – das kann nicht aus dem Workspace erfolgen und wird hier nur als Pflicht-Folgeschritt dokumentiert.

## Abnahmekriterien

- [ ] Startseite zeigt eine **statische Karte** statt Google-Maps-iframe
- [ ] Im HTML der Startseite ist **kein** `iframe` mit `google.com/maps/embed` mehr vorhanden
- [ ] Der öffentliche API-Key `AIzaSyBAM2o7...` erscheint **nicht mehr** im Seiten-HTML
- [ ] Ein Link **„In Google Maps öffnen"** führt zur Karten-Suche
- [ ] Lokale Quelle (Post 13) ist identisch zur Live-Korrektur
- [ ] WP Fastest Cache live geleert
- [ ] API-Key wurde in der Google Cloud rotiert/deaktiviert (extern)

## Verifikation

```bash
curl -s http://217.160.74.128/ | grep -c "google.com/maps/embed"
# erwartet: 0
curl -s http://217.160.74.128/ | grep -c "AIzaSy"
# erwartet: 0
```

## Deployment

- **Inhaltsänderung** (Post 13 Startseite), kein Code-Deploy.
- Lokale Quelle zuerst, Live-Nachzug manuell im Editor (Modus B).
- Kein `deploy.ps1`-Einsatz erforderlich.

## Rollback

- Falls die statische Karte nicht gefällt: Im Editor den Block wieder durch den Google-Maps-Embed ersetzen (nur solange der API-Key noch aktiv ist). Nach Key-Rotation ist ein Rollback nur mit neuem Key möglich – daher Key erst nach Freigabe der statischen Karte rotieren.

## Risiken und offene Punkte

- **Offene Frage:** Welches statische Kartenbild soll auf der Startseite verwendet werden – die vorhandene `map.png` oder ein neuer Ausschnitt? (Entscheidung vor der Umsetzung.)
- Der Google-Maps-Embed war im Kadence-Block `13_b84d5f-6e`; dieser Block-Container wird mit dem Block selbst entfernt.
- **Nicht Teil dieser Aufgabe:** Verschlüsselte HTTPS-Umstellung (separate Aufgabe), übrige Server-Sicherheits-Header (separates Runbook).

## Dokumentation

- Nach Umsetzung in `Barmbini_Seiteninhalte.md` (Startseiten-Abschnitt) vermerken, dass die Startseite nun eine statische Karte nutzt.
- API-Key-Rotation in `Server_Aenderungsdokumentation_*.md` bzw. im Ops-Kontext festhalten.
- `Barmbini_Vorbereitung_Features_und_Bugfixes.md` Ist-Stand aktualisieren.
